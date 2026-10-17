# Phase 8N, Certification Runtime Identity Hotfix

Contract: `fieldflow-certification-runtime-identity-v1`

## Purpose

FieldFlow 2.2.498 correctly shipped `assets/fieldflow-offline-runtime-2.2.498.js` with `RUNTIME_BUILD='2.2.498'`, but four Release Gate contracts still searched for the historical `RUNTIME_BUILD='2.2.497'` marker. Stable certification was therefore blocked even though the installed offline runtime itself was current.

## 2.2.499 guarantees

- Plugin, Stable tag, Phase 6 authority and versioned browser assets identify release 2.2.499.
- `ResponsiveAssets` references the 2.2.499 frontend, React and offline runtime assets.
- The offline runtime declares `RUNTIME_BUILD='2.2.499'`.
- Resumable Media Transport, End-to-End Offline Form Recovery, Offline Runtime Hardening and Typed Offline Core gates inspect the current runtime identity rather than a previous release marker.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No IndexedDB schema change.
- No outbox semantics change.
- No resumable media protocol change.
- No form validation or submission ownership change.
- No P1-P4 routing or optimisation-rule change.

## Continuity

`SOURCE_RELEASE` is `2.2.498` and `STABLE_RELEASE` is `2.2.499`. Release Gate is `1.49.1` and Phase 6 Stable authority is `1.0.56`.
