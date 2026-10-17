# Azure Licensing Contract, Phase 6B.3

Backend target: `fieldflow-licensing` 1.0.4
WordPress target: FieldFlow 2.2.384

## Compatibility

All existing 1.0.3 routes remain available:

- `POST /api/license/generate`
- `POST /api/license/activate`
- `POST /api/license/validate`
- `POST /api/license/deactivate`
- `POST /api/license/heartbeat`
- `GET /api/health`

Phase 6B.3 adds:

- `POST /api/license/manage`

The manage route uses the existing HMAC contract and additionally requires the configured administrative licensing secret.

## Manage operations

### inspect

Input: `operation=inspect`, `license_id`.

Returns the licence governance snapshot, current active activation count, policy fingerprint and supported governance capabilities.

### sync_policy

Input includes plan, max_activations, optional expires_at, capabilities and limits.

The service canonicalizes the snapshot and returns `policy_fingerprint_sha256`. Reducing max activations below the current active count returns HTTP 409.

### suspend / resume

Suspend changes the central licence status to `suspended`. Resume is accepted only for suspended licences and restores `active` when active activations exist, otherwise `issued`.

### revoke

Sets the central licence status to `revoked` and changes active activation records to `revoked`.

### rotate

Creates a replacement licence preserving the current policy. The old licence becomes `rotated`; its active activation records become `rotation_required`.

The response contains the replacement raw key exactly once. The backend does not persist the raw key, only its SHA-256 lookup hash and masked representation.

## Runtime status responses

Validate, activate and heartbeat reject centrally unusable licence states including `suspended`, `revoked`, `rotated` and expiry. The response includes a safe licence snapshot so FieldFlow 2.2.384 can record the decision in SHADOW.

WordPress 2.2.384 remains fail-open and does not turn these responses into runtime blocking.

## Storage

The service continues to use the existing Azure Table Storage entities:

- `FieldFlowLicenses`
- `FieldFlowActivations`
- `FieldFlowLicenseLogs`

Azure Table Storage is schema-less for the additional governance properties, therefore 1.0.4 requires no SQL migration, new table or downtime migration.
