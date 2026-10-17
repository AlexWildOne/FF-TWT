# Phase 9C2 - React Client Wave 2 & Cross-Portal Render Isolation

FieldFlow `2.2.518` continues the controlled React migration of the Client Portal while separating Client and Operative rendering/event ownership.

The release contract is `fieldflow-react-client-wave2-cross-portal-isolation-v1`.

`SOURCE_RELEASE` is `2.2.517` and `STABLE_RELEASE` is `2.2.518`.

## Scope

- Client and Operative shells declare explicit `data-fieldflow-portal="client|operative"` identity.
- Shared panel-open, lazy-load and scope-change runtimes resolve the emitting portal explicitly and no longer fall back to the first portal found in the document.
- Client lazy-panel completion no longer emits a global synthetic `window.resize`; it emits a scoped client layout event instead.
- Premium Analytics and Operational Experience mutation/event enhancement stays inside the portal or added subtree that triggered the update.
- Android WebView removes masked/backdrop-filtered sticky client navigation on the compositor-sensitive path, preserving horizontal navigation while preventing stale lower-right raster tiles.
- React Client Wave 2 activates only inside the Client Portal for Briefings, Implementations, Planear and Despesas.
- React Client Wave 1 remains limited to Stocks, Shopping and Messages in the Client Portal.
- PHP/REST remain authoritative for data, permissions, business actions and persistence.

## Safety boundaries

No P1-P4 routing or optimisation-rule change.
No database schema change.
No routing, mobility, distance-finalization or endpoint-source change.
No offline, media, form, Conditional Engine or submission business-ownership change.
`SubmissionPipeline::BUILD` remains `2.2.485`.

## Release acceptance

- React Client Wave 2 is active only for Briefings, Implementations, Planear and Despesas in the Client Portal.
- Client and Operative events cannot select the other portal through a global fallback.
- Client lazy completion does not emit global resize.
- Android WebView client navigation has no mask/backdrop compositor dependency.
- Current Phase 9A, 9B and Wave 1 compatibility markers remain available for N-to-N+1 package validation.
- The Release Manager exposes a dedicated `React Client Wave 2` KPI.
