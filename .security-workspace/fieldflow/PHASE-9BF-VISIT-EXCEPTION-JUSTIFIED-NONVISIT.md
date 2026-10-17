# Phase 9BF, Visit Exception & Justified Non Visit Continuity

Contract: `fieldflow-visit-exception-justified-nonvisit-v1`

## Objective

Give field teams an operational way to explain a missing visit without falsifying execution. A justified non-visit must stop feeding the critical/red queue while its exception is valid, but it must never become a completed visit and must never rewrite the store cadence.

## Canonical rules

- `SOURCE_RELEASE` is `2.2.575` and `STABLE_RELEASE` is `2.2.576`.
- `campaign_locations.visit_frequency` and `frequency_count` remain the cadence authority. P1/P2/P3/P4 are unchanged.
- A store exception records reason, note, validity and actor in `fieldflow_visit_exceptions_v1`, a non-autoloaded operational ledger. There is no SQL schema migration.
- Temporary reasons default to the selected month end when no explicit end date is supplied. Permanent closure may remain open until manually cleared.
- Active exceptions can be edited or cleared. Previous records are kept as superseded/cleared history and are mirrored to the canonical AuditLog when available.
- A `route_stops.status=failed` stop with a non-empty `fail_reason` resolves only that cadence slot as justified. It does not increment execution.
- A valid store-level exception resolves the remaining cadence operationally for its validity window, preventing repeated critical alerts while a known closure/works condition is active.
- `visits_completed` and `stores_covered` remain execution/plan metrics. Justification is exposed separately through `visits_justified`, `justified_stores` and `stores_operationally_resolved`.
- `without_visit` contains only non-justified missing stores. Justified rows use status `justified`, neutral visual treatment and risk score zero.
- Once a temporary exception expires, the store automatically returns to normal periodicity evaluation and can become attention/critical again.

## UX

`Minha Rota > Cobertura do mês` gains a `Justificar` action for unresolved non-future stores. The operator selects a reason, optional note and optional validity date. Existing exceptions can be edited or reopened. The UI explicitly states that the justification does not count as execution and does not alter periodicity.

## Certification continuity

This release preserves Planner 9BE, Form Visit Memory & Fronts 9BD, `LATEST_SCHEMA_VERSION = 2.2.529` and `SubmissionPipeline::BUILD = 2.2.485`.
