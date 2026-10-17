# FieldFlow 2.2.631 | Operative Dock & Status Parity 9D6

**Contract:** `fieldflow-operative-dock-parity-9d6-v1`
**Stable certified source:** 2.2.630
**Candidate successor:** 2.2.631
**Continuity:** 2.2.630 -> 2.2.631
**No new AI calls. No schema changes. No licensing or governance changes.**

## Scope
1. Reconcile the 9D5 mobile bottom navigation experience in Operative when content scrolls inside lazily mounted panels rather than `window`. Capture vertical scroll at document level and independently observe window scroll. Ignore horizontal-only tables and the tabbar itself.
2. Debounce micro-movements by direction/distance thresholds. Keep bottom navigation accessible on upwards movement, navigation commands, page restoration and when leaving a native dropdown. Respect reduced motion, mobile keyboard and native safe areas.
3. Visually refine the Operative panel labels and card wrapping using existing campaign tokens, without touching Client CSS or business logic.
4. Translate technical route/visit statuses to Portuguese only at the Client Portal display boundary; retain all canonical values (`done`, `in_progress`, etc.) in REST requests, option values, database and events.
5. Keep the whole 9D5 UX and its original kill switch. For 9D6-specific rollback set `define('FIELDFLOW_OPERATIVE_PARITY_9D6', false);` in wp-config.php. The old 9D5 scroll handler will handle the Operative again. This is NOT a full plugin rollback.

## Required acceptance
- Android: in Operative, scroll vertically in Minha Rota, Reportar, Stock and Implementações (both whole page and nested panels); dock hides descending and reappears ascending, without flicker or cutting the last button.
- iOS WebView: repeat with keyboard, native select open, orientation change, photo upload/return and session resume.
- Windows/browser: desktop navigation is unchanged; keyboard/focus access works.
- Client Portal: 9D5 filter expander and dock remains identical; De/Até still authority; report filters and exports remain unchanged.
- Brand/Elementor: no magenta/hover injection or cross-client CSS leakage.
- Route statuses show readable labels; server values in HTML data attributes, select values and payloads remain canonical.
- Run the installed 2.2.630 validator, install via private updater, revalidate 6B and Fase 6, complete real profile QA before Stable certification.

## QA/technical
`tools/test-operative-dock-parity-9d6.php`, `tools/test-operative-dock-parity-gate-9d6.php`, and `frontend/tests/operative-dock-parity-9d6.test.mjs`; full legacy tests retained.

**No paid service is called by the new JS/CSS. No schema/AI/offline/financial/licensing changes.**
