# Phase 9BK — Static Routing & Cost Authority

Contract: `fieldflow-static-routing-cost-authority-v1`

Release continuity: `SOURCE_RELEASE` is `2.2.581` and `STABLE_RELEASE` is `2.2.582`.

## Objective

Reduce Google Maps Platform routing spend without reducing the operational quality of FieldFlow routes. The route planner remains deterministic and territorially aware, while Google is reserved for the final road distance/time snapshot when the route materially changes.

## Certified behavior

- Default Google routing preference is `TRAFFIC_UNAWARE`.
- Google Route Matrix is disabled by default and only runs when explicitly enabled in Settings.
- The legacy manager REST `/matrix` endpoint also fails closed for Google while that premium matrix switch is off, preventing accidental legacy Distance Matrix spend.
- Google TOLLS and Google waypoint optimization are disabled by default and are explicit premium opt-ins.
- Planning uses `RoutingMatrixService` internal calibrated road edges, including fallbacks that previously called a map-provider distance matrix.
- Route metric previews and campaign CSV exports are non-billable planning operations and therefore use the internal estimator. Google finalization is reserved for save/publish/create or a materially stale persisted route.
- `GoogleRoutes::routeSignature()` fingerprints ordered routing-effective points and routing options. With trusted coordinates, store renames or address formatting do not trigger a paid refresh.
- `RouteMetricsService::maybeRefreshGoogleRoute()` no longer refreshes because a snapshot is old. It reuses an unchanged persisted signature.
- Existing exact Google snapshots without a signature are adopted once, without a paid API call, when geography and route state are current.
- Material route edits continue to mark `route_metrics_stale` / `routing_refresh_required`, so one final Google Compute Routes request may occur after a real change.
- Internal toll estimation remains available when premium Google toll computation is disabled.
- No database schema change. SubmissionPipeline remains 2.2.485.
