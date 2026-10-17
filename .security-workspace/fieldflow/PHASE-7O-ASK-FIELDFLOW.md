# FieldFlow Phase 7O, Perguntar ao FieldFlow

Release: 2.2.447
Source release: 2.2.446

## Objective

Introduce a grounded conversational analytics layer that lets managers and clients ask natural-language questions about the exact FieldFlow Analytics context already selected, without creating a second source of truth and without giving the conversational layer authority to mutate operations.

## Grounding contract

Every answer is built from a bounded evidence catalogue derived from already-scoped FieldFlow data. Sources can include execution, Decision Scores, deterministic anomalies, stock snapshots, Adaptive Intelligence, Predictive Intelligence, Store Intelligence, Contextual Next Best Actions, recommendation learning and active briefing evidence.

Evidence is explicitly classified as:

- `observed`, facts measured in the selected scope;
- `projected`, deterministic forward signals that are not probabilities or future facts;
- `learned`, historical patterns that do not prove causality;
- `advisory`, recommendations for human review that have not been executed.

The assistant must not invent stores, products, values, causes, visits or sources and may only cite evidence IDs supplied by FieldFlow.

## Scope and audiences

The endpoint inherits the existing client/project scope enforcement from Analytics. The context includes client, project, route, operative, store, period, category and product. Merchandiser audience is server-bound to the authenticated user. Client and manager audiences remain subject to the existing permission model.

## Runtime architecture

`/analytics/ask` reuses the proven Analytics 2.0 stats envelope and Analytics Intelligence response. Store Intelligence is requested only for questions that need PDV/product/risk/action detail. Store enrichment is a second, fail-open path and uses a 120-second transient cache keyed by runtime version, viewer and exact scope. A Store Intelligence failure reduces answer detail but cannot blank Analytics 2.0.

## AI and deterministic fallback

When the configured Analytics AI provider is available, the prompt is governed by the existing AI Manager, entitlement, usage and governance controls. If AI is unavailable, disabled or fails, FieldFlow returns a deterministic smart-rules response from the same evidence catalogue.

## Conversation lifecycle

The UI keeps only a short local conversation history for continuity and sends a bounded tail with the next question. Conversation history is not persisted as operational data and no new database table is introduced. Changing the Client Portal analytics scope resets the local conversation.

## Surfaces

- Analytics 2.0 BO: full Perguntar ao FieldFlow panel with suggestions, answer thread, evidence chips, confidence and follow-up questions.
- Client Portal: scoped conversational panel using the same endpoint and evidence contract.
- REST foundation is audience-aware and can be reused by future mobile/desktop surfaces without changing the grounding contract.

## Safety invariants

- Read-only: ON.
- Grounded evidence only: ON.
- Cause inference: OFF.
- Probability claims from deterministic forecasts: OFF.
- Autonomous operational mutations: OFF.
- Human review for recommendations: ON.
- Cross-client learning/leakage: OFF.
- Conversation persistence: OFF.
- Base Analytics dependence on Ask/Store enrichment: OFF.

## Certification continuity

`SOURCE_RELEASE 2.2.446` -> `STABLE_RELEASE 2.2.447`. The standard Release Manager publication, exact production-manifest verification, private updater and Azure-backed `Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable` gate remain mandatory.

No database migration is required.
