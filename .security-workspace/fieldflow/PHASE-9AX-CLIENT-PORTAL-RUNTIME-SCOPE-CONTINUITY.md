# Phase 9AX - Client Portal Runtime Scope Continuity

Contract: `fieldflow-client-portal-runtime-scope-continuity-v1`

`SOURCE_RELEASE` is `2.2.567` and `STABLE_RELEASE` is `2.2.568`.

This hotfix closes the client portal render regression introduced in 2.2.567.

Requirements:

- `renderKpis(stats)` resolves `stats.team_performance` into a local `perf` object before any KPI uses it.
- `leader` is resolved locally from `perf.leader`.
- no KPI path may rely on an outer lexical `perf` or `leader` binding.
- the 9AW financial and audience-aware analytics contract remains intact.
- Summary, Routes and Analytics continue mounting after the KPI stage.
- schema remains `2.2.529`; SubmissionPipeline build remains `2.2.485`.

The gate is intentionally narrow. It prevents a JavaScript ReferenceError at the first client portal render stage from blanking later sections.
