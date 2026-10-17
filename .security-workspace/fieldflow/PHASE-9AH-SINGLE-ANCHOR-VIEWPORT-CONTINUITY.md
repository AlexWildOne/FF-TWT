# Phase 9AH - Single Anchor Viewport Continuity

Contract: `fieldflow-single-anchor-viewport-continuity-v4`

`SOURCE_RELEASE` is `2.2.551` and `STABLE_RELEASE` is `2.2.552`.

## Scope

This release is limited to field-form visual continuity. It does not change database schema, SubmissionPipeline, routing, mail semantics, analytics, licensing, Native Shell or FlutterFlow.

## Runtime rules

- Each structural form mutation may compensate the viewport once, synchronously, against one captured anchor.
- No continuous `requestAnimationFrame` scroll guard is allowed.
- Camera/gallery return restores the captured form anchor before asynchronous file optimisation starts.
- Hot resume treats the mounted DOM as authoritative and must not replay an older draft.
- Cold reconstruction restores the durable draft during form initialization and then applies one saved anchor correction.
- The mobile resume paint guard may hide the portal but must preserve its document geometry.
- Store selection captures the viewport before the native picker opens and supplies that checkpoint to the form swap.

## Certification

Validate and publish in Release Manager, install with the private updater, then run Revalidar cadeia 6B, Testar fecho Fase 6 and Certificar Stable.
