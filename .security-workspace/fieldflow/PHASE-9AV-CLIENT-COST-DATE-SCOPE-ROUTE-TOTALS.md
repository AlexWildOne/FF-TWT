# Phase 9AV - Client Cost Date Scope & Route Totals

Contract: `fieldflow-client-cost-date-scope-route-totals-v1`

`SOURCE_RELEASE` is `2.2.565` and `STABLE_RELEASE` is `2.2.566`.

## Objective

Keep the Client Dashboard route and mobility costs aligned with the visible `De -> Ate` period while preserving the annual route agenda as a navigation surface when an operative is selected.

## Contract

- Cost, km, toll and energy aggregates shown in the operative route summary are calculated only from routes whose `route.date` is inside the selected dashboard interval.
- Selecting an operative may still load the annual agenda for navigation, but annual rows must never leak into the current-period financial totals.
- The route list shows totals for the visible route set: routes, PDVs, km, tolls, energy and mobility cost.
- Incomplete route costs remain explicit as `por calcular`; known amounts are not hidden merely because another route is incomplete.
- Route exports inherit `date_from` and `date_to` from the Client Dashboard filters.
- `/stats` accepts both canonical `from`/`to` and compatibility aliases `date_from`/`date_to`.
- No schema change. `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- No submission pipeline change. `SubmissionPipeline::BUILD` remains `2.2.485`.

## Certification markers

- `selectedFilterRange`
- `routeInRange`
- `routeAggregate`
- `aggregateMoney`
- `rp-route-list-totals`
- `date_from`
- `date_to`
- `CLIENT COST DATE SCOPE ROUTE TOTALS 9AV CONTRACT OK`
- `CLIENT COST DATE SCOPE ROUTE TOTALS 9AV RUNTIME OK`
