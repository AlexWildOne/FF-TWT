# FieldFlow 2.2.654 | Cardex Client/Project Scope Truth 9DT

Contract: `fieldflow-cardex-client-project-scope-truth-9dt-v1`
Status: candidate, based on 2.2.653 Stable (user-confirmed), uninstalled and uncertified.

## Evidence

The Cardex editor shows 69 stores linked to the Sonae Cardex, including #314 Continente Modelo Santo Tirso, but the 2.2.653 app says the Cardex has a retailer/client/project mismatch. The current runtime resolver excludes active linked Cardex rows when the route's `client_id` or `project_id` differs from the Cardex's IDs. The BO store list shows "selected" based only on the association table and campaign references, not on the actual route context. A Sonae umbrella label should match the Modelo retailer family, so the actual failure cannot be attributed to the screenshot's store name alone. Live DB values are still required to distinguish project mismatch, client mismatch, wrong-cardex priority, or a different route location ID.

## Changes

- The Cardex editor now shows actual client names and projects/campaigns instead of bare numeric inputs; projects are restricted to the selected client. Legacy 0 scopes remain explicit.
- Read-only "Diagnosticar ligacao ao formulario": choose an explicitly linked PDV and compare recent route client/project IDs with the chosen Cardex. Show whether the Cardex is accepted or which condition fails. No changes occur during diagnosis.
- A separate "Guardar apenas cliente/projeto" action avoids modifying SKUs, store links, overrides, revisions, stock and historical submissions.
- Changing commercial context on an active Cardex requires explicit acknowledgement. Project ownership is checked against the clients/projects tables; clones retain their stronger immutability contract.
- Client-wide scope is deliberate: project 0 means all campaigns of *that client*, only for explicitly linked shops and a verified matching retailer. Never silently relaxes cross-client or cross-retailer rules, nor changes existing settings on install.
- ProductCardex::inspect_location_binding() provides explicit decision code and resolution for BO diagnostics; read-only, no service or data migrations.

## Field acceptance

1. In Cardex Sonae, choose #314 Continente Modelo Santo Tirso under the diagnostic and compare the recent route context with Cardex commercial context. Do NOT change Cardex automatically without reviewing this result.
2. If project differs but the Cardex intentionally applies to all Pernod Ricard projects, select Pernod Ricard and "Todos os projetos do cliente" and save **only** client/project, with active-scope confirmation; this keeps the 69 store links and products intact.
3. Reopen the report for the same visit: Frentes and Shopping suggestion must use the intended Sonae SKU list, not an unrelated retailer. Existing answers must persist.
4. Test Continente Modelo, Bom Dia and Pingo Doce; ensure cross-client and cross-brand links remain blocked. A conflicting cardex with higher priority must be reviewed, not silently rewritten.

Not verified in the actual WordPress production database or Android app. The release does not claim to fix every real mismatch without diagnosing live scope.
