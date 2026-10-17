# Phase 7ZY — Submission Application Service

Release: **FieldFlow 2.2.484**  
Contract: `fieldflow-submission-application-service-v1`

## Objective

Centralize the durable application path used by form render, web submission and mobile submission without changing public endpoints, database schema, FlutterFlow contracts or the certified side-effect pipeline.

The new `RoutesPro\Forms\SubmissionApplicationService` is the shared application boundary for:

- canonical schema assembly;
- transaction start and durable submission persistence;
- insert/update locking semantics;
- canonical answer persistence;
- update audit snapshot capture;
- canonical submission payload construction;
- canonical `SubmissionPipeline` job construction.

## Web integration

`src/Forms/Forms.php` delegates the render schema, submit schema, durable submission write and post-commit envelope construction to `SubmissionApplicationService`.

The web path still owns request/nonce handling, upload extraction, UI error presentation, draft cleanup and post-commit orchestration.

## Mobile integration

`src/Mobile/SubmissionService.php` delegates schema assembly, durable persistence and pipeline envelope construction to the same service.

The certified mobile legacy transaction boundary is preserved. When the current `SubmissionPipeline` owns a request, the durable write is committed by the application service before post-commit processing. When legacy owns the request, `commit_after_persist=false` keeps the transaction open until the existing Cardex/history/visit-completion path finishes.

## Side-effect boundary

The service deliberately does **not** own:

- mail;
- Analytics;
- Azure delivery;
- exports;
- route notifications.

Those remain in the existing post-commit/legacy layers. This release therefore centralizes durable state without coupling report persistence to secondary systems.

## Compatibility

- No database migration.
- No REST endpoint change.
- No FlutterFlow change.
- No form UX change.
- No IndexedDB schema change.
- Existing web/mobile answer storage representation is preserved during this migration.

## Certification

`tools/verify-submission-application-service.php` verifies:

- canonical schema stage order;
- durable web commit;
- mobile legacy open-transaction semantics;
- web/mobile storage compatibility;
- update identity and audit preservation;
- canonical pipeline envelope;
- removal of duplicated submission-table insert logic from channel handlers;
- loader, Release Gate and Release Manager integration.

The Release Manager exposes **Submission Application Service — UNIFICADO** and rejects packages where the service is present but not connected to both channels.
