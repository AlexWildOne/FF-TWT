# FieldFlow 2.2.530 AI Governance Certification Continuity

Contract: `fieldflow-ai-governance-certification-continuity-v1`

## Release continuity

`SOURCE_RELEASE` is `2.2.529` and `STABLE_RELEASE` is `2.2.530`.

## Purpose

FieldFlow 2.2.529 correctly created and declared the AI usage attribution indexes in `Activator`, `Migrations` and `SchemaManifest`, but the runtime Release Gate also required the literal index names inside `AIUsageRepository.php`. That requirement mixed repository behavior with schema ownership and caused a false certification failure after a valid installation.

2.2.530 keeps the AI Governance 2.0 runtime and schema unchanged and fixes only the certification ownership boundary.

## Guarantees

- `AIUsageRepository` is validated for attribution columns and scoped query behavior.
- AI usage indexes are validated in `Migrations` and `SchemaManifest`, where schema ownership belongs.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- No new DDL or data migration is introduced.
- AI Governance 2.0, budgets, alerts and FinOps remain unchanged.
- Routing, P1-P4, ConditionalFlow, Mail Outbox and offline/media semantics remain unchanged.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- Package validation is successor-safe for releases `>= 2.2.530`.
