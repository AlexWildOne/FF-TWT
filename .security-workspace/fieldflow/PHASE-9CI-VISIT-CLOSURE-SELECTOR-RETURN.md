# Phase 9CI - Visit Closure Selector Return & Route First Paint

Contract: `fieldflow-visit-closure-selector-return-v1`

`SOURCE_RELEASE` is `2.2.605` and `STABLE_RELEASE` is `2.2.606`.

## Purpose

Field tuning of the operative Report and My Route, without changing submission semantics.

### 1. Visit closure returns to the store selector

Before 9CI, after `Queres mesmo concluir esta visita?` → OK, the Report reloaded the day and re-selected the store that had just been closed. The operative saw the locked form ("Reporte já efetuado") and had to scroll back up to choose the next store.

From 2.2.606 the Report returns to the store selector after every terminal outcome:

| Outcome | Closure card |
|---|---|
| Form submitted online | `Visita concluída` |
| Existing report edited (`Guardar alterações`) | `Alterações guardadas` |
| Form or fallback closure queued offline | `Visita guardada no dispositivo` |
| Justified non-visit online | `Visita não realizada registada` |
| Justified non-visit queued offline | `Visita não realizada guardada no dispositivo` |
| Fallback closure without form | `Visita concluída` |

Runtime rules:

- The closed form is removed from the DOM before the swap, so no lifecycle flush can recreate a draft for the completed visit.
- The selector returns to `Seleciona a loja`; the closed option keeps its canonical status label (`concluída` / `não realizada`).
- The stored report context (`fieldflow:last-report-visit`) is cleared, so reload, resume or the native context reload do not reopen the closed store.
- One scroll brings `Loja a visitar` into view, using the existing report scroll container and honouring `prefers-reduced-motion`. It is a single action after a terminal event, never a continuous viewport guard, so the 9AH single-anchor contract is preserved.
- A closure card shows the time, the state and a `Próxima loja` button with the first pending store by planned time. The next store is never selected automatically, so no involuntary check-in can happen.
- Background reconciliation (`ensureVisitDone`) still runs. The refresh uses `selection: 'none'` and preserves any store chosen by the operative in the meantime.
- The app shell receives `fieldflow:report-selection-changed` with an empty selection, so its resume checkpoint is cleared and a pause/resume of the app cannot select the closed store again.
- While the selector is free after a closure (`is-closure-return`), `Concluir visita`, `Não foi possível realizar a visita` and the failure panel are hidden; any new selection (dropdown, `Próxima loja`, resume or `fieldflow:select-visit`) restores them.
- If the operative answers `Cancelar`, a required field is missing or the server rejects the submission, nothing moves.

### 2. My Route first paint

My Route used to request the day's routes only after the Google Maps script loaded (up to the 15 s loader timeout on weak networks). The day is now requested immediately (`fieldflow-route-first-paint-v1`). Google is loaded only when the operative opens `Adicionar paragem`, which already called the loader.

### 3. Legacy PDV autocomplete retired

`assets/ff-autocomplete.js` queried the `ff_pdvs` table, which FieldFlow never creates, and rendered an unstyled `Sem resultados` under the search fields of Descobrir (manager route discovery, `front_routes`) and Base Comercial. The script is no longer enqueued (`fieldflow-legacy-pdv-autocomplete-retirement-v1`). Installations that really own that table can opt back in with the `fieldflow_legacy_pdv_autocomplete_enabled` filter. The authenticated REST endpoint is kept for compatibility.

## Non-regression

- `Queres mesmo concluir esta visita?` confirmation, `editingExisting` and `_rpVisitCloseConfirmed` semantics are unchanged.
- `renderForm` store-swap atomicity (9AE), canonical visit status sync (9AP), resume navigation (9AA/9AC), store identity (9BJ) and 9CH expenses are untouched.
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required. Web, app/WebView and desktop share the same runtime.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
