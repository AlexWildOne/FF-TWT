# Phase 9C - React Client Wave 1 & Analytics Stability

FieldFlow `2.2.517` closes the remaining Phase 9B transition instability and starts the first controlled React ownership wave in the Client Portal.

The release contract is `fieldflow-react-client-wave1-analytics-stability-v1`.

`SOURCE_RELEASE` is `2.2.516` and `STABLE_RELEASE` is `2.2.517`.

## Scope

- Analytics panel lifecycle is explicit through `fieldflow-client-panel-lifecycle-v1`: `mount`, `loading`, `ready`.
- Client AI analytics uses one parallel fetch boundary and one final render, preventing a second visual rebuild after store intelligence arrives.
- Client Portal tab activation keeps the active tab visible synchronously, before paint, without scheduling a second viewport adjustment.
- The shared `ResizeObserver` no longer emits synthetic `window.resize` events for height-only Client Portal growth. Only real width changes propagate after the first stable measurement.
- Stocks, Shopping and Messages adopt `fieldflow-react-client-wave1-v1` for React-owned control chrome, visual status and panel lifecycle.
- PHP/REST remain authoritative for data, permissions, business actions and persistence. This is a progressive ownership migration, not a business-logic rewrite.
- Client Portal module surfaces remain full width and preserve the Phase 9A/9B shell and mobile navigation contracts.

## Safety boundaries

No P1-P4 routing or optimisation-rule change.
No database schema change.
No routing, mobility, distance-finalization or endpoint-source change.
No offline, media, form, Conditional Engine or submission business-ownership change.
`SubmissionPipeline::BUILD` remains `2.2.485`.

## Release acceptance

- React Client Wave 1 is active only for Stocks, Shopping and Messages.
- Analytics lazy intelligence renders once per resolved scope refresh.
- Height-only lazy growth does not trigger a synthetic global resize in the Client Portal.
- Current Phase 9A and Phase 9B compatibility markers remain available for N-to-N+1 package validation.
- The Release Manager exposes a dedicated `React Client Wave 1` KPI.
