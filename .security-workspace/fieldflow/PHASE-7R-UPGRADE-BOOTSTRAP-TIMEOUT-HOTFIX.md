# FieldFlow 2.2.450 Upgrade Bootstrap Timeout Hotfix

## Objective

Prevent WordPress/nginx 504 timeouts caused by running the complete FieldFlow Activator on releases that do not contain a database schema change.

## Root cause

Before 2.2.450, `Plugin::maybeActivateOnVersionChange()` compared `routespro_version` directly with the plugin runtime version. Any mismatch invoked `Activator::activate()`. The Activator performs a large compatibility census, table existence checks, legacy migrations and schema maintenance. This was unnecessary for 2.2.449 because its latest versioned schema remained 2.2.436.

## Contract

- Runtime version and schema version are independent upgrade signals.
- `Migrations::LATEST_SCHEMA_VERSION` is 2.2.436.
- Installations already on 2.2.448 or newer with `routespro_schema_version >= 2.2.436` use a runtime-only upgrade.
- The runtime-only path executes before the heavy schema upgrade lock.
- It updates `routespro_version`, records `fieldflow_last_runtime_upgrade`, clears a stale legacy upgrade lock and does not call `Activator::activate()`.
- Fresh installations, older installations and schemas behind 2.2.436 retain the full Activator fallback.
- The 2.2.449 Offline Zero-Loss implementation is preserved unchanged apart from the runtime build identifier.

## Certification

Release Gate 1.4.0 validates the runtime-only bootstrap markers, schema baseline, installed runtime option and ordering of the lightweight path before the schema lock. Phase 6 Stable advances from 2.2.449 to 2.2.450 and remains subject to canonical WordPress Release Manager publication, package SHA-256 and the Azure-backed 6B certification chain.

## Database

No database migration is introduced by 2.2.450.
