# FieldFlow 2.2.626 | 9D1 Vision Intelligence II

**Contract:** `fieldflow-fronts-vision-intelligence-9d1-v1`  
**Continuity:** `2.2.625 -> 2.2.626`  
**Baseline:** 2.2.625 installed and certified Stable; 2.2.626 remains candidate until separately certified.

## Scope

- Counts **front-row visible physical products** from the existing blind Azure multimodal reading: no candidate product list exposed to AI, sem chamadas adicionais à IA, one provider request per capture.
- Each recognized box counts at most one physical product. Only extremely similar boxes attributed to the same product collapse. Ambiguous overlapping boxes and two different SKU claims for a single location trigger manual review rather than invent counts.
- Labels, brands, format, and variants remain validated with the existing local Cardex identities. Jameson Original is not counted as Black Barrel without evidence. Unknown competitor SKUs remain unidentified.
- Confirmation remains obligatory. User may correct a count or select manual counting; nothing is submitted automatically. Uncertain or contested values remain blank until reviewed.
- The front review can display **previous manually confirmed reading** linked to a **previous closed visit**, only when tenant, project, store, form, question key and question type match exactly, and prior date precedes today. The historic value is a read-only context and does not prepopulate the new visit. Ambiguous product identities fail closed.
- Admin history preserves the read-only comparison snapshot, with previous fronts and final manually confirmed delta when available. Does not overwrite current or past records.
- Preserves 9CS budget quota and fail-closed persistence, 9CY blind model SKU recognition, 9CZ metering and 9D0 FinOps reconciliation.

## Security and cost

No database schema/migration. No new permissions or external endpoints. No photo retained as evidence after processing. Unknown rate/token status does not bypass pre-call budget controls. Sem chamadas adicionais à IA; no extra Azure request and no background reprocessing of historic visits.

## Verification

- `php tools/test-fronts-vision-intelligence-9d1.php`
- `php tools/test-fronts-history-scope-9d1.php`
- `node tools/test-fronts-vision-intelligence-9d1.mjs`
- `npm --prefix frontend run verify` (TypeScript, compiled bundles, frontend regression)
- Installed predecessor ZIP validator 2.2.625 and candidate 2.2.626 release validators, plus 9D1 Release Gate and Azure publisher package/version validator.
- Installation via Release Manager -> Validar -> Publicar -> updater privado -> cadeia 6B -> teste Fase 6 -> Certificar Stable. No claim of remote certification before user confirmation.

## Deliberate limitations

The AI can still misread an occluded label or product arrangement; this is an assistive suggestion, not a stock audit without human confirmation. The last closed visit comparison is absent when its exact scope cannot be proven. Historical readings without linked, confirmed data do not produce a comparison. Costs on Azure Cost Management may lag the actual processing window.
