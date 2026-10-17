# Phase 7ZX, Typed Specialized Validation Core

FieldFlow 2.2.482 introduced `fieldflow-typed-specialized-validation-core-v1` as the shared contract for structured form fields whose validity cannot be reduced to generic empty/non-empty checks.

Scope:

- required media and zero-byte-safe media presence;
- categorized evidence selection and media-per-category validation;
- Evidence conditional photo/reason requirements;
- required Share Space completeness across every configured comparison;
- required product matrix meaningful-row validation;
- browser, web and mobile parity with TypeScript/PHP fixtures, garantindo explicitamente paridade entre web e mobile;
- Release Manager certification through a dedicated gate.

The existing generic Submission Validation Core remains responsible for active questions, required flags and persistence semantics. Specialized validation is layered on top and keeps legacy browser validation as fallback.

No database migration. No IndexedDB migration. No REST contract migration. No Flutter migration. No React migration.
