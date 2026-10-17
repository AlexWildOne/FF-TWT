# Phase 8A, React Backoffice Foundation

Release: 2.2.486

Contract: `fieldflow-react-backoffice-foundation-v1`

## Objective

Introduce React into the FieldFlow backoffice as progressive islands without rewriting WordPress pages or changing the field runtime. The first pilot is deliberately read-only and reversible.

## Runtime

- React is provided by WordPress through `wp-element`; FieldFlow does not bundle a second React or `react-dom` dependency.
- The React bundle is compiled from strict TypeScript into `assets/fieldflow-react-backoffice-2.2.486.js`.
- `RoutesPro\Admin\ReactBackofficeFoundation` limits the pilot to `routespro-mobile` and exposes the `FIELDFLOW_REACT_BACKOFFICE` kill switch.
- The first island is `mobile-release-status` in the Mobile Control Center overview.
- The existing PHP card remains the PHP fallback. If React or `wp-element` does not mount, the control center remains usable with the server-rendered content.

## Pilot island

`mobile-release-status` progressively enhances the existing publication-status card with React state for Android/iOS navigation while preserving all server-derived values and permissions.

The pilot does not own writes, REST mutations, forms, routes, analytics calculations or field reporting. It is intentionally a read-only UI island.

## Certification

- `frontend/tests/react-backoffice.test.mjs` verifies release identity and progressive mounting.
- `tools/verify-react-backoffice-foundation.php` verifies the WordPress enqueue, strict TypeScript build, pilot mount, PHP fallback and absence of bundled React dependencies.
- Release Manager requires `react_backoffice_foundation_ok`.
- Release Gate 1.37.0 requires the Phase 8A contract before stable certification.
- Release continuity keeps `SubmissionPipeline::BUILD` at `2.2.485` because that component is unchanged; the 2.2.486 validator certifies the side-effect contract/version instead of coupling an unchanged component build to the package version. This keeps N -> N+1 publication compatible with the installed Release Manager.

No database migration. No IndexedDB migration. No REST contract change. No FlutterFlow change. No form-runtime change.
