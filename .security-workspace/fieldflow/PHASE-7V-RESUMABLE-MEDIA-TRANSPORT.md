# Phase 7V - Resumable Media Transport

Release: 2.2.454
Protocol: `fieldflow-resumable-media-v1`
Release Gate: 1.8.0

## Objective

Prevent weak or interrupted store connectivity from forcing large photographs to restart at byte zero. The device remains outbox-first and keeps the original Blob locally until the normal strict form ACK confirms persistence.

## Transport contract

1. The protected outbox computes the full media SHA-256 after the local row is durable.
2. `/offline/media/start` creates or resumes a session bound to user, device, mutation id, field name, file index, byte length and SHA-256.
3. The server returns received and missing chunk indexes. The default client chunk is 512 KiB.
4. `/offline/media/{upload_id}/chunk` accepts idempotent binary chunks and validates chunk size plus optional chunk SHA-256.
5. `/offline/media/{upload_id}/complete` reconstructs the file only when every chunk is present and validates exact total bytes plus full SHA-256.
6. The server returns a signed short-lived `media_ref`. The final form replay sends references instead of re-sending completed Blobs.
7. `Forms` resolves the references, binds them again to the same mutation/device/user, and passes safe temporary copies through the existing `wp_handle_sideload` / form upload path.
8. `fieldflow-media-integrity-v1`, mutation idempotency and `fieldflow-form-ack-v1` remain mandatory. The local outbox is removed only after strict ACK.

## Recovery semantics

- Killing the app after some chunks were received does not discard those chunks.
- Reopening and reconnecting calls `start` again and uploads only `missing_chunks`.
- A completed server session can be reused for an idempotent retry if the final form response was lost.
- If Web Crypto or the resumable endpoint is unavailable, the client falls back to the previous full multipart replay rather than blocking the operation.
- Temporary resumable sessions expire after 48 hours and are cleaned opportunistically. The original outbox copy remains available on the device for a fresh retry.

## Observability

Device telemetry exposes `resumable_uploaded_chunks`, `resumable_resumed_chunks` and `resumable_completed_files`. The Devices control center renders these counters without copying photographs or form answers into telemetry.

## Certification

Release Gate 1.8.0 requires the client transport, REST routes, server service, form integration, capability contract and device telemetry markers. The exact 2.2.454 ZIP must still be published through WordPress Release Manager and certified through the Azure-backed Phase 6 / 6B chain.

No database migration is required.
