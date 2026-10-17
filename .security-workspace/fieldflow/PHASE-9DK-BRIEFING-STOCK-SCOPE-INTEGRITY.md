# 2.2.645 · Briefing, Stock & Route Scope Integrity 9DK

Contract: `fieldflow-weekly-import-store-scope-integrity-9dk-v1`

## Evidence and rules

- `W42 Briefing semanal 2026.xlsx` (13/10/2026 supplied by user): 12 Oct–18 Oct 2026 is the weekly briefing interval. Promotion/space intervals are individual records and may extend outside that week. Narrative retailer headings may be merged across multiple columns.
- Stock example `Stock - PD - 2026_10_12.xlsx`: Sheet1, 8174 SKU×store rows, stock quantity recognized, no auto-mapping to other business fields.
- Stock example `Info_PERNOD_RICARD_20261012.xlsx`: Stock_Loja_Final, 16236 rows, SOH column is stock, PACK is not assumed conversion factor.
- Store is "Na tua rota" only when current route stops for this user and week confirm membership. Campaign/briefing portfolio does not imply a visit.
- Store in recipient's briefing scope but not route is labeled "Só briefing", without hiding the related instruction or mutating assignments.
- Cardex retains literal supplier classification codes. B4 is **not** P4. Unknown family classifications may be selected *only explicitly* with verification warning; no silent tier changes.
- Stock-to-PDV and stock-to-Cardex matching against a live database cannot be certified from supplier Excel files alone. Check unpublished draft batch mapping coverage before publishing and confirm actual store IDs.

## Safety

Read-only visibility for route scope, no database changes, no auto-publishing or auto-updating already published briefings, no new external API calls. Existing releases are immutable. Install and certify using Release Manager N+1.
