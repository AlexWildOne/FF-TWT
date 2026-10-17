# Phase 8D — React Front + Form Wizard Progress

Release: **2.2.489**  
Contracts:
- `fieldflow-react-front-foundation-v1`
- `fieldflow-react-form-wizard-progress-v1`

## Goal

Start the FieldFlow operational front migration to React without transferring ownership of the critical form engine. The first front island enhances the form wizard progress surface while PHP, the typed form engine, offline runtime and the established FormRenderer remain canonical.

## Architecture

`FormRenderer` continues to render the full wizard, fields, canonical navigation and PHP fallback progress. When `FIELDFLOW_REACT_FRONT` is enabled, `ReactFrontFoundation` adds the `form-wizard-progress` island. The island reads the canonical DOM state and mirrors the current step/title/percentage. It does not change fields, answers, conditional rules, validation, drafts, media, submission or offline queues.

React comes from WordPress `wp-element`; FieldFlow does not package React or ReactDOM.

## Safety

- PHP fallback remains in the DOM and is hidden only after a successful React mount.
- Kill switch: `FIELDFLOW_REACT_FRONT=false`.
- Submission ownership stays with the legacy + typed FieldFlow runtime.
- Offline ownership stays with the existing offline runtime and IndexedDB contracts.
- No database migration.
- No REST contract change.
- No IndexedDB schema change.
- No FlutterFlow change.
- No FormData name change.

## Certification continuity

The React Devices gate now validates `credentials: 'same-origin'` in the shared React transport source instead of requiring transport implementation details inside the device island. A compatibility identity marker remains in the island bundle so the certified 2.2.488 validator can validate the 2.2.489 N→N+1 package.

## Next front migration waves

1. Front shell, route/day state and loading surfaces.
2. Form wizard/navigation presentation and visit context.
3. Simple question families: text, number, select, radio, checkbox.
4. Conditional branches using the existing typed conditional core.
5. Specialized matrices/Cardex/Share Space.
6. Media/evidence and order suggestion only after offline/recovery parity tests.
