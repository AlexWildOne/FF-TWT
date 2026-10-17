# Phase 9AI, Mail Intelligence & Daily Commercial Reporting

Contract: `fieldflow-mail-intelligence-daily-commercial-reporting-v1`

`SOURCE_RELEASE` is `2.2.552` and `STABLE_RELEASE` is `2.2.553`.

## Objective

Turn the daily route email from a generic completion notification into a deterministic commercial report while preserving the certified mail outbox, route closure, form, viewport and mobile contracts.

## Canonical source

`CanonicalOperationalReportService` remains the source of operational facts. `DailyCommercialReportService` interprets only those facts and produces dynamic capabilities, visit outcomes, gains, losses, exceptions, opportunities and alerts.

No narrative layer may calculate or invent a KPI. Narrative can only express canonical facts.

## Recipient semantics

Client output prioritizes commercial execution, gains, losses, implementations, order opportunities and relevant exceptions.

Merchandiser output prioritizes personal execution, achievements and actionable alerts.

Sections without relevant data are omitted rather than rendered as zero-value noise.

## Attachments

Excel and PDF are generated from the same canonical snapshot used by the email body. The spreadsheet exposes explicit store-level execution, justification and commercial movements, with merchandiser alerts when applicable.

## Mail reliability

Legacy route fallback sends one transport envelope per recipient. Shopping uses recipient-level idempotency for the original submission. Operational mail history distinguishes immediate `sent`, durable `queued` and `failed` states.

Sent and cancelled outbox rows with no retained attachments are eligible for bounded retention cleanup after 90 days. Failed rows remain available for operational investigation.

## Certification invariants

- Schema remains `2.2.529`.
- SubmissionPipeline remains `2.2.485`.
- Single Anchor Viewport Continuity 9AH remains mandatory.
- No FlutterFlow or WebView navigation changes are part of this release.
- Release flow remains Release Manager, validate, publish, private updater, revalidate 6B, Phase 6 closure test, certify Stable.
