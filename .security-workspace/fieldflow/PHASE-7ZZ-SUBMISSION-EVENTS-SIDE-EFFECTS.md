# Phase 7ZZ, Submission Events & Side Effects

Release: 2.2.485  
Contract: `fieldflow-submission-side-effects-v1`

## Objective

Separate durable field submission persistence from secondary effects. A visit that has already committed its submission and answers must remain successful even when Mail, EventBus/Azure, Performance automation, route notification or an extension callback fails afterwards.

## Architecture

`RoutesPro\Forms\SubmissionSideEffectDispatcher` owns the post-commit fan-out. It runs only after durable persistence and gives every internal effect its own idempotent lane:

- `route_notification`
- `visit_core_event`
- `visit_report_automation`
- `visit_public_hook`
- `submission_core_event`
- `submission_performance`
- `order_suggestion_mail`
- `submission_public_hook`

Each lane has an independent lock, a per-revision done marker and a dedicated retry through `fieldflow_submission_side_effect_retry`. A failed lane does not replay lanes that have already completed.

## Durable boundary

`SubmissionApplicationService` continues to own the durable submission and answers. `SubmissionPipeline` and the legacy web/mobile post-commit paths hand secondary work to `SubmissionSideEffectDispatcher` only after COMMIT.

The mobile legacy transaction boundary remains unchanged for Cardex, analytical history and visit completion. Once that legacy COMMIT is confirmed, MediaRegistry, AuditLog and the remaining submission side effects are non-blocking.

## Internal listeners

The internal listeners previously attached directly to `fieldflow_form_submitted`, `fieldflow_form_updated` and `fieldflow_form_visit_completed` are now invoked in isolated lanes:

- `RoutesPro\Core\EventBus`
- `RoutesPro\Admin\ReportAutomations`
- `RoutesPro\Performance\Module`

The public WordPress hooks remain available as compatibility lanes for third-party extensions. Failure in a public hook can no longer block FieldFlow internal lanes.

## Retry and observability

`fieldflow_submission_side_effect_retry` is registered in QueueSettings and ProcessMonitor. Retries are scoped to a single event revision and a single failed lane, with a maximum of four attempts.

The original `fieldflow_submission_post_commit` hook remains the canonical entry point for scheduled post-commit processing and rehydrates the submission before handing it to the side-effect dispatcher.

## Safety

- No database migration.
- No IndexedDB migration.
- No REST endpoint change.
- No FlutterFlow change.
- No React introduction.
- No change to form validation semantics.
- Order Suggestion Mail Eligibility v2 remains authoritative inside its own mail lane.
- Existing public `fieldflow_form_*` hooks are preserved.

## Certification

`tools/verify-submission-side-effects.php` proves:

- a failing mail lane does not block EventBus, Performance or public hooks;
- visit side effects are isolated;
- retries are lane-specific;
- a retry does not replay successful lanes;
- the same submission revision is idempotent;
- update submissions do not run the create-only Performance lane;
- a failing public extension hook does not block internal effects;
- web/mobile hand off to the dispatcher after durable persistence;
- internal legacy hook ownership was removed;
- queue and Process Monitor know the dedicated retry hook.

Phase 6 Stable advances from 2.2.484 to 2.2.485.
