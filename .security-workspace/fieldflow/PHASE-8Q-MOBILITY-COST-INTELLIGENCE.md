# Phase 8Q - Mobility Cost Intelligence

Contract: `fieldflow-mobility-cost-intelligence-v1`

`SOURCE_RELEASE` is `2.2.501` and `STABLE_RELEASE` is `2.2.502`.

## Objective

Add economic mobility estimates to FieldFlow without creating a second routing engine. Fuel, electricity and operational costs are derived exclusively from the canonical distance and toll metrics already produced by `RouteMetricsService`.

## Configuration hierarchy

1. Route override, for exceptional vehicle/price cases.
2. Operator profile inside a campaign.
3. Campaign default profile.

The resolved profile is captured into the route `meta_json` as a snapshot so later changes to campaign or operator prices do not silently rewrite historical routes.

## Storage

No database schema change.

No new database table.

Existing JSON metadata is reused:

- `projects.meta_json.mobility_cost_default`
- `projects.meta_json.mobility_cost_profiles[user_id]`
- `routes.meta_json.mobility_cost_override`
- `routes.meta_json.mobility_cost_snapshot`

## Supported energy models

- Gasoline: EUR/L + L/100 km.
- Diesel: EUR/L + L/100 km.
- LPG: EUR/L + L/100 km.
- Hybrid non plug-in: base fuel + EUR/L + L/100 km.
- Hybrid plug-in: base fuel + fuel consumption + EUR/kWh + kWh/100 km + estimated electric distance share.
- 100% electric: EUR/kWh + kWh/100 km.
- Optional operational cost EUR/km remains separate from energy cost.

Enabled profiles are rejected when the required price or consumption fields for their energy model are missing.

## Existing routes

The Intelligence Center adds a Mobility tab where Client, Campaign and Operator resolve the applicable profile. A controlled backfill can capture that profile on already-created routes by date range.

By default:

- completed routes are excluded;
- existing snapshots are preserved;
- PDVs, route order, distance, tolls and route status are not modified.

Explicit options allow completed routes or existing snapshots to be included when an administrator intentionally requests it.

## Campaign PDV and planning

Campaign PDV exposes the campaign mobility default. Automatic suggestions, direct planning and manual planning show energy and total mobility cost using canonical route metrics before publication.

Newly created routes capture the resolved profile snapshot.

## Front, Analytics and exports

Front route summaries and route detail expose energy cost, litres/kWh where applicable and total mobility cost.

Analytics exposes period totals without changing execution, risk or opportunity scores.

Route exports and Analytics CSV/XLSX/PDF include mobility profile, energy type, estimated litres/kWh, energy cost, optional operational cost, tolls and total mobility cost.

## Certified boundaries

No P1-P4 routing or optimisation-rule change.

No new database table.

No IndexedDB schema change.

No service worker ownership change.

No Draft/Media recovery change.

No SubmissionApplicationService or SubmissionSideEffectDispatcher change.

No Order Suggestion eligibility or mail change.

`SubmissionPipeline::BUILD` remains `2.2.485`.
