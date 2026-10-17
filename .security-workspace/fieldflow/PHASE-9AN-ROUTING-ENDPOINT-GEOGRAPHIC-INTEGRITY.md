# Phase 9AN, Routing Endpoint Geographic Integrity

`CONTRACT` is `fieldflow-routing-endpoint-geographic-integrity-v1`.

`SOURCE_RELEASE` is `2.2.557` and `STABLE_RELEASE` is `2.2.558`.

## Goal

Make the campaign/project country authoritative for route endpoint and PDV geocoding so ambiguous place names cannot silently resolve in another country. A visible label such as `Montijo` must resolve inside the campaign country before the route matrix, mobility costs, Analytics or Daily Close are calculated.

## Invariants

- Google geocoding uses both `region` and `components=country:XX`.
- Azure Maps geocoding uses `countrySet=XX`.
- Geocode provider caches include the country code in their key payload.
- Campaign planning stores `routing_country_code` and the geographic contract on generated routes.
- Route start/end resolution is country-aware and records formatted address, place id, country and verification metadata.
- Legacy routes are revalidated once before Daily Close or manual resend. Existing route stops used by that route are also checked against the campaign country.
- Exact route metrics are refreshed after geographic reconciliation. Generated weekly routes may not retain saved legacy metrics when the geographic contract is stale.
- Country-safe coordinates may survive a transient geocoder failure, but coordinates outside the campaign territory are discarded rather than silently reused.
- The Mail Center does not create a second distance model. It consumes the route metrics refreshed by the geographic source of truth.
- Daily Close subject dates use the route/report anchor day, not the dispatch clock day.
- An explicit Centro de Atribuições repair action can revalidate historical routes, including completed routes, for a chosen campaign/date range.
- No database schema migration is required. Schema remains `2.2.529` and SubmissionPipeline build remains `2.2.485`.
