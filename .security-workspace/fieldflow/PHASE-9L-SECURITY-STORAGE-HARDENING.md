# FieldFlow 2.2.528 Security & Storage Hardening

Contract: `fieldflow-security-storage-hardening-v1`

`SOURCE_RELEASE` is `2.2.527` and `STABLE_RELEASE` is `2.2.528`.

## Scope

- Adds `RestScopeGuard` as a conservative, authenticated REST boundary for explicit `client_id` / `project_id` context on FieldFlow namespaces.
- Fails closed when a request names a client or project outside the current user scope, when a project belongs to a different client than the request declares, or when the project client is outside the allowed client scope.
- Keeps existing controller and object permission callbacks authoritative and adds object-level permission callbacks for mobile form-submission reads and briefing view/acknowledge operations.
- Adds negative contract tests for manager scope, valid tenant scope, foreign client, foreign project and client/project mismatch decisions.
- Adds `StorageGovernance` for deterministic daily cleanup of expired private resumable-upload sessions only.
- Adds read-only private-storage diagnostics for resumable sessions, Mail Outbox retry attachments and private form drafts.
- Evidence, published attachments and active drafts are never deletion targets of this cleanup contract.
- Hardens the WebView browser fallback by replacing wildcard parent `postMessage` targets with the current same-origin target.
- Preserves the FlutterFlow native `window.flutter_inappwebview.callHandler('fieldflowNativeEvent', ...)` bridge unchanged.
- Exposes REST scope-guard and private-storage governance state in System Health for operational diagnosis.

## Safety boundaries

- No database schema change. `LATEST_SCHEMA_VERSION` remains `2.2.525`.
- No Routing, Mobility, P1-P4, route optimizer or route endpoint behavior change.
- No FormRenderer, ConditionalFlow, submission semantics or order-suggestion mail eligibility change.
- No Mail Outbox retry-policy or transport behavior change.
- No IndexedDB, offline queue, media recovery or resumable-upload protocol behavior change.
- Private cleanup is bounded to expired directories under `fieldflow-private-resumable` and is path-constrained to that root.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Release continuity

- Release Gate version is `1.77.0` and Phase 6 Stable authority is `1.0.85`.
- The package contract treats `2.2.528` as the minimum security/storage baseline, not as an exact-version pin.
- The 2.2.528 validator therefore remains successor-compatible for 2.2.529 and later releases that preserve this contract.
- Installation and promotion remain exclusively through Field Flow Release Manager and the private updater.
