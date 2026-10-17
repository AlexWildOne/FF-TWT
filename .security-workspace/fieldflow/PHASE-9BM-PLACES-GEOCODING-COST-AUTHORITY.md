# Phase 9BM, Places & Geocoding Cost Authority

Contract: `fieldflow-places-geocoding-cost-authority-v1`

Release: 2.2.584

## Objective

Reduce Google Maps Places and Geocoding consumption without degrading route quality or store identity.

## Runtime rules

- Unchanged route endpoints reuse already validated coordinates when the address fingerprint and campaign country remain coherent. Google-derived coordinates are reusable only inside the 30-day coordinate window; FieldFlow-owned/imported coordinates are not artificially expired.
- Any material address change invalidates endpoint reuse and re-enters normal geocoding validation.
- The REST geocode endpoint first checks a scoped FieldFlow location with the same address before making an external provider request.
- Google-derived location coordinates are only reused by the REST authority while the stored location is within the 30-day coordinate window. `place_id` remains retained independently.
- Google Geocoding provider transients are capped at 30 days even if the generic routing cache setting is higher.
- Automatic Google Places phone/website enrichment is disabled by default. Address selection, geometry, place ID and address components remain available.
- Contact enrichment remains an explicit opt-in setting for workflows that genuinely require Google phone/website data.
- Avoided geocoding requests are recorded in the privacy-safe API Usage Ledger without addresses, coordinates or response payloads.

## Non-regression

- No Route Matrix, traffic or Google TOLLS behavior is re-enabled.
- Existing store identity, planner, form history, mobility cost and Daily Close contracts remain unchanged.
- No mass geocoding or route refresh runs during upgrade.
