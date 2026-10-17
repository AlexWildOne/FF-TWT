# Phase 9BT — Mobile Analytics Readability & Evidence Density

Contract: `fieldflow-mobile-analytics-readability-evidence-density-v1`

Release target: `2.2.591`

## Scope

- Client and Operative Analytics take true viewport width on phones instead of inheriting a narrow WordPress/theme content column.
- Performance highlights and active evidence tabs receive deterministic readable contrast independent of inherited theme colors.
- Evidence galleries become collapsible at metric, store and date levels, closed by default on mobile and progressively disclosed on demand.
- Analytics data sections remain collapsible and use short mobile labels so headings and controls do not wrap into unusable vertical text.
- Ask FieldFlow textareas and table-search inputs use compact mobile heights instead of expanding into oversized blank surfaces.
- Mobile Analytics reserves bottom space for the fixed navigation so evidence and controls remain reachable.
- The same layout contract applies to Client and Operative dashboards.

## Safety

- Presentation and interaction density only.
- No analytics formula, KPI, route, submission, mail, visit or financial calculation is changed.
- No external AI or Google call is introduced.
- Existing 9BS monthly executive and explainability semantics are preserved.

## Data model

No database schema changes.
