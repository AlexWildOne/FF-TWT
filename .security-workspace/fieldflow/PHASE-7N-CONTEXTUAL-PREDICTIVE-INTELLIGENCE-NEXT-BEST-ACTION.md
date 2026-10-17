# Phase 7N - Contextual Predictive Intelligence + Next Best Action

Release: 2.2.446

## Objective
Move FieldFlow from portfolio-level forward signals to explainable assisted action by store, product and issue type, without introducing autonomous execution or causal claims.

## Contextual action contract
Every Next Best Action contains store scope, optional product, issue type, suggested action, assisted priority, confidence, future risk, forecast direction and a compact evidence trail. Evidence items are explicitly classified as `observed` or `projected`.

## Sources
The engine reuses already calculated Store Intelligence evidence: exact mapped stock when available, Share Space gaps, fronts evolution, execution/pending visits, active recommendations and Predictive Intelligence. It performs no database fan-out itself.

## Stock safety
Store/product stock is only actionable when exact location-level mapping exists. Campaign aggregate stock is never presented as store-specific evidence. The isolated front-end Store Intelligence endpoint keeps stock enrichment deferred when the exact safe context is unavailable.

## Next Best Action
Actions are ranked deterministically using evidence severity, future store priority, future risk and confidence. Ranking is advisory. Base Operational Health, Execution, Risk, Opportunity, Stock Risk and Priority scores are not mutated.

## Surfaces
- Analytics 2.0: Ações prioritárias para o próximo ciclo.
- Client Portal: fail-open Next Best Actions loaded from isolated Store Intelligence after the base intelligence view is usable.
- Mail Center: top contextual actions in Daily Close and Weekly Intelligence.
- Excel: `Contextual Actions` worksheet plus Store Intelligence action columns.
- PDF/CSV: same shared reporting intelligence envelope.
- AI envelope: compact contextual actions with explicit observed/projected separation and no invented product or causal claim.

## Guardrails
- Causality inference OFF.
- Probability claims OFF.
- Autonomous route/stock/form/target/threshold mutation OFF.
- Cross-client leakage OFF.
- Human review remains mandatory.
- Contextual enrichment is fail-open and cannot gate Analytics 2.0.

## Certification
SOURCE_RELEASE 2.2.445. STABLE_RELEASE 2.2.446. Azure Phase 6 certification remains mandatory after private publication and runtime update.
