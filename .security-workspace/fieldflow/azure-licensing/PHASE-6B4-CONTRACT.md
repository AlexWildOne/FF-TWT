# Phase 6B.4 Azure Licensing Contract

Backend target: fieldflow-licensing 1.0.5

## New persistence

Azure Table Storage:

- `FieldFlowUpdateReleases`

Blob container, default:

- `fieldflow-private-releases`

No Azure SQL migration is required.

## POST /api/license/updates

All requests retain the existing FieldFlow HMAC contract.

### operation=check

Client authorization requires active license + active Activation ID + matching Installation UUID/fingerprint. Input includes `current_version`, `channel`, `wp_version` and `php_version`.

Response fields include:

- `phase_6b4=true`;
- `entitlement=allowed`;
- `current_version` / `latest_version`;
- `newer_available` / `update_available`;
- compatibility decisions;
- `downgrade_blocked=true`;
- signed release manifest;
- optional short-lived package URL;
- update capability acknowledgement.

### operation=publish

Requires Admin Secret in addition to HMAC. The backend reads the private Blob, calculates SHA-256 and size itself and persists release metadata only after the package is readable and matches any supplied checksum.

### operation=inspect / unpublish

Require Admin Secret. They do not expose storage credentials.

## GET /api/updates/package?token=...

The package token is HMAC signed and short-lived. At download time the service revalidates release status, license status, plan, activation and Installation UUID. It then reads the private Blob using the Function storage connection and validates SHA-256 plus exact byte size before returning the ZIP.

## Release manifest authentication

The release manifest is canonicalized and signed with HMAC-SHA256 using the existing `ROUTESPRO_LICENSE_SHARED_SECRET`, so the already configured FieldFlow client can verify it independently.

`FIELDFLOW_UPDATE_DOWNLOAD_SECRET` may be configured as a dedicated server-side secret for short-lived package tokens; when absent it falls back to `ROUTESPRO_LICENSE_SHARED_SECRET`.

No secret is returned to the client.

## Safety invariants

- forced auto-updates: false;
- downgrade protection: true;
- rollback: manual_explicit;
- licensing governance remains SHADOW;
- package container stays private;
- storage account key never leaves Azure Function runtime;
- raw license keys are not logged or persisted by update operations.
