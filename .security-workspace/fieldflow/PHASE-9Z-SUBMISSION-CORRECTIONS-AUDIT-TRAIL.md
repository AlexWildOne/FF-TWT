# Phase 9Z — Submission Corrections & Audit Trail

Contract: `fieldflow-submission-corrections-audit-v1`

Release target: 2.2.542

This phase makes backoffice submission corrections durable, auditable and operationally safe.

## Contract

- Every administrative correction requires an explicit reason.
- `SubmissionCorrectionService` owns backoffice correction semantics.
- Answers are never hard-deleted by the normal submission administration flow.
- The former delete action becomes an audited `void` transition.
- Each correction appends bounded metadata with actor, timestamp, reason, status transition and changed question keys.
- Answer corrections create a new `RecordService` version while preserving all prior versions.
- `RecordService::reconcile_operational_records()` keeps historical versions but points current state only at submissions accepted by `SubmissionStatusPolicy`.
- If a corrected/voided submission was the only operational fact closing a visit, that visit is reopened from the stored `form_visit_completion.previous_status` state.
- Route progress is recalculated after commit with completion-event emission disabled: `RouteProgressService::sync($routeId, true, false)`.
- Administrative corrections do not emit `route.completed` and do not enqueue Daily Close or order-suggestion mail again.
- Restoring a corrected submission to an operational status may close the visit again through canonical route reconciliation, but does not duplicate the historical completion email.
- Audit detail is preserved in both the submission metadata and `AuditLog`; answer values remain in canonical answer/version tables rather than being copied into audit payloads.
- No database migration is introduced.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- `SOURCE_RELEASE` is `2.2.541` and `STABLE_RELEASE` is `2.2.542`.

## Certification

Run:

- `php tools/verify-submission-corrections-audit-9z.php`
- `php tools/test-submission-corrections-audit-9z.php`

Then follow the mandatory release flow:

Release Manager → Validate → Publish → private updater → revalidate 6B → Phase 6 closure test → functional correction/void tests → certify Stable.
