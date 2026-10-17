# Phase 8G, React Form Choice & Special Wave

Release: **2.2.492**

## Contracts

- `fieldflow-react-choice-field-control-v1`
- `fieldflow-react-special-field-assist-v1`
- inherited `fieldflow-react-front-foundation-v1`

## Choice controls

Radio, checkbox, multiple-choice and visual-choice questions gain visible React controls. The original FieldFlow inputs remain in the DOM as the canonical value/validation source. React writes to those inputs and emits the same `input` and `change` events already consumed by `fieldflow-typed-form-state-conditional-core-v1`, so conditional branches, evidence-flow rules and required semantics keep their certified owner.

## Advanced fields

Frentes/Product Matrix, Share Space, Sugestão de encomenda and evidence/media gain React status/progress surfaces. React may expose category open/close actions by invoking the existing category controls, but it does not calculate or persist the business values.

**ProductCardex remains canonical** for Cardex resolution, Frentes rows and Sugestão de encomenda rows. `fieldflow-order-suggestion-mail-eligibility-v2` remains unchanged and continues to fail closed whenever the controlling conditional answer makes the suggestion inactive.

## Media boundary

Media React in this phase is observational/status-only. Capture, multiple-photo recovery, draft persistence, zero-byte repair and resumable transport stay owned by the certified media/offline runtime.

## Safety and compatibility

- PHP/native form fallback remains present.
- No database migration.
- **No IndexedDB schema change.**
- No REST contract change.
- **No FlutterFlow change.**
- No FormData/name changes.
- No change to submission persistence or post-COMMIT side effects.
- React and ReactDOM are not bundled; WordPress `wp-element` remains the runtime.
