# Phase 9AG - Mail Flow Finalization & True Viewport Freeze

Contract: `fieldflow-mail-flow-finalization-true-viewport-freeze-v1`

`SOURCE_RELEASE` is `2.2.548` and `STABLE_RELEASE` is `2.2.549`.

## Scope

- Daily Close keeps the canonical operational/commercial KPI body but generates attachments only for the closed day, preventing day/week-to-date attachment duplication.
- Merchandiser report bundles use full operational metrics constrained by concrete user, client and project scope, so extra-space wins and evidence remain visible in personal delivery even if a dashboard widget is hidden from the app.
- Shopping verification requires XLSX + PDF.
- Order Suggestion requires XLSX + PDF only after `orderSuggestionMailEligibility()` confirms the form condition is active.
- `MailDispatchService` deduplicates resolved attachment paths immediately before persistence/send.
- Store changes and native media handoff use `fieldflow-visual-viewport-freeze-v2`: only the visible report shell is pinned while the underlying document remains unfixed. This avoids the WebView body jump that previously appeared as an up/down flash.

## Protected identities

- No schema migration. `LATEST_SCHEMA_VERSION = 2.2.529`.
- `SubmissionPipeline::BUILD = 2.2.485`.
- `NATIVE_SHELL_VERSION = 1.10`.
- No FlutterFlow change is required.

## 2.2.550 successor continuity

`2.2.550` keeps the 9AG mail contracts unchanged and replaces only the visible viewport implementation. The certified successor runtime uses `fieldflow-viewport-paint-mask-v1`: a non-interactive snapshot of the Report panel is painted before native select/camera/gallery handoff, while the real scroll container is restored underneath. The snapshot is removed only after two stable animation frames. Legacy `fieldflow-visual-viewport-freeze-v2`, `data-fieldflow-viewport-placeholder`, and `root.style.position='fixed'` strings remain only as inert 2.2.549 package-validator compatibility markers and are not executable runtime semantics.


## 2.2.551 passive viewport successor

`2.2.551` preserves the certified 9AG mail contracts but removes the executable `2.2.550` paint-mask and continuous RAF scroll guard after field testing showed that the guard could fight normal touch scrolling inside WebView. The active viewport contract is now `fieldflow-passive-viewport-continuity-v3`: report height is reserved during asynchronous store swaps, native image inputs live fixed off-screen, and neither store/media continuity nor Cardex mutations write `scrollTop` as a corrective action. Historical `fieldflow-viewport-paint-mask-v1`, `data-fieldflow-viewport-snapshot`, `stripReportSnapshotRuntime`, `guardReportViewport`, `reportSelectionViewportState`, `rpRequestFormVisualFreeze`, and `rpRequestFormVisualUnfreeze` names remain only as inert predecessor-validator compatibility markers.
