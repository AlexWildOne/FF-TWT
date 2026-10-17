# FieldFlow 9CY: Blind Inventory SKU Matching

Contract: `fieldflow-fronts-blind-inventory-9cy-v1`.
Candidate: `2.2.621 -> 2.2.622`, awaiting certification of 2.2.621.

## Incident and diagnosis

The uploaded photo has one green-label Jameson Original and a red-labelled CARINA
bottle to its right. Previous candidate-list prompts allowed Azure to claim two
separate physical boxes, P4 Original and P5 Black Barrel, so spatial collision
guards could not catch the false P5. This is an SKU *identity* error.

## Updated flow

1. A single authorised Azure call gets only the downscaled shelf photo, never
   candidate names, item codes, or catalog reference images. The model transcribes
   observed text and brand per physical bottle into normalized boxes.
2. WordPress locally matches the observed brand and variant tokens to Cardex SKUs;
   candidate/model code hints are ignored. No match means a blank count.
3. A specific variant like Black Barrel can never produce an automatically
   confirmed 1, even if the model fabricates the full variant text. Such results
   need explicit manual entry. Boxes from unverified variants are not overlaid.
4. Ambiguous capacity remains pending. A unique referenced product may be offered
   as a human-reviewed suggestion, never an automatic count.
5. Reflective/background bottles and overly elongated boxes do not count as valid
   positive suggestions. Source readings keep client, visit, operator, and scope.
6. Cost gates, one paid analysis per photo, idempotent replay, security controls,
   draft continuity, form values and deletion confirmations are unchanged.

This is a conservative pilot. Omitting the candidate list may reduce recall for
labels that cannot be read clearly. We prefer manual review over invented SKUs.
It is NOT a proof that the AI cannot misread printed text. A real field retest is
required, preferably using the exact shelf photo and camera on Android.

## Regression and release

Run `php tools/test-fronts-blind-inventory-9cy.php`,
`node tools/test-fronts-blind-inventory-9cy.mjs`, and the complete test suites.
The tests use fabricated provider responses and make no paid Azure requests.
Follow release manager, validate, publish, private updater, chain 6B,
phase-6 close and Stable certification after 2.2.621 has been certified.

## Compatibilidade do Release Manager 2.2.621

O validador da versao 2.2.621 exige encontrar literalmente a antiga
atribuicao `self::prompt($items, $type, count($references), true)` no
ficheiro FrontsVisionService.php como comprovativo textual da fase 9CW.
A partir da 9CY, a leitura ativa usa `self::blindInventoryPrompt()`
para nao enviar a lista de SKUs ao modelo. Mantemos a atribuicao antiga
**somente num comentario inerte**, para que o Release Manager ja instalado
possa validar o sucessor. O validador 2.2.622 e os testes 9CY exigem
a leitura cega efetiva; nao foi restaurada qualquer chamada antiga.

Regressao: validar o mesmo ZIP com `ReleasePackageValidator` tanto da
2.2.621 (predecessora) como da 2.2.622 (candidata), alem dos testes 9CY
e dos limites de uma unica chamada paga e orcamento.
