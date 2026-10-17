# Phase 9BC, Planner Canonical Assignment Catalog

Contract: `fieldflow-planner-canonical-assignment-catalog-v1`

## Objective

`Planear` is a planning catalogue, not an execution filter. Every store assigned to the logged-in merchandiser through `campaign_locations.assigned_to` must remain available for planning regardless of previous visits, existing routes, periodicity fulfilment, or the lifecycle flags of the campaign-location row.

## Rules

1. Assignment is canonical catalogue authority. If `assigned_to` is the current merchandiser, the store is available in `Planear` for that campaign.
2. A store may be planned on more than one date. This is required for P2, P3 and P4 coverage and for operational replanning.
3. The same store remains unique inside a single day.
4. Started and completed routes are immutable. They remain visible as history but are not resubmitted when another day is planned.
5. The planner submits only dirty dates, protecting historical routes from accidental rewrites.
6. Route creation still validates ownership of every requested store against `assigned_to`.
7. No database migration, routing algorithm, cost engine, form, media, mail or SubmissionPipeline change is introduced by this phase.

## Release continuity

`SOURCE_RELEASE` is `2.2.572` and `STABLE_RELEASE` is `2.2.573`.

`LATEST_SCHEMA_VERSION` remains `2.2.529` and `SubmissionPipeline::BUILD` remains `2.2.485`.
