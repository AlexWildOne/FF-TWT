# Phase 9AK - Daily Mobility Cost Reporting Continuity

Contract: `fieldflow-daily-mobility-cost-reporting-v1`

`SOURCE_RELEASE` is `2.2.554` and `STABLE_RELEASE` is `2.2.555`.

This release extends the certified Daily Commercial Report without changing route calculation semantics, schema or submission persistence.

## Contract

- Mobility facts come only from the canonical route metrics and `MobilityCostService` snapshots already present in Analytics.
- The reporting layer does not recalculate distance, fuel/energy or toll values.
- Only operationally closed routes contribute to the metric labelled `Km executados`.
- Daily rollups expose distance, estimated fuel/energy cost, estimated toll cost and total mobility cost.
- Multi-day scopes expose one row per day plus `TOTAL GERAL` for the selected period.
- A route with incomplete canonical distance or unavailable mobility cost marks its day and the period as `partial` instead of presenting a false complete total.
- Client and merchandiser email bodies consume the same canonical mobility rollup.
- Excel exports contain both route-level mobility columns and a `Mobilidade Diaria` sheet with daily and accumulated totals.
- PDF reporting follows the same canonical daily/total rollup.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
