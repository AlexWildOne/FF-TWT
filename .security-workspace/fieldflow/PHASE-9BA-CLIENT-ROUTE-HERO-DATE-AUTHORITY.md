# Phase 9BA - Client Route Hero Date Authority

Contract: `fieldflow-client-route-hero-date-authority-v1`

## Scope

This release restores a single temporal authority in the Client Portal route experience.

- The `De` / `Até` fields in the hero are the only date filter for Summary, route API loading, route calendar, route list, owner route summary, financial totals and route exports.
- The route calendar no longer exposes a month selector or previous/next month controls.
- A hero range spanning more than one month renders each month covered by the loaded route set automatically.
- Opening a route changes only route detail context. It never disables or rewrites the global `De` / `Até` fields.
- Route cards keep the 9AZ daily total mobility cost contract: tolls + energy/fuel + operational cost, summed across all routes on that day, with unresolved items signalled.
- Mobile Client and Operational feature matrices from 9AZ remain independent and backwards compatible.

## Certification continuity

`SOURCE_RELEASE` is `2.2.570` and `STABLE_RELEASE` is `2.2.571`.

The implementation is reconstructed on the certified 2.2.569 Client Portal filter semantics while carrying forward the accepted 9AZ route-cost and mobile portal feature changes. The release does not change the database schema or submission engine. `LATEST_SCHEMA_VERSION` remains `2.2.529` and `SubmissionPipeline::BUILD` remains `2.2.485`.
