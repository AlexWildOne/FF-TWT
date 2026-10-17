# Phase 9AY — Client Portal Filter Authority Rebase

Contract: `fieldflow-client-portal-filter-authority-rebase-v1`

`SOURCE_RELEASE` is `2.2.568` and `STABLE_RELEASE` is `2.2.569`.

## Objective

Rebase the Client Dashboard shell on the stable 2.2.565 interaction model while preserving the certified post-565 improvements for date-scoped costs, route financial totals, consolidated reporting and audience-aware analytics.

## Rules

- `De` / `Até` is the global source of truth for Summary, costs, reports and Analytics.
- Opening a route never disables or rewrites the global date controls.
- Route calendar navigation may remain annual when an operator is selected, but totals use the selected global period.
- Summary keeps the stable KPI composition and is not coupled to audience-performance rendering.
- Team performance remains isolated inside Analytics.
- Route detail keeps tolls, energy, operational cost and total mobility cost composition.
- No schema change. `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- No submission build change. `SubmissionPipeline::BUILD` remains `2.2.485`.
- No bypass of the Release Manager publication receipt or Phase 6 Stable gate.
