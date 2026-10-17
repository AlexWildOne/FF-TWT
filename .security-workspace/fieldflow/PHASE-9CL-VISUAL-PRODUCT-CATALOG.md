# Phase 9CL - Visual Product Catalog & Briefing Label Mapping

Contract: `fieldflow-visual-product-catalog-v1`

`SOURCE_RELEASE` is `2.2.608` and `STABLE_RELEASE` is `2.2.609`.

## Purpose

First step (E1) of camera-assisted fronts reading. Fronts questions need a stable product identity with reference images, for own brands and for competitors named in briefings. Cardex items cannot carry that identity: a Cardex import with `Substituir` deletes and recreates the items, so anything attached to a Cardex item ID is lost at the next import.

9CL adds a visual product catalog per client and maps briefing Share Space labels to catalog products. Forms are not changed in this phase; 9CM uses the catalog in fronts questions.

## Back office

The `Cardex Produtos` menu becomes `Cardex e Catálogo` (same page slug, `routespro-product-cardex`) with three tabs:

| Tab | Content |
|---|---|
| Cardex | Unchanged Cardex editor and CSV import |
| Catálogo visual | Products de-duplicated per client, filters by client, campaign, type, images and name, reference images, competitor products, merge |
| Mapeamento do briefing | Share Space labels (`pr_label` / `competitor_label`) linked to catalog products |

### Catalog identity (`fieldflow-visual-product-catalog-v1`)

- One product per client identity, independent of stores and of Cardex item IDs. The identity is the normalized reference; the normalized name plus capacity is always kept as a second key. The capacity written in the name (`70CL`, `70 cl`, `0,7 L`, `700ml`) is understood, so the same product written in different Cardex formats becomes one record.
- `Sincronizar com cardex` reads every active item of every Cardex of the client (directly or through its campaign). Cardex without client and campaign are reported and left out.
- The catalog refreshes in background after every Cardex save, CSV import or briefing Cardex sync (`fieldflow_mapping_source_changed`).
- Synchronisation never overwrites manual work: name, brand, type, images and other names stay as edited; only empty reference, category and capacity are completed, and Cardex and campaign links are added.
- EAN is completed from Stocks when the reference matches a stock article.
- Each product keeps up to 12 reference images from the WordPress media library. The first image is the main one shown in fronts questions. Images are validated as image attachments.
- Competitor products are created by hand or from a briefing label. Duplicates are merged: images, other names, briefing labels, campaigns and identity keys move to the surviving product.
- Products that come from a Cardex are deactivated or merged instead of deleted, because the next synchronisation would recreate them.

### Briefing label mapping (`fieldflow-briefing-product-label-mapping-v1`)

- Labels come from the Share Space sections of briefings and from the generated `share_space_matrix` context questions of the client.
- A label maps to one or several catalog products (for example a whole competitor family). The mapping is stored on the catalog product, keyed by the normalized label, so it applies to the following weeks' briefings without new work.
- Unmapped labels are listed first, with suggestions by name, brand and other names, and `Criar produto de concorrência` (or `Criar produto da marca` for own-side labels).
- The Mapping Center keeps its Cardex product mapping. Briefing labels are mapped in the catalog because Cardex item IDs are not stable across imports.

## Storage

- Catalog products are a private, non-public WordPress record type (`ff_catalog_product`: not public, no admin UI of its own, not in REST, not queryable), the low-volume pattern certified in 9CH. Identity keys, campaigns and labels are repeatable post meta, so lookups use the standard meta index.
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.

## Security

All back-office actions require `routespro_manage` and a nonce. Image upload uses the WordPress media library and the `upload_files` capability.

## Non-regression

- Cardex editor, CSV import, store assortment, live Cardex per store, briefing Cardex sync and every form question are unchanged.
- 9CI visit closure, 9CJ security hygiene and 9CK Google cost authority are preserved.
- No FlutterFlow source modification is required. Web, app/WebView and desktop share the same back office.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
