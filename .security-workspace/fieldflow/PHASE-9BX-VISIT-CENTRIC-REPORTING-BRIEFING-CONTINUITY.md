# Phase 9BX, Visit-Centric Reporting & Briefing Continuity

Contract: `fieldflow-visit-centric-reporting-briefing-continuity-v1`
Release target: `2.2.595`

## Objective

Make the operational visit the canonical report identity. One terminal route stop produces one Client Report, whether the visit was executed or closed as a justified non-visit. A form submission is optional content attached to that visit, never the condition for the report to exist.

## Runtime rules

1. Relatórios starts from `routespro_route_stops` joined to its route, never from `routespro_form_submissions`.
2. A report exists when `VisitCompletionPolicy::terminalSql('rs')` is true, which means `done` or `completed`, or `failed` with an explicit non-visit justification.
3. The latest operational form submission is attached with a `LEFT JOIN`. A completed visit or justified non-visit without a final submission remains visible.
4. Header date filters apply to the route operational date, so the visible De/Até interval governs report cardinality.
5. Operative filtering accepts canonical route ownership, active route assignment, exact campaign-store assignment and actual operational submitter evidence.
6. Briefing visibility is stricter: when an operative filter is selected, the exact project/store must be assigned to that operative in `routespro_campaign_locations.assigned_to`.
7. Briefing questions are resolved for the exact visit store, operational date and form. If no submission exists, the active form binding may resolve the form context without inventing answers.
8. Client UI and report exports use the same visit-centric dataset. Justified non-visits expose their closure reason.
9. Rotas runtime, route calculations and Google cost-control behaviour are unchanged.
10. Release Manager and Stable certification enforce visit-centric cardinality semantics, not merely the existence of report loader functions.

## Compatibility

- Source certified release: `2.2.594`.
- Successor: `2.2.595` or later.
- No database schema changes.
- No Google API calls added.
- Existing admin submission management remains submission-centric. Visit-centric mode is used only by Client Reports and their exports.
