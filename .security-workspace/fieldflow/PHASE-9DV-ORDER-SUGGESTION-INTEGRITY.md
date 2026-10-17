# FieldFlow 2.2.656 | Order Suggestion Integrity 9DV

**Contract:** `fieldflow-order-suggestion-integrity-9dv-v1`
**Predecessor:** 2.2.655 Stable, installed and certified by the user.
**Current:** 2.2.656 candidate, pending WordPress certification and device testing.

## Observed code-level defects in 2.2.655

1. The inline browser rule treated empty Fronts values as confirmed zero and pre-populated `order_qty=1`. A value prefilled from a prior visit also appeared to be a current-day confirmation, risking unwanted orders.
2. The web extractor repeated empty-as-zero inference server-side. A client could submit a numeric order for a wrong `item_id` or reference not belonging to the active store Cardex through forged Fronts rows.
3. Native/mobile submission sanitised but did not apply the Cardex assortment and source-of-zero validation to `order_suggestion`. Offline replays shared the exposure.
4. The browser scanned all Fronts for matching product identities; when a form had more than one Fronts/Cardex question, a zero in one could trigger an order in another.
5. An unavailable Cardex could lead to lost previous order answers when editing the exact same report, because the submission updates replace the answer set. This was already guarded for Fronts, not orders.

## Correction and bounded scope

- `OrderSuggestionIntegrity` is now the shared source of truth for web and mobile. It accepts only known store-assortment SKUs or a new product **explicitly present in the paired Fronts**; rejects item-id/ref impersonation, duplicates and invalid numeric values; recomputes automatic quantities from configured 0-fronts rules; and retains deliberate manual quantities, including zero and edits.
- **Unknown/unconfirmed, blank and inherited previous-visit Fronts never generate an automatic order.** New browser rows include `fronts_confirmed`, which becomes true when the operative actually edits the current count. A same-visit edit recovers this state; historical previous visits do not automatically become current confirmations. Native payloads including the flag honour it; legacy payloads with an explicit numeric 0 but no flag retain backward-compatible interpretation.
- The order sync uses one paired Fronts question only. A fixed Cardex cannot be silently reassigned to a different source, and legacy configured manual product lists remain independent. Orphan manual rows remain visible for correction instead of being silently deleted; they cannot be persisted if unauthorised.
- Edits to a report with a temporarily unavailable Cardex preserve the answers already recorded **in the same visit**, both Fronts and order. Historical visits and stocks are not changed.
- The existing conditional question `Foi efetuada uma sugestao de encomenda? = Sim` and the mail/output eligibility pipeline are unchanged; they consume only newly validated persisted order rows. Existing sent mails, historical answers, event receipts and autosend configuration are not rewritten or resent by this release.
- No changes to Sonae umbrella, CNT/MDL/CBD, Pingo Doce, mapping, photos, SKU recognition, camera AI costs, routing, other clients, or stock imports.

## Tests and limits

- `tools/test-order-suggestion-integrity-9dv.php`: 49 source-of-truth assertions, empty/zero/positive, user/manual source, item/reference impersonation, custom front rows, numeric validation, duplicate guard, client/mobile/web marker checks.
- `tools/test-order-source-runtime-9u.php`: backward-compatible order-source tests with shared normalizer class loaded.
- Run Cardex 9DU/9DT/9DS/9DR/9DP/9DQ, mail 9DO and 9AI gates, all PHP syntax, frontend TypeScript build, frontend tests, release validators of 2.2.655 and 2.2.656, manifest/ZIP integrity.
- The local tests do NOT certify real production delivery, SMTP message acceptance, app session behaviour, historical SKU states, or WordPress database fixtures. Confirm in production with at least one new visit, a previously completed visit under authorized edit, Android online and delayed offline replay.
- Recovery of previously auto-generated orders is **not automatic**. Review suspected historical suggestions manually before resending emails.
