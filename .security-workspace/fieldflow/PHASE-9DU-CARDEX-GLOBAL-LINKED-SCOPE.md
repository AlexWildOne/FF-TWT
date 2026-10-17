# FieldFlow 2.2.655 | Cardex Global Linked Scope Authority 9DU

Contract: `fieldflow-cardex-global-linked-scope-authority-9du-v1`
Release: 2.2.655 (candidate) based on user-confirmed 2.2.654 Stable.

## Root cause confirmed from 2.2.654 code and BO screenshots

The screenshot shows cardex #5 with `insignia=Continente`, while the cardex title is `Pernod Ricard - Sonae`. Store #314 `Continente Modelo Santo Tirso` is linked to this cardex, and visit client/project and cardex client/project both equal 2. In the old resolver, `Continente` was treated as exclusive CNT subtype, and `Continente Modelo` is MDL. The linked row was ignored despite verified same family, client and project. This affects all explicitly linked Modelo and Bom Dia stores in the same historic Sonae cardex, not only #314.

## Scoped solution, no stock identity regression

- For **non-cloned historical cardex only**, a declared exact `Continente` and a name ending in the explicitly separated suffix `Sonae` or `Sonae MC` indicate a group-level Sonae assortment. Inside the **Cardex consumer**, that cardex uses effective retailer evidence `Sonae`. The source column is not changed, and no existing PDV mapping is modified.
- The existing Cardex location link and active status remain necessary for historic grouped cards. Client and project validation remains intact. Unlinked stores do not receive the historical umbrella by automatic detection.
- An explicit `CNT`, `MDL`, `CBD`, `Continente Modelo`, `Continente Bom Dia`, or ordinary `Continente` cardex without the Sonae title continues to require the matching subtype. A cloned cardex NEVER receives this legacy exception.
- Pingo Doce, Makro, Garcias, Arcol, M. Cunha and other families keep their independent identity gates. Stock import/Briefing MappingEngine/StoreIdentityAuthority are NOT modified.
- Frentes (`product_matrix`), order suggestion (`order_suggestion`) and fixed cardex visibility all use the same Cardex effective retailer evidence and link/scope checks. Already submitted answers, store revisions and media are not touched.

## Global diagnostic

In **Cardex e Catalogo -> Cardex**, press **Auditoria global: Cardex / PDVs / Formularios**. This new read-only control scans ALL stored cardex/location links in pages of 60, with optional client, cardex and PDV filters. Each row displays declared/effective retailer, latest route client/project (or PDV context), base active reference count, the precise runtime binding verdict, and a link to per-store diagnostics. Each page summary covers only that page. There is no automatic reassignment, migration or batch update. Stores with no cardex link are outside this table and need separate inventory review.

## Verification scope and boundaries

- Synthetic tests exercise CNT, MDL, CBD, umbrella Sonae, exact subtype-only cardex, Pingo Doce, Garcias, Makro, disabled clone, draft, cross client, cross project, fixed and auto form questions, priority and no implicit global exposure.
- Tests also ensure existing 9DN global identity remains subtype-strict, since Sonae groups are a Cardex-specific exception ONLY.
- BO audit is privileged and GET-only. It does not write or change persisted database state. It compares a read-only **recent route sample**, not all historic visits. Actual full production reconciliation must be performed page by page in the live WordPress DB.
- New historical Gate 9DU validates presence of resolver fix, tests, admin audit and docs before release certification.

## Deployment plan

1. Backup DB/plugin; publish N+1 candidate 2.2.655 over certified 2.2.654 through Release Manager; certify Phase 6 Stable.
2. Reopen Santo Tirso #314, one Bom Dia and one ordinary Continente in the same Sonae cardex. Frentes and order suggestion should show approved Sonae references.
3. Open global audit and filter Pernod Ricard or Cardex #5. Review every page; check missing retailers, old/unpublished cardex, project scope, duplicate priorities and zero active reference counts, including Pingo Doce.
4. Do not bulk-correct any other brand's identity; investigate remaining result codes using read-only diagnostics and the relevant admin store/cardex records.
5. Confirm prior visit answers and retailer-specific stocks remain unchanged.

No real production DB or Android verification was performed during build, so field certification is pending user validation.
