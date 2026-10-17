# Phase 9DP | Safe Cardex Clone & Publication

Contract: `fieldflow-safe-cardex-clone-publication-9dp-v1`
Release: 2.2.650 candidate, directly after the 2.2.649 Stable confirmed by the operator.

## Root causes verified in 2.2.649

- Admin editing reused an existing cardex line by product name when the incoming reference was different. Distinct retail SKUs could overwrite each other.
- CSV importing found an existing cardex only by display name, client and project, without verifying insignia; different retailer assortments sharing a label could be overwritten.
- Any editor save set `status=active`; draft cloning was impossible without an implicit live publication.
- Catalog synchronization considered all cardex statuses, allowing unpublished items to contaminate the visual client catalog.
- Weekly briefing sync selected an active cardex of the same retailer family; a published clone must not be treated as a briefing-managed cardex.

## Changes

- `CardexCloneService::duplicate`: transaction, active base product rows only, no links or historical item/store metadata, source audit info in a new `meta_json`, initial status `draft` and manual/closed scope.
- `CardexCloneService::publish`: accepts only a draft, with explicit checkbox and manual selection or independently validated global scope; rejects mismatched brand, CNT/MDL/CBD cross subtype, client/project conflict, empty/duplicated SKUs, or overlapping active cards and duplicate global cardex.
- Form fixed-cardex and auto resolution refuse unpublished or unapproved manual clones, and verify store identity for published clones.
- New cardex are drafts. Saving any draft keeps draft; edits and linked store changes for a clone are checked transactionally, without changing origin or other store overrides.
- CSV import distinguishes cardex by client, project, insignia, name and active status. Matching by name when there is already a distinct nonempty SKU is removed. Explicit location_id and inferred CSV associations require strict commercial identity and scoped membership.
- Visual Catalog sync only reads active cardex; weekly briefing sync skips clones.
- WordPress nonce and `routespro_manage` authorization required; no destructive migration, no changes to existing cardex/overrides.

## QA conditions

1. Run `php tools/test-safe-cardex-clone-publication-9dp.php` (synthetic DB clone/copy/store-scope/global/rollback/fixed-cardex tests).
2. Run `php tools/test-cardex-csv-identity-9dp.php` (same label across brands and same name with distinct SKU).
3. Run `php tools/test-release-gate-runtime-9dp.php` and the existing 9DN, 9DO tests.
4. Verify the real 2.2.649 Package Validator on the candidate archive plus own 2.2.650 validator; verify manifest SHA and bundle continuity.
5. Publish and certify via Release Manager; verify a real Android operative never receives a draft and that an approved clone is correctly scoped.

## Limits

- This is not a repair of historical mixed cardex or a reconciliation of missing products from source Excel. It prevents known routes for new mixing; affected historical rows require a store-by-store audit before any correction.
- The global cardex option requires project-eligible stores and a confirmation of the scope; cloned draft cannot silently become a global assortment.
