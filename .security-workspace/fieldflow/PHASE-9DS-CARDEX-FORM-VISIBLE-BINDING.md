# FieldFlow 2.2.653: Visible Cardex Form Binding & Visit Answer Safety 9DS

Contract: `fieldflow-cardex-visible-binding-9ds-v1`

## Failure reproduced from 2.2.652 source

- `Forms/FormRenderer::render_field()` omitted `product_matrix` when the automatic Cardex could not resolve or an invalid fixed binding was saved.
- `Mobile/FormPayloadService` flagged Frentes unavailable; the app could therefore skip directly to the next question (e.g. Stock Ruturas).
- `Forms::handle_submit()` and `Mobile/SubmissionService` discarded an unavailable Frentes answer on a same-day edit, while other answers were rewritten.
- Imported historical cards can have `insignia=Pernod Ricard`, `name=Pernod Ricard · Sonae`. The resolver must use **verified** name suffix only for known retailers and non-clones. A wrong retailer, other Sonae subtype, draft or unrelated project remains prohibited.

## Corrective contract

1. Automatic Cardex resolution ignores a residual selected `cardex_id` field. A known historical terminal retailer label is evaluated and then checked against store identity and Sonae subtype; this is not a blanket re-assignment.
2. Both Frentes and Sugestao de Encomenda appear, even when cardex resolution fails. The form displays the store-specific binding reason rather than editable artificial SKUs. If this exact visit already has a prior answer, the UI shows that answer read-only.
3. Edits in web and mobile retain previously saved Frentes from the same submitted visit if its Cardex cannot be resolved. Never borrow rows from another visit or untrusted stale client payload. New visits do not get old answers copied as a replacement for missing Cardex.
4. Preserve 9DN retailer authority; 9DP clone draft and manual store scope; 9DQ new retailer identity; 9DR CNT/MDL/CBD isolation; Mail 9DO, stocks and routes unchanged.

## Verification

- `php tools/test-form-cardex-visible-binding-9ds.php`
- `php tools/test-form-cardex-identity-continuity-9dr.php`
- `php tools/test-release-gate-runtime-9ds.php`
- `php tools/test-release-gate-runtime-9dr.php` (historical marker, release version must be correct for that historical test; run as packaged)
- Frontend TypeScript build and PHP lint; Release Manager successor Package Validator + self-Validator + manifest.

## Production acceptance required

- Verify store CBD (Continente Bom Dia), MDL (Modelo), CNT, and affected Pingo Doce with client/project, linked active cardex, and actual nonzero SKU list.
- Open an existing same-day submitted visit and a fresh one. In each, both configured questions appear with correct rows; historical values retained on edit. No orders/fronts for another retailer.
- If an explicit warning remains, Backoffice must correct PDV identity/cardex status/links, not override matching rules or invent SKUs.
- User must confirm 2.2.652 certification before publishing N+1, and certify 2.2.653 only after real WordPress and WebView tests.

No destructive database migration. No SMTP, Google or Azure calls added.
