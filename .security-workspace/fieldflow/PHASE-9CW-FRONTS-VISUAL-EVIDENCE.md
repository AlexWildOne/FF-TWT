# FieldFlow 9CW: Fronts Visual Evidence and Variant Identity

Contract: `fieldflow-fronts-visual-evidence-9cw-v1`

Successor: `2.2.619 -> 2.2.620`. Candidate only, not certified before installation and the normal release sequence.

## What changes

1. **Single paid AI request:** Azure OpenAI/Foundry, Gemini and OpenAI now receive the same strict positive evidence format. One normalized bounding box per counted physical front, visible label transcription, readable variant text and optional capacity evidence. No second recognition provider call, no auto retry or model fallback. Result token budgets are slightly larger, under the prior 1600-output-token cap.
2. **Brand and variant:** Candidate names are only possible products. A positive front requires an observed brand label and a separate box. Among related SKUs the field-relevant variant words must be legible, not merely guessed from the catalog. For different capacity variants, the matching centilitre text must be visible. This is a conservative *model-attested* test, not independent computer vision or OCR; user confirmation is still essential.
3. **One physical object, one SKU:** A box with substantial overlap with another box (same or different SKU) forces the affected results into manual review. Suspicious positions cannot silently count twice. Invalid coordinates or missing boxes cannot establish a positive count.
4. **No invented zero:** A rejected positive becomes an unconfirmed blank, NOT zero; original `model_fronts` is stored as audit-only. Zero is still a model claim about absence and remains manually editable by the operative.
5. **Offline and cost continuity:** Capture review, budget reservation and 15-minute replay are unchanged except the replay key includes the 9CW contract so earlier unverified 9CV readings cannot be recycled. The photograph stays transient and the operative can use free manual, text and device dictation. No new external service, database migration or native app release.
6. **Known limit:** bounding boxes, transcribed words and confidence are all produced by the same vision model, not an independent proof. A model can still fabricate a plausible non-overlapping box or label. This change suppresses likely false positives and increases manual review; it does not mathematically guarantee correct identification. Real photographs must be tested in Android/iOS against operational ground truth before declaring accuracy sufficient.

## Reproduction and tests

- User reported shelf photo (10 Oct 2026): **Jameson Original, one front; Jameson Black Barrel, no visible front**. The image remains in the conversation and is NOT distributed with this package. The regression test supplies synthetic JSON model responses representing the error, without cloud calls and without storing the actual photo.
- `php tools/test-fronts-visual-evidence-9cw.php`: false Black Barrel, overlapping locations, variant wording, capacity distinction, bad box, duplicate result, low confidence, legitimate absence.
- `node tools/test-fronts-visual-evidence-9cw.mjs`: per-SKU review reasons, no auto-fill and provider single request markers.
- Historic `tools/test-fronts-camera-reading-9co.php`, `tools/test-front-theme-camera-live-9cq.php`, `tools/test-fronts-capture-review-9cu.php`, `tools/test-fronts-multimodal-9cs.mjs` and `tools/test-form-delete-confirm-9ct.mjs`.
- Full PHP syntax lint, frontend verify, final manifest integrity, ReleasePackageValidator; production acceptance includes app WebView and budget validation.

## Rollout

Run Release Manager Validar/Publicar, install using the private updater, revalidate 6B, test Phase 6 close, then Certify Stable. Keep 2.2.619 as rollback baseline until certified. Real Azure inference and MySQL production availability cannot be validated offline.
