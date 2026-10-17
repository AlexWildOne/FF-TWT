# Phase 9CM - Fronts Mobile Layout & Catalog Visuals

Contract: `fieldflow-fronts-mobile-catalog-visual-v1`

`SOURCE_RELEASE` is `2.2.609` and `STABLE_RELEASE` is `2.2.610`.

## Purpose

Second step (E2) of camera-assisted fronts reading. On mobile, each Cardex product of a fronts question (`product_matrix`) was a large card with three editable text fields (category, reference, product), the fronts field and a remove button. A Cardex with dozens of products became a very long page. 9CM makes the fronts questions compact and shows the product image from the 9CL visual catalog, so the operative recognises the product at a glance. Camera reading (E3) builds on this layout.

## Fronts questions (`product_matrix`)

On mobile and tablet (up to 1024 px):

- Each known product is one compact line: catalog image, product name, reference and capacity, and a `−` / `+` stepper with the numeric keypad.
- With store history, the previous value appears as `Antes N` and the stepper fills `Frentes depois`.
- The Cardex fields stay in the form with the same names and values behind `Editar referência`, which also shows `Remover`. Live Cardex per store (store overrides), drafts, validation, the zero-automatic rule, the order suggestion pairing and the 9BD/9BZ history prefill are unchanged.
- Rows without a product name (manual lists, references added in the store) keep the previous layout.
- Category groups, `Abrir todas` / `Fechar todas` and the React special-field assist are unchanged.

On desktop the table keeps its editable Cardex columns; when the question has catalog images, a thumbnail column comes first. The fronts field gets the same stepper.

Tapping an image opens it in full size. Without network the image falls back to the product initials.

## Share Space (`share_space_matrix`)

- Each side (brand and competitor) shows the images of the catalog products mapped to the briefing label in 9CL (up to two, plus `+N` for the rest of the family).
- Both fronts fields get the stepper. On phones the two sides stay side by side, so brand and competitor are compared in one view; below 360 px they stack.
- Input names, the share calculation, the goal status and the React assist are unchanged.

## Catalog resolution

- Images come from the 9CL catalog of the client of the visit (client, then campaign, then the Cardex of the question). Each row resolves through the catalog identity (reference, or name and capacity) with one catalog load per form and one attachment query per question.
- Inactive catalog products are not shown.
- The form builder has `Não mostrar as imagens do catálogo visual nesta pergunta` (`catalog_images_disabled`) for fronts questions. Images are shown by default.

## Non-regression

- Input names, values, attributes (`data-fronts-input`, `data-share-space-pr`, `data-share-space-competitor`, `data-product-row`) and the submission payload are unchanged.
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required. Web, app/WebView and desktop share the same runtime.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
