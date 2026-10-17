'use strict';

const { app } = require('@azure/functions');
const { DefaultAzureCredential } = require('@azure/identity');
const { SecretClient } = require('@azure/keyvault-secrets');
const sql = require('mssql');
const crypto = require('crypto');
const dns = require('dns').promises;
const net = require('net');

const VERSION = '1.1.0';
const CONTRACT = 'fieldflow.client_azure.relay';
const CONTRACT_VERSION = '1.0';
const HMAC_VERSION = 'fieldflow-hmac-v1';
const MAX_CLOCK_SKEW_SECONDS = 300;
const MAX_BODY_BYTES = 2 * 1024 * 1024;
const credential = new DefaultAzureCredential();
const secretCache = new Map();

function env(name, fallback = '') {
  return String(process.env[name] || fallback).trim();
}

function safeEqualHex(a, b) {
  if (!/^[a-f0-9]{64}$/i.test(a || '') || !/^[a-f0-9]{64}$/i.test(b || '')) return false;
  return crypto.timingSafeEqual(Buffer.from(a, 'hex'), Buffer.from(b, 'hex'));
}

function header(request, name) {
  return String(request.headers.get(name) || '').trim();
}

function jsonResponse(status, body) {
  return {
    status,
    jsonBody: body,
    headers: {
      'Cache-Control': 'no-store, private',
      'Pragma': 'no-cache',
      'X-Content-Type-Options': 'nosniff'
    }
  };
}

function deterministicDeliveryKey(eventUuid, destinationUuid) {
  const raw = `fieldflow-delivery-v1|${String(eventUuid).toLowerCase()}|${String(destinationUuid).toLowerCase()}`;
  return `ff_del_${crypto.createHash('sha256').update(raw, 'utf8').digest('hex').slice(0, 40)}`;
}

function parseHmacHeader(value) {
  // The certified WordPress signer uses `v1=<hex>`. Older 5B scaffolding
  // expected `hmac-v1=<hex>`. Accept both labels while preserving the same
  // `fieldflow-hmac-v1` canonical string.
  const match = /^(?:v1|hmac-v1)=([a-f0-9]{64})$/i.exec(String(value || '').trim());
  return match ? match[1].toLowerCase() : '';
}

async function resolveHmacSecret() {
  const configured = env('FIELDFLOW_HMAC_SECRET');
  if (configured.length >= 32 && !configured.startsWith('kv://') && !/\.vault\.azure\.net\/secrets\//i.test(configured)) {
    return { value: configured, source: 'app_setting' };
  }
  if (configured.startsWith('kv://') || /^https:\/\/[^/]+\.vault\.azure\.net\/secrets\//i.test(configured)) {
    const value = String(await resolveSecret(configured) || '').trim();
    if (value.length < 32) {
      throw Object.assign(new Error('Resolved HMAC secret is empty or too short'), { code: 'relay_hmac_resolution_failed', http: 503 });
    }
    return { value, source: 'key_vault_managed_identity' };
  }
  return { value: '', source: configured ? 'unsupported_reference' : 'missing' };
}

async function readAndAuthenticate(request) {
  const raw = await request.text();
  if (Buffer.byteLength(raw, 'utf8') > MAX_BODY_BYTES) {
    throw Object.assign(new Error('Payload too large'), { code: 'payload_too_large', http: 413 });
  }

  let payload;
  try { payload = JSON.parse(raw); }
  catch { throw Object.assign(new Error('Invalid JSON'), { code: 'invalid_json', http: 400 }); }

  if (!payload || payload.contract !== CONTRACT || String(payload.contract_version || '') !== CONTRACT_VERSION) {
    throw Object.assign(new Error('Invalid relay contract'), { code: 'invalid_contract', http: 400 });
  }

  const timestampRaw = header(request, 'x-fieldflow-timestamp');
  const timestamp = Number(timestampRaw);
  const tenant = header(request, 'x-fieldflow-tenant');
  const installation = header(request, 'x-fieldflow-installation');
  const eventId = header(request, 'x-fieldflow-event-id');
  const bodyHashHeader = header(request, 'x-fieldflow-body-sha256').toLowerCase();
  const signature = parseHmacHeader(header(request, 'x-fieldflow-signature'));

  if (!Number.isInteger(timestamp) || Math.abs(Math.floor(Date.now() / 1000) - timestamp) > MAX_CLOCK_SKEW_SECONDS) {
    throw Object.assign(new Error('Expired or invalid timestamp'), { code: 'invalid_timestamp', http: 401 });
  }
  if (!tenant || !installation || !eventId || !signature) {
    throw Object.assign(new Error('Missing HMAC context'), { code: 'missing_hmac_context', http: 401 });
  }

  const bodyHash = crypto.createHash('sha256').update(raw, 'utf8').digest('hex');
  if (!safeEqualHex(bodyHash, bodyHashHeader)) {
    throw Object.assign(new Error('Body hash mismatch'), { code: 'body_hash_mismatch', http: 401 });
  }

  const pTenant = String(payload.tenant?.tenant_id || '').trim();
  const pInstallation = String(payload.tenant?.installation_id || '').trim();
  const pEventId = String(payload.event?.uuid || '').trim();
  if (pTenant !== tenant || pInstallation !== installation || pEventId.toLowerCase() !== eventId.toLowerCase()) {
    throw Object.assign(new Error('Signed context mismatch'), { code: 'signed_context_mismatch', http: 401 });
  }

  const hmac = await resolveHmacSecret();
  const secret = hmac.value;
  if (secret.length < 32) {
    throw Object.assign(new Error('Relay HMAC trust is not configured'), { code: 'relay_hmac_not_configured', http: 503 });
  }
  const canonical = [HMAC_VERSION, timestampRaw, tenant, installation, eventId, bodyHash].join('\n');
  const expected = crypto.createHmac('sha256', secret).update(canonical, 'utf8').digest('hex');
  if (!safeEqualHex(expected, signature)) {
    throw Object.assign(new Error('Invalid signature'), { code: 'invalid_signature', http: 401 });
  }

  const destinationUuid = String(payload.destination?.uuid || '').trim();
  const deliveryKey = String(payload.delivery?.key || '').trim();
  const expectedKey = deterministicDeliveryKey(eventId, destinationUuid);
  if (!destinationUuid || deliveryKey !== expectedKey) {
    throw Object.assign(new Error('Invalid deterministic delivery key'), { code: 'invalid_delivery_key', http: 400 });
  }

  return payload;
}

async function sqlRequest() {
  const server = env('FIELDFLOW_SQL_SERVER');
  const database = env('FIELDFLOW_SQL_DATABASE');
  if (!server || !database) throw Object.assign(new Error('SQL relay configuration missing'), { code: 'sql_not_configured' });
  const token = await credential.getToken('https://database.windows.net/.default');
  if (!token?.token) throw Object.assign(new Error('Could not obtain Azure SQL token'), { code: 'sql_token_failed' });
  const pool = await new sql.ConnectionPool({
    server,
    database,
    options: { encrypt: true, trustServerCertificate: false, enableArithAbort: true },
    authentication: { type: 'azure-active-directory-access-token', options: { token: token.token } },
    pool: { max: 4, min: 0, idleTimeoutMillis: 15000 },
    connectionTimeout: 15000,
    requestTimeout: 30000
  }).connect();
  return pool;
}

function nullableBigInt(request, name, value) {
  const n = Number(value || 0);
  request.input(name, sql.BigInt, Number.isSafeInteger(n) && n > 0 ? n : null);
}

async function enqueue(payload) {
  const pool = await sqlRequest();
  try {
    const req = pool.request();
    req.input('delivery_key', sql.NVarChar(64), String(payload.delivery.key));
    req.input('tenant_id', sql.NVarChar(64), String(payload.tenant.tenant_id));
    req.input('installation_id', sql.NVarChar(64), String(payload.tenant.installation_id));
    req.input('destination_uuid', sql.NVarChar(64), String(payload.destination.uuid));
    req.input('destination_name', sql.NVarChar(191), String(payload.destination.name || 'Azure do Cliente').slice(0, 191));
    nullableBigInt(req, 'client_id', payload.destination.client_id || payload.context?.client_id);
    nullableBigInt(req, 'project_id', payload.destination.project_id || payload.context?.project_id);
    req.input('mode', sql.NVarChar(24), String(payload.destination.mode || 'mirror').slice(0, 24));
    req.input('endpoint_ref', sql.NVarChar(500), String(payload.destination.endpoint_ref || '').slice(0, 500));
    req.input('secret_ref', sql.NVarChar(500), String(payload.destination.secret_ref || '').slice(0, 500));
    req.input('retry_policy_json', sql.NVarChar(sql.MAX), JSON.stringify(payload.destination.retry_policy || {}));
    req.input('event_uuid', sql.UniqueIdentifier, String(payload.event.uuid));
    req.input('event_name', sql.NVarChar(96), String(payload.event.name || '').slice(0, 96));
    req.input('payload_json', sql.NVarChar(sql.MAX), JSON.stringify(payload));
    req.input('source_installation_id', sql.NVarChar(64), String(payload.tenant.installation_id));
    const result = await req.execute('ctl.sp_ff_delivery_enqueue');
    return result.recordset?.[0] || {};
  } finally { await pool.close(); }
}

function isPrivateV4(ip) {
  const p = ip.split('.').map(Number);
  if (p.length !== 4 || p.some((n) => !Number.isInteger(n))) return true;
  return p[0] === 10 || p[0] === 127 || p[0] === 0 ||
    (p[0] === 169 && p[1] === 254) ||
    (p[0] === 172 && p[1] >= 16 && p[1] <= 31) ||
    (p[0] === 192 && p[1] === 168) ||
    (p[0] === 100 && p[1] >= 64 && p[1] <= 127) ||
    (p[0] >= 224);
}

function isPrivateV6(ip) {
  const v = String(ip).toLowerCase();
  return v === '::' || v === '::1' || v.startsWith('fe80:') || v.startsWith('fc') || v.startsWith('fd') || v.startsWith('ff');
}

async function validateAzureEndpoint(value) {
  let url;
  try { url = new URL(String(value || '')); }
  catch { throw Object.assign(new Error('Invalid client Azure endpoint'), { code: 'client_endpoint_invalid' }); }
  if (url.protocol !== 'https:' || (url.port && url.port !== '443') || url.username || url.password) {
    throw Object.assign(new Error('Client Azure endpoint must use HTTPS/443'), { code: 'client_endpoint_unsafe' });
  }
  const host = url.hostname.toLowerCase();
  if (!(host.endsWith('.azurewebsites.net') || host.endsWith('.azurecontainerapps.io'))) {
    throw Object.assign(new Error('Phase 5B accepts only Azure-hosted HTTPS receivers'), { code: 'client_endpoint_not_azure' });
  }
  const addresses = await dns.lookup(host, { all: true, verbatim: true });
  if (!addresses.length) throw Object.assign(new Error('Client endpoint DNS did not resolve'), { code: 'client_endpoint_dns' });
  for (const item of addresses) {
    const family = net.isIP(item.address);
    if ((family === 4 && isPrivateV4(item.address)) || (family === 6 && isPrivateV6(item.address)) || family === 0) {
      throw Object.assign(new Error('Client endpoint resolved to a private or reserved address'), { code: 'client_endpoint_private' });
    }
  }
  return url.toString();
}

function parseSecretRef(ref) {
  const value = String(ref || '').trim();
  if (value.startsWith('kv://')) {
    const parts = value.slice(5).split('/').filter(Boolean);
    if (parts.length < 2 || !/^[a-zA-Z0-9-]{3,24}$/.test(parts[0]) || !/^[a-zA-Z0-9-]{1,127}$/.test(parts[1])) {
      throw Object.assign(new Error('Invalid Key Vault reference'), { code: 'secret_ref_invalid' });
    }
    return { vaultUrl: `https://${parts[0]}.vault.azure.net`, name: parts[1], version: parts[2] || undefined };
  }
  let url;
  try { url = new URL(value); }
  catch { throw Object.assign(new Error('Invalid Key Vault reference'), { code: 'secret_ref_invalid' }); }
  if (url.protocol !== 'https:' || !url.hostname.toLowerCase().endsWith('.vault.azure.net')) {
    throw Object.assign(new Error('Secret reference must target Azure Key Vault'), { code: 'secret_ref_invalid' });
  }
  const parts = url.pathname.split('/').filter(Boolean);
  if (parts[0] !== 'secrets' || !parts[1]) throw Object.assign(new Error('Invalid Key Vault secret path'), { code: 'secret_ref_invalid' });
  return { vaultUrl: `${url.protocol}//${url.hostname}`, name: parts[1], version: parts[2] || undefined };
}

async function resolveSecret(ref) {
  const parsed = parseSecretRef(ref);
  const cacheKey = `${parsed.vaultUrl}|${parsed.name}|${parsed.version || ''}`;
  const cached = secretCache.get(cacheKey);
  if (cached && cached.expires > Date.now()) return cached.value;
  const client = new SecretClient(parsed.vaultUrl, credential);
  const result = await client.getSecret(parsed.name, parsed.version ? { version: parsed.version } : undefined);
  const value = String(result.value || '');
  if (!value) throw Object.assign(new Error('Key Vault secret is empty'), { code: 'secret_empty' });
  secretCache.set(cacheKey, { value, expires: Date.now() + 5 * 60 * 1000 });
  return value;
}

async function deliverPayload(payload) {
  const retry = payload.destination?.retry_policy || {};
  const authMode = String(retry.auth_mode || 'key_vault_function_key');
  if (authMode !== 'key_vault_function_key') {
    throw Object.assign(new Error('Unsupported Phase 5B authentication mode'), { code: 'auth_mode_unsupported' });
  }
  const endpoint = await validateAzureEndpoint(payload.destination?.endpoint_ref);
  const functionKey = await resolveSecret(payload.destination?.secret_ref);
  const outbound = {
    contract: 'fieldflow.client_azure.delivery',
    contract_version: '1.0',
    delivery: {
      key: String(payload.delivery?.key || ''),
      probe: !!payload.delivery?.probe,
      relayed_at: new Date().toISOString()
    },
    tenant: payload.tenant,
    event: payload.event,
    context: payload.context || {},
    payload: payload.payload || {}
  };
  const body = JSON.stringify(outbound);
  if (Buffer.byteLength(body, 'utf8') > MAX_BODY_BYTES) throw Object.assign(new Error('Outbound payload too large'), { code: 'outbound_payload_too_large' });

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), Math.max(5000, Math.min(30000, Number(env('FIELDFLOW_CLIENT_TIMEOUT_MS', '15000')) || 15000)));
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      redirect: 'error',
      signal: controller.signal,
      headers: {
        'Content-Type': 'application/json',
        'X-Functions-Key': functionKey,
        'X-FieldFlow-Delivery-Key': String(payload.delivery?.key || ''),
        'User-Agent': `FieldFlow-ClientAzure-Relay/${VERSION}`
      },
      body
    });
    try { if (response.body) await response.body.cancel(); } catch {}
    if (response.status < 200 || response.status >= 300) {
      const err = new Error(`Client Azure returned HTTP ${response.status}`);
      err.code = 'client_http_error';
      err.httpCode = response.status;
      throw err;
    }
    return { httpCode: response.status };
  } catch (error) {
    if (error?.name === 'AbortError') {
      throw Object.assign(new Error('Client Azure request timed out'), { code: 'client_timeout' });
    }
    throw error;
  } finally { clearTimeout(timer); }
}

async function claim(pool, lockToken) {
  const req = pool.request();
  req.input('lock_token', sql.UniqueIdentifier, lockToken);
  const result = await req.execute('ctl.sp_ff_delivery_claim');
  return result.recordset?.[0] || null;
}

async function complete(pool, job, lockToken, httpCode) {
  const req = pool.request();
  req.input('id', sql.BigInt, job.id);
  req.input('lock_token', sql.UniqueIdentifier, lockToken);
  req.input('http_code', sql.SmallInt, httpCode);
  await req.execute('ctl.sp_ff_delivery_complete');
}

async function fail(pool, job, lockToken, error) {
  const req = pool.request();
  req.input('id', sql.BigInt, job.id);
  req.input('lock_token', sql.UniqueIdentifier, lockToken);
  req.input('http_code', sql.SmallInt, Number.isInteger(error?.httpCode) ? error.httpCode : null);
  req.input('error_code', sql.NVarChar(96), String(error?.code || 'delivery_failed').slice(0, 96));
  req.input('error_message', sql.NVarChar(500), String(error?.message || 'Delivery failed').slice(0, 500));
  await req.execute('ctl.sp_ff_delivery_fail');
}

async function runWorker(context, maxJobs) {
  const pool = await sqlRequest();
  let processed = 0;
  let completed = 0;
  let failed = 0;
  try {
    for (let i = 0; i < maxJobs; i += 1) {
      const lockToken = crypto.randomUUID();
      const job = await claim(pool, lockToken);
      if (!job) break;
      processed += 1;
      try {
        const payload = JSON.parse(String(job.payload_json || '{}'));
        const result = await deliverPayload(payload);
        await complete(pool, job, lockToken, result.httpCode);
        completed += 1;
      } catch (error) {
        context.error(`Client Azure delivery failed for ${String(job.delivery_key || '').slice(0, 18)}..., code=${String(error?.code || 'delivery_failed')}`);
        await fail(pool, job, lockToken, error);
        failed += 1;
      }
    }
  } finally { await pool.close(); }
  return { processed, completed, failed };
}

app.http('clientAzureRelayHealth', {
  methods: ['GET'],
  authLevel: 'anonymous',
  route: 'v1/health',
  handler: async (_request, _context) => {
    let hmacReady = false;
    let hmacSource = 'unavailable';
    try {
      const hmac = await resolveHmacSecret();
      hmacReady = String(hmac.value || '').length >= 32;
      hmacSource = hmacReady ? String(hmac.source || 'available') : String(hmac.source || 'unavailable');
    } catch (error) {
      hmacSource = String(error?.code || 'resolution_failed');
    }
    return jsonResponse(200, {
      ok: true,
      service: 'fieldflow-client-azure-relay',
      version: VERSION,
      hmac_header_compatibility: ['v1', 'hmac-v1'],
      hmac_runtime_ready: hmacReady,
      hmac_runtime_source: hmacSource,
      key_vault_managed_identity: true,
      delivery_queue_enabled: true,
      secret_value_exposed: false
    });
  }
});

app.http('clientAzureRelay', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'v1/client-azure-relay',
  handler: async (request, context) => {
    try {
      const payload = await readAndAuthenticate(request);
      if (payload.delivery?.probe === true) {
        const result = await deliverPayload(payload);
        return jsonResponse(200, { ok: true, accepted: true, probe: true, delivered: true, status: 'delivered', delivery_key: String(payload.delivery?.key || ''), http_code: result.httpCode });
      }
      const job = await enqueue(payload);
      return jsonResponse(202, { ok: true, accepted: true, duplicate_safe: true, status: String(job.status || 'pending'), delivery_key: String(payload.delivery?.key || '') });
    } catch (error) {
      const status = Number.isInteger(error?.http) ? error.http : 500;
      const code = String(error?.code || 'relay_error');
      context.error(`Client Azure relay request failed, code=${code}, status=${status}`);
      return jsonResponse(status, { ok: false, error: code });
    }
  }
});

app.timer('clientAzureDispatcher', {
  schedule: '0 * * * * *',
  runOnStartup: false,
  useMonitor: true,
  handler: async (_timer, context) => {
    const batch = Math.max(1, Math.min(50, Number(env('FIELDFLOW_CLIENT_RELAY_BATCH', '20')) || 20));
    try {
      const summary = await runWorker(context, batch);
      if (summary.processed > 0) context.log(`Client Azure dispatcher processed=${summary.processed}, completed=${summary.completed}, failed=${summary.failed}`);
    } catch (error) {
      context.error(`Client Azure dispatcher failed, code=${String(error?.code || 'dispatcher_error')}`);
    }
  }
});
