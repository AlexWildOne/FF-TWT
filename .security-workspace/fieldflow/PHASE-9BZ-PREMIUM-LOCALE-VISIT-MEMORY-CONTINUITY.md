# Phase 9BZ, Premium Locale & Visit Memory Continuity

Contract: `fieldflow-premium-locale-visit-memory-continuity-v1`

Release continuity: `SOURCE_RELEASE` is `2.2.596` and `STABLE_RELEASE` is `2.2.597`.

## Premium multilingual experience

- The client/user locale authority introduced in 9BY remains the source of truth.
- Front and native WebView language switching is progressively enhanced by a WordPress `wp-element` React component authored in TypeScript under `frontend/src/react-front/locale-switcher.ts`.
- The compact trigger uses a CSS 3D globe and locale badge. The menu shows only locales enabled for the active client.
- The original JavaScript selector remains a progressive fallback if React is unavailable.
- Operational translation coverage is expanded under `fieldflow-i18n-operational-coverage-v2` while preserving exact-match translation and explicit user-content guards. Client, store, product and other user-entered values are not generically machine-translated.
- Interface language remains independent from campaign country, routing, geocoding, Maps and periodicity.
- No FlutterFlow source modification is required.

## Visit memory auto-prefill

A new visit to the same canonical store now starts from the latest eligible operational visit in the same campaign/client scope.

Auto-prefill includes normal safe answers and `product_matrix` / Frentes continuity. It always excludes:

- `image_upload`
- `categorized_image_upload`
- `file_upload`
- `order_suggestion`
- briefing structural blocks

Web rendering, form submission extraction and Mobile/WebView payloads use the same `new_visit_autofill_prefill()` policy. Existing-report editing still restores only that report, including its own media and order data. Canonical store isolation from 9BJ remains authoritative, so memory from a different store cannot become current prefill.

Historical 9BH `new_visit_prefill()` semantics are retained only for predecessor certification continuity. Current runtime explicitly uses the new 9BZ auto-prefill method.

## Data and release safety

- No database schema changes.
- No destructive migration.
- No historical reports or media are deleted.
- Production manifest and the normal Release Manager flow remain mandatory.
