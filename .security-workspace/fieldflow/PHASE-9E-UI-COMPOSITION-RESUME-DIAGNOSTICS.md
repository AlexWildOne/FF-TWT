# Phase 9E - UI Composition Ownership + Resume Diagnostics

Contract: `fieldflow-ui-composition-resume-diagnostics-v1`

`SOURCE_RELEASE` is `2.2.520` and `STABLE_RELEASE` is `2.2.521`.

## Goal

Close the field-reported width perception gap without adding another global full-width patch, and improve evidence around the residual Operative resume flash.

The release keeps the existing DOM, IDs, lazy loading, PHP renderers and React progressive-enhancement ownership. It neutralises only duplicate presentation chrome on selected outer panel cards so each module can own its visible surface.

## UI composition contract

Client targets:

- Base (`#rpcp-commercial-panel`)
- Briefings (`#rpcp-briefings-panel`)
- Implementations (`#rpcp-implementations-panel`)
- Stocks (`#rpcp-stocks-panel`)
- Team (`#rpcp-team-panel`)
- Analytics (`#rpcp-analytics-panel`)

Operative targets:

- Briefing (`#rp-app-briefings`)
- Academy (`#rp-app-academy`)
- Expenses (`#rp-app-expenses`)
- Messages (`#rp-app-messages`)
- Analytics (`#rp-app-analytics`)

The portal shell owns geometry. The module owns its visible surface. Outer compatibility wrappers remain present but lose duplicate padding, border, radius, background and shadow under `fieldflow-ui-composition-ownership-v1`.

The contract can be disabled without rollback through the `fieldflow_ui_composition_enabled` filter.

## Resume diagnostics contract

Contract: `fieldflow-resume-diagnostics-v1`.

The native WebView resume guard keeps media surfaces behind the neutral guard during recomposition. Coverage now includes `iframe`, `img`, `object` and `embed`, in addition to the existing canvas/video/SVG handling.

Feature flags:

- `fieldflow_mobile_resume_guard_enabled`
- `fieldflow_mobile_resume_media_guard_enabled`
- `fieldflow_mobile_resume_diagnostics_enabled`

Diagnostics are bounded in memory, avoid content text, record only lifecycle/layout metadata, and emit the final summary to the existing native bridge as `fieldflow.resume.diagnostic`.

## Frozen areas

- No P1-P4 routing or optimisation-rule change.
- No database schema change.
- No routing service change.
- No FormRenderer change.
- No offline/media data-flow change. The offline runtime receives release identity only.
- No Mail Center, AI, Azure, licensing or billing behaviour change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Release continuity

The package must pass both the previous 2.2.520 package validator and the 2.2.521 package validator. Historical Phase 9D/9D2 contracts remain valid and are not replaced or removed.
