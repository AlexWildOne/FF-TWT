# Azure Licensing, Phase 6B.2 contract

The WordPress 2.2.383 client intentionally reuses the existing routes:

- `POST /license/activate`
- `POST /license/validate`
- `POST /license/deactivate`

No new public route is required for the first 6B.2 pilot.

## New optional request fields

The API should accept and ignore safely if it does not yet persist:

- `installation_id`
- `installation_uuid`
- `installation_fingerprint`
- `environment_hash`
- `plugin_version`
- `wp_version`
- `php_version`
- `heartbeat`
- `heartbeat_phase`
- `heartbeat_version`

Legacy `fingerprint` remains present for backwards compatibility.

## Expected validate response

For a valid activation the preferred response is:

```json
{
  "success": true,
  "message": "license validated",
  "license": {
    "license_id": "lic_...",
    "activation_id": "act_...",
    "status": "active",
    "domain": "example.com",
    "fingerprint": "...",
    "installation_uuid": "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx",
    "installation_fingerprint": "<sha256>",
    "heartbeat_recorded": true
  }
}
```

The 2.2.383 pilot remains operational if a legacy server omits the new fields, and an accepted heartbeat still never blocks FieldFlow. Final 6B.2 certification is intentionally stricter: it requires a real `license_id`, `activation_id`, successful validation, domain match, the echoed `installation_uuid`, matching `installation_fingerprint`, and `heartbeat_recorded=true`.

## Server-side heartbeat persistence

When `heartbeat=true`, the service should update `license_activations.last_seen_at` for the resolved activation and persist the installation metadata when those columns are available. It should then echo the stored `installation_uuid`, `installation_fingerprint`, and `heartbeat_recorded=true`. The SQL patch in `phase-6b2-schema.sql` is optional for the first compatibility test, but is required before final 6B.2 certification if the central licensing service uses `license_activations` for this inventory.
