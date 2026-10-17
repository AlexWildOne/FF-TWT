'use strict';

const { app } = require('@azure/functions');
const { ManagedIdentityCredential, ClientAssertionCredential } = require('@azure/identity');
const { SecretClient } = require('@azure/keyvault-secrets');
const { Connection, Request } = require('tedious');
const crypto = require('crypto');

const VERSION = '1.4.0';
const CONTRACT = 'fieldflow.entra.cross_tenant.probe';
const CONTRACT_VERSION = '1.0';
const RESOURCE_AUTH_CONTRACT = 'fieldflow.entra.resource_authorization.probe';
const RESOURCE_AUTH_CONTRACT_VERSION = '1.0';
const HMAC_VERSION = 'fieldflow-hmac-v1';
const MAX_CLOCK_SKEW_SECONDS = 300;
const MAX_BODY_BYTES = 128 * 1024;
const TOKEN_EXCHANGE_SCOPE = 'api://AzureADTokenExchange/.default';
const ONBOARDING_STATE_CONTEXT = 'fieldflow-entra-onboarding-state-v1';
const ONBOARDING_STATE_PURPOSE = 'fieldflow.entra.external_tenant.onboarding';
const ONBOARDING_RECEIPT_CONTRACT = 'fieldflow.entra.external_tenant.receipt';
const ONBOARDING_RECEIPT_CONTRACT_VERSION = '1.0';
const ONBOARDING_MAX_STATE_BYTES = 16 * 1024;
const BUILTIN_SCOPES = new Set([
  'https://management.azure.com/.default',
  'https://database.windows.net/.default',
  'https://analysis.windows.net/powerbi/api/.default',
  'https://api.fabric.microsoft.com/.default'
]);

function env(name, fallback = '') {
  return String(process.env[name] || fallback).trim();
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

function htmlResponse(status, html) {
  return {
    status,
    body: html,
    headers: {
      'Content-Type': 'text/html; charset=utf-8',
      'Cache-Control': 'no-store, private',
      'X-Content-Type-Options': 'nosniff',
      'X-Frame-Options': 'DENY',
      'Referrer-Policy': 'no-referrer'
    }
  };
}

function header(request, name) {
  return String(request.headers.get(name) || '').trim();
}

function safeEqualHex(a, b) {
  if (!/^[a-f0-9]{64}$/i.test(a || '') || !/^[a-f0-9]{64}$/i.test(b || '')) return false;
  return crypto.timingSafeEqual(Buffer.from(a, 'hex'), Buffer.from(b, 'hex'));
}

function parseHmacHeader(value) {
  // WordPress' certified HMAC v1 signer sends `v1=<hex>`. Earlier broker
  // scaffolding used the more verbose `hmac-v1=<hex>`. Accept both header
  // labels while keeping the canonical signed string unchanged as
  // `fieldflow-hmac-v1` so existing TWT trust is preserved.
  const match = /^(?:v1|hmac-v1)=([a-f0-9]{64})$/i.exec(String(value || '').trim());
  return match ? match[1].toLowerCase() : '';
}

function validGuid(value) {
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || '').trim());
}

function scopeAllowed(scope) {
  const value = String(scope || '').trim();
  if (BUILTIN_SCOPES.has(value)) return true;
  return /^api:\/\/[A-Za-z0-9._-]{3,180}\/\.default$/.test(value);
}

let hmacCache = { value: '', expiresAt: 0, source: '' };

function parseKeyVaultReference(value) {
  const raw = String(value || '').trim();
  let match = /^@Microsoft\.KeyVault\(SecretUri=https:\/\/([^.]+)\.vault\.azure\.net\/secrets\/([^/;)]+)(?:\/([^/;)]+))?\)$/i.exec(raw);
  if (match) return { vault: match[1], secret: match[2], version: match[3] || '' };

  match = /^@Microsoft\.KeyVault\(VaultName=([^;)]+);SecretName=([^;)]+)(?:;SecretVersion=([^;)]+))?\)$/i.exec(raw);
  if (match) return { vault: match[1], secret: match[2], version: match[3] || '' };

  match = /^kv:\/\/([^/]+)\/([^/]+)(?:\/([^/]+))?$/i.exec(raw);
  if (match) return { vault: match[1], secret: match[2], version: match[3] || '' };
  return null;
}

async function resolveHmacSecret() {
  const configured = env('FIELDFLOW_HMAC_SECRET');
  if (configured.length >= 32 && !configured.startsWith('@Microsoft.KeyVault(') && !configured.startsWith('kv://')) {
    return { value: configured, source: 'app_setting' };
  }

  let ref = parseKeyVaultReference(configured);
  if (!ref) {
    const vaultUri = env('FIELDFLOW_HMAC_KEYVAULT_URI');
    const secretName = env('FIELDFLOW_HMAC_KEYVAULT_SECRET_NAME');
    const secretVersion = env('FIELDFLOW_HMAC_KEYVAULT_SECRET_VERSION');
    const uriMatch = /^https:\/\/([^.]+)\.vault\.azure\.net\/?$/i.exec(vaultUri);
    if (uriMatch && secretName) {
      ref = { vault: uriMatch[1], secret: secretName, version: secretVersion || '' };
    }
  }
  if (!ref) return { value: '', source: configured ? 'unsupported_reference' : 'missing' };

  if (hmacCache.value.length >= 32 && Date.now() < hmacCache.expiresAt) {
    return { value: hmacCache.value, source: hmacCache.source || 'key_vault' };
  }

  const managedIdentityClientId = env('FIELDFLOW_ENTRA_MI_CLIENT_ID');
  if (!validGuid(managedIdentityClientId)) {
    throw Object.assign(new Error('HMAC managed identity configuration missing'), { code: 'broker_hmac_identity_not_configured', http: 503 });
  }

  try {
    const credential = new ManagedIdentityCredential(managedIdentityClientId);
    const client = new SecretClient(`https://${ref.vault}.vault.azure.net`, credential);
    const result = ref.version ? await client.getSecret(ref.secret, ref.version) : await client.getSecret(ref.secret);
    const value = String(result?.value || '').trim();
    if (value.length < 32) {
      throw new Error('Resolved Key Vault value is empty or too short');
    }
    hmacCache = { value, expiresAt: Date.now() + 5 * 60 * 1000, source: 'key_vault_uami' };
    return { value, source: 'key_vault_uami' };
  } catch (error) {
    hmacCache = { value: '', expiresAt: 0, source: '' };
    throw Object.assign(new Error('HMAC Key Vault resolution failed'), { code: 'broker_hmac_resolution_failed', http: 503, cause: error });
  }
}

async function readAndAuthenticate(request, expectedContract = CONTRACT, expectedContractVersion = CONTRACT_VERSION) {
  const raw = await request.text();
  if (Buffer.byteLength(raw, 'utf8') > MAX_BODY_BYTES) {
    throw Object.assign(new Error('Payload too large'), { code: 'payload_too_large', http: 413 });
  }
  let payload;
  try { payload = JSON.parse(raw); }
  catch { throw Object.assign(new Error('Invalid JSON'), { code: 'invalid_json', http: 400 }); }
  if (!payload || payload.contract !== expectedContract || String(payload.contract_version || '') !== expectedContractVersion) {
    throw Object.assign(new Error('Invalid contract'), { code: 'invalid_contract', http: 400 });
  }

  const timestampRaw = header(request, 'x-fieldflow-timestamp');
  const timestamp = Number(timestampRaw);
  const tenant = header(request, 'x-fieldflow-tenant');
  const installation = header(request, 'x-fieldflow-installation');
  const eventId = header(request, 'x-fieldflow-event-id');
  const bodyHashHeader = header(request, 'x-fieldflow-body-sha256').toLowerCase();
  const signature = parseHmacHeader(header(request, 'x-fieldflow-signature'));

  if (!Number.isInteger(timestamp) || Math.abs(Math.floor(Date.now() / 1000) - timestamp) > MAX_CLOCK_SKEW_SECONDS) {
    throw Object.assign(new Error('Invalid timestamp'), { code: 'invalid_timestamp', http: 401 });
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
    throw Object.assign(new Error('HMAC not configured'), { code: 'broker_hmac_not_configured', http: 503 });
  }
  const canonical = [HMAC_VERSION, timestampRaw, tenant, installation, eventId, bodyHash].join('\n');
  const expected = crypto.createHmac('sha256', secret).update(canonical, 'utf8').digest('hex');
  if (!safeEqualHex(expected, signature)) {
    throw Object.assign(new Error('Invalid signature'), { code: 'invalid_signature', http: 401 });
  }
  return payload;
}

async function acquireFederatedAccessToken(targetTenantId, scope) {
  const brokerClientId = env('FIELDFLOW_ENTRA_BROKER_CLIENT_ID');
  const managedIdentityClientId = env('FIELDFLOW_ENTRA_MI_CLIENT_ID');
  if (!validGuid(brokerClientId) || !validGuid(managedIdentityClientId)) {
    throw Object.assign(new Error('Broker identity configuration missing'), { code: 'broker_identity_not_configured', http: 503 });
  }
  if (!validGuid(targetTenantId)) {
    throw Object.assign(new Error('Invalid target tenant'), { code: 'invalid_target_tenant', http: 400 });
  }
  if (!scopeAllowed(scope)) {
    throw Object.assign(new Error('Scope not allowed'), { code: 'scope_not_allowed', http: 400 });
  }

  const miCredential = new ManagedIdentityCredential(managedIdentityClientId);
  const federatedCredential = new ClientAssertionCredential(
    targetTenantId,
    brokerClientId,
    async () => {
      const assertion = await miCredential.getToken(TOKEN_EXCHANGE_SCOPE);
      if (!assertion?.token) throw Object.assign(new Error('Managed identity assertion unavailable'), { code: 'managed_identity_assertion_failed' });
      return assertion.token;
    }
  );
  const token = await federatedCredential.getToken(scope);
  if (!token?.token) throw Object.assign(new Error('Federated token unavailable'), { code: 'federated_token_failed' });
  return token;
}

async function acquireFederatedToken(targetTenantId, scope) {
  const token = await acquireFederatedAccessToken(targetTenantId, scope);
  const expiresInSeconds = Math.max(0, Math.floor((Number(token.expiresOnTimestamp || 0) - Date.now()) / 1000));
  return { expiresInSeconds };
}

async function acquireFederatedTokenWithRetry(targetTenantId, scope, attempts = 4, delayMs = 2000) {
  let lastError;
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      return await acquireFederatedToken(targetTenantId, scope);
    } catch (error) {
      lastError = error;
      if (attempt >= attempts) break;
      await new Promise((resolve) => setTimeout(resolve, delayMs));
    }
  }
  throw lastError || Object.assign(new Error('Federated token unavailable'), { code: 'federated_token_failed' });
}

function normaliseSqlServer(value) {
  const raw = String(value || '').trim().toLowerCase().replace(/^tcp:/i, '').replace(/,1433$/, '');
  return /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.database\.windows\.net$/.test(raw) ? raw : '';
}

function normaliseSqlDatabase(value) {
  const raw = String(value || '').trim();
  if (!raw || raw.length > 128) return '';
  return /^[\p{L}\p{N}_.()\- ]+$/u.test(raw) ? raw : '';
}

async function probePowerBiWorkspace(targetTenantId, scope, resource) {
  if (String(scope || '').trim() !== 'https://analysis.windows.net/powerbi/api/.default') {
    throw Object.assign(new Error('Power BI scope mismatch'), { code: 'powerbi_scope_mismatch', http: 400 });
  }
  const workspaceId = String(resource?.workspace_id || '').trim().toLowerCase();
  if (!validGuid(workspaceId)) throw Object.assign(new Error('Invalid Power BI workspace ID'), { code: 'powerbi_workspace_invalid', http: 400 });
  const access = await acquireFederatedAccessToken(targetTenantId, scope);
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  let response;
  try {
    response = await fetch(`https://api.powerbi.com/v1.0/myorg/groups/${encodeURIComponent(workspaceId)}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${String(access.token || '')}`,
        'Accept': 'application/json',
        'User-Agent': `FieldFlow-Entra-Broker/${VERSION}`
      },
      redirect: 'manual',
      signal: controller.signal
    });
  } catch {
    throw Object.assign(new Error('Power BI probe transport failed'), { code: 'powerbi_transport_failed', http: 502 });
  } finally {
    clearTimeout(timeout);
  }
  const status = Number(response?.status || 0);
  try { if (response?.body) await response.body.cancel(); } catch {}
  if (status >= 200 && status < 300) return { authorized: true, httpStatus: status, probeCode: 'powerbi_workspace_read_ok' };
  if (status === 401 || status === 403) throw Object.assign(new Error('Power BI denied access'), { code: 'resource_access_denied', http: 403, remoteStatus: status });
  if (status === 404) throw Object.assign(new Error('Power BI workspace not found or not visible'), { code: 'resource_not_found', http: 404, remoteStatus: status });
  throw Object.assign(new Error('Power BI probe returned an unexpected status'), { code: 'powerbi_unexpected_status', http: 502, remoteStatus: status });
}


async function probeFabricWorkspace(targetTenantId, scope, resource) {
  if (String(scope || '').trim() !== 'https://api.fabric.microsoft.com/.default') {
    throw Object.assign(new Error('Fabric scope mismatch'), { code: 'fabric_scope_mismatch', http: 400 });
  }
  const workspaceId = String(resource?.workspace_id || '').trim().toLowerCase();
  if (!validGuid(workspaceId)) throw Object.assign(new Error('Invalid Fabric workspace ID'), { code: 'fabric_workspace_invalid', http: 400 });
  const access = await acquireFederatedAccessToken(targetTenantId, scope);
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  let response;
  try {
    response = await fetch(`https://api.fabric.microsoft.com/v1/workspaces/${encodeURIComponent(workspaceId)}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${String(access.token || '')}`,
        'Accept': 'application/json',
        'User-Agent': `FieldFlow-Entra-Broker/${VERSION}`
      },
      redirect: 'manual',
      signal: controller.signal
    });
  } catch {
    throw Object.assign(new Error('Fabric probe transport failed'), { code: 'fabric_transport_failed', http: 502 });
  } finally {
    clearTimeout(timeout);
  }
  const status = Number(response?.status || 0);
  try { if (response?.body) await response.body.cancel(); } catch {}
  if (status >= 200 && status < 300) return { authorized: true, httpStatus: status, probeCode: 'fabric_workspace_read_ok' };
  if (status === 401 || status === 403) throw Object.assign(new Error('Fabric denied access'), { code: 'resource_access_denied', http: 403, remoteStatus: status });
  if (status === 404) throw Object.assign(new Error('Fabric workspace not found or not visible'), { code: 'resource_not_found', http: 404, remoteStatus: status });
  throw Object.assign(new Error('Fabric probe returned an unexpected status'), { code: 'fabric_unexpected_status', http: 502, remoteStatus: status });
}

async function probeAzureSql(targetTenantId, scope, resource) {
  if (String(scope || '').trim() !== 'https://database.windows.net/.default') {
    throw Object.assign(new Error('Azure SQL scope mismatch'), { code: 'azure_sql_scope_mismatch', http: 400 });
  }
  const server = normaliseSqlServer(resource?.server);
  const database = normaliseSqlDatabase(resource?.database);
  if (!server || !database) throw Object.assign(new Error('Invalid Azure SQL target'), { code: 'azure_sql_target_invalid', http: 400 });
  const access = await acquireFederatedAccessToken(targetTenantId, scope);
  const token = String(access?.token || '');
  if (!token) throw Object.assign(new Error('Federated token unavailable'), { code: 'federated_token_failed', http: 502 });

  return await new Promise((resolve, reject) => {
    const connection = new Connection({
      server,
      authentication: {
        type: 'azure-active-directory-access-token',
        options: { token }
      },
      options: {
        database,
        encrypt: true,
        trustServerCertificate: false,
        port: 1433,
        connectTimeout: 10000,
        requestTimeout: 10000,
        rowCollectionOnRequestCompletion: false
      }
    });
    let settled = false;
    const finish = (error, result) => {
      if (settled) return;
      settled = true;
      try { connection.close(); } catch {}
      if (error) reject(error); else resolve(result);
    };
    connection.on('connect', (error) => {
      if (error) {
        const code = String(error?.code || 'azure_sql_connect_failed');
        const mapped = code === 'ELOGIN' ? 'resource_access_denied' : (code === 'ETIMEOUT' || code === 'ESOCKET' ? 'azure_sql_unreachable' : 'azure_sql_connect_failed');
        return finish(Object.assign(new Error('Azure SQL connection failed'), { code: mapped, http: code === 'ELOGIN' ? 403 : 502 }));
      }
      const request = new Request('SELECT 1 AS fieldflow_probe;', (requestError) => {
        if (requestError) {
          const code = String(requestError?.code || 'azure_sql_query_failed');
          return finish(Object.assign(new Error('Azure SQL read-only probe failed'), { code: code === 'ELOGIN' ? 'resource_access_denied' : 'azure_sql_query_failed', http: code === 'ELOGIN' ? 403 : 502 }));
        }
        finish(null, { authorized: true, httpStatus: 200, probeCode: 'azure_sql_select1_ok' });
      });
      connection.execSql(request);
    });
    connection.on('error', (error) => {
      if (settled) return;
      const code = String(error?.code || 'azure_sql_connection_error');
      finish(Object.assign(new Error('Azure SQL connection error'), { code: code === 'ELOGIN' ? 'resource_access_denied' : 'azure_sql_connection_error', http: code === 'ELOGIN' ? 403 : 502 }));
    });
    connection.connect();
  });
}

async function probeConcreteResource(targetTenantId, scope, resource) {
  const kind = String(resource?.kind || '').trim();
  if (kind === 'azure_sql') return await probeAzureSql(targetTenantId, scope, resource);
  if (kind === 'powerbi_workspace') return await probePowerBiWorkspace(targetTenantId, scope, resource);
  if (kind === 'fabric_workspace') return await probeFabricWorkspace(targetTenantId, scope, resource);
  throw Object.assign(new Error('Resource kind not supported'), { code: 'resource_kind_not_supported', http: 400 });
}

function entraCode(error) {
  const text = String(error?.message || '');
  const match = /(AADSTS\d{5,8})/i.exec(text);
  return match ? match[1].toUpperCase() : '';
}

function safeQueryCode(value, fallback = '') {
  const text = String(value || '').trim();
  return /^[A-Za-z0-9_.-]{1,64}$/.test(text) ? text : fallback;
}

function validFieldFlowId(value, prefix) {
  return new RegExp(`^${prefix}[a-f0-9]{24}$`, 'i').test(String(value || '').trim());
}

function receiptCallbackAllowed(rawUrl, siteHost) {
  try {
    const url = new URL(String(rawUrl || ''));
    if (url.protocol !== 'https:' || url.username || url.password) return false;
    if (url.port && url.port !== '443') return false;
    if (String(url.hostname || '').toLowerCase() !== String(siteHost || '').toLowerCase()) return false;
    const expectedPath = '/wp-json/fieldflow/v1/entra-onboarding/receipt';
    const pathOk = url.pathname === expectedPath || url.pathname.endsWith(expectedPath);
    const restRouteOk = url.searchParams.get('rest_route') === '/fieldflow/v1/entra-onboarding/receipt';
    return pathOk || restRouteOk;
  } catch {
    return false;
  }
}

async function verifyOnboardingState(stateToken) {
  const token = String(stateToken || '').trim();
  if (!token || Buffer.byteLength(token, 'utf8') > ONBOARDING_MAX_STATE_BYTES) {
    throw Object.assign(new Error('Invalid onboarding state'), { code: 'invalid_onboarding_state', http: 400 });
  }
  const parts = token.split('.');
  if (parts.length !== 2 || !/^[A-Za-z0-9_-]+$/.test(parts[0]) || !/^[a-f0-9]{64}$/i.test(parts[1])) {
    throw Object.assign(new Error('Invalid onboarding state shape'), { code: 'invalid_onboarding_state', http: 400 });
  }
  const hmac = await resolveHmacSecret();
  if (!hmac.value || hmac.value.length < 32) {
    throw Object.assign(new Error('HMAC not configured'), { code: 'broker_hmac_not_configured', http: 503 });
  }
  const expected = crypto.createHmac('sha256', hmac.value)
    .update(`${ONBOARDING_STATE_CONTEXT}\n${parts[0]}`, 'utf8')
    .digest('hex');
  if (!safeEqualHex(expected, parts[1].toLowerCase())) {
    throw Object.assign(new Error('Invalid onboarding state signature'), { code: 'invalid_onboarding_state_signature', http: 401 });
  }

  let state;
  try {
    const decoded = Buffer.from(parts[0], 'base64url').toString('utf8');
    state = JSON.parse(decoded);
  } catch {
    throw Object.assign(new Error('Invalid onboarding state payload'), { code: 'invalid_onboarding_state_payload', http: 400 });
  }
  const now = Math.floor(Date.now() / 1000);
  const issuedAt = Number(state?.issued_at || 0);
  const expiresAt = Number(state?.expires_at || 0);
  if (Number(state?.v) !== 1 || String(state?.purpose || '') !== ONBOARDING_STATE_PURPOSE) {
    throw Object.assign(new Error('Invalid onboarding state purpose'), { code: 'invalid_onboarding_state_purpose', http: 400 });
  }
  if (!Number.isInteger(issuedAt) || !Number.isInteger(expiresAt) || issuedAt <= 0 || expiresAt <= issuedAt || issuedAt > now + 60 || expiresAt < now || expiresAt - issuedAt > 7200) {
    throw Object.assign(new Error('Expired onboarding state'), { code: 'expired_onboarding_state', http: 400 });
  }
  if (!validFieldFlowId(state?.destination_uuid, 'ff_d_') || !validFieldFlowId(state?.fieldflow_tenant_id, 'ff_t_') || !validFieldFlowId(state?.installation_id, 'ff_i_')) {
    throw Object.assign(new Error('Invalid FieldFlow onboarding context'), { code: 'invalid_onboarding_context', http: 400 });
  }
  if (!validGuid(state?.target_tenant_id) || !scopeAllowed(String(state?.resource_scope || '').trim())) {
    throw Object.assign(new Error('Invalid target identity'), { code: 'invalid_onboarding_target', http: 400 });
  }
  if (!/^[A-Za-z0-9_-]{32,96}$/.test(String(state?.nonce || ''))) {
    throw Object.assign(new Error('Invalid onboarding nonce'), { code: 'invalid_onboarding_nonce', http: 400 });
  }
  const siteHost = String(state?.site_host || '').trim().toLowerCase();
  if (!/^[a-z0-9.-]{3,253}$/.test(siteHost) || !receiptCallbackAllowed(state?.callback_url, siteHost)) {
    throw Object.assign(new Error('Invalid callback URL'), { code: 'invalid_onboarding_callback', http: 400 });
  }
  return { state, secret: hmac.value };
}

function signedReceiptHeaders(payload, body, secret) {
  const timestamp = Math.floor(Date.now() / 1000);
  const tenant = String(payload?.tenant?.tenant_id || '').trim();
  const installation = String(payload?.tenant?.installation_id || '').trim();
  const eventId = String(payload?.event?.uuid || '').trim();
  const bodyHash = crypto.createHash('sha256').update(body, 'utf8').digest('hex');
  const canonical = [HMAC_VERSION, String(timestamp), tenant, installation, eventId, bodyHash].join('\n');
  const signature = crypto.createHmac('sha256', secret).update(canonical, 'utf8').digest('hex');
  return {
    'Content-Type': 'application/json',
    'User-Agent': `FieldFlow-Entra-Broker/${VERSION}`,
    'X-FieldFlow-Signature': `v1=${signature}`,
    'X-FieldFlow-Timestamp': String(timestamp),
    'X-FieldFlow-Tenant': tenant,
    'X-FieldFlow-Installation': installation,
    'X-FieldFlow-Event-Id': eventId,
    'X-FieldFlow-Body-SHA256': bodyHash,
    'X-FieldFlow-Contract-Version': ONBOARDING_RECEIPT_CONTRACT_VERSION
  };
}

async function postOnboardingReceipt(state, onboarding, secret) {
  const payload = {
    contract: ONBOARDING_RECEIPT_CONTRACT,
    contract_version: ONBOARDING_RECEIPT_CONTRACT_VERSION,
    tenant: {
      tenant_id: String(state.fieldflow_tenant_id || ''),
      installation_id: String(state.installation_id || '')
    },
    event: {
      uuid: crypto.randomUUID(),
      name: 'entra.external_tenant.receipt',
      occurred_at: new Date().toISOString()
    },
    destination_uuid: String(state.destination_uuid || ''),
    onboarding
  };
  const body = JSON.stringify(payload);
  const headers = signedReceiptHeaders(payload, body, secret);
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  try {
    const response = await fetch(String(state.callback_url), {
      method: 'POST',
      headers,
      body,
      redirect: 'manual',
      signal: controller.signal
    });
    return { delivered: response.status >= 200 && response.status < 300, httpStatus: response.status };
  } catch {
    return { delivered: false, httpStatus: 0 };
  } finally {
    clearTimeout(timeout);
  }
}

app.http('entraBrokerHealth', {
  methods: ['GET'],
  authLevel: 'anonymous',
  route: 'v1/health',
  handler: async () => {
    let hmacReady = false;
    let hmacSource = 'unavailable';
    try {
      const hmac = await resolveHmacSecret();
      hmacReady = hmac.value.length >= 32;
      hmacSource = hmac.source || (hmacReady ? 'configured' : 'unavailable');
    } catch (error) {
      hmacReady = false;
      const statusCode = Number(error?.cause?.statusCode || error?.cause?.status || 0);
      if (statusCode === 403) hmacSource = 'key_vault_forbidden';
      else if (statusCode === 404) hmacSource = 'key_vault_secret_not_found';
      else hmacSource = 'resolution_failed';
    }
    return jsonResponse(200, {
      ok: true,
      service: 'fieldflow-entra-broker',
      version: VERSION,
      credential_model: 'workload_identity_federation',
      managed_identity: 'user_assigned',
      secretless: true,
      token_probe_only: false,
      resource_authorization_control_plane: true,
      resource_probe_read_only: true,
      resource_probe_kinds: ['azure_sql', 'powerbi_workspace', 'fabric_workspace'],
      fabric_rest_v1: true,
      delivery_enabled: false,
      hmac_header_compatibility: ['v1', 'hmac-v1'],
      hmac_runtime_ready: hmacReady,
      hmac_runtime_source: hmacSource,
      external_tenant_onboarding: true,
      browser_only_tenant_bootstrap: true,
      client_subscription_required: false,
      onboarding_callback_token_probe: true,
      onboarding_receipt_hmac: true
    });
  }
});

app.http('entraTokenProbe', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'v1/token-probe',
  handler: async (request, context) => {
    try {
      const payload = await readAndAuthenticate(request);
      const targetTenantId = String(payload.target?.tenant_id || '').trim().toLowerCase();
      const scope = String(payload.target?.scope || '').trim();
      const tokenResult = await acquireFederatedToken(targetTenantId, scope);
      return jsonResponse(200, {
        ok: true,
        federated: true,
        credential_model: 'workload_identity_federation',
        target_tenant_id: targetTenantId,
        scope,
        expires_in_seconds: tokenResult.expiresInSeconds,
        token_exposed: false,
        delivery_performed: false
      });
    } catch (error) {
      const status = Number.isInteger(error?.http) ? error.http : 502;
      const code = String(error?.code || 'federation_probe_failed');
      const aad = entraCode(error);
      context.error(`Entra token probe failed, code=${code}${aad ? `, entra=${aad}` : ''}`);
      return jsonResponse(status, { ok: false, federated: false, error: code, entra_code: aad });
    }
  }
});

app.http('entraResourceAuthorizationProbe', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'v1/resource-probe',
  handler: async (request, context) => {
    let resourceKind = '';
    try {
      const payload = await readAndAuthenticate(request, RESOURCE_AUTH_CONTRACT, RESOURCE_AUTH_CONTRACT_VERSION);
      const targetTenantId = String(payload.target?.tenant_id || '').trim().toLowerCase();
      const scope = String(payload.target?.scope || '').trim();
      resourceKind = String(payload.resource?.kind || '').trim();
      const destinationUuid = String(payload.destination?.uuid || '').trim();
      if (!validFieldFlowId(destinationUuid, 'ff_d_')) {
        throw Object.assign(new Error('Invalid destination context'), { code: 'invalid_destination_context', http: 400 });
      }
      if (payload.probe?.read_only !== true || payload.probe?.delivery_enabled !== false) {
        throw Object.assign(new Error('Resource probe must be read-only'), { code: 'resource_probe_not_read_only', http: 400 });
      }
      const result = await probeConcreteResource(targetTenantId, scope, payload.resource || {});
      return jsonResponse(200, {
        ok: true,
        authorized: true,
        resource_kind: resourceKind,
        probe_code: String(result?.probeCode || 'resource_read_ok'),
        remote_http_status: Number(result?.httpStatus || 200),
        target_tenant_id: targetTenantId,
        scope,
        read_only: true,
        token_exposed: false,
        delivery_performed: false
      });
    } catch (error) {
      const status = Number.isInteger(error?.http) ? error.http : 502;
      const code = String(error?.code || 'resource_probe_failed');
      const aad = entraCode(error);
      const remoteStatus = Number(error?.remoteStatus || 0);
      context.error(`Entra resource probe failed, kind=${resourceKind || 'unknown'}, code=${code}${aad ? `, entra=${aad}` : ''}${remoteStatus ? `, remote_http=${remoteStatus}` : ''}`);
      return jsonResponse(status, {
        ok: false,
        authorized: false,
        resource_kind: resourceKind,
        error: code,
        entra_code: aad,
        remote_http_status: remoteStatus,
        read_only: true,
        token_exposed: false,
        delivery_performed: false
      });
    }
  }
});

app.http('entraAdminConsentCallback', {
  methods: ['GET'],
  authLevel: 'anonymous',
  route: 'v1/admin-consent/callback',
  handler: async (request, context) => {
    let verified;
    try {
      verified = await verifyOnboardingState(request.query.get('state'));
    } catch (error) {
      const safeCode = String(error?.code || 'invalid_onboarding_state');
      context.error(`Entra onboarding callback rejected, code=${safeCode}`);
      const html = '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ligação não validada</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f7fb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#0f172a}.card{max-width:620px;margin:24px;padding:32px;border:1px solid #dbe2ea;border-radius:24px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.12)}h1{margin:0 0 12px;font-size:27px}p{margin:0;color:#526174;line-height:1.6}</style></head><body><main class="card"><h1>Ligação não validada</h1><p>O pedido de onboarding não é válido ou expirou. Pede à The Wild Theory um novo link Microsoft.</p></main></body></html>';
      return htmlResponse(Number.isInteger(error?.http) ? error.http : 400, html);
    }

    const state = verified.state;
    const returnedTenant = String(request.query.get('tenant') || '').trim().toLowerCase();
    const adminConsent = String(request.query.get('admin_consent') || '').toLowerCase() === 'true';
    // Browser-only bootstrap uses the Microsoft authorize endpoint. The returned
    // authorization code is intentionally never exchanged, returned, persisted or
    // logged. Its presence only proves that Microsoft completed the browser leg;
    // the authoritative proof remains the workload-identity token probe below.
    const authorizationCodeReceived = String(request.query.get('code') || '').trim().length > 0;
    const queryError = safeQueryCode(request.query.get('error'), '');
    let status = 'consent_denied';
    let tokenVerified = false;
    let aad = '';
    let safeError = queryError || '';

    if (!queryError && (authorizationCodeReceived || adminConsent)) {
      if (adminConsent && returnedTenant && (!validGuid(returnedTenant) || returnedTenant !== String(state.target_tenant_id || '').toLowerCase())) {
        status = 'tenant_mismatch';
        safeError = 'tenant_mismatch';
      } else {
        status = 'consent_received';
        try {
          await acquireFederatedTokenWithRetry(String(state.target_tenant_id), String(state.resource_scope));
          status = 'ready';
          tokenVerified = true;
          safeError = '';
        } catch (error) {
          status = 'token_probe_failed';
          aad = entraCode(error);
          safeError = String(error?.code || 'federation_probe_failed');
          context.error(`External tenant token probe failed, code=${safeError}${aad ? `, entra=${aad}` : ''}`);
        }
      }
    } else if (!queryError) {
      safeError = 'microsoft_connection_not_completed';
    }

    const nonceHash = crypto.createHash('sha256').update(String(state.nonce || ''), 'utf8').digest('hex');
    const onboarding = {
      status,
      target_tenant_id: String(state.target_tenant_id || '').toLowerCase(),
      resource_scope: String(state.resource_scope || ''),
      nonce_hash: nonceHash,
      admin_consent: adminConsent,
      browser_bootstrap: authorizationCodeReceived,
      returned_tenant_id: validGuid(returnedTenant) ? returnedTenant : '',
      token_verified: tokenVerified,
      entra_code: aad,
      error: safeError,
      token_exposed: false,
      authorization_code_exposed: false,
      delivery_performed: false
    };

    const receipt = await postOnboardingReceipt(state, onboarding, verified.secret);
    const title = tokenVerified
      ? 'Ligação Microsoft Entra validada'
      : (status === 'consent_denied' ? 'Ligação Microsoft não concluída' : 'Ligação recebida, validação pendente');
    const detail = tokenVerified
      ? 'A identidade federada foi validada com sucesso. Pode fechar esta janela. O FieldFlow recebeu o estado automaticamente.'
      : (status === 'consent_denied'
        ? 'A ligação Microsoft não foi concluída. Pode fechar esta janela e pedir um novo link se necessário.'
        : 'A ligação Microsoft foi recebida, mas o token probe ainda não ficou validado. Pode fechar esta janela; a The Wild Theory consegue repetir a verificação sem pedir credenciais ao cliente.');
    const receiptDetail = receipt.delivered
      ? 'O estado foi sincronizado com o FieldFlow.'
      : 'A sincronização automática do estado não respondeu. O FieldFlow pode validar o tenant manualmente; não é necessário repetir a ligação Microsoft se esta já foi concluída.';
    const html = `<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${title}</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f7fb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#0f172a}.card{max-width:680px;margin:24px;padding:32px;border:1px solid #dbe2ea;border-radius:24px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.12)}h1{margin:0 0 12px;font-size:27px}p{margin:8px 0;color:#526174;line-height:1.6}.status{margin-top:18px;padding:12px 14px;border-radius:12px;background:#f6f7f7;font-size:14px}</style></head><body><main class="card"><h1>${title}</h1><p>${detail}</p><p class="status">${receiptDetail}</p></main></body></html>`;
    return htmlResponse(tokenVerified || status !== 'consent_denied' ? 200 : 400, html);
  }
});
