# Phase 8J, React Operational Modules

Release: 2.2.495
Contract: `fieldflow-react-operational-modules-v1`

## Objective

Extend the certified React front foundation into the priority operational modules without replacing their business logic. React owns the visible control surface while the existing PHP and JavaScript controls remain canonical and receive the same native `input`, `change` and `click` events as before.

## Covered modules

- Briefings
- Implementations
- Stocks
- Shopping
- Planear
- Despesas
- Mensagens

The shared island is `operational-module-controls`. It provides progressive search, filters, quick state chips, refresh/apply/back actions and pagination where the underlying module already exposes those behaviours.

## Ownership model

React UI -> native FieldFlow control -> existing module runtime -> existing REST/AJAX/service layer.

The native controls are hidden only while the React island is mounted. Unmounting removes the marker and immediately restores the canonical interface. No business action is reimplemented inside React.

## Guardrails

- WordPress `wp-element` remains the only React runtime.
- No bundled React or ReactDOM dependency.
- No IndexedDB schema change.
- No Service Worker rewrite.
- No offline queue, draft, media recovery or resumable transport rewrite.
- No SubmissionApplicationService or SubmissionSideEffectDispatcher rewrite.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No P1-P4 routing or optimisation-rule change.
- No Shopping mail/newsletter, Excel or PDF flow rewrite.
- No order-suggestion eligibility or conditional-mail contract change.
- No FlutterFlow change.

## Release continuity

`SOURCE_RELEASE` is `2.2.494` and `STABLE_RELEASE` is `2.2.495`.

The 2.2.494 `ReleasePackageValidator` must accept the 2.2.495 package before the package can be published. The 2.2.495 validator then additionally certifies this Phase 8J contract.
