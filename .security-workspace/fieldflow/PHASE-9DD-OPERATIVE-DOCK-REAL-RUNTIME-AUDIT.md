# FieldFlow 2.2.638 | Operative Dock Real Runtime Audit 9DD

Contract: `fieldflow-operative-dock-real-runtime-audit-9dd-v1`

Purpose: establish the real reason the certified 2.2.637 dock controller does not hide on Android and Chrome. This release DOES NOT claim the visual issue is fixed. It instruments the real DOM only when `ff_dock_diag=1` is explicitly supplied in the operative URL.

- PHP renders a static panel marked `Dock 9DD, PHP 2.2.638`. Its absence even with a cache-buster indicates an outdated shortcode response, a wrong page, or a server-side delivery issue.
- JS changes the panel label to `JS ativo`; the panel displays the operative root count, dock/controller presence, viewport width, scroll event counters, touch event counters, effective style and the actual element found at viewport bottom.
- `Testar recolha` temporarily forces hide on the current `.rp-tabs` element with inline important properties, without modifying business data. `Repor barra` restores every previous inline property. Browser navigation and pagehide also restore the test.
- `Copiar diagnostico` copies structural details only, no usernames, stores, addresses, URLs, product names, tokens or network requests.
- Without the diagnostic query parameter, new runtime code and panel do not execute or render.
- The existing 9DC CSS, JS and 9D5 Client dock are byte-for-byte unchanged. No database migrations.

Real-device acceptance: open the authenticated operative route in Chrome with `?ff_dock_diag=1` (or `&ff_dock_diag=1` if it already has URL parameters). Scroll in Minha Rota and Stocks, open the panel and inspect counters. Use `Testar recolha` once to see whether the visible black bar itself disappears; then click `Repor barra`. Report the resulting readout and the effect of the manual test. Do NOT equate a technical certification with visual closure.

Interpretation:
1. No PHP badge: stale HTML or wrong shortcode/page.
2. PHP badge, JS still waiting: script filtered, CSP, execution error, or runtime delivery problem.
3. JS active but no scroll/touch counters: gesture occurs in another document/frame or the event target is not captured.
4. Counters advance and dock state stays visible: detection thresholds, scrolling host, or 9DC/controller ownership.
5. State says hidden but manual hide not affecting visible bar: different visible element or CSS/DOM ownership.
6. Manual hide succeeds while automatic hide does not: JS scroll/gesture behavior, not a misplaced bar.

The feature is a diagnostic step only. It is not a speculative visual fix; the final correction must be based on runtime evidence.
