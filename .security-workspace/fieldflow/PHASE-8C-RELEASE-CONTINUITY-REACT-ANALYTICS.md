# Phase 8C — Release Continuity + React Analytics Foundation

Release: **2.2.488**

## Scope

This release combines three compatible modernization steps while preserving the certified operational core:

1. **single continuity authority** for Phase 6 N→N+1 release certification;
2. **React BO Shared Foundation** (`fieldflow-react-backoffice-shared-v1`);
3. **React Analytics Foundation** (`fieldflow-react-analytics-foundation-v1`).

## Release continuity authority

`Phase6StableRelease` is now the only authority for `SOURCE_RELEASE` and `STABLE_RELEASE`. Historical component gates validate their own contracts and no longer pin a historical `SOURCE_RELEASE` value.

For 2.2.488:

- source release: `2.2.487`
- stable release: `2.2.488`
- Release Gate: `1.39.0`

The package validator independently verifies that the source is exactly the previous patch release. This protects both publication from N and certification after installing N+1.

## React BO Shared Foundation

Contract: `fieldflow-react-backoffice-shared-v1`

Shared TypeScript now centralizes:

- progressive props parsing;
- authenticated same-origin POST transport;
- common date/range helpers;
- reusable behavior shared by Devices and Analytics islands.

FieldFlow continues to use WordPress `wp-element`; React and ReactDOM are not bundled by the plugin.

## React Analytics Foundation

Contract: `fieldflow-react-analytics-foundation-v1`

Page: `routespro-assignments-hub`, tab `analytics`

Island: `analytics-context-kpis`

Visible improvements:

- universal Analytics filters render as a React island;
- 7/30/90 day range actions update the context without a full page refresh;
- client/project/route/store dependencies update immediately;
- Operational Health, Execution, Risk, Opportunity, Stock Risk and Confidence KPIs update in the React island;
- the URL remains synchronized through `history.replaceState`;
- stale Analytics requests are ignored through a generation guard;
- existing Trend, Intelligence, Store Intelligence, Predictive and Ask FieldFlow panels reuse the same Analytics 2.0 controller and data sources.

## Progressive fallback

The original PHP filter and KPI markup remains in the DOM as a **PHP fallback**. It is hidden only after the React island mounts. If `wp-element`, the React bundle or the island fails, the PHP controls remain usable.

The existing Analytics 2.0 JavaScript exposes `window.FieldFlowAnalytics2` so React can apply context without duplicating the analytics engine.

## Safety boundaries

- No database migration.
- No IndexedDB migration.
- No REST contract change.
- No Flutter / FlutterFlow change.
- No field form React migration.
- No React or ReactDOM production dependency.
- SubmissionPipeline component build remains `2.2.485` because that component is unchanged.
- Submission, offline, draft/media, order suggestion mail and post-COMMIT side-effect contracts remain unchanged.
