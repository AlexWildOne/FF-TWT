# 2.2.647 | Closed-world Stock Insignia & PDV Identity 9DM

**Contract:** `fieldflow-stock-insignia-identity-authority-9dm-v1`.

- Sonae is the Excel supplier/retailer group, not a proof that a PDV is a Sonae store. A Sonae stock batch can only use explicitly marked CNT/Continente, MDL/Continente Modelo, or CBD/Continente Bom Dia locations.
- Pingo Doce batches only use PD/Pingo Doce PDVs. Arcol and M. Cunha/Mcunha/M Cunha are independent Cash & Carry retailers, never aliases of Sonae, Pingo Doce, Makro or Recheio. "M" in briefings still means merchandiser, not Mcunha.
- Name identity beats tags/metadata; if the name is generic, accept only an explicit and unambiguous retailer marker in tags or approved structured metadata fields. Generic SONAE tags without CNT/MDL/CBD are insufficient.
- Missing, unknown, multi-retailer and explicitly conflicting identities stay unmatched. Locality, numerical store code, name similarity, AI suggestions and previous mappings cannot override retailer identity.
- In existing batches, incompatible mappings remain stored for audit and are reported in BO as requiring review. Opening a stock batch performs NO UPDATE/DELETE; matching in Operative remains fail-closed. Only a manually confirmed new matching PDV may replace an incorrect mapping. Rows and historic snapshots are not deleted.
- The 9DL runtime Release Gate PHP variable-interpolation fix is carried forward into the canonical release. The source is checked by the 9DM release contract, and runtime reflection testing checks both 9DL and 9DM before publication.
- No DB migrations, no mobile dock changes, no route or briefing scope modifications, no AI provider/budget changes, no Azure/Google extra calls.
- Acceptance on production: import the actual Sonae and PD XLSX in **draft**, inspect Arcol and M. Cunha associations, review ambiguous PDVs in BO, certify only after 6B/Phase 6 validation; no forced publication of unreviewed rows.
