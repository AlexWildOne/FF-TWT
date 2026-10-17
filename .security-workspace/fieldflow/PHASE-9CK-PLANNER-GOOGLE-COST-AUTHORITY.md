# Phase 9CK - Planner & Commercial Google Cost Authority

Contract: `fieldflow-planner-google-cost-authority-v1`

`SOURCE_RELEASE` is `2.2.607` and `STABLE_RELEASE` is `2.2.608`.

## Purpose

The Google Cost Control Center (9BN) governs server-side Google requests. The Descobrir (manager route discovery) and Base Comercial screens also call Google directly from the browser/WebView, where those requests were neither measured nor governed. 9CK brings the browser paths in line with the certified low-cost policy (9BK, 9BM, 9BN) without reducing route quality.

## Descobrir (manager route discovery, `front_routes`)

Operatives use the self route planner (`Planear`, `SelfRoutePlannerShortcodeRenderer`), which never called Google Directions and is unchanged. The Google-heavy planner below is the manager discovery screen.

Before 9CK every queue change (add, remove, drag, lock) and every pause while typing the start/end point called `DirectionsService.route` with `optimizeWaypoints: true`, which Google bills at the advanced Directions rate.

From 2.2.608 (`fieldflow-planner-local-preview-cost-authority-v1`):

- Queue changes and endpoint typing produce a zero-cost local preview. Stop order is suggested locally (nearest neighbour + 2-opt) and km/time use the same calibrated road estimator as `RoutingMatrixService::internalEdge()` (road factor 1.38/1.30/1.23/1.17 and speed 28/38/55/72 km/h).
- Manual order and fixed positions are respected exactly as before: no local reordering when the operative dragged, removed or locked a stop.
- Start/end coordinates come from the Google address autocomplete or the campaign endpoint profile. Typed addresses without coordinates are excluded from the estimate and the status says so.
- Google is called only when the operative taps `Calcular percurso real`: one Directions request with the current order. Google waypoint optimisation runs only when the premium switch `google_routes_waypoint_optimization_enabled` is on.
- Saving still uses the server `/routes/{id}/optimize` hybrid optimiser, governed by the Cost Control Center. Saved routes are unchanged.
- Nearby Search only complements a Text Search that returned fewer than 8 places.
- `PlacesService` uses a plain element instead of a hidden `google.maps.Map`, removing one billable map load per screen.

## Base Comercial

- The Google button used to request Place Details with phone and website for up to 20 results per click, ignoring `google_places_contact_enrichment_enabled` (disabled by default since 9BM). Details are now requested only when that switch is on; otherwise the Text Search fields (name, address, coordinates, place ID) are used.
- The hidden map instance for `PlacesService` is removed, as in Descobrir.
- A pre-existing PHP warning (`$endpoint_profiles` undefined in `front_commercial`) is removed; the attribute now renders an empty profile map.

## Non-regression

- Route saving, server optimisation, endpoint provenance, 9BK/9BM/9BN cost authorities, 9CI visit closure and 9CJ security hygiene are preserved.
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
