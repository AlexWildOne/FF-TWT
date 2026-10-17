# FieldFlow 2.2.629, Commercial Production Hardening 9D4

Contract: `fieldflow-commercial-production-hardening-9d4-v1`.
Release continuity: `2.2.628 -> 2.2.629`.
Status: CANDIDATE pending independent installation, E2E and Phase 6 Stable certification.

## Scope delivered

1. Commercial search and facets: per-authenticated-user and effective permission scope cache identity, generation-aware invalidation. The global cache version remains respected. No reuse of the old shared keys.
2. Client and owner filters are now conjunctive, `include_unassigned` participates in the cache key, and `location_id` is passed to faceting.
3. SQL COUNT(DISTINCT location ID) and paged DISTINCT location-ID listing prevent JOIN duplication and truncated totals. Distinct tenant-owned PDV records must not be silently merged by matching place_id or address.
4. Client reporting loads canonical closed visit records through ten-row server pages with a real total, including visits without submissions or justified non-visits. Stale asynchronous responses cannot replace more recent date/client selections.
5. WebView report-selection fallback is now scoped by user/client/campaign with the same 12-hour expiry concept as the existing resume checkpoint; logout removes both new scoped and legacy unscoped keys.
6. Existing visual version label no longer shows an obsolete numeric release. Visual redesign is deferred until computed-style screenshot evidence.
7. Two historically literal tests now accept valid equivalent syntax, and additional focused PHP tests cover the 1001st visit, user isolation, route guard, filters, and caching.

## Hard invariants

No new AI calls. No schema changes. No backend architecture change. No new licensing enforcement, production revocation, forced updates or silent rollback. No offline database schema or media queue changes. No Google Maps routing changes. Legacy certification flow remains authoritative and the manual rollback path is preserved.

## Supported runtime surfaces and acceptance requirements

| Surface | Updated contract | What must be verified in a real installation |
| --- | --- | --- |
| WordPress/Elementor | REST, server pagination, commercial cache and theme-isolated markup | Permissions/tenants, previous and next page, route/briefing scope, light/dark branding, no magenta inherited hover |
| Cliente desktop browser | Report table, date scope, export URLs | 0, 10, 1000, 1001 visits, paging and CSV/XLSX/PDF totals, slow out-of-order network requests |
| Operativo browser/PWA | Authenticated cache, untouched form engine | Route/Reportar consistency, history per store, offline draft and submission recovery |
| Android Flutter WebView | Scoped localStorage and logout | Shared device with two accounts, external photo, app suspension/resume, poor mobile network, no duplicated report |
| iOS Flutter WebView | Same browser contract | Safari/WebKit lifecycle, camera permission, background/foreground, network offline sync |
| Windows Desktop | Unchanged native bridge and offline queues | App resume and export handoff, logout/login switching, no stale tenant visits |
| Azure/SQL Standard S0 | No schema and no AI logic changes | Query p95, DTU, COUNT DISTINCT cost, no extra token or Google Maps requests |
| Publisher/private updater | Manifest, SHA-256, Phase 6 and versioned bundles | 2.2.628->2.2.629 actual upload validation, publishing confirmation, updater install and Phase 6 final gate |

## Evidence / block conditions

Local tests and package validators do not prove live tenant isolation, device suspension behavior, branding, Azure runtime performance or a successful remote publication. Keep 2.2.628 as the certified rollback baseline until all critical rows are accepted.

A failure of client isolation, loss or duplication of a visit, incorrect totals, severe S0 load, or divergence between reports and exports blocks Stable certification.
