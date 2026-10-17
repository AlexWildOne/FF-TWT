# Phase 9CD, Adaptive Locale Dock Continuity

Contract: `fieldflow-adaptive-locale-dock-v1`

`SOURCE_RELEASE` is `2.2.600` and `STABLE_RELEASE` is `2.2.601`.

## Scope

The language control must remain discoverable without permanently covering the operational dashboards.

The 2.2.601 interaction contract is:

1. On first render or app resume, the branded globe is fully visible.
2. As soon as the user scrolls, the control moves almost completely outside the viewport so it does not cover operational content.
3. When scrolling settles, a small branded side tab remains visible.
4. A tap/click on that tab immediately restores the globe and opens the language menu.
5. On desktop, pointer hover and keyboard focus may reveal the control without requiring a click.
6. Closing the menu or choosing a language may return the selector to its compact docked state.

The control respects the right and bottom safe areas used by mobile WebViews. `prefers-reduced-motion` removes the transition animation while preserving the same state behavior and keyboard/touch access.

The same interaction is implemented in the React selector and the legacy fallback. The fallback therefore remains usable if a specific WebView delays or rejects the React island.

No database schema changes.

No FlutterFlow source modification is required.

## Historical continuity

9CD is visual interaction only. It does not alter the 9CC browser-like translation engine, the client language policy, the 9CB brand/scope authority, or the certified visit-memory rules 9BD/9BZ/9CA. Country, geocoding, routing, mobility costs and periodicity remain unchanged.
