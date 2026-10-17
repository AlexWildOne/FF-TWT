# Phase 9H, Mail Dispatch Foundation & Certification Continuity

Release: **FieldFlow 2.2.524**

Contract: `fieldflow-mail-dispatch-foundation-v1`

`SOURCE_RELEASE` is `2.2.523` and `STABLE_RELEASE` is `2.2.524`.

## Goal

This release moves FieldFlow email transport behind one canonical service without changing the actual mail provider, templates, attachments, recipients, route/form behaviour or data model. It also removes a brittle Phase 9A certification dependency on the literal runtime build number of `fieldflow-operational-experience.js`.

## Mail dispatch foundation

`RoutesPro\\Services\\MailDispatchService` becomes the single application seam for FieldFlow email sending.

The service:

- continues to use WordPress `wp_mail()` as the transport;
- captures `wp_mail_failed` consistently;
- catches transport exceptions;
- returns a normalized result containing `sent`, `error`, `dispatch_id`, `duration_ms` and recipient count;
- exposes `fieldflow_mail_dispatch_context` and `fieldflow_mail_dispatch_after` extension points;
- does not create a queue or retry policy in this phase;
- does not change SMTP/provider configuration;
- does not create or migrate any database table.

The previously distributed direct `wp_mail()` callers are routed through the canonical dispatcher, including operational notifications, performance automations, billing, Shopping, report automations, Mail Center tests and portal messaging.

Existing business-specific logging remains in place. The dispatcher provides the common transport ownership needed for a future outbox/retry layer without forcing that migration into this release.

## Certification continuity

Phase 9A previously allowed runtime identity to leak into a historical shell contract. This caused certification to fail when the runtime build changed while the actual Client Portal Shell Stability behaviour remained valid.

From 2.2.524, `assets/fieldflow-operational-experience.js` publishes the durable marker:

`fieldflow-client-portal-shell-stability-v1`

`ReleaseGate::checkClientPortalShellStabilityContract()` validates that contract marker instead of requiring a specific `BUILD` number.

Historical build strings remain as non-runtime compatibility markers so the installed 2.2.523 package validator can still validate the successor package before update.

Client Portal Shell Stability now validates a durable contract marker instead of a release number.

## Explicit non-goals

- No database schema change.
- No P1-P4 routing or optimisation-rule change.
- No RouteOptimizationService, RouteMetricsService, RouteEndpointResolverService or RoutingMatrixService change.
- No FormRenderer change.
- No SubmissionPipeline change. `SubmissionPipeline::BUILD` remains `2.2.485`.
- No IndexedDB schema change.
- No offline queue, media recovery, form submission or sync semantics change.
- No email template redesign.
- No asynchronous mail queue or retry engine yet.

## Acceptance

- Every FieldFlow application mail caller uses `MailDispatchService`.
- The only direct `wp_mail()` call in `src/` is inside `MailDispatchService`.
- Existing mail-specific logs still record their previous business state.
- A failed mail transport exposes the same failure detail through one normalized result.
- Client Portal Shell Stability certification is independent of the current operational-experience build number.
- The 2.2.523 package validator accepts the 2.2.524 package.
- The 2.2.524 validator accepts the 2.2.524 package.
