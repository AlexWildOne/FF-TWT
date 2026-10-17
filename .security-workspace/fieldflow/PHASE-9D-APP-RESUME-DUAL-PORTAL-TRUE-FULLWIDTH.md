# Phase 9D - App Resume Stability & Dual Portal True Full Width

FieldFlow `2.2.519` aligns the Client and Operative dashboards on one layout system and hardens the native mobile WebView against stale compositor tiles when the app moves between background and foreground.

The release contract is `fieldflow-app-resume-dual-portal-true-fullwidth-v1`.

`SOURCE_RELEASE` is `2.2.518` and `STABLE_RELEASE` is `2.2.519`.

## Scope

- Client and Operative portals declare the shared `fieldflow-dual-portal-true-fullwidth-v1` layout contract.
- Both shells use the full width available from the WordPress/native host instead of 1520/1540/1680px shell caps.
- Briefings, Implementations, Stocks, Shopping, Planear/Performance, Despesas, Mensagens, Analytics and route/report surfaces inherit the same shell geometry.
- Local reading widths, chart geometry and table minimum widths remain local concerns and may keep internal scrolling where required.
- The legacy operative Analytics mobile `100vw` breakout with negative margins is removed because the parent shell is now truly full width.
- Client and Operative use the same width-only synthetic resize discipline for active panels. Height-only lazy growth does not trigger a global resize.
- Lazy completion emits portal-scoped layout events for both Client and Operative where an explicit portal identity exists.
- Native WebView includes a permanent neutral paint-guard surface. On `visibilitychange`, `pagehide`, `pageshow` and `fieldflow:app-resume`, the guard covers the first resumed paint frames and is removed after the live portal has completed a double animation-frame layout pass.
- During the resume guard, transitions/animations are suppressed and the portal surface is paint-contained to prevent a stale lower-right compositor tile from being exposed.
- Mobile compositor hardening for masks/backdrop filters is applied symmetrically to both dashboards.

## Safety boundaries

No P1-P4 routing or optimisation-rule change.
No database schema change.
No routing, mobility, distance-finalization or endpoint-source change.
No form, Conditional Engine, submission, media or offline business-ownership change.
`SubmissionPipeline::BUILD` remains `2.2.485`.

## Release acceptance

- Client and Operative expose the same dual-portal layout contract.
- Neither dashboard shell contains the former 1520/1540/1680px structural cap.
- Operative Analytics no longer requires a viewport breakout.
- Briefings, Performance/Planear and Team Inbox outer surfaces no longer impose a centered 1680px wrapper.
- Resume guard is armed on background/resume lifecycle and released only after two paint frames.
- Active Client and Operative panels ignore height-only ResizeObserver changes.
- Release Manager exposes a dedicated `Dual Portal Full Width` KPI.
- Current Phase 9A, 9B and 9C compatibility markers remain available for N-to-N+1 package validation.
