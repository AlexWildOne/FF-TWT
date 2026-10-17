# Phase 9AB — Report Selection & Media Continuity

Contract: `fieldflow-report-selection-media-continuity-v1`

Release target: 2.2.544

This phase fixes the reporting regression introduced after durable WebView resume: Android native select focus events must not be mistaken for app resume, manual store selection must be authoritative, and media selection must be durably checkpointed before any lifecycle restoration can re-render the report.

## Contract

- Manual selection in the **Loja a visitar** dropdown is authoritative over any stored resume visit.
- Opening or closing an Android native `<select>` must not trigger a report restore merely because the WebView receives `focus`.
- Native resume reconciliation is armed only by a real hidden/pagehide transition or an explicit `fieldflow:app-resume` event.
- Form draft/step reconciliation follows the same armed-resume rule; a bare WebView `focus` from a native dropdown/file picker cannot restore an older draft or wizard step.
- A live mounted reporting form is newer than a stored checkpoint and must never be replaced by an older route/stop/location during resume.
- `fieldflow:report-selection-intent` cancels pending stored-visit retries before the native dropdown opens.
- `fieldflow:report-selection-changed` immediately persists the selected route/stop/location into the durable resume checkpoint.
- Camera/gallery handoff still flushes answers and wizard step before leaving the WebView.
- After a file is selected, its Blob is committed to IndexedDB before post-selection navigation reconciliation, and the form answer snapshot is checkpointed again.
- The optimised media set is checkpointed a second time while the original capture remains a valid durable fallback.
- Resume must keep the operator on the current report and wizard step when that report is still mounted.
- No FlutterFlow change is required by this release.
- No database migration is introduced.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- `NATIVE_SHELL_VERSION` remains `1.10`.
- `SOURCE_RELEASE` is `2.2.543` and `STABLE_RELEASE` is `2.2.544`.

## Terrain acceptance

1. Open **Reportar** directly and select a different store from the dropdown. The chosen store must remain selected and its form must load.
2. Repeat several store changes without the selection jumping back to a previous visit.
3. Open a visit from **Minha Rota → Preencher** and confirm the same form still opens.
4. Fill several steps, select/take a photo, return to FieldFlow and confirm the same store, same step, same answers and selected photo remain.
5. Repeat with gallery, Maps and another app.
6. Open/close the native store dropdown without changing the store and confirm no resume navigation occurs.
7. Perform a real logout and confirm no stale reporting context is restored after the next login.

## Certification

Run:

- `php tools/verify-report-selection-media-continuity-9ab.php`
- `php tools/test-report-selection-media-continuity-9ab.php`

Then follow the mandatory release flow:

Release Manager → Validate → Publish → private updater → revalidate 6B → Phase 6 closure test → terrain dropdown/media/resume tests → certify Stable.
