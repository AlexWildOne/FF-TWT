# Phase 9P, Frontend & Runtime Certification Continuity

Contract: `fieldflow-frontend-runtime-certification-continuity-v1`

## Release continuity

`SOURCE_RELEASE` is `2.2.531` and `STABLE_RELEASE` is `2.2.532`.

This release is successor-safe. The certification-continuity contract applies to release `2.2.532` and later successors while the contract remains present and valid.

## Scope

This hotfix fixes two certification-only identity mismatches discovered after 2.2.531 was installed successfully in production-like front validation:

- Frontend Build & TypeScript Foundation no longer requires the historical literal `"version": "2.2.524"` inside `frontend/package.json`; it validates the package version against the current `FIELDFLOW_VERSION`.
- `assets/fieldflow-offline-runtime.js` is required to be byte-identical to the current versioned offline runtime and to declare the same `RUNTIME_BUILD` as the release.

## Release Manager prevention

`ReleasePackageValidator` now rejects a package before publication when:

- `frontend/package.json` does not declare the same version as the plugin package;
- the canonical offline runtime does not declare the current release identity; or
- the canonical offline runtime is not byte-identical to the current versioned offline runtime.

## Protected contracts

- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No database schema change.
- No IndexedDB schema change.
- No P1-P4 routing or optimisation-rule change.
- No form business-rule change.
- No Mail Outbox schema or dispatch ownership change.
- No operational UI behavior change from 2.2.531.
