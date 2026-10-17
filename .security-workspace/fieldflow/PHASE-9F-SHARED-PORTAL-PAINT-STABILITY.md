# Phase 9F - Shared Portal Paint Stability

Contract: `fieldflow-shared-portal-paint-stability-v1`

`SOURCE_RELEASE` is `2.2.521` and `STABLE_RELEASE` is `2.2.522`.

## Goal

Remove the residual flash shared by Client and Operative dashboards by making the first browser paint structurally final and by avoiding intermediate tab paints while lazy fragments are still preparing.

## First-paint contract

Both portal roots now receive `ff-scroll-extent-safe`, `ff-responsive-runtime`, `ff-ope` and `ff-paint-stable-v1` from PHP before JavaScript runs. The command deck is server-rendered when the contract is enabled, so Operational Experience no longer inserts a large block after the first paint.

The contract can be disabled without rollback through the `fieldflow_portal_paint_stability_enabled` filter.

## Tab contract

- Operative restores the canonical initial panel synchronously instead of waiting one animation frame.
- Client and Operative can ask the shared UI runtime to prepare an unloaded lazy panel while the current panel remains visible.
- `loadPanelByKey()` returns a promise for this prepare step.
- A lazy fragment is marked paint-ready only after its scripts and UI enhancement have completed.
- Main portal panels and loaded lazy fragments do not use opacity/translate entrance animations under the paint-stability contract.
- Client table/SVG layout repair is scoped to the target panel during a tab switch.

## Frozen areas

- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No routing service change.
- No FormRenderer change.
- No offline/media data-flow change. The offline runtime receives release identity only.
- No Mail Center, AI, Azure, licensing or billing behaviour change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Release continuity

The package must pass the previous 2.2.521 package validator and the 2.2.522 package validator. Phase 9E remains valid and is extended, not replaced.
