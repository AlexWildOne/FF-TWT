# Phase 7ZU - Typed Draft & Media Core

Release: `2.2.479`
Contract: `fieldflow-typed-draft-media-core-v1`

## Objective

Move draft-state validation, IndexedDB persistence and draft media recovery decisions into strict TypeScript without changing the public form contract, the existing `fieldflow-form-drafts` database, or the certified fallback behaviour.

## Typed scope

- `frontend/src/drafts/draft-core.ts` owns the typed draft contract.
- The IndexedDB database remains `fieldflow-form-drafts` at schema v2.
- Snapshot store remains `snapshots` and media store remains `files`.
- Snapshot validity, schema matching, expected media count and newest-local reconciliation are typed.
- Snapshot and media read/write/delete/cleanup operations are exposed through `FieldFlowFrontend.draftCore.persistence`.
- Media group keys and signatures are typed and deterministic.

## Progressive integration

`src/Forms/FormRenderer.php` resolves `FieldFlowFrontend.draftCore` dynamically. When the typed core is present and its build identity matches the active plugin release, draft snapshot and media persistence use the typed implementation. Any typed failure falls back to the previously certified JavaScript implementation.

This is intentionally not a destructive rewrite. Existing device drafts remain readable because the IndexedDB database name, stores and schema version are unchanged.

## Recovery semantics

- A zero-byte WebView handoff never erases a previous durable media copy.
- LocalStorage remains the synchronous mirror for immediate resume.
- IndexedDB remains the durable device copy.
- Server draft reconciliation still selects the newest local checkpoint by `updatedAt` before comparing with the server revision.
- A schema change restores compatible answers first and only checkpoints the new schema after media rehydration.
- Final form submission still checkpoints optimised media before the offline outbox can merge durable draft media.

## Release Manager

The Release Manager remains mandatory. `typed_draft_media_core_ok` must be true before publication. The validator confirms that the TypeScript core is compiled, `FormRenderer` consumes it, the verifier is present, and the package still has exact runtime/frontend/release identity.

## Compatibility

No REST contract change. No WordPress database migration. No destructive IndexedDB migration. No Flutter change. No React introduction.

No database migration.
