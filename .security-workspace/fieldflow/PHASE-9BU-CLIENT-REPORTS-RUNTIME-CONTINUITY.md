# Phase 9BU, Client Reports Runtime Continuity

Contract: `fieldflow-client-reports-runtime-continuity-v1`
Release target: `2.2.592`

Restores the missing client portal report loader and bridges the canonical client/project/operative/location/date filters to `/form-submissions`. As clarified by successor contract 9BW, route selection belongs to the Rotas tab and must not silently narrow the Reports header scope.

No database schema changes.
No Google calls.
No report semantics changed.
