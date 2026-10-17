# Phase 9U - Order Source & Cardex Consistency

Contract: `fieldflow-order-source-cardex-consistency-v1`

This restricted field hotfix is the direct successor to the certified 2.2.536 baseline. `SOURCE_RELEASE` is `2.2.536` and `STABLE_RELEASE` is `2.2.537`.

## Scope

- Explicit store-to-Cardex links remain authoritative when route/client/project context is temporarily incomplete. Known conflicting non-zero scope still prevents an unrelated Cardex from winning.
- Automatic Cardex fallback recognises normalized operational aliases including PD/Pingo Doce, CBD/Continente Bom Dia, CNT/Continente and MDL/Modelo without bypassing explicit store scopes.
- `order_suggestion` is paired to the exact Fronts `question_key`; backend extraction no longer searches every Product Matrix indiscriminately.
- Every order row carries `quantity_source=auto|manual`. Once the merchandiser edits a quantity, `manual` is authoritative and server fallback cannot reconstruct the automatic zero-front quantity over it.
- Web renderer, Web submission, Mobile payload and Mobile submission agree that an Order Suggestion shell is not discarded solely because Cardex resolution is temporarily unavailable.
- Order email/export presentation continues to read the persisted submission quantity. No email-time order recalculation is introduced.

## Protected identities

`LATEST_SCHEMA_VERSION` remains `2.2.529`.

`SubmissionPipeline::BUILD` remains `2.2.485`.

No Routing/P1-P4, anchor, route optimisation, Mail Outbox transport, IndexedDB schema or database schema change is introduced.

## Required release flow

Validate and publish through Field Flow Release Manager, install through the private updater, Revalidate 6B, Test Phase 6 closure, execute the field order-suggestion tests, then Certify Stable. Direct `2.2.536 -> 2.2.537` continuity is mandatory.

## Field acceptance scenarios

1. Store explicitly linked to PD Cardex with complete route context resolves PD.
2. The same store with a temporarily missing client or project context still resolves its explicit PD link.
3. A known conflicting non-zero client/project context does not select an unrelated scoped Cardex.
4. Store without explicit scope can resolve PD/Pingo Doce and CBD/Continente Bom Dia through normalized aliases.
5. Fronts at zero produce an automatic order quantity and `quantity_source=auto`.
6. Merchandiser changes the suggested quantity, the row becomes `quantity_source=manual`.
7. Resume, conditional reconciliation and submission preserve the manual quantity.
8. Persisted answer, RecordService presentation, export and order email all expose the same manual quantity.
9. A second Product Matrix in the same form cannot feed the order question paired to another Fronts key.
