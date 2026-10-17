# Phase 8V, Route Financial Continuity Hotfix

Contract: `fieldflow-route-financial-continuity-v1`

`SOURCE_RELEASE` is `2.2.506` and `STABLE_RELEASE` is `2.2.507`.

## Objective

Separate route endpoint trust from route financial calculability. A proximity warning between a configured endpoint and the first or last PDV remains visible and auditable, but it must not convert a fully routable route into a zero-cost or `Por calcular` route.

## Runtime contract

- `endpoints_routable` answers whether start and end exist and every route leg can be calculated.
- `endpoints_trusted` and the historical `endpoints_complete` flag preserve the data-quality/trust warning for suspicious endpoint coincidence.
- Mobility cost, tolls and operational cost may be calculated when the route is routable, `distance_status=complete`, and there are no unresolved route legs.
- Missing start/end or unresolved route legs remain a hard financial blocker.
- A 2.2.506 route that stored a full `known_distance_km` only because endpoint trust blocked the authoritative total is recovered without inventing distance. The persisted access, inter-stop and return legs remain visible.
- Historical `known_toll_cost_eur` is recovered before any new toll estimation is attempted.
- Endpoint coincidence tolerance is reduced to 30 metres. Exact or near-exact coincidence remains a warning unless explicitly confirmed.
- The trust warning remains visible in Rotas and Portal Cliente even when financial values are available.

## Compatibility

No P1-P4 routing or optimisation-rule change.

No database schema change.

No IndexedDB, offline, form, Conditional Engine or submission-pipeline change.

`SubmissionPipeline::BUILD` remains `2.2.485`.
