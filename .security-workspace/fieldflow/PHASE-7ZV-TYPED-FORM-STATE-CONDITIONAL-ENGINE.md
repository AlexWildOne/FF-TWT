# Phase 7ZV, Typed Form State + Conditional Engine

Release: `2.2.480`
Contract: `fieldflow-typed-form-state-conditional-core-v1`

## Objective

Move the browser-side conditional form decision layer into strict TypeScript without changing the current form markup, REST contracts, database schema, Flutter integration, Cardex content or submission format.

## Scope

- Adds `frontend/src/forms/form-core.ts` as the typed browser authority for conditional equality semantics, active/hidden question state, active required keys, active-value filtering and the conditional snapshot used by order-suggestion flows.
- `FormRenderer.php` and Forms Runtime V3 consume the typed engine when available and keep the certified JavaScript implementation as automatic fallback.
- `RoutesPro\\Forms\\ConditionalFlow` remains the server-side canonical contract and advances to contract version 3 with the same `show_target_when_visible_source_value_matches` and OR-combination semantics.
- Shared fixtures certify paridade PHP/TypeScript for direct, chained, OR, checkbox and multi-choice conditions, including the `Não` state that keeps `order_suggestion` inactive.
- Hidden questions remain excluded from active required validation and from active values.
- Order-suggestion conditional snapshots carry the same contract identity server-side and client-side.

## Release Manager

Release Manager must reject the package unless `typed_form_conditional_core_ok` proves that the TypeScript bundle contains the core, both browser runtimes consume it, the PHP contract is version 3, and the standalone parity verifier/fixtures are included.

## Safety

- No database migration.
- No IndexedDB schema migration.
- No REST endpoint change.
- No Flutter change.
- No React introduction.
- Legacy JavaScript conditional evaluation remains available as fallback.
- Existing Mail Eligibility v2 guard remains mandatory and unchanged.

No database migration.
