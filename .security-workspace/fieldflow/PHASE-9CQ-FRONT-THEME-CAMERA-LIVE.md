# Phase 9CQ - Theme isolation, readable fronts, camera on the question switch, live counting and insignia name variants

Contracts: `fieldflow-theme-isolation-v1`, `fieldflow-fronts-vision-question-switch-v1`, `fieldflow-fronts-vision-live-count-v1`, `fieldflow-catalog-insignia-name-variants-v1`

`SOURCE_RELEASE` is `2.2.613` and `STABLE_RELEASE` is `2.2.614`.

## Theme isolation (`fieldflow-theme-isolation-v1`)

The FieldFlow sites run the Hello Elementor theme, whose reset paints every button and its border magenta and fills it on hover and focus. On a phone a tapped button keeps the focus, so it stayed magenta; FieldFlow rules weaker than the theme state rules (`[type=button]:hover`, `button:focus`) or printed before the theme lost.

- FieldFlow stylesheets are printed after the theme and Elementor stylesheets (`ThemeIsolation::enqueue`, dependency order, loops avoided), so rules of equal specificity follow FieldFlow.
- A late stylesheet gives the form buttons (primary, secondary, add reference, remove, uploads, zoom, camera), the in-page camera and the shared app buttons (`.rp-btn`, `.rp-cta`) their own colours in every state, from the campaign template variables (`--rp-form-*`, `--ff-*`, `--rp-primary-solid`). Unclassed links and buttons inside FieldFlow use the template link and border colours.

## Readable fronts on phone and tablet

- Share Space: each side is a row (image and name on the left, `−/+` on the right, 148 px), up to 900 px. The two-column layout between 361 and 560 px squeezed the number field to a few pixels and hid the value.
- References added in the store (`Ajustes da loja`): a card per reference (product on the full width, category and reference side by side, fronts with `−/+`, remove aligned right). The desktop grid no longer applies on phones (labels were drawn one letter per line and fields overflowed).
- The `Ajustes da loja` group shows its chevron like the other categories.

## Camera on the question switch (`fieldflow-fronts-vision-question-switch-v1`)

- A fronts question with `Leitura por câmara (piloto)` on shows `Ler com câmara` without any other switch, also in reports already done (after `Editar reporte`).
- Per client (Cardex e Catálogo → Leitura por câmara): `Nas perguntas com a opção ligada` (default), `Ligada (inclui Share Space)` or `Desligada`. Clients saved as on in 2.2.612–2.2.613 keep Share Space.
- Managers see under the question why the button is missing (client off, no AI provider ready, client not identified).

## Live counting (`fieldflow-fronts-vision-live-count-v1`)

- The camera sheet offers three ways: `Contar ao vivo` (main), `Tirar fotografia`, `Escolher da galeria`.
- Live: the camera opens inside the sheet; when the phone is held still (frame difference and light measured on the device every 280 ms) one frame is sent and counted automatically; `Contar agora` sends it at once. The frame is read from the temporary upload and discarded: no photo is kept (history shows `Ao vivo`). One reading per capture; `Contar outra vez` reopens the live camera.
- Without live camera support or permission (for example an app WebView that does not allow it) the sheet says so and keeps photo and gallery.

## Catalog: names written differently by each insignia (`fieldflow-catalog-insignia-name-variants-v1`)

- More packaging and category words are ignored: `bebida espirituosa`, `lata`, `RTD`, `PET`, `vidro`, unit words.
- A bare bottle size at the end of the name is the capacity (`JAMESON ORANGE 700` = 70 cl); ages are never read as capacities.
- After the exact identity, groups of different insignias with the same capacity and numbers are joined when every other word matches, is an abbreviation (`MART.` for `MARTINI`, `BLK` for `BLACK`) or is a short chain code without vowels (`SDR`, `STD`). A different variant word (Orange, Pink, Black Barrel, Red, Regal) always keeps two products; a group never takes two products of the same insignia.
- Identity version 3: the catalog of each client is reorganised the first time it is opened after the update (and in background). `Exportar CSV` lists every product with the names used by each insignia, for review.

## Non-regression

- Every 9CL–9CP contract keeps its markers; the 9CO and 9CP runtime tests follow the new defaults (question switch, `STD` joined automatically).
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
