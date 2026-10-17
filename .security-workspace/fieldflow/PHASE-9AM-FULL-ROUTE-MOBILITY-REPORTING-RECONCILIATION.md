# Phase 9AM, Full Route Mobility Reporting Reconciliation

Contract: `fieldflow-full-route-mobility-reporting-reconciliation-v1`

`SOURCE_RELEASE` is `2.2.556` and `STABLE_RELEASE` is `2.2.557`.

## Goal

Ensure Daily Close mobility reporting never treats the stop-to-stop distance as the full executed route when access and return legs are available.

The reporting distance is reconciled from the complete route geometry:

`access_distance_km + inter_stop_distance_km + return_distance_km`

If the canonical/known total is lower than a coherent full-route component sum, reporting uses the full-route sum and recomputes the mobility estimate with the existing `MobilityCostService`. Canonical route metrics are not mutated.

## Reporting rules

- Prefer the canonical full total when it already matches or exceeds the complete component sum.
- Reconcile upward when a complete component triplet proves that access and/or return were omitted from the stored total.
- Use persisted route-stop legs as a second reporting source when route meta components are incomplete or stale.
- Keep recovered values explicit and partial. Never silently upgrade historical route integrity.
- Recalculate estimated tolls on the reconciled full route only when the toll is not from a real routing API and no complete persisted leg toll total exists.
- Email and Excel expose `Km acesso`, `Km entre PDVs`, `Km regresso` and the full executed total.
- No schema migration. `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- Submission semantics remain frozen at `SubmissionPipeline::BUILD = 2.2.485`.
