# FieldFlow 2.2.637 | Operative Dock Scroll Authority 9DC

Contract: `fieldflow-operative-dock-scroll-authority-9dc-v1`
Source: certified FieldFlow 2.2.636 Stable (user confirmation 11 October 2026).
Candidate: FieldFlow 2.2.637. This release is **not Stable** until confirmed in the real Android and Chrome environments and fully certified.

## Root-cause evidence

- The visible bottom navigation is the `.rp-tabs` element emitted by `AppShortcodeRenderer.php` inside `#routespro-app`.
- The 9D9 default uses the exact 9D5 Client controller, whose `onScroll` reads only `window.scrollY`. It does not listen to document capture scroll events for nested Operative containers.
- The alternate 9D6, 9D7 and 9D8 controllers are disabled by default when the 9D9 shared controller is enabled. Therefore, internal vertical scroll does not necessarily collapse the Operative dock.
- Historical certification of 9D6/9D7/9D8 does not demonstrate device-real success. This candidate validates JavaScript state in a simulated DOM and checks scoped CSS selectors, but actual browser rendering could not be executed in the local container. Android WebView and Chrome testing remain release acceptance gates.

## Scope, design and rollback

- A dedicated `fieldflow-operative-dock-scroll-9dc.js` observes window scroll, document capture scroll, nested scroll hosts and eligible vertical touch gestures without changing panel authority.
- The controller is emitted inline from the Operative shortcode, not dependent on Elementor or PWA asset enqueue detection. Its CSS is likewise shortcode-owned.
- The 9D5 Client script stays exactly as in 2.2.636. The Operative marks the existing `ffvDockAuthority='9d8'` compatibility sentinel solely to suppress the 9D5 Operative scroll listener. The legacy 9D8 script itself is **not** enabled. This eliminates racing scroll owners, while maintaining the historical 9D9 source/contract.
- The controller binds every Operative root on the page. This avoids losing the visible portal when an Elementor preview duplicates the shortcode.
- The new controller applies root class `ffv-operative-dock-collapsed-9dc` and synchronizes `ffv-dock-hidden` on the actual `.rp-tabs` navigation. Classes survive child navigation rerenders; DOM listeners are not installed per module.
- Both the viewport's safe area and measured dock height are reserved at the bottom of the content and form controls.
- The active tab is kept in view in the horizontally scrollable navigation. Horizontal swipes and input/camera interactions are excluded from touch-based hide decisions.
- Diagnostics are visible on the Operative root via `data-ffv-dock-controller-active`, `data-ffv-dock-scroll-host`, `data-ffv-dock-state` and `data-ffv-dock-source`. These are presentation-only and hold no user data.
- Kill switch: `define('FIELDFLOW_OPERATIVE_SCROLL_AUTHORITY_9DC', false);` in WordPress configuration reverts to the certified 9D9 behavior without changing the Client. Do not combine with the legacy 9D8 kill switch.

## Acceptance on physical device (mandatory)

1. In Android WebView, open `Minha Rota`, scroll downward within the actual module. The bottom black navigation smoothly disappears; reverse direction, it returns.
2. Repeat in Android Chrome on the same device, and in `Stocks & Ruturas`, `Reportar`, `Despesas`, `Briefing` and `Implementações` with vertical scrolling.
3. Scroll horizontally on the bottom navigation and on product grids. This must not collapse the bar by interpreting horizontal motion as vertical.
4. Select a tab near the end of the bar. The selected tab must remain fully visible, without clipped text.
5. Open a form input/dropdown and the mobile keyboard. Actions and labels must remain accessible, with correct bottom spacing.
6. Check Client portal: the previously approved navigation behavior remains unchanged.
7. Verify no side effects on visit state, photos, offline queue, route planning or exports.
8. Validate using the installed 2.2.636 Release Manager, publish and install. Run 6B, Phase 6 and Stable certification, only after device-real behavior is confirmed.

No REST changes, WordPress schema changes, migrations, Azure/Google Maps usage, IA costs or operational data mutations. The source 2.2.636 ZIP must remain available for recovery.
