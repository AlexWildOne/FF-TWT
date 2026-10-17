# FieldFlow 2.2.467 - Operational Intelligence & Field Reliability Hardening

FieldFlow 2.2.467 is a conservative production hotfix built on 2.2.466. It changes the WordPress plugin only. No Flutter source, API route names, database schema or operational data is migrated.

## Analytics temporal model

The raw `/stats` envelope remains backward compatible. A new read-only `TemporalAnalyticsService` adds an operational clock without changing the historical KPIs consumed by existing dashboards.

- Past closed days can be scored and compared.
- Today is work in progress. Unresolved visits today are not automatically classified as failure.
- Future route days are planning. They do not lower execution/risk scores and are not described as overdue or non-compliant.
- Executed visit rate remains based only on `done/completed` visits.
- A justified failed visit is terminal for route closure but never becomes an executed visit.
- Ask FieldFlow receives explicit today and future-route evidence while operational scoring uses closed past work only. AI prompts receive a compact temporal envelope rather than full location/day tables.

## Ask FieldFlow front and help

- Operational Analytics, Client Portal and Analytics 2.0 management no longer render the user's own question as a blue chat bubble. The question remains in the internal short conversation history used for follow-up context.
- Suggestion chips now expose route planning and FieldFlow help.
- Functional help is deterministic and bypasses stats and external AI calls for questions such as how FieldFlow, routes, Analytics, Cardex, order suggestion and daily close work.

## Cardex / order suggestion

Existing 2.2.276 pairing remains authoritative. 2.2.467 adds a narrow legacy bridge for old `order_suggestion` questions saved as manual with no explicit product rows while a valid paired Fronts question already resolves a Cardex for the store.

- Empty legacy manual order fields inherit the Fronts Cardex at runtime only.
- Explicit manual product lists are never modified.
- The saved form schema is not rewritten.
- Both web renderer and mobile payload benefit from the same server-side schema reconciliation, with no Flutter change.

## Daily close after justified non-visit

`route.completed` remains the canonical integration event. The Mail Center queue is now exposed through an idempotent `queueDailyCloseForRoute()` entry point. After the stop transaction COMMIT, the Events endpoint queues the same route again only when `route_completed_now` is true.

The queue is keyed by route and the existing per-audience dispatch claims remain authoritative, so the post-COMMIT reliability path cannot intentionally create a second daily-close delivery.

## Certification continuity

- Release Gate 1.18.0 adds `fieldflow-operational-intelligence-hardening-v1`.
- `tools/verify-operational-intelligence-hardening.php` includes static contract checks and a deterministic temporal fixture.
- SOURCE_RELEASE is 2.2.466 and STABLE_RELEASE is 2.2.467.
- The versioned offline plugin runtime advances to 2.2.467 only to maintain cache/release identity. Its offline transport behavior is unchanged.
- Release Manager publication, package SHA-256, production manifest verification and Azure-backed Phase 6 revalidation remain mandatory.
- No database migration is required.
