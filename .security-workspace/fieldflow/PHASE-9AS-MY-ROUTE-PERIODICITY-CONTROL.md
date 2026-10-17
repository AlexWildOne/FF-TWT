# FieldFlow 2.2.563, My Route Periodicity Control 9AS

Contract: `fieldflow-my-route-periodicity-control-v1`

`SOURCE_RELEASE` is `2.2.562` and `STABLE_RELEASE` is `2.2.563`.

## Objective

Give each merchandiser a fast, operational view in `Minha Rota` of which assigned stores have met their monthly cadence, which are still uncovered, and which missing visits are already protected by published future routes.

## Operational model

- Uses the existing campaign assignment as source of truth for store scope and periodicity.
- Monthly `frequency_count` 1 to 4 is presented as P1, P2, P3 or P4, preserving the routing engine semantics already used by Campaign PDVs and route generation.
- Weekly cadence is translated into the number of business ISO weeks intersecting the selected month.
- Completed visits come from canonical operational submissions (`submitted`, `reviewed`, `approved`) and terminal route stops (`done`, `completed`), deduplicated by route stop whenever possible.
- Future coverage only counts published `planned` or `in_progress` route stops. Draft routes do not suppress alerts.
- A store still below target but fully covered by future published stops is labelled `Coberta no plano` instead of being shown as an unresolved alert.
- The panel is read-only. It does not create, move, remove or reschedule routes.

## Front UX

`Minha Rota` gains one compact collapsible block, `Cobertura do mês`:

- summary chips: `cumpridas`, `por resolver`, `sem visita`;
- filters: `Por resolver`, `Sem visita`, `Todas`;
- per-store cadence label, completed/required count, future planned visits, progress bar and concise state;
- responsive layout designed for field mobile use without adding another permanent dashboard.

The panel follows the project of the active route. When the selected day has no route, it falls back to all active campaign stores assigned to the authenticated user.

## Preserved contracts

- No database schema change. `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No change to route generation, mobility, endpoint reconciliation, forms, offline media, mail or WordPress Media Isolation 9AR.
- 2.2.562 media isolation remains mandatory and intact.

## Certification

Release Gate 2.10.0 adds My Route Periodicity Control 9AS. The package validator checks the read-only service, REST endpoint, compact front surface, source/stable continuity, immutable schema/submission baselines and standalone verification tools.
