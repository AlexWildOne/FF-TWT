# FieldFlow 2.2.625, 9D0B Azure private publisher compatibility recovery

## Proven root causes

1. The 2.2.624 runtime was installed but not certified: the 9CS Release Gate required an obsolete `if (!update_option($key, $new, false))` marker after the 9D0 change to locked `persist($key, $row)`.
2. An attempted 2.2.624.1 safety hotfix (not published) passed the local ZIP validators, but Azure Release Publisher rejected the four-numeric-segment version. Its validateVersion() contract accepts three numeric segments only.
3. The banner "last Release Gate blocked" reflects the prior failed local certification attempt. It does not mean that Azure credentials are invalid, and it does not itself veto upload.

## Recovery semantics

- Version 2.2.625, standard three-segment publisher-compatible version; this is exclusively a certification repair, not a functional 2.2.625 feature release.
- SOURCE_RELEASE is 2.2.624 (installed predecessor required by the already-deployed package validator). 2.2.624 was NOT certified; last certified Stable remains 2.2.623 until the 2.2.625 server certification succeeds.
- The corrected active 9CS gate validates the current row persistence and MySQL advisory lock. FinOps 9D0 reconciliation and all other modules unchanged.
- ReleasePackageValidator and ReleasePublisherClient now check the actual Azure publisher version syntax before paid/remote network operations. New regression test executes the actual remote publisher validateVersion and validateChannel functions without accessing Azure.
- SHA-256 manifest, versioned bundles and offline runtime are aligned. No database schema change or paid AI calls.

## Runbook

1. Back up deployment and confirm current installed 2.2.624 (uncertified).
2. Release Manager: Validate 2.2.625 ZIP, Publish to Stable, install through private updater.
3. Revalidate 6B, test Phase 6 closure, certify 2.2.625 Stable.
4. If already-published immutable blob for 2.2.625 has a different SHA, STOP; never overwrite it or change channels to evade immutability.
5. Verify form flow, AI budget reconciliation (no paid test needed), reporting and Azure SQL Standard S0 health.

Do not retroactively mark 2.2.624 or the unpublished 2.2.624.1 as Stable. 2.2.623 is the certified reference until the recovery completes.
