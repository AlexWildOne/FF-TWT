# Phase 9D2 — Dual Portal Full-Width Parity + Resume Hardening v2

Contract: `fieldflow-dual-portal-fullwidth-parity-resume-v2`

`SOURCE_RELEASE` is `2.2.519` and `STABLE_RELEASE` is `2.2.520`.

## Scope

This release closes field-verified layout parity gaps in both dashboards and hardens the Android/WebView resume paint path.

Client parity targets: Briefings, Base, Implementations, Stocks, Team and Analytics widgets.

Operative parity targets: Briefing, Academy, Expenses, Messages and Analytics widgets.

The historical Analytics Mobile Full-Width gate accepts the canonical true-full-width shell as the supported successor to the obsolete 2.2.472 viewport breakout. The old `100vw`/negative-margin runtime hack is not re-enabled.

Resume Guard v2 hides the live portal while the app is backgrounded, keeps a neutral guard visible on resume, exposes the current portal underneath that guard after layout confirmation, and only then releases the final frame.

No P1-P4 routing or optimisation-rule change.

No database schema change.

No form submission or offline data-flow change. `SubmissionPipeline::BUILD` remains `2.2.485`.
