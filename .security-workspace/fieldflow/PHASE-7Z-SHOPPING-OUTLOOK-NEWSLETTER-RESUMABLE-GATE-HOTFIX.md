# Phase 7Z - Shopping Outlook Newsletter & Resumable Gate Hotfix

Release: 2.2.458
Source release: 2.2.457
Release Gate: 1.12.0

## Objective

Make Shopping client communication readable and reliable in Microsoft Outlook while removing a false-negative Stable certification result in the inherited Resumable Media Transport source-contract check.

## Shopping email

- The outgoing Shopping message now uses a table-first newsletter shell.
- Outlook desktop receives a fixed 640 px conditional table while mobile clients can collapse the metric cells.
- No operational information depends on CSS grid, flexbox, gradients or rounded corners.
- TWT/FieldFlow branding, preheader, Shopping metrics, price rows, observations and footer remain inline-styled.
- Existing batch subject/body customisation and all Shopping tokens remain supported.
- Delivery remains one message per recipient and every attempt is still audited in Mail Center.

## Certification hotfix

The 2.2.454 Resumable Media Transport contract was functionally present, but the gate marker for `hash_file('sha256',` was encoded with literal backslashes inside a PHP double-quoted search string. The Release Gate therefore searched for a token that does not exist in the source file and could incorrectly block Phase 6 Stable.

2.2.458 corrects only the marker representation. It does not remove or relax any Resumable Media requirement. The same runtime, controller routes, contract registry, form integration, device telemetry and SHA-256 reconstruction markers remain mandatory.

## Safety

Production Enabled remains OFF. Full Operational Payload remains OFF. Licensing governance remains SHADOW. Runtime blocking, remote runtime revocation, forced auto-updates and automatic downgrade remain OFF. Canonical private publication, exact production-manifest integrity, package SHA-256 and Azure-backed 6B revalidation remain mandatory.
