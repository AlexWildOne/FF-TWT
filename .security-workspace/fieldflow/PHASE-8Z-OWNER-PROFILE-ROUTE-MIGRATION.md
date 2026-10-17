# Phase 8Z, Owner Profile Route Migration

Contract: `fieldflow-owner-profile-route-migration-v1`

`SOURCE_RELEASE` is `2.2.510` and `STABLE_RELEASE` is `2.2.511`.

## Purpose

Repair editable routes already persisted during the endpoint-transition period with the first PDV as departure and the last PDV as arrival, without requiring route-by-route editing.

## Behaviour

- Reconciliation remains scoped to `draft` and `planned` routes and preserves the existing started-route safety gate.
- When a specific operative is selected and the admin explicitly enables `Forçar a Base habitual deste operativo`, the operative route-base profile is authoritative for that reconciliation run.
- The forced migration bypasses the legacy early `endpoints_complete` short-circuit and ignores historical route endpoint recovery for that run.
- Start/end endpoints are replaced from the saved operator profile, canonical route metrics are recalculated, and PDV order is preserved with `manual_order_locked`.
- General reconciliation without the explicit force option keeps the existing precedence and conservative behaviour.

## Safety

- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No IndexedDB, offline, media recovery, form, Conditional Engine or submission change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- Started, completed and cancelled routes are not rewritten by the forced migration path.
