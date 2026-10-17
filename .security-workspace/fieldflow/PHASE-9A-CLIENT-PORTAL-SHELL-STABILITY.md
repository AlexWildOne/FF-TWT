# Phase 9A, Client Portal Shell Stability

Contract: `fieldflow-client-portal-shell-stability-v1`

`SOURCE_RELEASE` is `2.2.514` and `STABLE_RELEASE` is `2.2.515`.

## Goal

Stabilise the Client Portal shell before the next React migration wave. The release removes first-paint layout shifts, duplicate panel-load triggers and navigation-driven smooth-scroll jumps while keeping every existing module and business owner intact.

## Behaviour

- The desktop client rail is shaped server-side with `data-ff2-layout="server"` and `data-ff2-rail="server"`, so JavaScript hydrates navigation instead of reparenting the already-painted `.rp-shell`.
- Client panel navigation has one canonical shell event with `source: client-shell`; module compatibility events are tagged `client-shell-module` and no longer re-enter the global lazy loader.
- Lazy-loader completion events are tagged `lazy-loader`, preventing loader echo loops.
- Client tab clicks do not trigger the shared lazy loader before the canonical shell switch.
- Client tab switching no longer calls smooth `scrollIntoView`; the document scroll position is preserved.
- Active panels synchronise `hidden`, `aria-hidden`, `aria-selected`, `tabindex` and `data-active-panel` without replacing existing module HTML.
- Lazy placeholders receive the current client/project scope before first load. The Growth Hub placeholder is invalidated on scope change and refreshed only when active or next opened.
- The Operational Experience command deck keeps smooth-scroll behaviour for the operative portal but suppresses it in the Client Portal.

## Preserved ownership

- No route planning, endpoint or mobility calculation change.
- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No IndexedDB, offline queue, media recovery or FlutterFlow change.
- No form rendering, validation or submission change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
