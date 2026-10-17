# Phase 9AW - Route Financial Breakdown & Audience Analytics

Contract: `fieldflow-route-financial-audience-analytics-v1`

`SOURCE_RELEASE` is `2.2.566` and `STABLE_RELEASE` is `2.2.567`.

## Objective

Close the remaining route-cost visibility gap and make Analytics audience-aware without creating a parallel cost, routing or scoring engine.

## Contract

- Route consultation shows tolls, fuel/energy cost, operational vehicle cost and total mobility cost as an explicit composition.
- Canonical mobility remains authoritative; `MobilityCostService::reportingProjection()` is only used to surface already-known/reconciled values while a canonical route distance is incomplete.
- The Consolidated Report is full-width, visually aligned with the Client Portal and includes executive totals plus per-route execution progress.
- Client Analytics defaults to team scope. Selecting an operative changes performance and question metrics to that individual scope.
- Client team scope can identify the named best performer using current filtered evidence.
- Merchandiser Analytics remains self-only and never exposes peer names, rankings or metrics.
- Question response volume is contextual/descriptive, not a component of the performance score.
- Performance index is transparent: 80% execution + 20% reporting coverage; completed visits resolve ties.
- Ask FieldFlow performance answers use only the audience-scoped `team_performance` evidence contract.
- No schema change. `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- No submission pipeline change. `SubmissionPipeline::BUILD` remains `2.2.485`.

## Certification markers

- `routeOperationalCost`
- `routeMobilityReportingAvailable`
- `Custo operacional`
- `Custo deslocação total`
- `rp-consolidated-summary`
- `fieldflow-audience-performance-v1`
- `renderTeamPerformance`
- `renderAudiencePerformance`
- `team_leader_name`
- `audience === 'merchandiser' ? 'self' : 'scope_member'`
- `ROUTE FINANCIAL AUDIENCE ANALYTICS 9AW CONTRACT OK`
- `ROUTE FINANCIAL AUDIENCE ANALYTICS 9AW RUNTIME OK`
