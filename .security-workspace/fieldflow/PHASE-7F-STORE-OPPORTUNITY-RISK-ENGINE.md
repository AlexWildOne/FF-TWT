# Phase 7F - Store Opportunity & Risk Engine

Release: 2.2.437

## Objective
Create one explainable intelligence profile per FieldFlow location and use the same profile in BO analytics, client and merchandiser dashboards, store alerts, email and CSV/Excel/PDF reporting.

## Inputs
- Current route-stop execution by location.
- Equal-window previous execution when a comparable route scope exists.
- Share Space and fronts metrics with exact location IDs.
- Exact mapped stock rows from the latest published snapshot.
- Recommendation Learning history for confidence evidence only.

## Output
Each store exposes Operational Health, Execution Score, Risk Score, Opportunity Score, Stock Risk, Share Gap, Trend, Priority Score and Confidence Score, plus source signals and a suggested human-reviewed action.

## Safety and learning policy
- No causal claims are inferred from correlation.
- No route, stock, form or operational record is changed automatically.
- Learning evidence cannot alter Risk, Opportunity or Health in 2.2.437. It may adjust confidence only.
- Narrow stock scopes require exact mapped FieldFlow location IDs.
- Human review remains mandatory.

## Release gate
The build is not certified by packaging alone. Publish through the WordPress Release Manager, install through the private updater, revalidate chain 6B, test Phase 6 closure and certify Stable in the Azure-backed gate.
