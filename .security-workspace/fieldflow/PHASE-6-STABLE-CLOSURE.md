## 2.2.508 Operational Endpoint Source of Truth continuity

SOURCE_RELEASE 2.2.507. STABLE_RELEASE 2.2.508. Phase 6 Stable authority advances to 1.0.65 and Release Gate to 1.57.0. The release adds `fieldflow-operational-endpoint-source-of-truth-v1`, makes campaign/operative route bases canonical master data in `projects.meta_json`, removes implicit first/last-PDV endpoint fallbacks from current route producers, and adds explicit migration/recovery for editable legacy routes. No P1-P4 routing or optimisation-rule change. No database schema change. `SubmissionPipeline::BUILD` remains 2.2.485.

## 2.2.507 Route Financial Continuity Hotfix continuity

SOURCE_RELEASE 2.2.506. STABLE_RELEASE 2.2.507. Phase 6 Stable authority advances to 1.0.64 and Release Gate to 1.56.0. The release adds `fieldflow-route-financial-continuity-v1`, keeps endpoint-proximity warnings auditable while allowing fully routable routes to calculate km, tolls and mobility costs, and recovers 2.2.506 known-distance snapshots blocked only by trust state. Missing endpoints and unresolved route legs remain hard blockers. No P1-P4 routing or optimisation-rule change. No database schema change. `SubmissionPipeline::BUILD` remains 2.2.485.

## 2.2.506 Route Endpoint Provenance & Legacy Reconciliation continuity

SOURCE_RELEASE 2.2.505. STABLE_RELEASE 2.2.506. Phase 6 Stable authority advances to 1.0.63 and Release Gate to 1.55.0. The release adds `fieldflow-route-endpoint-provenance-reconciliation-v1`, centralizes endpoint resolution, blocks unconfirmed legacy endpoints that collapse to first/last PDV, and safely reconciles draft/planned routes from stored defaults. No P1-P4 routing or optimisation-rule change. No database schema change. `SubmissionPipeline::BUILD` remains 2.2.485.

## 2.2.505 Route Endpoint Coordinate Trust continuity

SOURCE_RELEASE 2.2.504. STABLE_RELEASE 2.2.505. Phase 6 Stable authority advances to 1.0.62 and Release Gate to 1.54.0. The release adds `fieldflow-route-endpoint-coordinate-trust-v1`: visible start/end addresses invalidate stale hidden coordinates, endpoints are re-geocoded through the configured cached Maps provider before route metrics are calculated, corrected coordinates are persisted, and route summaries expose the endpoints used. It also aligns Mobility Cost Intelligence certification markers without changing P1-P4, database schema, IndexedDB, offline/media recovery, durable submission or order-suggestion eligibility. `SubmissionPipeline::BUILD` remains 2.2.485.

## 2.2.503 Mobility Integrity & Canonical Metrics continuity

SOURCE_RELEASE 2.2.502. STABLE_RELEASE 2.2.503. Phase 6 Stable authority advances to 1.0.60 and Release Gate to 1.52.0. The release adds `fieldflow-mobility-integrity-canonical-metrics-v1`: a single compatibility resolver in `RouteMetricsService` for stored km/portagens, readback-verified mobility persistence, canonical route-status aliases and protected historical mobility snapshots. Draft/planned routes refresh mobility snapshots after legitimate owner/project/date changes; in-progress/completed/canceled routes remain immutable. No P1-P4 routing or optimisation rule changes, no new database table, and no changes to IndexedDB, service worker, draft/media recovery, ProductCardex, durable submission or order-suggestion eligibility. `SubmissionPipeline::BUILD` remains 2.2.485.
