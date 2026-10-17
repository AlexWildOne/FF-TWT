# FieldFlow 2.2.476 Frontend Build & TypeScript Foundation

Contract: `fieldflow-frontend-build-foundation-v1`

## Objective

Introduce a structured, typed frontend source-of-truth without changing existing operational business logic. WordPress continues to serve ordinary JavaScript assets. TypeScript exists only at build time and compiles to a versioned browser asset in `assets/`.

## Build foundation

- `frontend/package.json` defines build, strict typecheck and dependency-free behavioural test commands.
- `frontend/tsconfig.json` enables strict TypeScript validation and emits no browser modules.
- `frontend/tools/build.mjs` reads the canonical version from `fieldflow.php`, generates the typed version source and emits `assets/fieldflow-frontend-core-<version>.js`.
- The emitted bundle is fully scope-isolated and safe if the browser or WebView attempts to evaluate the same asset more than once.
- `frontend/tests/frontend-build.test.mjs` verifies bundle identity, browser API exposure, offline runtime visibility and double-load idempotence.

## Runtime contract

The compiled foundation exposes `window.FieldFlowFrontend` with:

- exact release identity;
- `fieldflow-frontend-build-foundation-v1` build contract;
- `source: typescript` identity;
- front/admin context;
- native WebView context;
- a typed runtime snapshot that can inspect the current offline runtime build;
- a small event API for future typed modules.

No existing operational form, route, Analytics or offline business logic is migrated in this release.

## Release Manager

Release Manager remains the only normal publication path. Package validation now rejects a release when:

- the compiled TypeScript asset is missing;
- its embedded release identity differs from `fieldflow.php`;
- the build contract marker is missing;
- `ResponsiveAssets` does not reference the exact versioned compiled asset.

This extends the same exact-version rule already applied to the offline runtime.

## React boundary

React is intentionally not introduced in 2.2.476. A later release may add React selectively to Backoffice surfaces such as Analytics, Route Planner or Mail Center after this build foundation is certified. The operational field form remains outside that first React migration.

## Compatibility

- No database migration.
- No REST contract change.
- No IndexedDB schema change.
- No Flutter source change.
- No form schema change.
- No Analytics data-contract change.
- No Mail Center business-rule change.
- No Azure contract change.
