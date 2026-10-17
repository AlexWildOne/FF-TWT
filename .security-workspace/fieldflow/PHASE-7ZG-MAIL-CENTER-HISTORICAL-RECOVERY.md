# FieldFlow 2.2.465 - Mail Center Historical Recovery

FieldFlow 2.2.465 adds a guarded back-office recovery path for operational emails that predate the automatic delivery rules introduced in 2.2.464.

## Historical route replay

- The Mail Center adds an `Envio manual` area with date, client, project and merchandiser filters.
- A historical daily-close message is rebuilt from the persisted report data for the selected route day and merchandiser.
- The current `daily_close` template for the same client/project is used, even when the historical date predates the automation start date.
- A route left technically `in_progress` by an older build can be reconciled before delivery. A justified non-visit is terminal, but an unresolved stop still blocks the send.
- Reconciliation emits the normal route status change for integrations but suppresses the automatic `route.completed` delivery event for that manual operation, preventing a hidden duplicate email.

## Historical order suggestion replay

- Existing submissions that contain order suggestions are listed independently from the automatic event history.
- The original persisted suggestion is the source of truth. Excel and PDF are regenerated from that exact submission at send time.
- If the project has no order-suggestion automation yet, the existing safe auto-provisioning path prepares the default editable template before the manual send.
- Manual order replay does not acquire the automatic per-recipient dispatch claims and does not schedule the normal automatic retry chain.

## Current client associations

- `Só merchandiser` targets the operative persisted on the route/submission, even if project membership changed later.
- `Só cliente` resolves the current client users associated with the project/client plus the principal client email.
- `Cliente + merchandiser` combines both sets with email de-duplication and least-privilege preference for the merchandiser profile if one address belongs to both sets.
- Extra automation addresses are not silently included in a manual client resend.

## Manual test delivery

- Test sends go only to the back-office email typed in the manual center.
- Daily-close tests can reproduce either the merchandiser personal view or the client view restricted to that merchandiser/day.
- Order-suggestion tests reproduce the exact historical suggestion and mandatory Excel/PDF attachments without contacting production recipients.

## Audit and safety

- Real manual route messages are logged as `scheduled_report_manual`.
- Real manual order messages are logged as `order_suggestion_manual`.
- Test messages keep their existing test email types and carry a manual trigger in metadata.
- A manual resend never creates a new form submission, never re-completes a visit, and never changes Analytics source data.
- Manual execution does not overwrite the automation's normal `last_run` / `last_status`, so scheduled-delivery health remains meaningful.
- Paused templates may be used deliberately for a manual historical resend without reactivating their schedules.
- No database migration is required.

## Certification continuity

- Release Gate 1.16.0 adds `fieldflow-mailcenter-historical-recovery-v1`.
- Private Release Manager publication, production-manifest SHA-256 verification, licensing, installation identity, heartbeat, governance and Azure-backed Phase 6 certification remain mandatory.
- SOURCE_RELEASE is 2.2.464 and STABLE_RELEASE is 2.2.465.
