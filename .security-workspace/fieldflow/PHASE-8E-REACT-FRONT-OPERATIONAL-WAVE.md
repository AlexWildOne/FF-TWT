# Phase 8E, React Front Operational Wave

Release: **2.2.490**  
Contract: `fieldflow-react-front-operational-wave-v1`

## Scope

This release expands the progressive React front foundation into three visible operational surfaces while preserving the certified PHP/TypeScript runtimes as the source of truth.

### Operational shell

`fieldflow-react-operational-shell-v1` adds a bounded React island to the FieldFlow operational shell. It reflects online/offline state, the active panel and quick navigation. Existing `.rp-tabbar` / `data-target` controls remain present and are the canonical navigation fallback.

### Minha Rota

`fieldflow-react-my-route-toolbar-v1` progressively enhances the daily route date and route picker. React writes back to the existing `#rp-date` and `#rp-route-select` controls and dispatches their existing change events. Route REST calls, route rendering, stop actions and route state remain owned by the existing route runtime.

### Simple field assist

`fieldflow-react-simple-field-assist-v1` is enabled for `text`, `textarea`, `number`, `currency`, `percent`, `date`, `time` and `select`. React observes the canonical native control and adds live required/optional/filled state. The original input remains in the DOM with the same `name`, value, required semantics and event listeners.

## Dynamic content

The React front bootstrap now remounts progressive islands after `fieldflow:lazy-panel-loaded`, `fieldflow:form-rendered` and child-list mutations. This allows React enhancements to mount when forms or panels arrive after initial page load.

## Ownership boundaries

- Submission owner: legacy + typed submission runtime.
- Conditional logic owner: `fieldflow-typed-form-state-conditional-core-v1`.
- Offline owner: FieldFlow offline runtime and existing IndexedDB schema.
- Route data owner: current REST route runtime.
- React owns presentation and bounded interaction only.

## Compatibility

- PHP fallback remains present for shell navigation, Minha Rota controls and form fields.
- No database migration.
- No REST contract change.
- No IndexedDB schema change.
- No FlutterFlow change.
- No React or ReactDOM package is bundled; WordPress `wp-element` remains the runtime.
