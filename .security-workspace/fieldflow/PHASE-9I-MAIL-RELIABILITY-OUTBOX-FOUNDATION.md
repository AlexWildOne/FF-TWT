# Phase 9I, Mail Reliability / Outbox Foundation

Contract: `fieldflow-mail-reliability-outbox-v1`

`SOURCE_RELEASE` is `2.2.524` and `STABLE_RELEASE` is `2.2.525`.

## Purpose

FieldFlow 2.2.525 turns the 2.2.524 canonical `MailDispatchService` seam into a persistent reliability boundary without changing the WordPress mail provider. `wp_mail()` remains the transport. The release adds durable message identity, optional caller-owned idempotency, bounded retries, atomic claims, stale-lock recovery and operator visibility through Mail Center.

## Compatibility rules

- The historical `fieldflow-mail-dispatch-foundation-v1` contract and `MailDispatchService::BUILD = 2.2.524` remain present for installed-validator continuity.
- The successor contract is `fieldflow-mail-reliability-outbox-v1` with OUTBOX BUILD 2.2.525.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No P1-P4 routing or optimisation-rule change.
- No FormRenderer or submission-flow refactor.
- No offline queue, IndexedDB or media-core behavior change.
- The database change is additive only: `routespro_mail_outbox`.

## Reliability ownership

Automated events may provide an explicit idempotency key. Manual sends and tests remain repeatable. Order-suggestion generation failures may still use the legacy generation retry, but once `MailDispatchService` accepts a message into the outbox, transport retry ownership belongs only to the outbox.

## States

`pending`, `sending`, `retry`, `sent`, `failed`, `cancelled`.

`sent` means WordPress transport accepted the message. It does not claim provider-level delivery or inbox receipt.

## Safety

Failed-send attachment copies are stored only in a protected private FieldFlow directory and removed after terminal-state retention. The outbox can be disabled without a plugin downgrade, restoring direct transport through the same canonical service.
