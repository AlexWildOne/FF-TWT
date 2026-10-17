'use strict';

const { app } = require('@azure/functions');
const { DefaultAzureCredential } = require('@azure/identity');
const { BlobServiceClient } = require('@azure/storage-blob');
const AdmZip = require('adm-zip');
const crypto = require('crypto');

const VERSION = '1.0.0';
const PRODUCT = 'fieldflow';
const MAX_CLOCK_SKEW_SECONDS = 300;
const DEFAULT_MAX_BODY_BYTES = 64 * 1024 * 1024;
const credential = new DefaultAzureCredential();
let blobService = null;

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

function header(request, name) {
  return String(request.headers.get(name) || '').trim();
}

function safeEqualText(a, b) {
  const ah = crypto.createHash('sha256').update(String(a || ''), 'utf8').digest();
  const bh = crypto.createHash('sha256').update(String(b || ''), 'utf8').digest();
  return crypto.timingSafeEqual(ah, bh);
}

function safeEqualHex(a, b) {
  if (!/^[a-f0-9]{64}$/i.test(String(a || '')) || !/^[a-f0-9]{64}$/i.test(String(b || ''))) return false;
  return crypto.timingSafeEqual(Buffer.from(a, 'hex'), Buffer.from(b, 'hex'));
}

function maxBodyBytes() {
  const n = Number(env('FIELDFLOW_RELEASE_MAX_BYTES', String(DEFAULT_MAX_BODY_BYTES)));
  return Number.isSafeInteger(n) && n > 0 && n <= 128 * 1024 * 1024 ? n : DEFAULT_MAX_BODY_BYTES;
}

function storageClient() {
  if (blobService) return blobService;
  const url = env('FIELDFLOW_RELEASE_STORAGE_URL');
  if (!/^https:\/\/[^/]+\.blob\.core\.windows\.net$/i.test(url)) {
    throw Object.assign(new Error('Release storage URL is not configured'), { code: 'storage_not_configured', http: 503 });
  }
  blobService = new BlobServiceClient(url, credential);
  return blobService;
}

function validateVersion(value) {
  const v = String(value || '').trim();
  return /^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/.test(v) ? v : '';
}

function validateChannel(value) {
  const c = String(value || '').trim().toLowerCase();
  return ['stable', 'beta'].includes(c) ? c : '';
}

async function authenticate(request, body) {
  const timestampRaw = header(request, 'x-routespro-time');
  const timestamp = Number(timestampRaw);
  if (!Number.isInteger(timestamp) || Math.abs(Math.floor(Date.now() / 1000) - timestamp) > MAX_CLOCK_SKEW_SECONDS) {
    throw Object.assign(new Error('Expired or invalid timestamp'), { code: 'invalid_timestamp', http: 401 });
  }

  const product = header(request, 'x-routespro-product').toLowerCase();
  if (product !== PRODUCT) throw Object.assign(new Error('Invalid product'), { code: 'invalid_product', http: 403 });

  const shared = env('ROUTESPRO_LICENSE_SHARED_SECRET');
  const admin = env('ROUTESPRO_LICENSE_ADMIN_SECRET');
  if (shared.length < 24 || admin.length < 16) {
    throw Object.assign(new Error('Publisher trust is not configured'), { code: 'publisher_trust_not_configured', http: 503 });
  }

  const suppliedAdmin = header(request, 'x-routespro-admin-secret')
    || header(request, 'x-fieldflow-admin-secret')
    || header(request, 'x-license-admin-secret');
  if (!suppliedAdmin || !safeEqualText(suppliedAdmin, admin)) {
    throw Object.assign(new Error('Invalid admin secret'), { code: 'invalid_admin_secret', http: 403 });
  }

  const suppliedSignature = header(request, 'x-routespro-signature').toLowerCase();
  const expected = crypto.createHmac('sha256', shared)
    .update(Buffer.from(`${timestampRaw}.`, 'utf8'))
    .update(body)
    .digest('hex');
  if (!safeEqualHex(expected, suppliedSignature)) {
    throw Object.assign(new Error('Invalid HMAC signature'), { code: 'invalid_signature', http: 401 });
  }
}

function isUnsafeName(name) {
  const n = String(name || '').replace(/\\/g, '/');
  const l = n.toLowerCase();
  if (!n || n.startsWith('/') || n.includes('\0') || n.includes('../') || n.includes('/..')) return true;
  for (const needle of ['/.git/', '/.svn/', '/node_modules/', '/.env', '.pem', '.pfx', '.p12', '.key', 'id_rsa', 'id_ed25519', 'wp-config.php']) {
    if (`/${l}`.includes(needle)) return true;
  }
  return false;
}

function normaliseRelative(value) {
  const p = String(value || '').replace(/\\/g, '/').replace(/^\/+/, '');
  if (!p || p.includes('../') || p.includes('/..')) return '';
  return p;
}

function validatePackage(body, expectedVersion) {
  let zip;
  try { zip = new AdmZip(body); }
  catch { throw Object.assign(new Error('Invalid ZIP package'), { code: 'invalid_zip', http: 400 }); }

  const entries = zip.getEntries();
  if (!entries.length || entries.length > 12000) {
    throw Object.assign(new Error('ZIP entry count outside safety limit'), { code: 'zip_entry_limit', http: 400 });
  }

  const roots = new Set();
  const files = new Map();
  let main = null;
  let manifestRaw = null;

  for (const entry of entries) {
    const name = String(entry.entryName || '').replace(/\\/g, '/');
    if (isUnsafeName(name)) throw Object.assign(new Error(`Unsafe ZIP path: ${name}`), { code: 'unsafe_zip_path', http: 400 });
    const root = name.replace(/^\/+/, '').split('/')[0];
    if (root) roots.add(root);
    if (entry.isDirectory) continue;
    if (!name.startsWith('fieldflow/')) {
      throw Object.assign(new Error('ZIP must contain only the fieldflow/ root'), { code: 'noncanonical_root', http: 400 });
    }
    if (name === 'fieldflow/fieldflow.php') main = entry.getData();
    if (name === 'fieldflow/production-manifest.json') {
      manifestRaw = entry.getData().toString('utf8');
      continue;
    }
    const relative = normaliseRelative(name.slice('fieldflow/'.length));
    if (!relative) continue;
    const data = entry.getData();
    files.set(relative, { sha256: crypto.createHash('sha256').update(data).digest('hex'), bytes: data.length });
  }

  if (roots.size !== 1 || !roots.has('fieldflow') || !main || !manifestRaw) {
    throw Object.assign(new Error('Canonical fieldflow/ package structure not found'), { code: 'noncanonical_package', http: 400 });
  }

  const mainText = main.toString('utf8');
  const versionMatch = /Version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?)/i.exec(mainText);
  const internalVersion = versionMatch ? versionMatch[1] : '';
  if (!internalVersion || internalVersion !== expectedVersion) {
    throw Object.assign(new Error('Internal plugin version does not match requested release'), { code: 'version_mismatch', http: 400 });
  }

  let manifest;
  try { manifest = JSON.parse(manifestRaw); }
  catch { throw Object.assign(new Error('Invalid production manifest JSON'), { code: 'invalid_manifest', http: 400 }); }
  if (String(manifest.version || '') !== expectedVersion) {
    throw Object.assign(new Error('Production manifest version mismatch'), { code: 'manifest_version_mismatch', http: 400 });
  }

  const expectedFiles = new Map();
  const manifestFiles = manifest && manifest.files && typeof manifest.files === 'object' ? manifest.files : {};
  if (Array.isArray(manifestFiles)) {
    for (const row of manifestFiles) {
      if (!row || typeof row !== 'object') continue;
      const p = normaliseRelative(row.path);
      if (!p || p === 'production-manifest.json') continue;
      expectedFiles.set(p, { sha256: String(row.sha256 || '').toLowerCase(), bytes: Number(row.bytes) });
    }
  } else {
    for (const [path, row] of Object.entries(manifestFiles)) {
      if (!row || typeof row !== 'object') continue;
      const p = normaliseRelative(path);
      if (!p || p === 'production-manifest.json') continue;
      expectedFiles.set(p, { sha256: String(row.sha256 || '').toLowerCase(), bytes: Number(row.bytes) });
    }
  }
  if (Number(manifest.file_count || 0) !== files.size || expectedFiles.size !== files.size) {
    throw Object.assign(new Error('Production manifest file count mismatch'), { code: 'manifest_file_count_mismatch', http: 400 });
  }

  const sorted = [...files.keys()].sort((a, b) => (a < b ? -1 : (a > b ? 1 : 0)));
  let aggregateSource = '';
  for (const p of sorted) {
    const actual = files.get(p);
    const expected = expectedFiles.get(p);
    if (!expected || !safeEqualHex(actual.sha256, expected.sha256) || actual.bytes !== expected.bytes) {
      throw Object.assign(new Error(`Production manifest mismatch: ${p}`), { code: 'manifest_file_mismatch', http: 400 });
    }
    aggregateSource += `${p}\t${actual.sha256}\t${actual.bytes}\n`;
  }
  const aggregate = crypto.createHash('sha256').update(aggregateSource, 'utf8').digest('hex');
  if (!safeEqualHex(aggregate, String(manifest.aggregate_sha256 || '').toLowerCase())) {
    throw Object.assign(new Error('Production manifest aggregate mismatch'), { code: 'manifest_aggregate_mismatch', http: 400 });
  }

  return { version: internalVersion, fileCount: files.size, aggregateSha256: aggregate };
}

app.http('health', {
  methods: ['GET'],
  authLevel: 'anonymous',
  route: 'health',
  handler: async () => jsonResponse(200, {
    ok: true,
    service: 'fieldflow-release-publisher',
    version: VERSION,
    credential_model: 'managed_identity',
    immutable_uploads: true,
    canonical_root: 'fieldflow/',
    max_package_bytes: maxBodyBytes()
  })
});

app.http('releaseUpload', {
  methods: ['POST'],
  authLevel: 'anonymous',
  route: 'release/upload',
  handler: async (request, context) => {
    try {
      const version = validateVersion(request.query.get('version'));
      const channel = validateChannel(request.query.get('channel'));
      if (!version || !channel) return jsonResponse(400, { success: false, code: 'invalid_release_identity', message: 'Invalid version or channel.' });

      const arrayBuffer = await request.arrayBuffer();
      const body = Buffer.from(arrayBuffer);
      if (!body.length || body.length > maxBodyBytes()) return jsonResponse(413, { success: false, code: 'package_too_large', message: 'Package is empty or too large.' });
      await authenticate(request, body);

      const actualSha = crypto.createHash('sha256').update(body).digest('hex');
      const expectedSha = header(request, 'x-fieldflow-package-sha256').toLowerCase();
      const expectedBytes = Number(header(request, 'x-fieldflow-package-bytes'));
      if (!safeEqualHex(actualSha, expectedSha) || expectedBytes !== body.length) {
        return jsonResponse(400, { success: false, code: 'package_fingerprint_mismatch', message: 'Package SHA-256 or byte count mismatch.' });
      }

      const validated = validatePackage(body, version);
      const containerName = env('FIELDFLOW_RELEASE_CONTAINER', 'fieldflow-private-releases');
      if (!/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/.test(containerName)) {
        throw Object.assign(new Error('Invalid release container configuration'), { code: 'invalid_container', http: 503 });
      }
      const blobName = `fieldflow/${channel}/${version}/fieldflow-${version}.zip`;
      const container = storageClient().getContainerClient(containerName);
      await container.createIfNotExists();
      const blob = container.getBlockBlobClient(blobName);

      if (await blob.exists()) {
        const props = await blob.getProperties();
        const oldSha = String(props.metadata?.sha256 || '').toLowerCase();
        const oldBytes = Number(props.contentLength || 0);
        if (oldSha && safeEqualHex(oldSha, actualSha) && oldBytes === body.length) {
          return jsonResponse(200, {
            success: true,
            idempotent: true,
            service: 'fieldflow-release-publisher',
            version,
            channel,
            blob_container: containerName,
            blob_name: blobName,
            package_sha256: actualSha,
            package_size: body.length,
            manifest_file_count: validated.fileCount,
            manifest_aggregate_sha256: validated.aggregateSha256
          });
        }
        return jsonResponse(409, { success: false, code: 'immutable_release_exists', message: 'A different package already exists for this version/channel.' });
      }

      await blob.uploadData(body, {
        blobHTTPHeaders: {
          blobContentType: 'application/zip',
          blobContentDisposition: `attachment; filename="fieldflow-${version}.zip"`,
          blobCacheControl: 'private, no-store, max-age=0'
        },
        metadata: { product: PRODUCT, version, channel, sha256: actualSha },
        conditions: { ifNoneMatch: '*' }
      });

      context.log(`Published immutable FieldFlow blob ${blobName} (${body.length} bytes, ${actualSha})`);
      return jsonResponse(201, {
        success: true,
        idempotent: false,
        service: 'fieldflow-release-publisher',
        version,
        channel,
        blob_container: containerName,
        blob_name: blobName,
        package_sha256: actualSha,
        package_size: body.length,
        manifest_file_count: validated.fileCount,
        manifest_aggregate_sha256: validated.aggregateSha256
      });
    } catch (error) {
      const status = Number(error?.http || 500);
      const code = String(error?.code || 'publisher_error');
      context.error(`Release publisher error ${code}: ${String(error?.message || error)}`);
      return jsonResponse(status >= 400 && status < 600 ? status : 500, { success: false, code, message: status >= 500 ? 'Release publisher unavailable.' : String(error?.message || 'Request rejected.') });
    }
  }
});
