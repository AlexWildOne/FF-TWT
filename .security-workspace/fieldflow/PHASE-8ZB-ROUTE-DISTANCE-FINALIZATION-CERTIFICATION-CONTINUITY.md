# Phase 8ZB, Route Distance Finalization & Certification Continuity

Contract: `fieldflow-route-distance-finalization-certification-continuity-v1`

`SOURCE_RELEASE` is `2.2.512` and `STABLE_RELEASE` is `2.2.513`.

## Purpose

Finalize reconciled route distance with the real ordered Google Routes sequence whenever the provider is available, while preserving the routing matrix as a fallback. Also preserve the visible certification history across plugin updates without weakening fingerprint revalidation.

## Distance behaviour

- Endpoint reconciliation still preserves the saved PDV order and does not re-optimize P1-P4.
- The routing matrix remains the planning and fallback source.
- After a draft/planned route is reconciled and persisted, `RouteMetricsService::maybeRefreshGoogleRoute()` is forced once for that exact route.
- Google Routes receives the complete ordered sequence: departure, every PDV by `route_stops.seq`, and arrival.
- When Google succeeds, the exact access, inter-stop, return and total distance replace hybrid matrix values as the final canonical metrics.
- When Google is disabled or unavailable, the existing matrix metrics remain available and the reconciliation result reports that fallback explicitly.
- Canonical metrics persist `routing_finalized_by`, `route_distance_finalization_contract`, and a component-sum delta for diagnostics.

## Certification behaviour

- Existing certification options and fingerprints are never cleared by this change.
- A phase that has historical certification but needs a new fingerprint validation is displayed as `CERTIFICADA, REVALIDAR`, not `POR CERTIFICAR`.
- The real certification gates remain unchanged. Fingerprint mismatch still requires the normal post-update revalidation chain.
- Phase 6 Stable also distinguishes first certification from post-update revalidation.

## Safety

- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No IndexedDB, offline, media recovery, form, Conditional Engine or submission change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No certification gate is bypassed or auto-certified.
