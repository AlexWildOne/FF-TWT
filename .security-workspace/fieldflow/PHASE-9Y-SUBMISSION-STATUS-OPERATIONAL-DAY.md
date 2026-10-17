# Phase 9Y — Submission Status & Operational Day

Contract: `fieldflow-submission-status-operational-day-v1`

`SOURCE_RELEASE` is `2.2.540` and `STABLE_RELEASE` is `2.2.541`.

## Canonical submission status

Operational reporting, route reconciliation, last-visit state and execution snapshots accept only:

- `submitted`
- `reviewed`
- `approved`

Legacy empty status remains equivalent to `submitted`.

`draft` and `rejected` remain recoverable/editable but are not operational facts. `void`, `cancelled`, `deleted` and `trash` remain inactive.

Authority: `RoutesPro\Services\SubmissionStatusPolicy`.

## Canonical operational day

The business/reporting date resolves in this order:

1. `report_day`
2. route `date`
3. `DATE(submitted_at)` as legacy/audit fallback

`submitted_at` remains the real audit timestamp and is never rewritten merely to align reporting dates.

Authority: `RoutesPro\Services\OperationalReportDayPolicy`.

## Required consumers

The policies are wired into route reconciliation, manual completion safeguards, contextual execution snapshots, canonical visit state, BO submission date filters, Analytics, smart briefing analytics, public API date filters and Store Profile last-visit resolution.

No schema migration is introduced. `LATEST_SCHEMA_VERSION` remains `2.2.529` and `SubmissionPipeline::BUILD` remains `2.2.485`.
