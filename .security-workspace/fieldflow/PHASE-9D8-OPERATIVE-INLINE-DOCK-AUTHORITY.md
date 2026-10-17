# FieldFlow 2.2.633 | Operative Inline Dock Authority (9D8)

**Contract:** `fieldflow-operative-inline-dock-authority-9d8-v1`
**Baseline certified:** 2.2.632 Stable.
**Release transition:** 2.2.632 -> 2.2.633.
**Scope:** Operative Portal only, mobile bottom dock.

## Root cause and evidence

After 2.2.630, 2.2.631 and 2.2.632 were technically certified, the real Operative dock remained fixed, both in Android and the Chrome browser. The shortcode always renders `.rp-tabs` and `.rp-tabbar`, but previous scroll and touch logic was only shipped through the WordPress `wp_enqueue_scripts` path in `ResponsiveAssets.php`. An Elementor/PWA page not discovered by that heuristic can render the dock without executing those handlers. This is a supported failure mechanism in the code, not a proven assertion about Hostinger's live HTML.

9D7 also stopped tracking touch gestures when it could not find a scroll host (`canScrollVertically`) and relied on CSS classes emitted by the external script. The resulting visual regression may persist despite source tests and release certification.

## 9D8 correction

- The Operative shortcode embeds its own scoped 9D8 CSS and JavaScript after rendering the portal shell, irrespective of whether the WordPress enqueue heuristic detects that page. No external CDN or script URL is required. WordPress shortcode content already uses inline scripts, so no new cross-origin dependency is introduced.
- Root-owned `ffv-dock-authority-9d8` / `ffv-dock-collapsed-9d8` class controls the dock. The visual rule uses an explicitly hidden state with opacity, visibility, transform and pointer-events, not just a transform that could be overridden.
- Touch gestures track vertically without a precondition that a particular scrollable DOM parent must be found. Captured scroll and wheel events complement touch. Gestures from inputs, native selects, canvas and horizontal swipers are excluded; keyboard and navigation access remains protected.
- Bottom clearance is computed from dock height plus a safe area for the end of forms, product lists and route actions.
- 9D5/9D6/9D7 preserve their historical contracts for the Client and release gate, but do not overwrite the Operative dock when the 9D8 authority is present.
- `FIELDFLOW_OPERATIVE_DOCK_AUTHORITY_9D8=false` in wp-config.php reverts only this visual layer to the previous (known-imperfect) 9D7 presentation. No data or schema rollback occurs.
- The historical 9D7 validator references versioned runtime files dynamically in the successor, avoiding permanent fixed 2.2.632 expectations in future releases. The installed 2.2.632 validator still needs its separate compatibility bridge before releasing 2.2.633.

## Tests and acceptance

- Test with the Operative shortcode rendered **without** loading 9D5/9D6/9D7 JS externally, on a mobile viewport: down scroll hides, up scroll restores, CSS opacity/visibility truly change, bottom clearance exists.
- Test touch gesture without scroll events, horizontal gesture ignored, native select and keyboard behavior, and no effect on Client Portal.
- Test SHA-256 manifest, PHP syntax, frontend regressions, original and successor release package validators, and phase 6.
- Real-world acceptance still required in Android WebView and Chrome: Minha Rota, Reportar, Stock & Ruturas, Implementacoes, store contact card; no overlap or clipped final actions; Client unaffected.

**No new AI calls, no schema changes, no routes, business logic, REST, offline queues or licensing changes.**
