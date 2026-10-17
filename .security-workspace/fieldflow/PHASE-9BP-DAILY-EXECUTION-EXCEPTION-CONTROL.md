# Phase 9BP - Daily Execution & Exception Control

Contract: `fieldflow-daily-execution-exception-control-v1`

FieldFlow 2.2.587 closes the operational loop between Planear, Minha Rota and Reportar with a deterministic read-only execution authority and compact mobile alerts.

## Guarantees

- `SOURCE_RELEASE` is `2.2.586` and `STABLE_RELEASE` is `2.2.587`.
- My Route daily alerts can be opened and closed, with a compact mobile-first header that keeps alert count visible without occupying the screen.
- The execution authority reconciles route-stop terminal semantics with operational submission semantics using the same canonical rules already used by FieldFlow.
- Completed visits with no operational report, operational reports without visit closure, failed visits without a reason, visits left open from a prior day and overdue unresolved visits are surfaced as explainable exceptions.
- A route marked completed while still containing unresolved stops is surfaced as a critical route-state mismatch.
- The coordinator `Operação diária` view shows execution-exception counters and a dedicated exception table.
- The service is read-only. It never closes visits, mutates routes, edits submissions or changes planning automatically.
- No Google, AI or other external API call is performed by the 9BP authority.
- Existing 9BK, 9BL, 9BM, 9BN and 9BO contracts remain unchanged.
- No database schema, SubmissionPipeline, offline protocol or routing-cost migration change.
