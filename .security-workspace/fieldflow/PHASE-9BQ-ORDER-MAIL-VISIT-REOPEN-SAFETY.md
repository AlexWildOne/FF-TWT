# Phase 9BQ, Order Mail & Visit Reopen Safety

Contract: `fieldflow-order-mail-visit-reopen-safety-v1`

Release: `2.2.588`

## Purpose

Protect three high-risk field workflows without changing the database schema:

1. A persisted positive order suggestion must never disappear silently from the mail lane.
2. Closing a visit from a field form requires an explicit final confirmation, except when editing an already completed daily report.
3. A manager can reopen an accidentally completed/failed store while preserving every historical submission, answer and media asset.

## Order suggestion mail continuity

- `FormSubmissions::persistedOrderSuggestionEvidence()` detects persisted positive order quantities.
- Positive persisted quantities become mail authority when a stale conditional re-evaluation disagrees, unless the submission snapshot explicitly hid that order question.
- Every automatic order-suggestion mail attempt persists one explicit state in submission meta: `queued_or_sent`, `partial_retry`, `failed_retry`, `failed`, or `not_applicable` with a concrete reason.
- Missing scope, automation, recipients or reconstructable rows trigger durable retry instead of a silent return.
- Existing Mail Center / Outbox remains the delivery authority and existing idempotency claims are preserved.

## Visit close confirmation

- Focused visit, Quick Report and standalone visit-form close paths require `Queres mesmo concluir esta visita?` before terminal completion.
- Editing an existing daily report remains a save/correction action and does not show a redundant close confirmation.
- Offline and resumable delivery semantics are unchanged after the confirmation is accepted.

## Administrative reopen

- Backoffice route workspace exposes `Reabrir loja` for completed/failed stops, even when the route is already complete.
- Reopen requires a reason and writes bounded audit history under `visit_admin_reopen_history`.
- Existing submissions, answers and media are never deleted.
- An active `visit_admin_reopen` marker blocks passive historical-submission reconciliation.
- A deliberate new submission resolves the marker and may close the visit again.
- Route progress is recalculated without passive submission reconciliation so a completed route can safely return to `in_progress`/`planned`.

## Compatibility

- Source release is `2.2.587`; `2.2.588` is the immutable publication successor carrying the 9BQ safety corrections.
- Preserves 9BK, 9BL, 9BM, 9BN, 9BO and 9BP.
- No Google/API architecture changes.
- No database schema changes.

No database schema.
