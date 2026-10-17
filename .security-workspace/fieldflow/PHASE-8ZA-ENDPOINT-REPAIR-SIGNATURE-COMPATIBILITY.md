# Phase 8ZA, Endpoint Repair Signature Compatibility

Contract: `fieldflow-endpoint-repair-signature-compatibility-v1`

`SOURCE_RELEASE` is `2.2.511` and `STABLE_RELEASE` is `2.2.512`.

## Purpose

Restore the certified runtime contract of `RouteMetricsService::repairPlannedRouteIntegrity()` after 2.2.511 added a sixth argument for owner-profile migration and caused Phase 6 Stable certification to fail with `runtime:repair_signature`.

## Behaviour

- `repairPlannedRouteIntegrity()` again exposes exactly the historical five parameters: project, owner, date from, date to and actor.
- Forced owner-profile migration remains available through the dedicated `repairPlannedRouteIntegrityWithOwnerProfile()` entrypoint.
- Both public entrypoints share the same internal implementation, so the 2.2.511 route-migration behaviour is preserved without mutating the certified method signature.
- The 2.2.511 package marker is retained as a non-runtime compatibility marker so the validator already installed on sites can validate the hotfix package.

## Safety

- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No IndexedDB, offline, media recovery, form, Conditional Engine or submission change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- The change only separates the public API surface from the internal force-migration flag.
