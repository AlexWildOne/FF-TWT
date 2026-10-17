# FieldFlow 2.2.564, My Route Periodicity Action UX 9AT

Contract: `fieldflow-my-route-periodicity-action-ux-v1`

`SOURCE_RELEASE` is `2.2.563` and `STABLE_RELEASE` is `2.2.564`.

## Objective

Turn the monthly periodicity view introduced in 9AS into a fast field action surface without changing routing, submission state, cadence ownership or database schema.

## Runtime behaviour

- `Minha Rota` keeps the monthly coverage block compact and collapsed by default.
- Stores are ordered by operational risk before status, missing visits and campaign priority.
- Risk is exposed as `normal`, `attention` or `critical` from remaining visits, overdue weekly slots and remaining business days.
- The current month summary exposes business days remaining and a compact `covered / total` monthly close.
- The `Só falta cumprir` filter persists in `sessionStorage` for the current browser session.
- Stores that still require planning expose a `Planear` action.
- `Planear` opens the operational planner, applies the correct client/project/month and focuses or preselects the exact store.
- Saving a personal route plan refreshes the coverage block so a newly published future stop immediately counts toward projected coverage.

## Guardrails

- `PeriodicityProgressService` remains read-only.
- No route is created by the coverage widget itself.
- No store is silently added to a day. The planner only focuses/preselects it and the merchandiser chooses the day.
- Completed visit truth still uses canonical operational submission statuses and completed route stops.
- Future coverage still counts only published `planned` / `in_progress` route stops.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- WordPress Media Isolation 9AR remains mandatory and unchanged.

## Certification

Release Gate 2.11.0 adds My Route Periodicity Action UX 9AT. The package validator checks the risk model, business-day metadata, persistent filter, planner handoff, exact-store focus, source/stable continuity, unchanged schema/submission baselines and standalone verification tools.
