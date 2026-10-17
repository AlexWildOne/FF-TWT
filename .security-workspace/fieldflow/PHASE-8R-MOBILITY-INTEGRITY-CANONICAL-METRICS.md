# Phase 8R - Mobility Integrity & Canonical Metrics

Contract: `fieldflow-mobility-integrity-canonical-metrics-v1`

`SOURCE_RELEASE` is `2.2.502` and `STABLE_RELEASE` is `2.2.503`.

## Objective

Close the integrity gap found after Mobility Cost Intelligence by making route distance and toll values resolve through one compatibility boundary in `RouteMetricsService` before they reach mobility, REST, Client Portal, Analytics or exports.

## Canonical route metrics

`RouteMetricsService::canonicalFromRouteArray()` resolves persisted route metrics from the supported historical buckets and publishes one canonical distance/toll pair. `MobilityCostService::calculateForRouteArray()` consumes that resolver instead of maintaining its own fallback tree.

The resolver preserves the existing route-metric contract and does not change P1-P4 optimisation ownership. Historical stored-leg fallback is centralized in `RouteMetricsService`; the Client Portal no longer calculates its own toll estimate in JavaScript.

## Mobility persistence

Campaign defaults, per-operative profiles and route overrides still use existing `meta_json` storage. Saves now perform readback verification after `$wpdb` accepts the write. Validation failures surface the concrete missing price/consumption requirement, while readback failure is reported separately.

No new database table or migration is introduced.

## Snapshot integrity

Draft/planned routes refresh the mobility snapshot when owner, campaign or route date changes. In-progress, completed and canceled routes keep their historical snapshot.

Backfill excludes all terminal aliases by default:

- `completed`
- `done`
- `canceled`
- `cancelled`

## Route status vocabulary

`RoutesPro\Support\RouteStatus` is the canonical compatibility boundary. Historical input aliases remain accepted:

- `done` -> `completed`
- `cancelled` -> `canceled`

New REST writes use the canonical values.

## Protected contracts

- No P1-P4 routing or optimisation-rule change.
- No IndexedDB, offline, resumable-media or draft-recovery change.
- No Conditional Engine, ProductCardex or order-suggestion eligibility change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- React continues to use WordPress `wp-element`.
