'use strict';

const { app } = require('@azure/functions');
const { TableClient } = require('@azure/data-tables');
const crypto = require('crypto');

const TABLE_NAME = 'FieldFlowClientReceipts';
const MAX_BODY_BYTES = 2 * 1024 * 1024;
let tableReady = false;

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

function client() {
  const connection = String(process.env.AzureWebJobsStorage || '');
  if (!connection) throw Object.assign(new Error('Receiver storage is not configured'), { code: 'storage_not_configured' });
  return TableClient.fromConnectionString(connection, TABLE_NAME);
}

async function ensureTable(table) {
  if (tableReady) return;
  try { await table.createTable(); }
  catch (error) { if (error?.statusCode !== 409) throw error; }
  tableReady = true;
}

function safeKey(value, fallback) {
  const cleaned = String(value || '').replace(/[^A-Za-z0-9_.-]/g, '_').slice(0, 180);
  return cleaned || fallback;
}

app.http('clientAzureReceive', {
  methods: ['POST'],
  authLevel: 'function',
  route: 'v1/receive',
  handler: async (request, context) => {
    try {
      const raw = await request.text();
      if (Buffer.byteLength(raw, 'utf8') > MAX_BODY_BYTES) return jsonResponse(413, { ok: false, error: 'payload_too_large' });
      let payload;
      try { payload = JSON.parse(raw); }
      catch { return jsonResponse(400, { ok: false, error: 'invalid_json' }); }
      if (payload?.contract !== 'fieldflow.client_azure.delivery' || String(payload?.contract_version || '') !== '1.0') {
        return jsonResponse(400, { ok: false, error: 'invalid_contract' });
      }
      const deliveryKey = String(payload.delivery?.key || '').trim();
      const eventUuid = String(payload.event?.uuid || '').trim();
      if (!/^ff_del_[a-f0-9]{40}$/i.test(deliveryKey) || !eventUuid) return jsonResponse(400, { ok: false, error: 'invalid_delivery' });

      const table = client();
      await ensureTable(table);
      const entity = {
        partitionKey: safeKey(String(payload.tenant?.tenant_id || ''), 'tenant'),
        rowKey: deliveryKey,
        eventUuid: safeKey(eventUuid, 'event'),
        eventName: String(payload.event?.name || '').slice(0, 96),
        installationId: String(payload.tenant?.installation_id || '').slice(0, 64),
        clientId: Number(payload.context?.client_id || 0),
        projectId: Number(payload.context?.project_id || 0),
        probe: payload.delivery?.probe === true,
        bodySha256: crypto.createHash('sha256').update(raw, 'utf8').digest('hex'),
        receivedAtUtc: new Date().toISOString()
      };
      try {
        await table.createEntity(entity);
        return jsonResponse(200, { ok: true, received: true, duplicate: false, delivery_key: deliveryKey });
      } catch (error) {
        if (error?.statusCode === 409 || error?.code === 'EntityAlreadyExists') {
          return jsonResponse(200, { ok: true, received: true, duplicate: true, delivery_key: deliveryKey });
        }
        throw error;
      }
    } catch (error) {
      context.error(`Client Azure test receiver failed, code=${String(error?.code || 'receiver_error')}`);
      return jsonResponse(500, { ok: false, error: 'receiver_error' });
    }
  }
});
