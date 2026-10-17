# Phase 8X, Endpoint Reconciliation & Explicit Address Reuse

Contract: `fieldflow-endpoint-reconciliation-address-reuse-v1`

`SOURCE_RELEASE` is `2.2.508` and `STABLE_RELEASE` is `2.2.509`.

## Goal

Close the 2.2.508 endpoint-reconciliation regression without introducing another endpoint source. Explicit start/end entered in Campaign PDV, Route or Planear remain authoritative for that route/day. Campaign/operator route-base profiles in the Intelligence Center are optional fallbacks only.

## Runtime contract

- `RouteMetricsService::repairPlannedRouteIntegrity()` has five parameters: project, owner, date from, date to and actor. Date-to can never be consumed as the actor parameter.
- The Intelligence Center endpoint fallback fields use the same Google Places loader already used by Routes and Campaign PDV, and manual typing is geocoded on blur when possible.
- Resolution priority remains explicit route/day, trusted route endpoint, operator profile, campaign default, missing.
- Campaign PDV route generation persists the explicit start/end selected in the planner into the created route.
- Route creation/editing persists explicit start/end directly in route meta.
- The Intelligence Center is not a mandatory duplicate address source.

## Safety

- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No IndexedDB, offline/media, form, Conditional Engine or submission-pipeline change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
