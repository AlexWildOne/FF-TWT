# Phase 9BW, Client Report Header Scope & Briefing Assignment Integrity

Contract: `fieldflow-client-report-scope-briefing-assignment-v1`
Release target: `2.2.594`

## Objective

Keep Client Reports governed by the canonical header filters and make briefing questions first-class report content without widening briefing scope beyond the exact store that received each question.

## Runtime rules

1. Relatórios uses only the visible header scope: client, project, operative, optional store, date from and date to. A route selected in Rotas is local UI state and must never narrow Relatórios.
2. Selecting an operative returns every matching submission in the header date interval, subject to the existing permission scope. It is not implicitly reduced to the currently selected route.
3. Briefing questions are resolved per submission with `ContextQuestions::briefing_for_context()` using the exact client, project, location, form and historical operational date.
4. When an operative filter is active, briefing visibility is intersected with the existing campaign-location assignment map, `routespro_campaign_locations.assigned_to`, for that project and store.
5. A briefing question mapped to Store A must never appear on Store B merely because both stores belong to the same client, project or operative.
6. Unanswered briefing questions that were applicable to the exact store remain visible as pending, so the report accurately describes both execution and missing responses.
7. Client UI, BO detail, PDF, XLS/XLSX and normalized export data expose the same briefing scope.
8. The package validator and Stable Release Gate enforce this same contract so a build cannot be published and later certified under different assumptions.

## Compatibility

- Source certified release: `2.2.593`.
- Successor: `2.2.594` or later.
- No database schema changes.
- No Google API calls added.
- Route runtime and route financial behaviour are unchanged.
