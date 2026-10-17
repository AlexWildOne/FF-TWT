# FieldFlow 2.2.628 | 9D3 Catalog Category Picker

**Contract:** `fieldflow-catalog-category-picker-9d3-v1`  
**Continuity:** `2.2.627 -> 2.2.628`  
**Baseline:** 2.2.627 certified Stable; 2.2.628 is a candidate until installed and certified.

## Scope

- Catálogo visual / editar produto / novo produto: the free-text Category field becomes a dropdown populated from the selected client's catalog, Cardex and previously confirmed custom categories.
- The option `+ Adicionar nova categoria…` exposes a new-name field only while selected. Saving the product also persists its category in a per-client WordPress option, so it remains available after product retirement.
- Existing free-text categories remain editable. Exact values are preserved and shown even when an existing product is inactive or its label only exists on a legacy product.
- Server validates category choice and client ownership; a forged category from another client is rejected. New labels are cleaned, have an 80-character limit, and are de-duplicated by normalised identity, respecting established client spelling.
- The category mapping introduced in 9D2 reads the same client-scoped registry. No cross-client sharing and no retroactive rewriting of historic briefing decisions.
- No AI usage, quota adjustments, Azure requests, database schema migrations, or changes to camera/front counts.

## Tests

- `tools/test-catalog-category-picker-9d3.php` checks existing values, new values, case-insensitive reuse, client isolation, invalid values and persistent registry under client options.
- `tools/test-catalog-category-gate-9d3.php` executes the new Release Gate.
- Predecessor and successor ReleasePackageValidator must approve the final ZIP; Azure Publisher must accept three-part version and all manifest hashes.

## Publishing

Release Manager > Validar > Publicar > updater privado > Revalidar cadeia 6B > Testar fecho Fase 6 > Certificar Stable.
