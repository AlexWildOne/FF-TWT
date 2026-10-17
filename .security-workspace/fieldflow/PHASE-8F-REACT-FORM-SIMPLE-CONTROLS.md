# Phase 8F, React Form Simple Controls

Release: **2.2.491**  
Contract: `fieldflow-react-simple-field-control-v1`

## Scope

This release advances the progressive React front migration from field-state assistance to visible React controls for the simple form families: `text`, `textarea`, `number`, `currency`, `percent`, `date`, `time` and `select`.

## Canonical ownership

The original FieldFlow input remains mounted in the DOM with its existing `id`, `name`, required semantics, validation listeners and submission ownership. React renders the visible control and mirrors every user edit into the canonical input through the existing `input` and `change` events.

The bridge is bidirectional: changes coming from prefill, recovery, typed form state or the existing runtime refresh the React control. Native invalid events hand focus to the visible React control instead of leaving the user on an off-screen canonical input.

## Safety boundaries

- Conditional logic owner remains `fieldflow-typed-form-state-conditional-core-v1`.
- Submission owner remains the certified typed/legacy submission runtime and `SubmissionApplicationService`.
- Offline drafts, media and recovery remain owned by the current offline runtime and existing IndexedDB schema.
- The canonical native input remains present as the PHP/native fallback and payload source of truth.
- No database migration.
- No REST contract change.
- No IndexedDB schema change.
- No FlutterFlow change.
- No React or ReactDOM package is bundled; WordPress `wp-element` remains the runtime.
