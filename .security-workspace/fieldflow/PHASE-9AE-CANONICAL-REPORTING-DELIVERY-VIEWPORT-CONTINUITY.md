# Phase 9AE - Canonical Operational Reporting, Delivery Reliability & Viewport Continuity

Contract: `fieldflow-canonical-reporting-delivery-viewport-continuity-v1`

This release promotes one canonical operational/commercial snapshot as the source of truth for Analytics, email, Excel, PDF and CSV. It also makes report delivery recoverable and keeps the field form visually anchored across store changes, media handoff, Cardex mutations and app resume.

## Release continuity

- `SOURCE_RELEASE` is `2.2.546` and `STABLE_RELEASE` is `2.2.547`.
- No database schema migration.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- `NATIVE_SHELL_VERSION` remains `1.10`.

## Canonical report model

`CanonicalOperationalReportService` emits `fieldflow-canonical-operational-commercial-report-v1` with the following first-class domains:

1. Routing and execution, including route path, ordered stops, actual execution and operational closure.
2. Fronts, including before, after, net gain and growth.
3. Extra-space wins.
4. Evidence and images.
5. Order suggestions.
6. Briefing questions and coverage.

The same canonical snapshot feeds Analytics, email, Excel, PDF and CSV. Execution counts only completed visits; justified non-visits contribute to operational closure but never inflate execution.

## Delivery reliability

- Reporting automation hooks use the durable FieldFlow queue with `force_durable` when recovery is required.
- Daily Close has retry plus reconciliation for terminal routes that missed their original dispatch window.
- Empty legacy format settings default to XLSX + PDF.
- Requested report formats are fail-closed: if a required attachment is missing, the email is not sent as if complete.

## Viewport continuity v2

- `fieldflow-form-viewport-continuity-v2` resolves the actual scroll container instead of assuming `window`.
- The current form remains in the DOM while the next store form is fetched.
- Replacement is atomic after the next form is available, avoiding temporary page-height collapse.
- Scroll anchoring is disabled during controlled swaps and restored afterwards.
- Form-level checkpoints continue across media, Cardex and resume actions.

## Certification

The 9AE gate verifies the canonical service, shared export source, durable delivery recovery, strict attachment contract, real scroll-container continuity, atomic store swap, historical 9AD compatibility and protected runtime identities.
