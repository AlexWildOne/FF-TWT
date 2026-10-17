# Phase 9CO - Fronts Camera Reading (pilot)

Contract: `fieldflow-fronts-vision-reading-v1`

`SOURCE_RELEASE` is `2.2.611` and `STABLE_RELEASE` is `2.2.612`.

## Purpose

Third step (E3) of camera-assisted fronts reading: the operative photographs a shelf from a fronts question, the AI proposes the fronts per product using the 9CL catalog reference images, and the operative confirms or corrects them before anything is written in the form. Nothing is ever submitted automatically.

## Field flow (web, app WebView and desktop)

- Fronts questions (`product_matrix`) with `Leitura por câmara (piloto)` switched on in the form builder show `Ler com câmara` next to `Adicionar referência`. Briefing Share Space cards (`share_space_matrix`) show `Ler com câmara` when the client has Share Space reading on.
- The button only appears when the client is in the pilot (Cardex e Catálogo → Leitura por câmara) and the AI module `Leitura de frentes` has a ready provider.
- The sheet asks for the shelf category (Cardex categories), opens the native camera (`capture="environment"`, the same mechanism as the photo questions) or the gallery, downsizes the photo to 1600 px JPEG and sends it with the products of that category.
- The result lists each product with its image, the proposed fronts, a −/+ stepper and the confidence. Low confidence is highlighted. With Gemini, the photo shows one box per counted front.
- `Preencher frentes` writes the confirmed values into the same inputs (`data-fronts-input`, `data-share-space-pr`, `data-share-space-competitor`) and fires the usual input events, so drafts, validation, the zero rule, category status, order suggestion pairing and Share Space shares behave as if typed. `Contar à mão` closes without changes.
- Without network the sheet says so and the operative counts by hand.

## Server

- `wp_ajax_fieldflow_fronts_vision_read` / `wp_ajax_fieldflow_fronts_vision_confirm`: logged-in users only, nonce `fieldflow_fronts_vision`, route and client/campaign scope (same rules as the 9CJ context form), 15 readings per user per 10 minutes, monthly limit per client.
- Products are resolved on the server: Cardex rows through the catalog identity, Share Space sides through the 9CL briefing label mapping (label fallback when a side is not mapped).
- The request goes through `AIManager::complete('fronts_vision', …)`: governance, entitlement (`ai.use`), monthly AI quota, usage log and cost accounting apply. Up to 12 catalog reference images (small size, low detail) plus the shelf photo (1280 px, high detail).
- The AI providers accept optional images (`ProviderVisionPayload`): Azure OpenAI / Foundry (chat and Responses), OpenAI and Gemini. Without images the payloads are unchanged. Azure can use a dedicated `Deployment para leitura de imagens`.
- Each reading is a private record per store (`ff_fronts_reading`): client, campaign, store, route, user, question, category, photo, provider, model, duration, detected and confirmed values, corrections, and the submission of the same store and day.

## Back office

`Cardex e Catálogo` gets a fourth tab, `Leitura por câmara`: provider status, pilot clients (on/off, Share Space, monthly limit, reference images per reading), a test reading with an uploaded photo, and the reading history per store (detected → confirmed, photo, provider, timing). The AI Control Center lists the new module `Leitura de frentes`.

## Gemini model availability (`fieldflow-gemini-model-availability-v1`)

Google limits the Gemini 2.5 models to accounts that already used them; new projects get HTTP 404. The default becomes `gemini-3.5-flash-lite`, the former default `gemini-2.5-flash-lite` maps to it, retired 1.x/2.0 models map to 3.5/3.8 models, and a hand-picked `gemini-2.5-flash` or `gemini-2.5-pro` retries once with its successor on 404. The 9CJ markers are kept as comments for certification continuity and the 9CJ runtime test now checks the new defaults.

## Non-regression

- Off by default for every client and every question. Fronts questions without the switch, Share Space without the client switch and every other module are unchanged.
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
