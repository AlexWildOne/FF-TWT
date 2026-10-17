# FieldFlow 2.2.627 | 9D2 Catalog & Briefing Category Continuity

**Contract:** `fieldflow-briefing-category-continuity-9d2-v1`  
**Continuity:** `2.2.626 -> 2.2.627`  
**Baseline:** 2.2.626 certified Stable. Candidate 2.2.627 requires its own publication, installation, Phase 6B checks and certification.

## Backoffice

- Four-card navigation: Cardex, Catálogo visual, Mapeamento do briefing and Leitura por câmara; preserve all existing tabs/endpoints and actions.
- Review panel for up to 80 latest execution questions per selected client/campaign with automatic, manual-confirmed, ambiguous or pending status. Manual dropdown persists in tenant/project options, scoped by the verified product identity/section fingerprint.
- Automatic category association uses exact catalog EAN/reference/name plus size and variant checks. Cardex categories are available in the dropdown even when visual sync has not yet run.
- Explicit “Associar categorias automaticamente” backfills existing active/draft questions. It is capped, does not override confirmed manual category and never rewrites historic submissions.
- Inline category choice in the briefing question editor can save manual corrections. Ordinary saves do not convert an auto suggestion to a manual rule.

## Weekly briefing transitions

- On new import, reuse prior published or archived execution questions only for the same client/campaign, same section identity, explicit mapped location and verifiable product identity.
- If other products are added or removed from a section, unchanged individual products can retain a question if a full product label/reference is visible in both source and target sections. A different SKU, unverified variant/format, absent scope, duplicate sections or unverified product fails closed and requires manual review.
- New question keys and valid_from/to refer exclusively to the newly imported briefing; previous briefing and historic visits remain immutable.

## Front and safety

- Operative sees category in briefing execution preview and in form field help; no ability to mutate global mapping from front.
- No new Azure, AI, Google Maps calls, scheduled jobs, REST endpoints or database schema changes. **sem chamadas adicionais à IA**. Existing 9CS, 9D0, 9D1 limits, protected budget and SKU/vision logic unchanged.

## Verification

- `php tools/test-briefing-category-9d2.php`: manual/project/client separation, format/variant safety, same-SKU carryover when other products change, changed-product no-carry.
- `npm --prefix frontend run verify`, PHP syntax lint and existing runtime regression checks.
- Both predecessor 2.2.626 and candidate 2.2.627 ReleasePackageValidator on final ZIP plus Azure publisher version/manifest contract.
- Release Manager → Validar → Publicar → updater privado → revalidar 6B → testar fecho Fase 6 → Certificar Stable.

## Limitations

- Without an unambiguous product identity, the system never assigns or carries categories automatically. Manual choice applies to the current question, but a generic question without product or section identity cannot safely become a persistent cross-week product rule.
- Read-only historical questions are not retroactively rewritten by the explicit backfill; sync covers recent draft and published context rows.
- Real integrations with client data and platform certification can only be confirmed on the production installation.
