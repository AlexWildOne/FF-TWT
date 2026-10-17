# FieldFlow 2.2.529 AI Governance & Modernization Consolidation

Contract: `fieldflow-ai-governance-modernization-wave-v1`

`SOURCE_RELEASE` is `2.2.528` and `STABLE_RELEASE` is `2.2.529`.

## Scope

### AI Governance & FinOps 2.0

- Promotes the central AI governance contract to `fieldflow-ai-governance-finops-v2`.
- Extends the AI usage ledger with additive native attribution columns: `client_id`, `project_id`, `feature_key` and `request_kind`.
- Adds indexed current-month aggregation by client, project, feature, provider, model and request kind.
- Keeps prompt and commercial response content out of the usage ledger. Existing prompt hash/version and risk metadata remain the audit boundary.
- Preserves the global monthly AI fuse and adds optional default monthly caps per client and per project.
- Emits de-duplicated monthly budget threshold events at 70%, 85% and 100% through `fieldflow_ai_budget_threshold` and technical logging.
- In `enforce` mode, the global/client/project budget at 100% fails closed before a new provider request. Shadow mode observes and reports without blocking.
- Connection tests are explicitly attributed as `admin_test`, and operational callers provide feature/request-kind context where available.
- The AI Control Center gains a FinOps tab with monthly budget state and aggregated cost attribution.

### React / TypeScript operational consolidation

- Adds `fieldflow-react-operational-modules-v2` on top of the certified v1 module island.
- The v2 layer remains presentation-only and does not take ownership of REST writes, routing, forms, submissions, media or offline state.
- Adds explicit `fieldflow:operational-module-refresh` lifecycle support while retaining the existing scoped MutationObserver fallback.
- Adds deterministic ownership/contract markers to operational module roots and an accessible polite status region.
- Applies to the already-certified hybrid module set: Briefings, Implementations, Stocks, Shopping, Planner, Expenses and Messages.

### WebView fallback completion

- Removes the remaining wildcard parent `postMessage` fallbacks from FormRenderer, Expenses and the shared UI Runtime.
- Same-origin browser fallback is used while the certified FlutterFlow native `fieldflowNativeEvent` bridge remains unchanged.

## Schema

- Additive migration `2.2.529` extends only `routespro_ai_usage_logs`.
- `LATEST_SCHEMA_VERSION` is `2.2.529`.
- Schema Manifest is `1.11.0`.
- Existing AI usage rows remain valid and attribution columns default safely to zero/runtime.
- No destructive migration, table rename or data rewrite is performed.

## Safety boundaries

- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No Routing, Mobility, P1-P4, route optimizer or route endpoint behavior change.
- No ConditionalFlow, FormRenderer submission semantics or order-suggestion mail eligibility change.
- No Mail Outbox retry-policy or transport behavior change.
- No IndexedDB, offline queue, draft persistence, media recovery or resumable-upload protocol change.
- Operational React v2 is progressive presentation enhancement with the certified PHP/legacy/typed runtimes kept as canonical business owners.

## Release continuity

- Release Gate version is `1.78.0` and Phase 6 Stable authority is `1.0.86`.
- The package contract treats `2.2.529` as a minimum baseline, not as an exact-version pin.
- The 2.2.529 validator must accept a compatible `2.2.530` successor without a bridge or direct WordPress installation.
- Installation, publication and promotion remain exclusively through Field Flow Release Manager and the private updater.
