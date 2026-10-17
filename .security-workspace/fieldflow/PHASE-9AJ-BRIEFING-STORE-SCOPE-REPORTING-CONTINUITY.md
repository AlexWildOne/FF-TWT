# Phase 9AJ, Briefing Store Scope Reporting Continuity

Contract: `fieldflow-briefing-store-scope-reporting-v1`

`SOURCE_RELEASE` is `2.2.553` and `STABLE_RELEASE` is `2.2.554`.

## Purpose

Daily client and merchandiser reporting must use the exact same briefing eligibility already used by the field form. An active briefing question is not automatically relevant to every store or every route.

## Canonical scope

For reporting, a briefing assignment is eligible only when all of these are true:

1. the briefing question is active and analytics-enabled;
2. the question is explicitly mapped to the store by the briefing scope engine;
3. the store belongs to the current Analytics/user scope;
4. for route-backed daily reporting, the store is present in the routes represented by the canonical report.

The effective scope is therefore `question mapping x report route stores`. Stores outside the route cannot create false pending alerts.

## Reporting semantics

The smart briefing rollup exposes target and answered assignment identities. `CanonicalOperationalReportService` intersects those identities with route store IDs before calculating applicable questions, target assignments, answered assignments, pending assignments, target stores and coverage.

`DailyCommercialReportService` generates a briefing alert only from `briefing_pending_assignments`. The email card and Excel briefing sheet use the same scoped counts.

## Compatibility

No database schema change is introduced. The certified Submission Pipeline remains at build `2.2.485`, schema stays `2.2.529`, and 9AI mail/reporting semantics remain intact.
