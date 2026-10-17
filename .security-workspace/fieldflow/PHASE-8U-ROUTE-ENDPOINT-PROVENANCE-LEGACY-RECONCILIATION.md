# Phase 8U - Route Endpoint Provenance & Legacy Reconciliation

Contract: `fieldflow-route-endpoint-provenance-reconciliation-v1`

`SOURCE_RELEASE` is `2.2.505` and `STABLE_RELEASE` is `2.2.506`.

## Scope

FieldFlow now resolves route departure and arrival through one canonical endpoint service used by route editing, campaign route generation, route optimization and planned-route repair.

An endpoint stores its provenance and the visible address remains authoritative. Stale coordinates are discarded when the current address cannot be verified instead of being silently reused.

Route integrity is semantic rather than presence-only. A start point that collapses onto the first PDV, or an end point that collapses onto the last PDV, is treated as suspicious unless the operator explicitly confirms that the route genuinely begins or ends at that PDV.

Planned-route reconciliation can repair missing and suspicious legacy endpoints from the stored client/campaign/operative route defaults. It never invents the first or last PDV as an endpoint, preserves stop order, and never modifies routes that have already started or reached a terminal state.

Financial distance remains canonical:

`access_distance_km + inter_stop_distance_km + return_distance_km = distance_km`

When endpoint trust is not complete, `known_distance_km` remains diagnostic while fuel/energy, operating cost and toll totals are not treated as authoritative.

## Stability boundaries

No P1-P4 routing or optimisation-rule change.

No database schema change.

No offline, IndexedDB, draft, media, conditional engine or submission-flow change.

`SubmissionPipeline::BUILD` remains `2.2.485`.
