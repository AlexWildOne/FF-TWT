# FieldFlow Phase 7E, Intelligence Learning Foundation

Release: 2.2.436
Source release: 2.2.435

## Objective

Create an auditable operational-learning dataset before enabling any adaptive or autonomous behaviour. FieldFlow records what it recommended, why it recommended it, the human decision and any later observable outcome.

## Learning contract

The 2.2.436 cycle is:

`Operational fact -> deterministic insight -> recommendation -> human feedback -> later observation -> outcome signal`

The following human decisions are supported: `accepted`, `not_relevant`, `deferred` and `resolved`. Optional notes are retained with actor and timestamp.

## Safety and governance

- Self-learning is OFF.
- Autonomous score mutation is OFF.
- Autonomous operational writes are OFF.
- Human feedback does not rewrite historical source data.
- Outcomes are observations, not proof of causality.
- Recommendation rationale and confidence remain visible and auditable.
- Existing form, stock, route and reporting contracts remain unchanged.

## Storage

Migration 2.2.436 creates only:

- `routespro_intelligence_recommendations`
- `routespro_intelligence_feedback_events`

Both are included in Schema Manifest health checks. Existing operational tables are not rewritten.

## User surfaces

Back Office gains `Intelligence Center > Aprendizagem`, with universal scope filters, recommendation status, feedback and outcome coverage. Front Analytics and Client Portal expose recommendations read-only.

Smart Reporting uses the same recommendation source in automated email, route email, Excel, PDF and CSV.

## Azure readiness

Intelligence events use the `fieldflow.intelligence.learning` contract and are Azure-ready, but cloud sync for this domain is a separate opt-in and defaults to OFF. It must remain off until the target receiver contract is validated for the tenant.

## Stable closure

2.2.436 is not closed merely by publication or installation. After private update, run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`. Only the certified exact 2.2.436 runtime is considered complete.
