# FieldFlow Phase 7H - Analytics Critical Path Decoupling Hotfix

Release: 2.2.439
Source: 2.2.438
Database migration: none

## Incident

2.2.436 was stable because Store Opportunity & Risk did not participate in the blocking Analytics request chain. 2.2.437 introduced per-location history plus Store Intelligence synchronously. 2.2.438 removed one duplicate Store Intelligence pass, but the single Store Engine pass and previous-location enrichment still remained in requests that had to complete before the Command View could render. On the real large Pernod project this could still terminate the PHP request with HTTP 500.

## Fix

- Base `/stats` no longer performs previous-period per-location aggregation.
- Base `/analytics/ai-insights` explicitly skips Store Intelligence.
- Store Intelligence is served by `/analytics/store-intelligence` after base Analytics is already rendered.
- The isolated request starts in `isolated_core` mode: execution, trend, Share/Fronts and priority are available; exact per-store stock and Learning enrichment are deferred.
- Failure of Store Intelligence affects only the Store Intelligence panels and never the six base scores, temporal comparison, route view or AI summary.

## Safety

No database migration. No changes to form validation, stock snapshot semantics, routes, emails or export contracts. Adaptive Intelligence remains disabled.

## Certification

2.2.439 must be published through Release Manager, installed through the private updater, then pass Revalidar cadeia 6B, Testar fecho Fase 6 and Certificar Stable before closure.
