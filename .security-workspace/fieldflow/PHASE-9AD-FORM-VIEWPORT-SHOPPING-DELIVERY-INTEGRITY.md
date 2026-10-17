# Phase 9AD, Form Viewport & Shopping Delivery Integrity

Contract: `fieldflow-form-viewport-shopping-delivery-integrity-v1`

`SOURCE_RELEASE` is `2.2.545` and `STABLE_RELEASE` is `2.2.546`.

## Objective

Keep the merchandiser visually anchored to the same working point while the form changes or the WebView resumes, and guarantee that Shopping email, Excel and PDF are generated from one canonical submission snapshot.

## Form viewport continuity

- Store selection reserves the existing report slot height before re-rendering, preventing transient document collapse from clamping scroll to the top.
- The current question and its viewport offset are checkpointed durably per form context.
- Form resume, photo/media operations, conditional refresh and Cardex add/remove restore the visual anchor after DOM changes.
- User actions remain authoritative. No bare `focus` restore is reintroduced.

## Shopping delivery integrity

- Email body, XLSX and PDF are derived from the same canonical submission snapshot.
- Requested Shopping attachments are mandatory for that dispatch. If a requested XLSX or PDF cannot be generated, the email is not sent incomplete.
- Attachment failure is logged in Mail Center with requested, missing and error metadata.
- XLSX uses a vertical submission summary plus a price table with regular price, promotion, difference, variation and factual reading.
- PDF separates submission summary, verified prices and observations for operational readability.

## Compatibility

- No database migration.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- `NATIVE_SHELL_VERSION` remains `1.10`.
- 9AA, 9AB and 9AC resume/media semantics remain active.
- The installed 2.2.545 ReleasePackageValidator must accept the 2.2.546 successor package before publication.
