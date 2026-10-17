# Phase 9BY, Multilingual Client Locale Authority

Contract: `fieldflow-multilingual-client-locale-authority-v1`

Target successor: FieldFlow `2.2.596` and later.

## Objective

FieldFlow becomes a multilingual product without coupling interface language to the operational country, routing, geocoding, periodicity or campaign data.

## Canonical rules

1. Client language configuration is persisted inside the existing `routespro_clients.meta_json` structure under the `i18n` key.
2. No database schema changes are required.
3. The built-in baseline contains `pt-PT`, `en-GB`, `es-ES` and `fr-FR`.
4. Each client has one default locale and an explicit list of enabled locales.
5. Backoffice can allow or block user language switching per client.
6. Signed-in user preference is persisted per client, with a global user fallback.
7. Frontend and native WebView use the same WordPress runtime. No FlutterFlow source modification is required.
8. The language selector is rendered by the FieldFlow runtime with flag plus language identity and remains available to dynamic React, AJAX and offline UI.
9. A `MutationObserver` re-applies translation to runtime-created system UI without modifying user-entered business data.
10. Client, store, product, person and campaign names are never translated automatically.
11. Unsupported or unavailable locales fall back to the client default, with `pt-PT` as the safe platform fallback.
12. Locale selection does not alter country authority, Google Maps routing, route calculation or geographic integrity.
13. Translation is local to the plugin. There is no external translation API dependency and no translation request cost.
14. Release Gate and Release Package Validator enforce the same 9BY contract before Stable certification.

## Runtime components

* `src/Localization/LanguageService.php`
* `src/Localization/LanguageController.php`
* `assets/fieldflow-i18n.js`
* `assets/fieldflow-i18n.css`
* `src/Admin/Clients.php`
* `src/Architecture/RuntimeRegistry.php`
* `src/Architecture/RestRouteRegistry.php`

## Certification continuity

The certified predecessor is `2.2.595`. The 9BX predecessor markers remain present in `Phase6StableRelease`, while the active successor markers become:

* source release `2.2.595`
* stable release `2.2.596`

The 9BY verifier and runtime test are part of the release package and are required by both Release Gate and Release Package Validator.
