# Phase 8W, Operational Endpoint Source of Truth

Contract: `fieldflow-operational-endpoint-source-of-truth-v1`.

`SOURCE_RELEASE` is `2.2.507` and `STABLE_RELEASE` is `2.2.508`.

## Goal

Make the operational start and end points of every route explicit master data rather than a side effect of route stops. The same source of truth is consumed by Backoffice Routes, Campaign PDV planning, the merchandiser Planear flow, REST route writes, route optimisation and distance reconciliation.

## Canonical persistence

No database schema change. Endpoint profiles live in `projects.meta_json`:

- `route_endpoint_default`, campaign-level fallback.
- `route_endpoint_profiles[user_id]`, operative-specific profile.
- `route_endpoint_contract`, contract marker.

Resolution order is explicit route/day override, trusted route endpoint, operative profile, campaign default, otherwise missing. Legacy `routespro_route_defaults` are not consumed implicitly. They can only be copied through the explicit migration action in Centro de Atribuições.

## Legacy protection and recovery

A route endpoint that collapses onto the first or last PDV is rejected unless the zero access/return leg was explicitly confirmed. Draft/planned routes can recover a trustworthy historical endpoint from route workspace revisions or audit history and then fall back to the canonical operative/campaign profile. Started, completed and cancelled routes remain protected by the existing safety rules.

## Negative contract

No current routing producer may manufacture a start point from the first PDV or an end point from the last PDV. This applies to Mobile Planear, Backoffice Routes, Rotas Front, Campaign PDV generation, REST writes, optimisation, reconciliation and Google Maps navigation URLs. Navigation also fails closed when either real endpoint is missing.

No P1-P4 routing or optimisation-rule change.
No database schema change.
No IndexedDB, offline/media, form, Conditional Engine or SubmissionPipeline behavior change.
`SubmissionPipeline::BUILD` remains `2.2.485`.
