# Phase 9AU - Release Manager Publication Receipt Continuity

Contract: `fieldflow-release-manager-publication-receipt-v1`

`SOURCE_RELEASE` is `2.2.564` and `STABLE_RELEASE` is `2.2.565`.

## Problem closed

Phase 6 previously depended on a single local WordPress option, `fieldflow_release_manager_last_state`, to prove that the installed Stable release had been published through the canonical WordPress Release Manager. A stale persistent object cache, multisite option scope mismatch, or option drift could therefore make the Phase 6 matrix forget a publication that had already completed successfully and existed in the private catalog.

## Contract

- The WordPress Release Manager remains the only canonical publication path.
- Publication still requires package validation, canonical `fieldflow/` root, production manifest integrity, private Release Publisher health, remote catalog confirmation and exact package SHA-256.
- A successful WordPress publication writes a durable publication receipt to the local option and, on multisite, a network mirror.
- The existing `release.published` AuditLog event remains a durable secondary receipt created only after the WordPress Release Manager has confirmed the release in the private catalog.
- Phase 6 can recover an exact-version receipt from the audit log when the local option is missing or stale.
- Recovered evidence is accepted only when the receipt SHA-256 is structurally valid and matches the exact Stable release SHA-256 verified by the private 6B.4 catalog.
- The recovered receipt heals the local option for later reads.
- No bypass exists. Private catalog evidence alone is insufficient without WordPress-origin publication evidence.
- No route, form, SubmissionPipeline, media, mail, offline protocol or database schema behavior changes.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Expected operator flow

Release Manager -> Validate -> Publish -> private updater -> revalidate 6B -> test Phase 6 closure -> certify Stable.

If the local publication option is stale after the update, Phase 6 reconstructs the WordPress publication receipt from the audit log and still requires the private catalog SHA-256 to match before certification.
