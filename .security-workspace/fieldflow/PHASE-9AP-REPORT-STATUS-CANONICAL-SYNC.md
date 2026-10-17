# Phase 9AP - Report Status Canonical Sync

Contract: `fieldflow-report-status-canonical-sync-v1`

`SOURCE_RELEASE` is `2.2.559` and `STABLE_RELEASE` is `2.2.560`.

## Goal

Keep the `Reportar` visit selector aligned with the canonical operational visit state already used by `Minha Rota`.

## Rules

1. A valid operational submission closes the canonical visit state.
2. `/routes/{id}` exposes `canonical_status`, `legacy_status` and `status_source` for every stop and projects canonical `completed` as front status `done`.
3. `Reportar` requests route state live first when connectivity exists. Offline cache is fallback only.
4. `fieldflow:visit-completed` updates the Reportar selector immediately in the current DOM.
5. A recent terminal state may protect an offline cached response from visually regressing to `pending`, but a fresh live server response remains authoritative and can legitimately reopen/reset a visit.
6. `Minha Rota`, `Reportar`, route completion, Daily Close and submission semantics remain based on the existing canonical services.

## Non-scope

- No database schema change.
- No change to `SubmissionPipeline` certified build `2.2.485`.
- No change to visit completion policy, routing, mobility, mail semantics or FlutterFlow.
