# FieldFlow Phase 7C - Required Form Validation Hotfix

Release: 2.2.434
Source release: 2.2.433

## Incident

A form could pass browser validation and still be rejected by the server with a generic mandatory-answer error. The primary defect was a contract mismatch: FormRenderer excluded conditionally hidden required targets, while `Forms::handle_submit()` validated every required schema question regardless of conditional visibility.

Mandatory Share Space comparisons also lacked an equivalent client-side completeness check, so an incomplete comparison could reach the server before the missing field was identified.

## Fix

- Submit-time schema now applies `ConditionalFlow::normalise_schema()` in the same order as render/mobile payload.
- Server required validation evaluates ConditionalFlow against submitted values plus the current prefill and skips hidden targets.
- Required-field errors include `field`, `question_label` and an actionable message.
- Quick Report and Focused Report can reveal the exact server-rejected question.
- Required Share Space matrices are checked before submission. Explicit zero remains valid.
- 0/0 Share Space observations are displayed as `Sem frentes registadas`, not as an unanswered row.
- Native Mobile SubmissionService uses the same conditional required rule.

## Safety

No database migration. No relaxation of visible mandatory questions. The hotfix only prevents non-visible conditional targets from being treated as missing and improves identification of genuinely incomplete visible questions. Upload safety, daily-report idempotency, visit completion, stock replacement, Analytics, licensing and Azure safety switches are unchanged.

## Certification

2.2.434 follows the canonical WordPress Release Manager flow and remains subject to the mandatory Azure Phase 6 certification gate: `Revalidar cadeia 6B`, `Testar fecho Fase 6`, `Certificar Stable`.
