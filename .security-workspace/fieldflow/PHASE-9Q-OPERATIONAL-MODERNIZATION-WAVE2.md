# Phase 9Q, Operational Modernization Wave 2

Contract: `fieldflow-operational-modernization-wave2-v1`

This phase advances the certified operational front without moving critical business ownership out of the existing PHP/domain services.

## Release continuity

- `SOURCE_RELEASE` is `2.2.532` and `STABLE_RELEASE` is `2.2.533`.
- The contract is successor-safe for `2.2.533` and later packages.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Academy

- Academy gets a bounded React operational control shell and scoped search.
- `fieldflow-academy-front-batch-v2` replaces the front-end per-course, per-lesson and per-mission status read pattern with batch reads.
- Course completion semantics, required lesson gating, certificates, mission submissions and media ownership remain unchanged.
- Academy lifecycle handlers are scoped to the Academy root and receive the normal panel lifecycle event directly.

## Expenses

- Expenses joins the event-driven operational React shell.
- The certified upload, QR, camera, REST submission and expense-domain services remain canonical.
- Async list refreshes explicitly emit `fieldflow:operational-module-refresh`, so the React shell no longer needs a MutationObserver for this module.

## Analytics

- The premium analytics enhancer no longer installs a global document MutationObserver.
- Enhancement runs on explicit initial, lazy-panel, panel-open and `fieldflow:analytics-rendered` events.
- The operational analytics dashboard emits the render event after a successful data render.
- Existing analytics services, exports, evidence, KPI calculations and backoffice React analytics remain canonical.

## Protected cores

No routing/P1-P4, ConditionalFlow, form submission, IndexedDB/offline transport, media transport, Mail Outbox or database schema behavior is changed by this phase.
