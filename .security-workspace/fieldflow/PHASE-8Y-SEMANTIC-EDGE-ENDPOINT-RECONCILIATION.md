# Phase 8Y, Semantic Edge Endpoint Reconciliation

Contract: `fieldflow-semantic-edge-endpoint-reconciliation-v1`

`SOURCE_RELEASE` is `2.2.509` and `STABLE_RELEASE` is `2.2.510`.

## Goal

Fix the remaining route-base contamination case where a legacy route start/end is the same first/last PDV address but its coordinates differ from the stored PDV coordinates by more than the 30 metre geometric coincidence threshold. Those routes were incorrectly trusted, so reconciliation never reached the canonical operator/campaign endpoint profile and access/return legs remained around 0.1 to 0.2 km.

## Runtime contract

- Exact normalized address identity is evaluated before coordinate distance in `RouteEndpointResolverService::pointsCoincide()`.
- Coordinate proximity remains limited to 30 metres when addresses are different or unavailable. The radius is not widened.
- A legacy route endpoint with the same normalized address as the edge PDV is untrusted unless `allow_matches_stop` is explicitly confirmed.
- `RouteEndpointProfileService::resolveForRoute()` therefore falls through to the enabled operator profile or campaign default when a stored route endpoint is identified as legacy edge contamination.
- `RouteMetricsService::repairPlannedRouteIntegrity()` then recalculates the access and return legs using the reconciled endpoints without changing PDV order.

## Safety

- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change.
- No widening of the 30 metre coordinate coincidence threshold.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
