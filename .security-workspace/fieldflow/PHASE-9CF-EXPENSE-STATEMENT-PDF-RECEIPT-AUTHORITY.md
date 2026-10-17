# Phase 9CF - Expense Statement PDF & Receipt Authority

Contract: `fieldflow-expense-statement-pdf-receipt-authority-v1`

`SOURCE_RELEASE` is `2.2.602` and `STABLE_RELEASE` is `2.2.603`.

## Goal

Turn the existing Expenses module into a signed-ready expense statement workflow without creating a second ledger. Existing expenses remain the single source of truth.

## Filter authority

- Explicit De/Até has precedence over the month shortcut.
- If only one explicit date is supplied, the period is that single day.
- If De/Até are absent, the selected month expands to its exact calendar bounds.
- Category, status and search are applied through the existing canonical `ExpensesController::buildWhere()` query path.
- With no period filter, the PDF mirrors the unbounded Expenses view instead of silently inventing a different month.

## PDF statement

- A4 portrait, paginated for long periods.
- TWT and client/campaign branding through the existing Branding authority.
- Operative, period, total, record/day summary.
- Expenses grouped by day with daily subtotals.
- Final receipt declaration with total, period, signature line and blank date.
- Stable document identifier for filing/audit.

## Access

- Operatives export their own statement from the Expenses front/app.
- Managers can select an operative in the Expenses BO and generate that individual statement.
- The WebView refreshes the live filters and active client/project scope at tap time before native download handoff.

## Continuity

No database schema changes. No duplicated expense storage. No FlutterFlow source modification is required. Historical 9BV, 9BZ, 9CC, 9CD and 9CE authorities remain intact.
