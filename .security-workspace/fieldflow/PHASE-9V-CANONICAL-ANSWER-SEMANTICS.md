# Phase 9V - Canonical Answer Semantics

Contract: `fieldflow-canonical-answer-semantics-v1`

This release is the direct successor to the certified 2.2.537 baseline. `SOURCE_RELEASE` is `2.2.537` and `STABLE_RELEASE` is `2.2.538`.

## Scope

- Web and Mobile submissions use the same server-side answer normalization authority before required checks and persistence.
- The TypeScript frontend mirrors the same contract so online and offline/preflight state use the same semantic rules before submission.
- `number`, `currency` and `percent` accept finite numeric values, support comma decimal input, enforce schema `min` and `max`, and reject malformed values instead of silently coercing them to zero or null.
- `select`, `radio`, `visual_choice`, `multi_select` and `checkbox_group` enforce the current schema option domain. Removed or unknown choices fail closed instead of being silently stored or discarded.
- Multiple-choice answers are de-duplicated while preserving order. Single-choice answers reject multi-value payloads.
- Checkbox values normalize to canonical 1/0 semantics in the submission pipeline.
- Structured matrix answers reject scalar payloads before specialized validation. Existing Product Matrix, Order Suggestion, Share of Space and categorized-media validators retain ownership of their deeper domain rules.
- Historical storage compatibility remains owned by `SubmissionApplicationService`; no historical record rewrite is performed.

## Protected identities

`LATEST_SCHEMA_VERSION` remains `2.2.529`.

`SubmissionPipeline::BUILD` remains `2.2.485`.

No Routing/P1-P4, route anchors, Cardex resolution, Mail Outbox transport, IndexedDB schema or database schema change is introduced.

## Required release flow

Validate and publish through Field Flow Release Manager, install through the private updater, Revalidate 6B, Test Phase 6 closure, execute the answer-semantics functional checks, then Certify Stable. Direct `2.2.537 -> 2.2.538` continuity is mandatory.

## Acceptance scenarios

1. Web and Mobile both normalize `2,5` as numeric `2.5`.
2. Invalid numeric text is rejected and never converted to `0` or silently dropped.
3. Numeric values below `min` or above `max` are rejected server-side.
4. A valid current select/radio option persists identically through Web and Mobile.
5. An option removed from the form schema is rejected rather than stored.
6. Multiple choices are unique and remain in the submitted order.
7. Checkbox false persists as canonical `0`; true persists as canonical `1`.
8. Malformed scalar matrix payloads are rejected before specialized validation.
9. Offline/preflight TypeScript exposes the same normalization contract as the server.
10. Historical persisted answers are not rewritten by this release.
