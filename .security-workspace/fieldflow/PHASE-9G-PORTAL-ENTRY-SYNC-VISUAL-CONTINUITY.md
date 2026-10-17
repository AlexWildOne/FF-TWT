# Phase 9G - Portal Entry Sync Visual Continuity

Release: FieldFlow 2.2.523

Contract: `fieldflow-portal-entry-sync-visual-v1`

`SOURCE_RELEASE` is `2.2.522` and `STABLE_RELEASE` is `2.2.523`.

## Objective

Close two issues found after field validation of 2.2.522 without reopening stable business logic:

1. The historical Front Transition Stability gate still required the literal legacy call `window.requestAnimationFrame(applyInitial)` even though Phase 9F deliberately replaced it with the synchronous `applyInitial();` successor to avoid a one-frame wrong-tab paint.
2. The offline runtime displayed the fixed `A sincronizar` badge on every sync start through `setBanner('syncing',0)`, including portal entry with zero queued items. In a native WebView this transient fixed layer was visible as the residual corner flash.

## Changes

- Historical Front Transition Stability accepts either the original RAF activation or the Phase 9F synchronous first-paint successor.
- The legacy behaviour is not reintroduced.
- Offline queue, IndexedDB, retry, media and submission mechanics are unchanged.
- `syncing` with zero items is visually hidden. Real pending, offline, degraded, failed, conflict, auth and revoked states remain visible.
- The versioned offline runtime moves to 2.2.523 with the same data and transport contracts.
- Release Gate advances to 1.72.0 and Phase 6 Stable authority to 1.0.80.

## Frozen areas

- `SubmissionPipeline::BUILD = 2.2.485`.
- No P1-P4 routing or optimisation-rule change.
- No routing service change.
- No FormRenderer or canonical submission change.
- No IndexedDB schema change.
- No database schema change.
- No Mail, AI, Azure or licensing behaviour change.

## Acceptance

- Front Transition Stability passes with the synchronous 9F successor.
- Zero-item sync does not render the fixed `A sincronizar` badge.
- Real actionable offline/sync states remain visible.
- Old and new ReleasePackageValidator accept the package.
- All historical verifiers remain green.
