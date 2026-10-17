# Phase 9BH — Latest visit history and media/order isolation

Contract: `fieldflow-latest-visit-history-isolation-v1`

## Root causes found in 2.2.578

1. Strict `report_day < current day` skipped a more recent completed same-day visit after a form revision. Callers now explicitly include same-day submissions and exclude the current edited report by its ID.
2. When no eligible operational submission existed, Web and Mobile fell back to the generic record current-version pointer. That pointer can be stale, non-operational or outside the reporting date. There is no such fallback now.
3. Order suggestions and file uploads were excluded from the history popup but could remain as current prefill. The old media filter removed only image questions. A new-visit allowlist now carries only product_matrix values; informational history excludes all media/files/orders and obsolete schema keys.
4. Editing could merge missing answers with the older record. Edits now restore only their own submission, including their current media and order lines.
5. Quick-report requests lacked the selected-store response guard already used by the focused Form tab. Late responses now cannot replace the currently selected store's form.

## Behaviour and scope

- History remains scoped to store, campaign and client, across form revisions. Only submitted/reviewed/approved rows qualify. Future report days, drafts, rejected and inactive submissions never supply memory.
- Latest means operational report day descending (route date is the authoritative fallback for legacy reports), then submission timestamp and ID descending. The date ceiling is the earlier of the report day and today in WordPress's timezone.
- Normal question answers are informational memory. Frentes uses the last visit's final after/qty as the new before; own-report edits preserve their saved before/after pair.
- Web rendering, legacy Web extraction and Mobile payloads use the same source and input policy. Historical evidence/order reporting remains intact.
- The focused Form tab already has a response sequence/visit identity guard; the quick-report path now has equivalent protection. Cached offline forms cannot acquire newly completed history while disconnected; reconnect/reload refreshes the server source.
- No stored report, image, order, table or schema is deleted or migrated.

## Certification continuity

`SOURCE_RELEASE` is `2.2.578` and `STABLE_RELEASE` is `2.2.579`.
The installed 2.2.578 Release Manager's predecessor markers remain present. Production manifest hashes cover every packaged file; the new 9BH check is exposed in Release Gate and Release Manager.
`LATEST_SCHEMA_VERSION` remains `2.2.529`; `SubmissionPipeline::BUILD` remains `2.2.485`.

Local validation exercises the SQL against an in-memory database and the actual Mobile payload, plus the quick-report asynchronous selection guard. It does not replace running certification in the installed WordPress environment after installation.

Three historical CLI identity verifiers pinned to 2.2.530 now validate the actual release and its immediate predecessor dynamically. Their contract checks remain enforced; the central continuity verifier executes the real ReleaseGate method.
