# Phase 7Q, Offline Zero-Loss Foundation

Release: 2.2.449
Source release: 2.2.448

## Objective

Prevent loss of FieldFlow reports in stores with weak, intermittent or absent connectivity while keeping the existing Azure-certified installation and private release chain mandatory.

## Zero-Loss contract

1. Form data and photos are written to IndexedDB before the first network submission attempt.
2. Every form submission has an idempotent mutation ID.
3. HTTP status alone is never proof of persistence.
4. The local queue item is deleted only after a JSON acknowledgement with protocol `fieldflow-form-ack-v1`, `persisted=true`, a positive submission ID and the exact mutation ID.
5. HTML 200 responses, invalid JSON, timeouts, 5xx responses and incomplete acknowledgements remain pending locally.
6. Weak network is an explicit `degraded` state and uses store-and-forward behaviour.
7. Authentication expiry does not revoke the device or erase the queue.
8. Device revocation is only accepted through the explicit `fieldflow_device_revoked` policy.
9. The PWA service worker only caches FieldFlow static assets and never caches private REST/API payloads.
10. Release Gate 1.3.0 blocks Stable certification when any mandatory Zero-Loss marker is missing.

## Connectivity states

- `online`, authenticated probe completes within the safe threshold.
- `degraded`, browser reports connectivity but the FieldFlow probe is slow or unreliable.
- `offline`, no network connectivity is available.
- `auth_required`, the server answers 401 and the local queue is preserved.
- `revoked`, explicit device policy revocation is confirmed.

## Azure continuity

2.2.449 remains subject to the existing private release process, production manifest SHA-256 verification, Release Manager publication, 6B.2 activation/identity heartbeat, 6B.3 governance and 6B.4 private distribution checks. The offline runtime does not bypass licensing or Azure certification. It only allows already-authorised field work to be stored safely on the device until connectivity returns.

## Database impact

No database migration is required.
