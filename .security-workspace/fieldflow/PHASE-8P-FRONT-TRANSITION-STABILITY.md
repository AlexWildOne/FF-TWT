# Phase 8P, Front Transition Stability

Contract: `fieldflow-front-transition-stability-v1`

This release removes transient front-office flashes without changing any operational data owner.

## Behaviour

- React fallbacks for the operational shell, Minha Rota toolbar and form-wizard progress start in a pending/invisible state.
- The shared UI runtime restores a fallback only when its React island does not mount within the failover window.
- Lazy-panel skeleton and loading text stay hidden for the first 180 ms, so fast responses swap directly to real content.
- `fieldflow:lazy-panel-loaded` is emitted once from the placeholder and reaches document listeners through bubbling.
- Initial app-panel activation is idempotent and does not smooth-scroll on first paint.

`SOURCE_RELEASE` is `2.2.500` and `STABLE_RELEASE` is `2.2.501`.

## Preserved ownership

- No IndexedDB schema change.
- No offline queue or recovery protocol change.
- No resumable media protocol change.
- No ProductCardex change.
- No submission validation change.
- No SubmissionApplicationService change.
- No SubmissionSideEffectDispatcher change.
- No P1-P4 routing or optimisation-rule change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
