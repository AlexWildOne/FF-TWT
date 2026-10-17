# FieldFlow 2.2.451 Offline Operations Intelligence

## Objective

Make offline behaviour observable without weakening the 2.2.449 Zero-Loss contract or the 2.2.450 runtime-only upgrade path.

## Runtime contract

- The local outbox remains the durable source of truth until a strict `fieldflow-form-ack-v1` acknowledgement is verified.
- The client keeps a bounded local synchronization history with protected, syncing, confirmed, pending, attention, conflict and recovered states.
- Device telemetry reports queue age, maximum retry count, acknowledgement errors, degraded-network duration, authentication interruptions, app resumes, automatic recoveries, storage usage and storage persistence.
- Terminal client errors no longer discard a form outbox item automatically. The item remains in attention state until corrected or explicitly removed by the operator.
- The BO Devices screen calculates a non-destructive offline health score and exposes the most recent synchronization events per device.

## Form definition identity

Every rendered/resolved form now carries three additive identifiers:

- `definition_version`, a compact fingerprint of the form definition.
- `schema_hash`, a full SHA-256 of the resolved schema actually shown to the merchandiser.
- `downloaded_at`, the time at which the form payload was rendered/generated.

Offline replay keeps the existing schema revision conflict protection and additionally verifies the full schema hash when available. The server stores both client and server definition evidence in submission metadata for later audit.

## Privacy and storage

No new database table is introduced. Current device intelligence is stored in the existing mobile device `meta_json`, with a bounded sanitized history. The device keeps only a bounded local history. No answer values or photographs are copied into operational telemetry.

## Certification

Release Gate 1.5.0 makes Offline Operations Intelligence mandatory. Phase 6 Stable advances from 2.2.450 to 2.2.451 and remains subject to canonical WordPress Release Manager publication, exact package SHA-256, Installation UUID, activation binding and the Azure-backed 6B certification chain.

## Database

No database migration is introduced by 2.2.451.
