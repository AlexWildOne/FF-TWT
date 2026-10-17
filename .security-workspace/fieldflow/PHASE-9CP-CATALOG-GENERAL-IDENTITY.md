# Phase 9CP - General Catalog Identity and Field Reference Photos

Contract: `fieldflow-catalog-general-identity-v1` (catalog) and `fieldflow-fronts-field-reference-v1` (camera sheet)

`SOURCE_RELEASE` is `2.2.612` and `STABLE_RELEASE` is `2.2.613`.

## Problem

The operational briefing creates one Cardex per insignia (Continente, Pingo Doce, Auchan, Intermarché, El Corte Inglés) and per campaign. Each chain uses its own article codes and its own way of writing the product name ("WHISKY JAMESON 70CL", "JAMESON IRISH WHISKEY 0,7L"), and some sheets give the capacity in litres. The 9CL identity used the article code as a global key and the full name as written, so the same product became one catalog product per chain (a Pernod catalog with 254 products, many of them repeated 70 cl products), and a code of one chain could attach the product of another chain.

## General identity (one catalog per client, never per insignia)

Every active Cardex line of the client is grouped into one product when the lines share:

- the EAN (leading zeros ignored), the same in every insignia;
- the article code inside the same insignia (`c:<insignia>:<code>`): codes are never compared across chains;
- the word signature of the name with the capacity in cl: word order, category and packaging words (whisky, whiskey, irish, gin, vodka, garrafa, original, london dry, ...), apostrophes, capacity formats (70CL, 70 cl, 0,7L, 700 ml, 1 LT) and age formats (12 ANOS, 12Y, 12 years) are ignored; brands, variants, ages and pack sizes are kept.

Capacities written in litres become cl (1 → 100, 0,7 → 70). Chivas 12 and Chivas 18, Jameson and Jameson Black Barrel, 70 cl and 1 L stay separate products.

## Reorganisation of existing catalogs

After the update the first back-office load schedules one background rebuild (or `Sincronizar com cardex` runs it at once). Existing products join the groups through their EAN, the signature of their name and other names, and the keys of the last sync:

- duplicates of the same group are merged into one product: the one with images first, then manual work, briefing labels and active state; images, other names, briefing labels, campaigns and Cardex links move to the survivor;
- the names used by the other insignias become "other names" of the product (search and camera reading);
- products of the previous identity that no Cardex line claims and that hold no images, briefing labels or edited names are removed (moved to the bin);
- names saved in the back office are kept by the sync and such products are never removed;
- a product never joins two groups that hold different codes of the same insignia (one insignia lists each product once), so a code reused by a chain does not merge two products;
- the sync is idempotent and a merge confirmed in the back office persists in later syncs.

## Back office

- Each product shows the insignias where it is sold (chips) and its EAN; the editor shows the insignias and the origin (`Terreno (câmara)` for products created from the field).
- `Possíveis duplicados`: products with the same capacity, very similar names and never listed together in the same insignia are suggested; nothing is merged without `Juntar`, and `São diferentes` hides a pair for good.
- The sync message reports lines, Cardex, insignias, unique products, merged duplicates and removed products.
- `Leitura por câmara` gets `Fotos de referência no terreno` per client (on by default for pilot clients).

## Field reference photos (`fieldflow-fronts-field-reference-v1`)

- The camera sheet lists the products of the chosen category with their image; products without an image come first with `Fotografar`. Share Space shows both sides.
- The operative photographs the label up close; the photo is downsized on the device and sent to `wp_ajax_fieldflow_fronts_vision_reference`: logged-in users only, nonce `fieldflow_fronts_vision`, route and client/campaign scope, 30 photos per user per 10 minutes, client in the pilot with field references on.
- The Cardex row is resolved through the general identity (code inside its insignia, EAN, name signature); a Share Space side through the briefing label mapping. The photo is accepted only for a product without an image, so back-office images are never replaced from the field. A product that does not exist yet is created once (origin `field`) and a Share Space label is mapped to it.
- The photo becomes a media library image of the product, tagged as field reference (user, store, client, campaign, date) and shown as `Terreno` in the editor; it is used by the reading right away and by the next readings.
- Reading rows resolve the article code inside the insignia of their Cardex (Absolut of Pingo Doce is not Jameson of Continente even with the same code).

## Non-regression

- `resolveProductId`, `syncFromCardex`, `merge`, `mapLabel`, `visualsForRows`, `copyFromClient` and every 9CL/9CM/9CN/9CO contract keep their signatures and behaviour for callers.
- Fronts questions, Share Space, the camera reading flow, confirmation and history are unchanged apart from the product list in the sheet.
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`. After installing, open `Cardex e Catálogo → Catálogo visual` and press `Sincronizar com cardex` (or wait for the background rebuild) and check the product count and the `Possíveis duplicados` list.
