# Phase 9BG - Open Day Planning & Visit Completion Sync

Contract: `fieldflow-open-day-planning-visit-completion-sync-v1`

## Goal

Keep the operational day flexible in Planear without corrupting visits that already have real execution state. Starting or completing the currently planned route must not prevent the merchandiser from adding or adjusting remaining visits while the operational day is still open.

## Behaviour

- The current and future operational day remain plannable even when the existing route is `in_progress` or `completed`.
- Historical started/completed days and canceled routes remain protected from normal self-planning edits.
- A route stop with canonical status `completed`, `in_progress` or `failed`, or with arrival/departure evidence, remains protected by `RoutePlanSyncService` and cannot be silently removed.
- Pending stores can still be removed or supplemented with newly assigned stores.
- Existing stop state is loaded into Planear from the canonical route detail response instead of being discarded when it is merged with the assigned-store catalogue.
- `fieldflow:visit-completed` immediately marks the matching store as `Concluída` in Planear, while leaving the day open for additional planning.
- Adding a new pending stop to a route whose previous stops are already complete preserves those completed stops; `RouteProgressService` reconciles the route state after the plan sync.
- No change to Daily Close, mail idempotency, route-stop execution semantics, schema or submission pipeline.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Release continuity

`SOURCE_RELEASE` is `2.2.577` and `STABLE_RELEASE` is `2.2.578`.
