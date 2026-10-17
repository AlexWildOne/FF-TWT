# 2.2.652 | Cardex Form Binding & Retailer Continuity 9DR

Contract: `fieldflow-cardex-form-binding-continuity-9dr-v1`

## Problem confirmed in 2.2.651

`src/Forms/ProductCardex.php` used `CardexCloneService::locationMatchesInsignia()` to validate every Cardex, but the new 9DQ retailer registry correctly rejects legacy generic `Sonae` and stored aliases such as `pingo_doce`. Existing explicit Cardex-to-store links became invisible to automatic `product_matrix` Fronts and consequently to paired `order_suggestion` questions. 9DN historical aliases are still valid for old cardex, distinct from the strict 9DQ new-brand registry.

## Safe remediation

- One shared 9DR matcher for automatic and fixed Form Cardex: uses 9DN `StoreIdentityAuthority::locationMatches()` for recognised retailers and 9DQ's strict validation **only for additional independent retailers**.
- Explicit active Cardex-to-PDV link, correct client/project and retailer evidence remain authoritative. A stale cross-brand or cross-Sonae-subtype link is never accepted merely because it is selected in the Backoffice.
- When several valid linked Cardex exist at the same project/client authority, an exact `CBD`, `MDL` or `CNT` subtype is preferred over generic `Sonae`; other authority priority remains unchanged. Historical `Pingo Doce`/`pingo_doce` links are recognised.
- Fixed-mode questions are validated using the same retailer contract; an incompatible fixed Cardex is not silently replaced. Order suggestion remains paired with the resolved Fronts question where configured.
- Weekly Briefing synchronization only **reuses an already weekly-managed Cardex** for the same retailer key and the same Sonae subtype. It never seizes a manually curated Cardex or a published clone. No data migration or automatic destructive cleanup.
- The legacy store-specific overrides, intentionally deleted items and visit histories are not rewritten; if a store still has an empty historical override, review it manually. No silent re-add of removed SKU.
- No changes to stocks, retailer identity in routes, 9DF operative dock, Daily Close 9DO, Google/Azure, mobile uploads or signatures.

## Operational configuration and acceptance

- A multi-store Form should configure BOTH **Frentes** (`product_matrix`) and **Sugestão de Encomenda** (`order_suggestion`) with source **Cardex automático pela loja**, not a fixed Cardex from another retailer. Existing saved fixed questions are not mutated by this release.
- After certification, validate one real assigned store from each `Continente Bom Dia`, `Continente Modelo` and at least two `Pingo Doce` sites. Check per-store product references and order proposal rows, as well as a negative cross-brand case.
- If a correct Cardex is selected in the Backoffice but the Form still has no items, inspect active Cardex status, explicit PDV link, project/client, active base SKU and historical store overrides. This release does not rewrite production data or guarantee historical completeness.

## Regression and release requirements

- `php tools/test-form-cardex-identity-continuity-9dr.php`
- `php tools/test-weekly-cardex-scope-9dr.php`
- `php tools/test-release-gate-runtime-9dr.php`
- Preserve predecessor gates 9AI, 9DN, 9DO, 9DP and 9DQ; release 2.2.651 -> 2.2.652 N+1; validate final signed/hashed ZIP and Phase 6 Stable on target site.
