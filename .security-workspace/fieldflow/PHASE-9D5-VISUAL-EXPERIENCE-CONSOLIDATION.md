# FieldFlow 2.2.630 | Visual Experience Consolidation 9D5

**Contract:** `fieldflow-visual-experience-consolidation-9d5-v1`  
**Source:** 2.2.629 Stable  
**Successor:** 2.2.630 Candidate  
**Continuity:** 2.2.629 -> 2.2.630  
**No new AI calls. No schema changes. No licensing or governance policy changes.**

## Scope
- On Android/iOS mobile WebView and responsive browsers, reduce the height of the Cliente/Operativo hero without replacing existing identities or controls.
- Keep the Cliente `rpcp-from` / `rpcp-to` interval as the single date authority. A mobile-only expander reveals exactly the existing toolbar with all listeners and IDs intact.
- Compact the fixed tabbar and preserve every authorized navigation entry; hide it during downward scroll and while typing; restore it on upward scroll, focus and return to the page.
- Reserve measured bottom space to prevent obscuring the last controls, with Android/iOS safe area handling.
- Show all critical Operativo shortcuts without clipping; refine only compact SKU rows and report-export cards.
- Preserve template/client theme tokens and FieldFlow/Elementor isolation; no global stylesheet selectors.
- PHP 8.4: explicit nullable string in `LicenseManager::get()`; no licensing behavior changes.

## Rollback
The installed 2.2.629 Stable ZIP is preserved externally. While evaluating this candidate, the isolated visual layer can be disabled through `define('FIELDFLOW_VISUAL_CONSOLIDATION_9D5', false);` (preexisting styles remain). Reverting a full release still follows the approved manual rollback procedure.

## Required QA
1. Android portrait 360/390/430 widths: nav actions remain visible by horizontal scroll; after scrolling down all form submit controls reachable, when scrolling up nav returns; keyboard does not overlap inputs.
2. iOS WebView/safari: safe area, orientation change, interrupted session and restored panel, upload/camera flow remains unchanged.
3. Desktop Windows/Chromium: no changes to tab navigation, dashboard and data tables beyond prior base.
4. WordPress Elementor/client templates: branding colors and focus/hover, no changes to unrelated content and themes.
5. Cliente De/Ate: filter expand/apply retains original single temporal authority; reports and exports use original scope and permissions.
6. Operativo stock/fronts: SKU thumbs, name, count stepper, add product, photo capture and human review.
7. Release Manager 2.2.629 Validator -> 2.2.630 Validator, 6B, Fase 6 and Stable certification.

## Contract tests
`tools/test-visual-experience-9d5.php` and `tools/test-visual-experience-gate-9d5.php`; `frontend/tests/visual-experience-9d5.test.mjs`.
