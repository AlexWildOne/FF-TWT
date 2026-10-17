# Phase 9R, Field Form Terrain Continuity

Contract: `fieldflow-field-form-terrain-continuity-v1`

This restricted terrain hotfix protects active field work while preserving the certified submission, routing and reporting cores.

## Release continuity

- `SOURCE_RELEASE` is `2.2.533` and `STABLE_RELEASE` is `2.2.534`.
- The contract is successor-safe for `2.2.534` and later packages.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Cardex and wizard continuity

- Text/numeric mobile keyboard Enter/Done is prevented from implicitly submitting the whole form while the operator edits a Cardex/form control.
- Enter/Done advances focus only inside the current visible step, or blurs the final editable control.
- Draft protection happens before conditional/layout reconciliation on every input/change.
- Conditional visibility and wizard step visibility are idempotent, avoiding unnecessary hide/show churn when the current step remains valid.
- Conditional rules themselves remain owned by the existing certified ConditionalFlow/typed form-state core.

## Draft and native resume continuity

- The local answer snapshot is checkpointed synchronously on each input/change.
- Server draft delay is reduced to 900 ms.
- `pagehide`, `freeze` and equivalent lifecycle flushes attempt `navigator.sendBeacon()` before falling back to the existing keepalive fetch path.
- A stable local recovery alias is stored by user, form, binding, client, project, store and report day. It deliberately excludes route/stop ids so a native resume or replan can still recover the same operational work.
- The server performs the same conservative route/stop recovery only when exactly one non-expired draft exists for the stable operational scope.
- When recovered, the existing draft row is rebased to the current route/stop instead of creating a competing copy.
- Existing IndexedDB media remains authoritative; when a local alias recovers from a previous primary key, recovered file inputs are checkpointed into the current key.

## Protected cores

No routing/P1-P4 algorithm, database schema, submission business pipeline, Mail Outbox, order-suggestion eligibility, form conditional semantics or reporting calculation is changed by this hotfix.
