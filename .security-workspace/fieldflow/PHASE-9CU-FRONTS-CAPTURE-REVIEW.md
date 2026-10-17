# FieldFlow 9CU: Fronts Capture Review and Controlled AI Spend

Contract: `fieldflow-fronts-capture-review-9cu-v1`

Successor: `2.2.617 -> 2.2.618`. Candidate: NOT certified until Release Manager chain completes.

## Intent

1. Live stream stability captures a photo on the device only. No AI is invoked until the operative explicitly presses **Analisar com IA, 1 tentativa** on a visual photo review. File and gallery capture share this opt-in gate. The opt-in button is disabled when no products are selected.
2. Before opt-in, a 128 x 96 canvas computes approximate photo size, brightness and edge detail; warnings guide recapture without network requests. Low light can be legitimate, so warnings remain advisory.
3. A product-matrix user may choose up to 12 products in the currently relevant Cardex group. The server revalidates authorized question, store and product scope; no client-invented products become eligible. Nonselected input fields remain unchanged.
4. The model prompt distinguishes first-row fronts, variants and sizes and tells the model not to guess. Invalid or uncertain quantities do not automatically fill fields. A human must confirm the reading before the current form values are updated.
5. `capture_id`, exact photo digest, user, project, form, store, visit, date, route, stop, question and product selection form a replay key. The transient stores a completed reading response for 15 minutes. A MySQL named lock prevents concurrent duplicate upstream calls for that same context and is released even when the attempt fails.
6. Existing `VisionUsageGuard::check` and `reserve` continue to enforce a per-client operational reservation budget before the paid call. Repeated capture after a failed or incomplete upstream call may consume another attempt. The operational budget is not a guarantee of the provider's actual invoice.
7. Written, manual, select and device-supported voice input remain available without FieldFlow AI calls; 9CT delete confirmations remain intact. There are no schema migrations and no change to native app source.

## Test plan

- `php tools/test-fronts-capture-review-9cu.php` exercises real server `FrontsVisionService` parsing, replay scope and lock, and budget reserve.
- `node tools/test-fronts-capture-review-9cu.mjs` exercises inline browser logic, explicit approval, local quality and selection.
- `node tools/test-fronts-multimodal-9cs.mjs` and `node tools/test-form-delete-confirm-9ct.mjs` verify predecessor continuity.
- Complete `frontend/npm run verify` and PHP lint. Run `ReleasePackageValidator::validate()` on the final canonical ZIP and verify its manifest.
- On real Android and iOS WebViews: take a picture, review without network costs, select products, retry, upload once, confirm, close/reopen form, verify only chosen fields filled. Verify internet-off, provider errors, poor lighting, same-date same-store separate visits, and remaining 9CT delete prompts.
- Track usage in the Backoffice and compare real invoice in the AI provider dashboard; test with a tightly scoped pilot budget.

## Limitations

- The quality score is a local heuristic, not optical product recognition; accuracy depends on shelf images and AI provider response.
- Replay cache is a best-effort guard against **completed identical** submissions within 15 minutes, not exactly-once billing in case of network failure or crash after a paid provider call.
- The provider and Google charges for other FieldFlow modules remain outside the 9CS reservation budget.
