# FieldFlow 2.2.527 Runtime & Database Performance

Contract: `fieldflow-runtime-database-performance-v1`

`SOURCE_RELEASE` is `2.2.526` and `STABLE_RELEASE` is `2.2.527`.

## Scope

- Removes confirmed Academy query fanout from `top_performers_v2()` by replacing per-user lesson/certificate reads with grouped batch queries.
- Removes confirmed Academy reset-engine N+1 reads by prefetching progress, certificates and users for the selected course set, with request-local assignment scope caches.
- Keeps Academy output semantics and permissions unchanged.
- Narrows the React bootstrap MutationObserver so generic DOM insertions do not trigger a full React island scan unless an unmounted FieldFlow island was actually added.
- Narrows the shared UI runtime MutationObserver to enhance only affected DOM scopes instead of rescanning the complete document after every insertion.
- Advances Performance & Load Readiness to 1.2.0 with an explicit read-only database footprint captured only during the manual/CLI safe probe.
- The database footprint records aggregate FieldFlow table count, data/index bytes and the largest table suffixes. It does not store SQL text, payloads, user IDs, IP addresses or content.

## Safety boundaries

- No database schema change. `LATEST_SCHEMA_VERSION` remains `2.2.525`.
- No automatic index creation or DDL.
- No Routing, Mobility or P1-P4 logic change.
- No FormRenderer, Conditional Engine or order-suggestion eligibility change.
- No IndexedDB, offline queue, resumable media or recovery change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- The release remains fully installable through Field Flow Release Manager and the package validator keeps successor compatibility for later releases.
