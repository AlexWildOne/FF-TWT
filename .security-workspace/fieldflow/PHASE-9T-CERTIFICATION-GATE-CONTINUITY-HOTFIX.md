# Phase 9T — Certification Gate Continuity Hotfix

Contract: `fieldflow-certification-gate-continuity-9t-v1`

This certification-only hotfix is the direct successor to the installed 2.2.535 emergency terrain release. `SOURCE_RELEASE` is `2.2.535` and `STABLE_RELEASE` is `2.2.536`.

## Scope

- Security & Storage Hardening no longer pins `WebViewBridge::NATIVE_SHELL_VERSION` to the historical literal `1.9`; it requires a semantic minimum of `>= 1.9`, allowing the certified 1.10 shell and later compatible successors.
- Field Session & Form Continuity checks the actual escaped Release Manager label `Field Session &amp; Form Continuity` instead of an impossible raw ampersand marker inside HTML source.
- Adds an explicit successor-safe 9T contract so future releases must preserve both corrections.

## Frozen contracts

- The 2.2.535 field session, refresh replay, app resume and order-suggestion behavior is unchanged.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No Routing/P1-P4, database schema, Cardex, Mail Outbox, Analytics or form business-rule change.

## Certification

Validate/publish through Field Flow Release Manager, install through the private updater, Revalidate 6B, Test Phase 6 closure, then Certify Stable. Direct `2.2.535 -> 2.2.536` package validation is mandatory.
