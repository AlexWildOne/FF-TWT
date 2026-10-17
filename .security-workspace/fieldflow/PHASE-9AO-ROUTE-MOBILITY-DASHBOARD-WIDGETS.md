# Phase 9AO, Route & Mobility Dashboard Widgets

Contract: `fieldflow-route-mobility-dashboard-widgets-v1`

`SOURCE_RELEASE` is `2.2.558` and `STABLE_RELEASE` is `2.2.559`.

## Objective

Expose route execution and mobility as configurable Intelligence Center widgets without introducing a second routing or cost calculation path.

## Canonical source

All route/mobility widgets consume `CanonicalOperationalReportService::routeMobilitySnapshot()`.
That is the same route and mobility projection used by Daily Close and reporting exports.

System widget source: `route_mobility`.

## Widget catalog

- Route execution
- Visit execution
- Executed kilometres
- Route kilometre breakdown, access + between PDVs + return
- Fuel / energy estimate
- Toll estimate
- Mobility cost, fuel/energy + tolls
- Daily mobility

## Intelligence Center

The widget editor can select either a form/question source or `Rotas e mobilidade`.
System widgets retain the current dashboard layout, ordering, visibility and Widget Intelligence behavior.
They may be scoped by client, project or route.

## Frontend

Route/mobility widgets reuse the current analytics card layout and responsive behavior.
Support tables expose route/day, status, access km, inter-PDV km, return km, total km, fuel/energy, tolls and combined cost where applicable.

## Invariants

- No routing calculation is implemented in the widget service.
- No mobility price formula is implemented in the widget service.
- Only operationally closed routes contribute to executed mobility totals.
- Open routes remain visible to execution widgets but are excluded from executed mobility totals.
- `SubmissionPipeline` remains at certified build `2.2.485`.
- Database schema remains `2.2.529`.
- No FlutterFlow or viewport changes.
