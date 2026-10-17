# 2.2.646 | Briefing, Stock & Mobile Route Scope 9DL

**Contract:** `fieldflow-briefing-stock-mobile-scope-integrity-9dl-v1`.

- Operative briefing is strictly scoped by route stops in the briefing's ISO week. Campaign portfolio, spreadsheet M markers, unmapped spaces and other store entries are NOT treated as actual route visits and are not shown as operative store instructions; client/Backoffice continues to receive full data. Source data are never deleted.
- On narrow mobile viewports, promotions and Cardex use labelled item cards with generous width instead of unreadable squeezed table columns. Parser source rows and raw import-debug fields are hidden from frontline UI, retained in the underlying source/BO.
- Correct Sonae/Continente/Modelo/Bom Dia store-family identity before stock matching: `SONAE` resolves to the `continente` family; explicit Makro or Pingo Doce identifiers are rejected. No name-based cross-retailer migration; legacy mismatches are excluded from operative display and left for review/unmapping by existing BO workflow.
- Preserve operative Sonae/PD store selector state through refresh, filter both cards and totals immediately, and update store options to the chosen retailer. Do not affect the client dashboard.
- No changes to route planning, actual allocations, form submission, offline synchronization, mobile dock 9DF, AI, Google/Azure costs, database schema or license automation.
- Test contracts: `php tools/test-briefing-stock-mobile-9dl.php`, `node --test frontend/tests/stocks-insignia-filter-9dl.test.mjs`, source JS extraction and `node --check`, production manifest 9DH and package validator N+1. Physical Android and real PDV mapping remain acceptance tests after deployment.
