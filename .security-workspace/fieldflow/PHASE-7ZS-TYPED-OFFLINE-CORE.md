# Phase 7ZS - Typed Offline Core

Release: `2.2.477`
Contract: `fieldflow-typed-offline-core-v1`

## Objective

Move the first high-risk pure decisions of FieldFlow offline execution into strict TypeScript without replacing the certified runtime, changing public APIs, or altering storage/database contracts.

## Typed modules

- `frontend/src/offline/connectivity.ts` owns normalization and usable-network policy. `degraded` remains usable.
- `frontend/src/offline/sync-lock.ts` owns lease parsing, owner/token validation, foreign active lease detection, create and renew semantics.
- `frontend/src/offline/outbox.ts` owns retry delay, queue eligibility, stale syncing recovery and creation-order comparison.
- `frontend/src/offline/offline-core.ts` publishes the immutable `fieldflow-typed-offline-core-v1` contract through `FieldFlowFrontend.offlineCore`.

## Runtime compatibility

The existing `window.FieldFlowOffline` contract remains unchanged. The 2.2.477 runtime resolves the typed core dynamically and uses it when available. Every migrated decision retains the previous JavaScript implementation as a fallback, so a missing frontend bundle cannot disable offline operations.

No IndexedDB schema change is introduced. No REST contract changes are introduced. No database migration. No React is introduced in this release.

## Release Manager

The Release Manager remains the mandatory publication path. Package validation now requires:

- exact plugin/runtime/frontend version identity;
- the compiled `fieldflow-typed-offline-core-v1` contract;
- typed connectivity, sync-lock and outbox modules inside the frontend bundle;
- runtime integration markers for all three modules;
- `typed_offline_core_ok=true` before publication.

## Verification

`frontend/npm verify` must pass strict typecheck plus behavioural tests. `tools/verify-typed-offline-core.php` verifies source, compiled asset, runtime integration, Release Gate and Release Manager wiring.

No database migration.
