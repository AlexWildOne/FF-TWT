'use strict';

const { app } = require('@azure/functions');
const { ManagedIdentityCredential, ClientAssertionCredential } = require('@azure/identity');
const { SecretClient } = require('@azure/keyvault-secrets');
const { Connection, Request } = require('tedious');
const crypto = require('crypto');
const dns = require('dns').promises;
const net = require('net');
const https = require('https');

const VERSION = '1.6.0';
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
const CONTROLLED_DELIVERY_CONTRACT = 'fieldflow.connector.controlled_delivery';
const CONTROLLED_DELIVERY_CONTRACT_VERSION = '1.0';
const CONTROLLED_RECEIVER_CONTRACT = 'fieldflow.connector.controlled_receiver';
const CONTROLLED_RECEIVER_CONTRACT_VERSION = '1.0';
const CONTROLLED_RECEIPT_CONTRACT = 'fieldflow.connector.controlled_receipt';
const CONTROLLED_RECEIPT_CONTRACT_VERSION = '1.0';
const CONTROLLED_RECEIPT_CONTEXT = 'fieldflow-controlled-receipt-v1';
const SAFE_PILOT_PREPARE_CONTRACT = 'fieldflow.connector.safe_live_pilot.prepare';
const SAFE_PILOT_PREPARE_CONTRACT_VERSION = '1.0';
const SAFE_PILOT_DELIVERY_CONTRACT = 'fieldflow.connector.safe_live_pilot.delivery';
const SAFE_PILOT_DELIVERY_CONTRACT_VERSION = '1.0';
const SAFE_PILOT_TARGET_CONTRACT = 'fieldflow.connector.safe_live_pilot.target';
const SAFE_PILOT_TARGET_CONTRACT_VERSION = '1.0';
const SAFE_PILOT_RECEIPT_CONTRACT = 'fieldflow.connector.safe_live_pilot.receipt';
const SAFE_PILOT_RECEIPT_CONTRACT_VERSION = '1.0';
const SAFE_PILOT_TOKEN_CONTEXT = 'fieldflow-safe-live-pilot-token-v1';
const SAFE_PILOT_RECEIPT_CONTEXT = 'fieldflow-safe-live-pilot-receipt-v1';
const SAFE_PILOT_MIN_TTL_SECONDS = 300;
const SAFE_PILOT_MAX_TTL_SECONDS = 1800;
const SAFE_PILOT_MAX_TARGET_BODY_BYTES = 16 * 1024;
const SAFE_PILOT_MAX_RESPONSE_BYTES = 32 * 1024;
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


function decodeJwtPayloadUnsafe(token) {
  try {
    const parts = String(token || '').split('.');
    if (parts.length < 2) return {};
    let raw = parts[1].replace(/-/g, '+').replace(/_/g, '/');
    while (raw.length % 4) raw += '=';
    const parsed = JSON.parse(Buffer.from(raw, 'base64').toString('utf8'));
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}

function safeFabricTokenDiagnostics(token) {
  const claims = decodeJwtPayloadUnsafe(token);
  return {
    token_tenant_id: validGuid(claims.tid) ? String(claims.tid).toLowerCase() : '',
    token_client_id: validGuid(claims.azp) ? String(claims.azp).toLowerCase() : (validGuid(claims.appid) ? String(claims.appid).toLowerCase() : ''),
    token_object_id: validGuid(claims.oid) ? String(claims.oid).toLowerCase() : '',
    token_audience: typeof claims.aud === 'string' ? claims.aud.slice(0, 180) : '',
    token_identity_type: typeof claims.idtyp === 'string' ? claims.idtyp.slice(0, 40) : ''
  };
}

async function fabricFetch(url, token) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  try {
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${String(token || '')}`,
        'Accept': 'application/json',
        'User-Agent': `FieldFlow-Entra-Broker/${VERSION}`
      },
      redirect: 'manual',
      signal: controller.signal
    });
    const status = Number(response?.status || 0);
    let text = '';
    try { text = await response.text(); } catch {}
    let body = {};
    if (text) {
      try { body = JSON.parse(text); } catch {}
    }
    return { status, body, headers: response?.headers };
  } finally {
    clearTimeout(timeout);
  }
}

async function probeFabricWorkspace(targetTenantId, scope, resource) {
  if (String(scope || '').trim() !== 'https://api.fabric.microsoft.com/.default') {
    throw Object.assign(new Error('Fabric scope mismatch'), { code: 'fabric_scope_mismatch', http: 400 });
  }
  const workspaceId = String(resource?.workspace_id || '').trim().toLowerCase();
  if (!validGuid(workspaceId)) throw Object.assign(new Error('Invalid Fabric workspace ID'), { code: 'fabric_workspace_invalid', http: 400 });
  const access = await acquireFederatedAccessToken(targetTenantId, scope);
  const token = String(access?.token || '');
  const tokenDiag = safeFabricTokenDiagnostics(token);

  let direct;
  try {
    direct = await fabricFetch(`https://api.fabric.microsoft.com/v1/workspaces/${encodeURIComponent(workspaceId)}`, token);
  } catch {
    throw Object.assign(new Error('Fabric probe transport failed'), { code: 'fabric_transport_failed', http: 502, ...tokenDiag });
  }

  const directStatus = Number(direct?.status || 0);
  const directBody = direct?.body && typeof direct.body === 'object' ? direct.body : {};
  const remoteErrorCode = String(directBody.errorCode || directBody.error?.code || '').slice(0, 120);
  const remoteRequestId = String(directBody.requestId || direct?.headers?.get?.('x-ms-request-id') || direct?.headers?.get?.('request-id') || '').slice(0, 120);

  if (directStatus >= 200 && directStatus < 300) {
    return { authorized: true, httpStatus: directStatus, probeCode: 'fabric_workspace_read_ok' };
  }
  if (directStatus === 401 || directStatus === 403) {
    throw Object.assign(new Error('Fabric denied access to the service principal'), {
      code: 'resource_access_denied', http: 403, remoteStatus: directStatus,
      remoteErrorCode, remoteRequestId, ...tokenDiag
    });
  }

  // A 404 can mean either "wrong workspace id" or "workspace not visible to this principal".
  // Instead of sending the administrator back through more portal toggles, perform a second
  // read-only call that lists the workspaces visible to the exact same token. If the target
  // workspace appears there, that is sufficient proof of concrete read access for Phase 5C.3.
  if (directStatus === 404) {
    let listed;
    try {
      listed = await fabricFetch('https://api.fabric.microsoft.com/v1/workspaces?roles=Admin,Member,Contributor,Viewer', token);
    } catch {
      throw Object.assign(new Error('Fabric workspace lookup failed after 404'), {
        code: 'fabric_workspace_lookup_transport_failed', http: 502, remoteStatus: directStatus,
        remoteErrorCode, remoteRequestId, ...tokenDiag
      });
    }
    const listStatus = Number(listed?.status || 0);
    const values = Array.isArray(listed?.body?.value) ? listed.body.value : [];
    const visible = values.some((item) => String(item?.id || '').trim().toLowerCase() === workspaceId);
    if (listStatus >= 200 && listStatus < 300 && visible) {
      return { authorized: true, httpStatus: 200, probeCode: 'fabric_workspace_list_read_ok' };
    }
    if (listStatus === 401 || listStatus === 403) {
      throw Object.assign(new Error('Fabric authenticated the token but did not allow workspace discovery'), {
        code: 'fabric_principal_not_authorized_for_public_api', http: 403, remoteStatus: listStatus,
        directRemoteStatus: directStatus, remoteErrorCode, remoteRequestId,
        accessibleWorkspaceCount: values.length, ...tokenDiag
      });
    }
    if (listStatus >= 200 && listStatus < 300 && !visible) {
      throw Object.assign(new Error('Fabric token is valid, but the target workspace is not visible to this service principal'), {
        code: 'fabric_workspace_not_visible_to_principal', http: 404, remoteStatus: directStatus,
        listRemoteStatus: listStatus, remoteErrorCode, remoteRequestId,
        accessibleWorkspaceCount: values.length, ...tokenDiag
      });
    }
    throw Object.assign(new Error('Fabric workspace lookup returned an unexpected status'), {
      code: 'fabric_workspace_lookup_unexpected_status', http: 502, remoteStatus: listStatus,
      directRemoteStatus: directStatus, remoteErrorCode, remoteRequestId, ...tokenDiag
    });
  }

  throw Object.assign(new Error('Fabric probe returned an unexpected status'), {
    code: 'fabric_unexpected_status', http: 502, remoteStatus: directStatus,
    remoteErrorCode, remoteRequestId, ...tokenDiag
  });
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


function validDeliveryKey(value) {
  return /^ff_del_[a-f0-9]{40}$/i.test(String(value || '').trim());
}

function controlledBehavior(value) {
  const behavior = String(value || 'success').trim().toLowerCase();
  return ['success', 'fail_first', 'fail_always'].includes(behavior) ? behavior : '';
}

function controlledReceiverUrl() {
  const host = env('WEBSITE_HOSTNAME').toLowerCase();
  if (!/^[a-z0-9][a-z0-9.-]*\.azurewebsites\.net$/i.test(host)) {
    throw Object.assign(new Error('Controlled receiver host unavailable'), { code: 'controlled_receiver_host_unavailable', http: 503 });
  }
  return `https://${host}/api/v1/controlled-receiver`;
}

function signedFieldFlowHeaders(payload, body, secret, contractVersion = '1.0') {
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
    'X-FieldFlow-Contract-Version': String(contractVersion || '1.0')
  };
}

function signControlledReceipt(receipt, secret) {
  const canonical = [
    CONTROLLED_RECEIPT_CONTEXT,
    String(receipt.contract || ''),
    String(receipt.contract_version || ''),
    String(receipt.delivery_key || ''),
    String(receipt.event_uuid || ''),
    String(receipt.destination_uuid || ''),
    String(Number(receipt.remote_http_status || 0)),
    String(receipt.outcome || ''),
    String(receipt.receiver_request_id || ''),
    String(receipt.received_at || '')
  ].join('\n');
  return crypto.createHmac('sha256', secret).update(canonical, 'utf8').digest('hex');
}

async function postControlledReceiver(receiverPayload, secret) {
  const body = JSON.stringify(receiverPayload);
  const headers = signedFieldFlowHeaders(receiverPayload, body, secret, CONTROLLED_RECEIVER_CONTRACT_VERSION);
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  try {
    const response = await fetch(controlledReceiverUrl(), {
      method: 'POST',
      headers,
      body,
      redirect: 'manual',
      signal: controller.signal
    });
    const raw = await response.text();
    let decoded = {};
    try { decoded = raw ? JSON.parse(raw) : {}; } catch { decoded = {}; }
    return { httpStatus: Number(response.status || 0), body: decoded };
  } catch (error) {
    return { httpStatus: 0, body: {}, transportError: String(error?.name || 'fetch_failed') };
  } finally {
    clearTimeout(timeout);
  }
}


function validPilotDestinationType(value) {
  return ['external_api', 'webhook'].includes(String(value || '').trim().toLowerCase());
}

function isPublicIpv4(ip) {
  const parts = String(ip || '').split('.').map(Number);
  if (parts.length !== 4 || parts.some((n) => !Number.isInteger(n) || n < 0 || n > 255)) return false;
  const [a,b,c,d] = parts;
  if (a === 0 || a === 10 || a === 127 || a >= 224) return false;
  if (a === 100 && b >= 64 && b <= 127) return false;
  if (a === 169 && b === 254) return false;
  if (a === 172 && b >= 16 && b <= 31) return false;
  if (a === 192 && b === 168) return false;
  if (a === 192 && b === 0 && c === 0) return false;
  if (a === 192 && b === 0 && c === 2) return false;
  if (a === 198 && (b === 18 || b === 19)) return false;
  if (a === 198 && b === 51 && c === 100) return false;
  if (a === 203 && b === 0 && c === 113) return false;
  if (a === 255 && b === 255 && c === 255 && d === 255) return false;
  return true;
}

function isPublicIp(ip) {
  const family = net.isIP(String(ip || ''));
  if (family === 4) return isPublicIpv4(ip);
  if (family !== 6) return false;
  const v = String(ip || '').toLowerCase();
  if (v === '::' || v === '::1') return false;
  if (v.startsWith('fc') || v.startsWith('fd')) return false;
  if (/^fe[89ab]/.test(v)) return false;
  if (v.startsWith('ff')) return false;
  if (v.startsWith('2001:db8')) return false;
  if (v.startsWith('::ffff:')) {
    const mapped = v.slice(7);
    if (net.isIP(mapped) === 4) return isPublicIpv4(mapped);
  }
  return true;
}

function localPilotHost(hostname) {
  const host = String(hostname || '').trim().toLowerCase().replace(/^\[|\]$/g, '').replace(/\.$/, '');
  if (!host || host === 'localhost' || host === 'localhost.localdomain') return true;
  if (!host.includes('.') && net.isIP(host) === 0) return true;
  return ['.localhost','.local','.internal','.lan','.home','.home.arpa','.test'].some((suffix) => host.endsWith(suffix));
}

async function validatePilotTargetUrl(rawUrl) {
  let parsed;
  try { parsed = new URL(String(rawUrl || '').trim()); }
  catch { throw Object.assign(new Error('Pilot target URL invalid'), { code: 'pilot_target_invalid', http: 400 }); }
  if (parsed.protocol !== 'https:') throw Object.assign(new Error('Pilot target requires HTTPS'), { code: 'pilot_https_required', http: 400 });
  if (parsed.username || parsed.password) throw Object.assign(new Error('Pilot target userinfo blocked'), { code: 'pilot_userinfo_blocked', http: 400 });
  if (parsed.port && parsed.port !== '443') throw Object.assign(new Error('Pilot target port blocked'), { code: 'pilot_port_blocked', http: 400 });
  const host = parsed.hostname.toLowerCase();
  if (localPilotHost(host)) throw Object.assign(new Error('Pilot local target blocked'), { code: 'pilot_local_target_blocked', http: 400 });
  if (net.isIP(host)) throw Object.assign(new Error('Pilot IP literals blocked'), { code: 'pilot_ip_literal_blocked', http: 400 });
  let addresses = [];
  try { addresses = await dns.lookup(host, { all: true, verbatim: true }); }
  catch { throw Object.assign(new Error('Pilot target DNS failed'), { code: 'pilot_dns_failed', http: 400 }); }
  if (!Array.isArray(addresses) || addresses.length < 1) throw Object.assign(new Error('Pilot target DNS empty'), { code: 'pilot_dns_empty', http: 400 });
  for (const row of addresses) {
    if (!isPublicIp(row?.address)) throw Object.assign(new Error('Pilot target resolves to private/reserved network'), { code: 'pilot_private_network_blocked', http: 400 });
  }
  parsed.hash = '';
  const normalized = parsed.toString();
  if (normalized.length > 1000) throw Object.assign(new Error('Pilot target URL too long'), { code: 'pilot_target_too_long', http: 400 });
  return {
    url: normalized,
    host,
    addresses: addresses.map((row) => ({ address: String(row.address || ''), family: Number(row.family || net.isIP(String(row.address || ''))) })),
    endpointSha256: crypto.createHash('sha256').update(normalized, 'utf8').digest('hex')
  };
}

function safePilotToken(destinationUuid, destinationType, endpointSha256, expiresAt, secret) {
  const canonical = [SAFE_PILOT_TOKEN_CONTEXT, destinationUuid, destinationType, endpointSha256, String(expiresAt)].join('\n');
  const signature = crypto.createHmac('sha256', secret).update(canonical, 'utf8').digest('hex');
  return `v1.${expiresAt}.${endpointSha256}.${signature}`;
}

function verifySafePilotToken(token, destinationUuid, destinationType, endpointSha256, secret) {
  const match = /^v1\.(\d{10})\.([a-f0-9]{64})\.([a-f0-9]{64})$/i.exec(String(token || '').trim());
  if (!match) return { ok: false, code: 'pilot_token_invalid' };
  const expiresAt = Number(match[1] || 0);
  const tokenEndpointSha = String(match[2] || '').toLowerCase();
  const signature = String(match[3] || '').toLowerCase();
  if (expiresAt < Math.floor(Date.now()/1000)) return { ok: false, code: 'pilot_token_expired' };
  if (!safeEqualHex(tokenEndpointSha, endpointSha256)) return { ok: false, code: 'pilot_target_changed' };
  const expected = safePilotToken(destinationUuid, destinationType, endpointSha256, expiresAt, secret).split('.').pop();
  if (!safeEqualHex(signature, expected)) return { ok: false, code: 'pilot_token_signature_invalid' };
  return { ok: true, expiresAt };
}

function safePilotProjection(payload) {
  const src = payload?.data?.safe_projection || {};
  const eventName = String(payload?.event?.name || src?.event_name || '').trim().slice(0, 96);
  const eventUuid = String(payload?.event?.uuid || src?.event_uuid || '').trim().toLowerCase();
  if (!eventName || !/^[a-z0-9_.:*\-]{1,96}$/i.test(eventName)) {
    throw Object.assign(new Error('Pilot event name invalid'), { code: 'pilot_event_invalid', http: 400 });
  }
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(eventUuid)) {
    throw Object.assign(new Error('Pilot event UUID invalid'), { code: 'pilot_event_uuid_invalid', http: 400 });
  }
  const integer = (v) => Math.max(0, Math.min(Number.MAX_SAFE_INTEGER, Number.isFinite(Number(v)) ? Math.floor(Number(v)) : 0));
  return {
    event_name: eventName,
    event_uuid: eventUuid,
    occurred_at: String(src?.occurred_at || payload?.event?.occurred_at || new Date().toISOString()).slice(0, 40),
    entity_type: String(src?.entity_type || '').replace(/[^a-z0-9_\-]/gi, '').slice(0, 48),
    entity_id: integer(src?.entity_id),
    client_id: integer(src?.client_id),
    project_id: integer(src?.project_id),
    route_id: integer(src?.route_id),
    route_stop_id: integer(src?.route_stop_id),
    location_id: integer(src?.location_id),
    submission_id: integer(src?.submission_id),
    briefing_id: integer(src?.briefing_id),
    synthetic_marker: String(src?.synthetic_marker || '').replace(/[^a-z0-9_.:\-]/gi, '').slice(0, 96)
  };
}

function signSafePilotReceipt(receipt, secret) {
  const canonical = [
    SAFE_PILOT_RECEIPT_CONTEXT,
    String(receipt.contract || ''),
    String(receipt.contract_version || ''),
    String(receipt.delivery_key || ''),
    String(receipt.event_uuid || ''),
    String(receipt.destination_uuid || ''),
    String(receipt.endpoint_sha256 || ''),
    String(Number(receipt.remote_http_status || 0)),
    String(receipt.outcome || ''),
    String(receipt.receiver_request_id || ''),
    String(receipt.received_at || '')
  ].join('\n');
  return crypto.createHmac('sha256', secret).update(canonical, 'utf8').digest('hex');
}

async function postSafePilotTarget(target, targetPayload, secret) {
  const body = JSON.stringify(targetPayload);
  if (Buffer.byteLength(body, 'utf8') > SAFE_PILOT_MAX_TARGET_BODY_BYTES) {
    return { httpStatus: 0, body: {}, transportError: 'pilot_payload_too_large' };
  }
  if (!target || !Array.isArray(target.addresses) || target.addresses.length < 1) {
    return { httpStatus: 0, body: {}, transportError: 'pilot_dns_pin_missing' };
  }
  const pinned = target.addresses.filter((row) => isPublicIp(row?.address));
  if (pinned.length < 1 || pinned.length !== target.addresses.length) {
    return { httpStatus: 0, body: {}, transportError: 'pilot_dns_pin_invalid' };
  }
  const headers = signedFieldFlowHeaders(targetPayload, body, secret, SAFE_PILOT_TARGET_CONTRACT_VERSION);
  headers['X-FieldFlow-Pilot'] = '5C.7';
  headers['X-FieldFlow-Safe-Projection'] = '1';

  return await new Promise((resolve) => {
    let settled = false;
    const finish = (result) => {
      if (settled) return;
      settled = true;
      resolve(result);
    };
    let rrIndex = 0;
    const lookup = (_hostname, options, callback) => {
      const all = !!(options && typeof options === 'object' && options.all);
      if (all) {
        callback(null, pinned.map((row) => ({ address: row.address, family: row.family || net.isIP(row.address) })));
        return;
      }
      const row = pinned[rrIndex++ % pinned.length];
      callback(null, row.address, row.family || net.isIP(row.address));
    };
    const request = https.request(target.url, {
      method: 'POST',
      headers,
      lookup,
      timeout: 10000,
      maxHeaderSize: 16 * 1024,
      agent: false
    }, (response) => {
      const status = Number(response.statusCode || 0);
      if ([301,302,303,307,308].includes(status)) {
        response.resume();
        finish({ httpStatus: status, body: {}, transportError: 'pilot_redirect_blocked' });
        return;
      }
      const chunks = [];
      let total = 0;
      response.on('data', (chunk) => {
        total += chunk.length;
        if (total > SAFE_PILOT_MAX_RESPONSE_BYTES) {
          request.destroy(new Error('pilot_response_too_large'));
          return;
        }
        chunks.push(chunk);
      });
      response.on('end', () => {
        if (settled) return;
        const raw = Buffer.concat(chunks).toString('utf8');
        let decoded = {};
        try { decoded = raw ? JSON.parse(raw) : {}; } catch { decoded = {}; }
        finish({ httpStatus: status, body: decoded });
      });
    });
    request.on('timeout', () => request.destroy(new Error('pilot_timeout')));
    request.on('error', (error) => {
      const message = String(error?.message || error?.code || 'pilot_https_request_failed').slice(0, 96);
      finish({ httpStatus: 0, body: {}, transportError: message });
    });
    request.end(body);
  });
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
      fabric_workspace_visibility_fallback: true,
      controlled_delivery_foundation: true,
      controlled_delivery_certification_only: true,
      controlled_delivery_receiver_fixed: true,
      controlled_delivery_signed_receipts: true,
      safe_live_pilot_foundation: true,
      safe_live_pilot_dynamic_target_guarded: true,
      safe_live_pilot_tokenized: true,
      safe_live_pilot_one_shot: true,
      safe_live_pilot_safe_projection_only: true,
      safe_live_pilot_sink_available: true,
      operational_payload_delivery_blocked: true,
      operational_delivery_blocked: true,
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
        message: String(error?.message || '').slice(0, 240),
        entra_code: aad,
        remote_http_status: remoteStatus,
        remote_error_code: String(error?.remoteErrorCode || '').slice(0, 120),
        remote_request_id: String(error?.remoteRequestId || '').slice(0, 120),
        list_remote_http_status: Number(error?.listRemoteStatus || 0),
        direct_remote_http_status: Number(error?.directRemoteStatus || 0),
        accessible_workspace_count: Number(error?.accessibleWorkspaceCount || 0),
        token_tenant_id: String(error?.token_tenant_id || ''),
        token_client_id: String(error?.token_client_id || ''),
        token_object_id: String(error?.token_object_id || ''),
        token_audience: String(error?.token_audience || ''),
        token_identity_type: String(error?.token_identity_type || ''),
        read_only: true,
        token_exposed: false,
        delivery_performed: false
      });
    }
  }
});



app.http('safeLivePilotPrepare', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'v1/pilot/prepare',
  handler: async (request, context) => {
    try {
      const payload = await readAndAuthenticate(request, SAFE_PILOT_PREPARE_CONTRACT, SAFE_PILOT_PREPARE_CONTRACT_VERSION);
      const destinationUuid = String(payload?.destination?.uuid || '').trim();
      const destinationType = String(payload?.destination?.type || '').trim().toLowerCase();
      if (!validFieldFlowId(destinationUuid, 'ff_d_') || !validPilotDestinationType(destinationType)) {
        throw Object.assign(new Error('Pilot destination invalid'), { code: 'pilot_destination_invalid', http: 400 });
      }
      if (String(payload?.pilot?.phase || '') !== '5C.7' || payload?.pilot?.safe_projection_only !== true || payload?.pilot?.full_operational_payload_allowed === true) {
        throw Object.assign(new Error('Pilot safety contract invalid'), { code: 'pilot_safety_contract_invalid', http: 403 });
      }
      const ttl = Math.max(SAFE_PILOT_MIN_TTL_SECONDS, Math.min(SAFE_PILOT_MAX_TTL_SECONDS, Number(payload?.pilot?.ttl_seconds || 900)));
      const target = await validatePilotTargetUrl(String(payload?.target?.url || ''));
      const hmac = await resolveHmacSecret();
      if (!hmac.value || hmac.value.length < 32) throw Object.assign(new Error('HMAC not configured'), { code: 'broker_hmac_not_configured', http: 503 });
      const expiresAt = Math.floor(Date.now()/1000) + ttl;
      const token = safePilotToken(destinationUuid, destinationType, target.endpointSha256, expiresAt, hmac.value);
      return jsonResponse(200, {
        ok: true,
        safe_live_pilot: true,
        phase: '5C.7',
        destination_uuid: destinationUuid,
        endpoint_host: target.host,
        normalized_target_url: target.url,
        endpoint_sha256: target.endpointSha256,
        pilot_token: token,
        expires_at_unix: expiresAt,
        ttl_seconds: ttl,
        safe_projection_only: true,
        full_operational_payload_allowed: false,
        token_exposed: false,
        secret_exposed: false
      });
    } catch (error) {
      const status = Number.isInteger(error?.http) ? error.http : 502;
      const code = String(error?.code || 'pilot_prepare_failed');
      context.error(`Safe live pilot prepare failed, code=${code}`);
      return jsonResponse(status, { ok:false, safe_live_pilot:true, phase:'5C.7', error:code, safe_projection_only:true, full_operational_payload_allowed:false });
    }
  }
});

app.http('safeLivePilotDeliver', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'v1/pilot/deliver',
  handler: async (request, context) => {
    try {
      const payload = await readAndAuthenticate(request, SAFE_PILOT_DELIVERY_CONTRACT, SAFE_PILOT_DELIVERY_CONTRACT_VERSION);
      const destinationUuid = String(payload?.destination?.uuid || '').trim();
      const destinationType = String(payload?.destination?.type || '').trim().toLowerCase();
      const deliveryKey = String(payload?.delivery?.key || '').trim();
      const pilotKind = String(payload?.pilot?.kind || '').trim().toLowerCase();
      if (!validFieldFlowId(destinationUuid, 'ff_d_') || !validPilotDestinationType(destinationType) || !validDeliveryKey(deliveryKey)) {
        throw Object.assign(new Error('Pilot delivery context invalid'), { code: 'pilot_delivery_context_invalid', http: 400 });
      }
      if (!['synthetic','one_shot'].includes(pilotKind) || String(payload?.pilot?.phase || '') !== '5C.7' || payload?.pilot?.safe_projection_only !== true || payload?.pilot?.full_operational_payload_allowed === true) {
        throw Object.assign(new Error('Pilot delivery safety contract invalid'), { code: 'pilot_delivery_safety_invalid', http: 403 });
      }
      const target = await validatePilotTargetUrl(String(payload?.target?.url || ''));
      const hmac = await resolveHmacSecret();
      if (!hmac.value || hmac.value.length < 32) throw Object.assign(new Error('HMAC not configured'), { code: 'broker_hmac_not_configured', http: 503 });
      const tokenCheck = verifySafePilotToken(String(payload?.pilot?.token || ''), destinationUuid, destinationType, target.endpointSha256, hmac.value);
      if (!tokenCheck.ok) throw Object.assign(new Error('Pilot token rejected'), { code: tokenCheck.code || 'pilot_token_rejected', http: 403 });
      const projection = safePilotProjection(payload);
      const targetPayload = {
        contract: SAFE_PILOT_TARGET_CONTRACT,
        contract_version: SAFE_PILOT_TARGET_CONTRACT_VERSION,
        tenant: { tenant_id: String(payload?.tenant?.tenant_id || ''), installation_id: String(payload?.tenant?.installation_id || '') },
        event: { uuid: projection.event_uuid, name: projection.event_name, occurred_at: projection.occurred_at },
        destination: { uuid: destinationUuid, type: destinationType },
        delivery: { key: deliveryKey, pilot_kind: pilotKind, safe_projection_only: true },
        pilot: { phase:'5C.7', kind:pilotKind, safe_projection_only:true, full_operational_payload_allowed:false },
        data: { safe_projection: projection }
      };
      const remote = await postSafePilotTarget(target, targetPayload, hmac.value);
      const remoteStatus = Number(remote.httpStatus || 0);
      const delivered = remoteStatus >= 200 && remoteStatus < 300;
      const outcome = delivered ? 'delivered' : 'pilot_failed';
      const receiverRequestId = String(remote?.body?.receiver_request_id || '').slice(0,96);
      const receipt = {
        contract: SAFE_PILOT_RECEIPT_CONTRACT,
        contract_version: SAFE_PILOT_RECEIPT_CONTRACT_VERSION,
        delivery_key: deliveryKey,
        event_uuid: projection.event_uuid,
        destination_uuid: destinationUuid,
        endpoint_sha256: target.endpointSha256,
        remote_http_status: remoteStatus,
        outcome,
        receiver_request_id: receiverRequestId,
        received_at: new Date().toISOString()
      };
      receipt.signature = signSafePilotReceipt(receipt, hmac.value);
      return jsonResponse(200, {
        ok: true,
        safe_live_pilot: true,
        phase: '5C.7',
        pilot_kind: pilotKind,
        delivery_performed: true,
        delivered,
        endpoint_sha256: target.endpointSha256,
        remote_http_status: remoteStatus,
        transport_error: String(remote?.transportError || '').slice(0,96),
        receipt,
        safe_projection_only: true,
        full_operational_payload_allowed: false,
        token_exposed: false,
        secret_exposed: false
      });
    } catch (error) {
      const status = Number.isInteger(error?.http) ? error.http : 502;
      const code = String(error?.code || 'pilot_delivery_failed');
      context.error(`Safe live pilot delivery failed, code=${code}`);
      return jsonResponse(status, { ok:false, safe_live_pilot:true, phase:'5C.7', delivery_performed:false, error:code, safe_projection_only:true, full_operational_payload_allowed:false });
    }
  }
});

app.http('safeLivePilotSink', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'v1/pilot-sink',
  handler: async (request, context) => {
    try {
      const payload = await readAndAuthenticate(request, SAFE_PILOT_TARGET_CONTRACT, SAFE_PILOT_TARGET_CONTRACT_VERSION);
      if (String(payload?.pilot?.phase || '') !== '5C.7' || payload?.pilot?.safe_projection_only !== true || payload?.pilot?.full_operational_payload_allowed === true) {
        throw Object.assign(new Error('Pilot sink safety contract invalid'), { code:'pilot_sink_safety_invalid', http:400 });
      }
      const projection = safePilotProjection(payload);
      const requestId = crypto.randomUUID();
      return jsonResponse(200, {
        ok:true,
        safe_live_pilot_sink:true,
        receiver_request_id:requestId,
        event_uuid:projection.event_uuid,
        event_name:projection.event_name,
        safe_projection_only:true,
        persisted:false
      });
    } catch (error) {
      const status = Number.isInteger(error?.http) ? error.http : 400;
      const code = String(error?.code || 'pilot_sink_rejected');
      context.error(`Safe live pilot sink rejected request, code=${code}`);
      return jsonResponse(status, { ok:false, safe_live_pilot_sink:true, error:code, persisted:false });
    }
  }
});

app.http('controlledDelivery', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'v1/controlled-delivery',
  handler: async (request, context) => {
    try {
      const payload = await readAndAuthenticate(request, CONTROLLED_DELIVERY_CONTRACT, CONTROLLED_DELIVERY_CONTRACT_VERSION);
      const destinationUuid = String(payload?.destination?.uuid || '').trim();
      const destinationType = String(payload?.destination?.type || '').trim().toLowerCase();
      const deliveryKey = String(payload?.delivery?.key || '').trim();
      const attempt = Number(payload?.delivery?.attempt || 0);
      const maxAttempts = Number(payload?.delivery?.max_attempts || 0);
      const behavior = controlledBehavior(payload?.delivery?.certification_behavior);
      const certification = payload?.data?.certification || {};

      if (!validFieldFlowId(destinationUuid, 'ff_d_') || !['external_api', 'webhook'].includes(destinationType)) {
        throw Object.assign(new Error('Invalid controlled destination'), { code: 'controlled_destination_invalid', http: 400 });
      }
      if (!validDeliveryKey(deliveryKey)) {
        throw Object.assign(new Error('Invalid controlled delivery key'), { code: 'controlled_delivery_key_invalid', http: 400 });
      }
      if (payload?.delivery?.certification_only !== true || String(certification?.phase || '') !== '5C.6') {
        throw Object.assign(new Error('Controlled delivery is certification only'), { code: 'controlled_delivery_not_certification', http: 403 });
      }
      if (!Number.isInteger(attempt) || attempt < 1 || attempt > 20 || !Number.isInteger(maxAttempts) || maxAttempts < 1 || maxAttempts > 20 || attempt > maxAttempts) {
        throw Object.assign(new Error('Invalid attempt context'), { code: 'controlled_attempt_invalid', http: 400 });
      }
      if (!behavior) {
        throw Object.assign(new Error('Invalid certification behavior'), { code: 'controlled_behavior_invalid', http: 400 });
      }

      const hmac = await resolveHmacSecret();
      if (!hmac.value || hmac.value.length < 32) {
        throw Object.assign(new Error('HMAC not configured'), { code: 'broker_hmac_not_configured', http: 503 });
      }

      const receiverPayload = {
        contract: CONTROLLED_RECEIVER_CONTRACT,
        contract_version: CONTROLLED_RECEIVER_CONTRACT_VERSION,
        tenant: {
          tenant_id: String(payload?.tenant?.tenant_id || ''),
          installation_id: String(payload?.tenant?.installation_id || '')
        },
        event: {
          uuid: String(payload?.event?.uuid || ''),
          name: 'connector.controlled_receiver.certification',
          occurred_at: new Date().toISOString()
        },
        destination: { uuid: destinationUuid, type: destinationType },
        delivery: {
          key: deliveryKey,
          attempt,
          max_attempts: maxAttempts,
          certification_behavior: behavior,
          certification_only: true
        },
        data: {
          certification: {
            phase: '5C.6',
            release: String(certification?.release || ''),
            probe: String(certification?.probe || 'controlled_delivery'),
            marker: String(certification?.marker || '').slice(0, 96)
          }
        }
      };

      const remote = await postControlledReceiver(receiverPayload, hmac.value);
      const remoteStatus = Number(remote.httpStatus || 0);
      const delivered = remoteStatus >= 200 && remoteStatus < 300 && remote?.body?.ok === true;
      const outcome = delivered ? 'delivered' : 'retryable_failure';
      const receiverRequestId = String(remote?.body?.receiver_request_id || '').slice(0, 96);
      const receipt = {
        contract: CONTROLLED_RECEIPT_CONTRACT,
        contract_version: CONTROLLED_RECEIPT_CONTRACT_VERSION,
        delivery_key: deliveryKey,
        event_uuid: String(payload?.event?.uuid || ''),
        destination_uuid: destinationUuid,
        remote_http_status: remoteStatus,
        outcome,
        receiver_request_id: receiverRequestId,
        received_at: new Date().toISOString()
      };
      receipt.signature = signControlledReceipt(receipt, hmac.value);

      return jsonResponse(200, {
        ok: true,
        controlled_delivery: true,
        certification_only: true,
        external_target_locked: true,
        delivery_performed: true,
        delivered,
        remote_http_status: remoteStatus,
        receiver_request_id: receiverRequestId,
        receipt,
        token_exposed: false,
        secret_exposed: false
      });
    } catch (error) {
      const status = Number.isInteger(error?.http) ? error.http : 502;
      const code = String(error?.code || 'controlled_delivery_failed');
      context.error(`Controlled delivery failed, code=${code}`);
      return jsonResponse(status, {
        ok: false,
        controlled_delivery: true,
        certification_only: true,
        delivery_performed: false,
        error: code,
        token_exposed: false,
        secret_exposed: false
      });
    }
  }
});

app.http('controlledReceiver', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'v1/controlled-receiver',
  handler: async (request, context) => {
    try {
      const payload = await readAndAuthenticate(request, CONTROLLED_RECEIVER_CONTRACT, CONTROLLED_RECEIVER_CONTRACT_VERSION);
      const deliveryKey = String(payload?.delivery?.key || '').trim();
      const attempt = Number(payload?.delivery?.attempt || 0);
      const behavior = controlledBehavior(payload?.delivery?.certification_behavior);
      if (!validDeliveryKey(deliveryKey) || payload?.delivery?.certification_only !== true || String(payload?.data?.certification?.phase || '') !== '5C.6' || !behavior || !Number.isInteger(attempt) || attempt < 1 || attempt > 20) {
        throw Object.assign(new Error('Invalid controlled receiver contract'), { code: 'controlled_receiver_contract_invalid', http: 400 });
      }
      const receiverRequestId = crypto.randomUUID();
      const shouldFail = behavior === 'fail_always' || (behavior === 'fail_first' && attempt === 1);
      if (shouldFail) {
        return jsonResponse(503, {
          ok: false,
          certification_receiver: true,
          receiver_request_id: receiverRequestId,
          delivery_key: deliveryKey,
          attempt,
          retryable: true,
          persisted: false
        });
      }
      return jsonResponse(200, {
        ok: true,
        certification_receiver: true,
        receiver_request_id: receiverRequestId,
        delivery_key: deliveryKey,
        attempt,
        retryable: false,
        persisted: false
      });
    } catch (error) {
      const status = Number.isInteger(error?.http) ? error.http : 400;
      const code = String(error?.code || 'controlled_receiver_failed');
      context.error(`Controlled receiver rejected request, code=${code}`);
      return jsonResponse(status, { ok: false, certification_receiver: true, error: code, persisted: false });
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
