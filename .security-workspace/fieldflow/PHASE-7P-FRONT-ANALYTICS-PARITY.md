# FieldFlow Phase 7P, Front Analytics Parity

Release: 2.2.448
Source release: 2.2.447

## Objective

Deliver the same Analytics 2.0 intelligence contract to both front dashboards while adapting the presentation to each audience. The Client Portal remains executive and portfolio-oriented; the Merchandiser dashboard becomes operational and personal.

## Client Portal

The Client Portal keeps Predictive Intelligence, Store Intelligence, Contextual Next Best Actions and Perguntar ao FieldFlow and adds an explicit six-score Command View for Operational Health, Execution, Risk, Opportunity, Stock Risk and Confidence. Conversational evidence chips visibly identify Observed, Projected, Learned and Advisory evidence.

## Merchandiser Dashboard

The `fieldflow_dashboard` self mode now exposes the same six Decision Scores, Predictive Intelligence, Store Intelligence, Contextual Next Best Actions and Perguntar ao FieldFlow. The presentation is operational: the first contextual actions are surfaced as `Prioridades de hoje / próximo ciclo`.

The merchandiser audience is server-bound. Requests use `audience=merchandiser` and the REST controller forces `user_id=me`, so the browser cannot widen the operative scope by changing request parameters.

## Progressive enrichment

Base Analytics and AI Intelligence load first. Store Intelligence is then requested through the existing isolated endpoint and merged into the already-rendered front response. Failure of Store Intelligence hides only PDV-level enrichment; it cannot blank the base Analytics experience.

This preserves the critical-path separation introduced in 2.2.439 and the WidgetOrchestrator runtime contract restored in 2.2.440.

## Conversational lifecycle

Perguntar ao FieldFlow on the Merchandiser dashboard uses the same grounded evidence contract as BO and Client Portal. Conversation history remains bounded and browser-local. Changing project, operative scope or period clears the local conversation context. No chat table is created.

## Safety invariants

- Client scope enforcement: ON.
- Merchandiser self-binding in REST: ON.
- Grounded evidence only: ON.
- Observed vs projected distinction: ON.
- Predictive probability claims: OFF.
- Cause inference: OFF.
- Autonomous route/stock/form/score mutation: OFF.
- Store Intelligence in base Analytics critical path: OFF.
- Conversation persistence: OFF.
- Human review for recommendations and Next Best Actions: ON.

## Certification continuity

`SOURCE_RELEASE 2.2.447` -> `STABLE_RELEASE 2.2.448`. The standard Release Manager publication, exact production-manifest verification, private updater and Azure-backed `Revalidar cadeia 6B -> Testar fecho Fase 6 -> Certificar Stable` sequence remain mandatory.

No database migration is required.
