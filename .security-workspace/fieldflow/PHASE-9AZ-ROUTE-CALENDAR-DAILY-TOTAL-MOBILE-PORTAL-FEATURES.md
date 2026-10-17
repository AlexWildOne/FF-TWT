# Phase 9AZ — Route Calendar Daily Total & Mobile Portal Features

Contract: `fieldflow-route-calendar-daily-total-mobile-portal-features-v1`

`SOURCE_RELEASE` is `2.2.569` and `STABLE_RELEASE` is `2.2.570`.

## Objective

Close two presentation/control gaps on top of the certified 2.2.569 baseline without changing route-cost calculation, database schema or submission semantics.

## Route calendar rules

- Each calendar day card shows the sum of canonical `total_mobility_cost_eur` for every route on that day.
- The displayed daily total therefore represents tolls + energy/fuel + operational cost, not tolls alone.
- When one or more routes still have no canonical/reporting total, the card keeps the known sum and appends the number still pending calculation.
- Km and PDV counts continue to be aggregated across all routes of the day.
- Route detail and route list remain the canonical drill-down surfaces.

## Mobile portal feature rules

- Mobile feature visibility is configured independently for `operational` and `client` portals.
- Existing installations migrate the legacy global feature state identically into both matrices. No previously hidden feature becomes visible merely because of the upgrade.
- `Settings::feature_for_target()` and `Settings::features_for_target()` are the source of truth for portal navigation and WebView panel visibility.
- The legacy `features` map remains the union of both matrices for API guards and older app builds.
- Entitlements and WordPress permissions still apply after the visibility matrix, so a checked feature never grants permission by itself.

## Frozen contracts

- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- Release Manager publication receipt 9AU and Client Portal filter authority 9AY remain mandatory.
