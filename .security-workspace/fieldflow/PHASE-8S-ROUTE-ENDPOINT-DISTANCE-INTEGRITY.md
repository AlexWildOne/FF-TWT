# Phase 8S - Route Endpoint & Distance Integrity

Contract: `fieldflow-route-endpoint-distance-integrity-v1`

`SOURCE_RELEASE` is `2.2.503` and `STABLE_RELEASE` is `2.2.504`.

## Objective

Close route and mobility integrity as one coherent release. A distance is only authoritative when the complete itinerary is known:

`start -> first PDV -> intermediate PDVs -> last PDV -> end`

The release prevents partial inter-store kilometres from being presented or charged as the total route distance.

## Authoritative route distance

`RouteMetricsService` owns endpoint integrity and canonical route distance. Canonical complete distance requires both route endpoints and all route legs to be resolved.

The canonical identity is:

`distance_km = access_distance_km + inter_stop_distance_km + return_distance_km`

subject only to normal route-provider rounding.

When an endpoint or leg is missing:

- `distance_km` is not authoritative and remains `null`;
- resolved segments remain visible through `known_distance_km`;
- `distance_status` is `incomplete`;
- `endpoint_status` identifies `missing_start`, `missing_end` or `missing_both`;
- toll, fuel/energy and operational route costs are not presented as calculated totals.

## Publishing and planning rules

Draft routes may remain incomplete so work is never lost. A route cannot be published as `planned` without real start and end points.

The system does not infer missing endpoints from the first or last PDV. `return_to_start` is valid only when it explicitly copies the configured start point to the end point.

Campaign route generation skips days whose endpoints cannot be resolved from the plan or saved client/campaign/operative route defaults.

## Existing route repair

The Mobility area in Assignment Centre includes an explicit repair action for existing `draft` and `planned` routes. It:

- fills only missing endpoints;
- uses already saved route defaults for the same client, campaign and operative, with campaign fallback;
- never invents endpoints from PDVs;
- preserves the existing PDV order;
- recalculates canonical metrics after repair;
- never edits started, completed or canceled routes.

Historical routes remain immutable by default.

## Mobility and analytics

`MobilityCostService::estimateForMetrics()` exposes `costs_available`. When route distance is incomplete, estimated fuel, electricity, operational cost and total mobility cost are not treated as calculated values.

Analytics counts configured routes separately from routes with cost-ready distance and reports incomplete-distance routes explicitly. Client Portal, operational UI, BO and exports distinguish `Km conhecidos` from complete total kilometres.

## Export integrity

Exports use the canonical route resolver before emitting route totals. For incomplete routes:

- total distance is blank/unavailable;
- status remains `incomplete`;
- calculated mobility cells are blank instead of false `0.00` values;
- known partial route metrics remain available for diagnostics where appropriate.

## Protected contracts

- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No IndexedDB, offline, resumable-media or draft-recovery change.
- No Conditional Engine, ProductCardex or order-suggestion eligibility change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- React continues to use WordPress `wp-element`.
