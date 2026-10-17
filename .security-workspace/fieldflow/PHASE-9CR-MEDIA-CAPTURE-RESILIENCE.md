# Phase 9CR - Photo capture resilience and media diagnostics

Contract: `fieldflow-media-capture-resilience-v1`

`SOURCE_RELEASE` is `2.2.614` and `STABLE_RELEASE` is `2.2.615`.

## Context

Operatives reported that the photo buttons of form questions (`Tirar fotografia`, `Escolher imagens`, including the evidence photo shown after answering `Sim`) did nothing in the Android app. The same questions work in a real WordPress with the 2.2.614 package (web, app portal emulated for Android and iPhone, administrator and operative, standard and resumable upload, new and already submitted reports, stepped forms, emulated app lifecycle around the picker). The difference is the device WebView, so this release makes the opening path resilient and records the exact failing step.

## Opening the gallery and the camera

- In the app WebView (`FieldFlowMobile.native`) the file chooser opens with the classic `click()`; browsers keep `showPicker()` with `click()` as fallback.
- A second tap on the same button without any sign that the picker opened (no blur, no hidden page, no file) switches the method for the rest of the session and tells the operative it is trying another way. Cancelling the picker is not counted as a failure.
- When the in-page camera is refused or fails (permission of the app or WebView, camera busy, device error), the phone camera opens through the native file input (`capture=environment`) and the session keeps using it. A device without a camera keeps the previous message.
- A visible, editable question never keeps a disabled photo field or button: the state is restored on tap and reported. In a report under consultation the operative is told to use `Editar reporte`.

## Diagnostics

- Each failing step (picker without signal, no opening method, camera unavailable, empty file delivered by the device, disabled field, submission with photos failing) is sent to the existing authenticated endpoint `fieldflow-mobile/v1/diagnostics/client-event` as `media_error` with the device summary (Android/iOS version, WebView, app or browser), the question, store, route and form; the last steps of the photo flow are kept in the event `detail` (bounded to 600 characters).
- Events appear in `Segurança e observabilidade` as `mobile_media_error`. At most 10 events per page; the endpoint keeps its own rate limit.
- The form carries the endpoint and a REST nonce; the offline runtime nonce is preferred when present.

## Non-regression

- The photo upload, resumable transport, drafts, conditional evidence and edit flows are unchanged; every 9CL to 9CQ contract keeps its markers.
- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
