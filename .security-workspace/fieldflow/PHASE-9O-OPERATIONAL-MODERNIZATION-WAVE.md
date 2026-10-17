# Phase 9O, Operational Modernization Wave

Contract: `fieldflow-operational-modernization-wave-v1`

## Release continuity

`SOURCE_RELEASE` is `2.2.530` and `STABLE_RELEASE` is `2.2.531`.

This release is successor-safe. The operational modernization contract applies to release `2.2.531` and later successors while the contract remains present and valid.

## Scope

This wave consolidates the presentation lifecycle of four priority operational modules:

- Briefings
- Stocks
- Shopping
- Messages

React/TypeScript owns the visible control shell and refresh lifecycle for these modules. Certified PHP/domain renderers and existing service endpoints remain canonical for business content and side effects during this transition.

## Briefings

- React mount parity is required in Team and Client modes.
- Device cache is scoped by user, mode, client and project.
- Cache payloads use a versioned envelope and a 15 minute TTL.
- React state refresh is event-driven through `fieldflow:operational-module-refresh`.

## Stocks

- React mount parity is required in Team and Client modes.
- Team and Client control state use explicit refresh events instead of a React MutationObserver.
- Client batch view counts are aggregated in one grouped query, removing the per-batch N+1 read.

## Shopping

- Draft keys are scoped by user, assignment and location.
- Existing legacy draft keys are migrated only after the current assignment/location has been authorised by the existing backend flow.
- Duplicate panel-open listeners are removed.
- Project merchandiser membership is cached per project during assignment listing.
- Submission, email and Mail Outbox ownership remain unchanged.

## Messages

- React owns the visible toolbar shell and event lifecycle.
- The certified AJAX actions remain the transport in this wave.
- Message body rendering strips active/embed/form content, event-handler attributes and unsafe link protocols before insertion into the detail view.
- Existing mail dispatch and reply side effects remain unchanged.

## Layout

Stocks and Shopping are included in the same full-width and UI-composition contracts already used by the modernised operational panels.

## Protected contracts

- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No IndexedDB schema change.
- No P1-P4 routing or optimisation-rule change.
- No Mail Outbox schema or dispatch ownership change.
- No media transport ownership change.
