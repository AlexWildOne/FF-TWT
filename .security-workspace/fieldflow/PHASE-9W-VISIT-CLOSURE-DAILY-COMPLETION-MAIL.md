# Phase 9W, Visit Closure & Daily Completion Mail

Contract: `fieldflow-visit-closure-daily-mail-v1`

`SOURCE_RELEASE` is `2.2.538` and `STABLE_RELEASE` is `2.2.539`.

## Operational contract

1. A visit can only be closed by a final operational submission. `submitted`, `reviewed` and `approved` are accepted. Historical `NULL` status is interpreted as `submitted`. `draft`, `rejected`, `void`, `cancelled`, `deleted` and `trash` cannot close a visit.
2. A route stop in `done` or `completed` is an executed visit.
3. A route stop in `failed` is terminal only when `fail_reason` contains a non-empty justification. A justified non-visit closes the operational day without counting as an executed visit.
4. A route is operationally complete only when every stop is either executed or a justified non-visit.
5. Form completion, route-event completion, simple stop updates, route stop updates and backoffice manual completion all queue the same idempotent Daily Close after the persisted route state is terminal.
6. Daily Close uses a durable single-event retry hook in addition to the request shutdown fallback. Failed report generation can therefore retry without asking the merchandiser to touch the route again.
7. Extra Mail Center emails are first-class recipients. Immediate Daily Close sends them in immediate mode. Consolidated mode includes them in the scheduled client view.
8. When no active Mail Center Daily Close owns the route, the stable route-completed notification remains the fallback and is idempotent through Mail Outbox.
9. Mail Outbox remains the canonical transport seam. Route-completion notifications carry a deterministic idempotency key.
10. No database migration is introduced by this phase.

## Protected identities

- `LATEST_SCHEMA_VERSION = 2.2.529`
- `SubmissionPipeline::BUILD = 2.2.485`

## Certification

Release Manager must validate the package marker `visit_closure_daily_mail_9w_ok` and the installed Release Gate must pass `Visit Closure & Daily Completion Mail 9W` before Stable certification.
