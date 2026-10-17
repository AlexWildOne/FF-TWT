# FieldFlow 2.2.635 | Release Manager Assisted Preflight 9DA

Contract: `fieldflow-release-manager-assisted-preflight-9da-v1`
Continuity: 2.2.634 -> 2.2.635

## Scope

- Existing certified package 2.2.634 remains the recovery baseline.
- Add a read-only assisted panel to the existing WordPress Release Manager.
- Validate complete uploaded package using the existing `ReleasePackageValidator` before any read-only difference report.
- Show ZIP SHA-256, exact successor, status, verified-manifest diff and sensitive file warnings.
- A rejection keeps structured diagnostics on screen and never sends a package to Azure.
- Publication continues to require the original upload gate, publisher health and explicit confirmation in the same request.
- Reusing a validated upload in a later HTTP request is NOT implemented in 9DA. The ZIP must be reselected, and is fully revalidated for publishing.
- The original Publisher, receipt persistence, Private Update Lifecycle, Phase 6 tests and manual Stable certification remain untouched.
- No data schema changes, no AI calls, no operational changes to routes, forms, costs, reports or UI docks.

## Successor policy

- The active `Phase6StableRelease::SOURCE_RELEASE` and `STABLE_RELEASE` are the only identity authorities.
- Historical 9D9 identity strings survive solely as non-executable predecessor comments for the installed 2.2.634 package validator.
- The 9D9 runtime feature contract checks the same dock implementation without pinning old Phase6 identity strings.
- New packages must pass all previous gates. The new 9DA contract adds checks but does not approve a package independently.
- Before shipping, simulate 2.2.636 with the 2.2.635 validator to prove the successor logic does not pin 2.2.635.

## Manual production acceptance

1. Back up WordPress database, options and files. Retain official 2.2.634 ZIP.
2. Upload 2.2.635 through the actual 2.2.634 Release Manager; validate, publish and inspect remote SHA.
3. Install normally through WordPress private update, without forced activation.
4. Open Release Manager: test assisted preflight with a valid ZIP, an invalid ZIP and a skipped-version ZIP.
5. Verify no changes to public portals, apps, operative forms or Client dock.
6. Revalidate 6B, run Phase 6 local probe and certify Stable only if all checks pass.
7. Verify no secrets, manifest data or upload bytes appear in external requests except authorised publisher upload.

## Known limits

The report is read-only and does not cache ZIP uploads or resume remote uploads; those belong to the later recovery phase. Local PHP test environments may lack ZIP or SQLite extensions. Certificate remains pending until WordPress real-world tests succeed.
