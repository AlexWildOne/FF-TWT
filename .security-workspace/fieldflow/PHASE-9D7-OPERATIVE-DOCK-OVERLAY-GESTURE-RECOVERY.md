# Phase 9D7 - Operative Dock Overlay & Gesture Recovery

Release: 2.2.631 -> 2.2.632 (candidate), baseline: 2.2.631 Stable.
Contract: `fieldflow-operative-dock-overlay-9d7-v1`.

## Scope
The Android screenshot evidence showed a fixed black bottom dock hiding fields in Operative Stocks and the store Operation Card. 9D6 had source-level scroll-event checks but lacked a live Android gesture fallback and actual panel bottom spacing.

9D7 observes captured document/window vertical scroll and passive vertical `touchstart`/`touchmove` gestures from inside #routespro-app, excluding horizontal swipes, native selects, keyboards and editing controls. The fallback does not require a specific scroll host to issue a `scroll` event. Only one layer (9D7) changes the Operative dock while 9D5 owns the validated Client dock. Layout adds a real bottom reserve to panels and a measured dock height. On upwards movement the navigation is restored.

## Governance
No new AI calls. No schema changes. No API mutations. No offline queue, WebView bridge, licensing or tenancy changes. No WordPress global styles. Existing 2.2.631 9D6 JS/CSS and its old assets remain available, with limited changes to enable authority handoff. Kill switch: `define('FIELDFLOW_OPERATIVE_OVERLAY_9D7', false);` in wp-config.php restores 9D6. This is a visual fallback, not a full release rollback.

## Acceptance
- Real Android: My Route, Reportar, Stock & Rupturas, Implementacoes and store Operation Card: scroll down hides, scroll up shows; keyboard and dropdown functional; last fields never unreachable.
- Client dock stays unchanged, including De/Até authority.
- iOS WebView, desktop WordPress and Windows sanity checks.
- Release Manager of 2.2.631 validates package, updated release validator, 6B, Phase 6 and Stable after manual acceptance.

## Evidence caveat
Local source/Chromium and package checks cannot fully reproduce the Hostinger native WebView or confirm real-device behavior. Do not claim production certification until the user confirms it.
