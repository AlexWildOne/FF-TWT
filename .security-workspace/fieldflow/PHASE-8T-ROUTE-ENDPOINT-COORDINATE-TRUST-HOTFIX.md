# Phase 8T - Route Endpoint Coordinate Trust Hotfix

Contract: `fieldflow-route-endpoint-coordinate-trust-v1`

`SOURCE_RELEASE` is `2.2.504` and `STABLE_RELEASE` is `2.2.505`.

## Scope

- The visible start/end address is authoritative over hidden latitude/longitude values in the route editor.
- Manual edits to a route endpoint immediately invalidate the old hidden coordinates.
- Server-side route preview re-geocodes start/end addresses through the configured cached Maps provider before calculating access and return legs.
- Corrected endpoint coordinates are persisted with the route on save, so stale coordinates do not survive into later previews or mobility cost calculations.
- Route summary exposes the actual start/end addresses used and warns when a zero access/return leg means the endpoint coincides with the first/last PDV.
- Mobility Cost Intelligence Release Gate markers are aligned with the certified export wording and historical schema statement.

## Certified boundaries

No P1-P4 routing or optimisation-rule change.

No database schema change.

No IndexedDB schema change.

No service worker ownership change.

No Draft/Media recovery change.

No SubmissionApplicationService or SubmissionSideEffectDispatcher change.

`SubmissionPipeline::BUILD` remains `2.2.485`.
