# FieldFlow 2.2.475 Offline Runtime Hardening

Source release: 2.2.474  
Stable release: 2.2.475  
Contract: `fieldflow-offline-runtime-hardening-v1`  
Release Gate: 1.26.0

## Objective

Harden the existing FieldFlow offline runtime without changing the operational form contract, REST routes, persisted submissions, Flutter source, Analytics data model or Azure delivery architecture.

This release is a stabilization release. It preserves the current WordPress/PHP backend and existing IndexedDB/outbox model while removing confirmed synchronization risks discovered during an end-to-end audit of the 2.2.474 runtime.

## Confirmed risks corrected

### Runtime identity continuity

The 2.2.474 asset filename was current, but its exported runtime identity still declared 2.2.472 in multiple markers. That could allow a stale WebView/browser runtime to be treated as current.

2.2.475 introduces one canonical `RUNTIME_BUILD` identity and the Release Manager package validator now rejects a release when the versioned offline runtime does not declare the exact plugin release version or when `ResponsiveAssets` does not reference that exact runtime file.

### Renewable synchronization lease

The previous local synchronization lock had a 30 second lifetime while form and media synchronization can legitimately take longer. The same WebView could also re-enter synchronization because multiple operations shared the same tab identity.

2.2.475 introduces:

- a minimum 120 second synchronization lease;
- a 10 second heartbeat while a lease is active;
- a unique lease token in addition to the tab identity;
- in-memory same-context re-entry protection;
- owner/token verification before renewal or release;
- Web Locks API coordination when supported, with the existing localStorage mechanism retained as fallback/compatibility protection.

Server-side mutation idempotency and strict `fieldflow-form-ack-v1` acknowledgement remain unchanged and continue to provide the final persistence safety net.

### Degraded network synchronization

A slow but usable mobile connection was previously classified as `degraded` and then treated as non-syncable by the general synchronization gate. This could accumulate submissions unnecessarily while a merchandiser still had working connectivity.

2.2.475 keeps the `degraded` state for diagnostics and adaptive behavior but allows normal synchronization attempts in both `online` and `degraded` states. Offline remains the hard network stop.

The route shortcodes use the same policy for operational fetches and check-in/check-out/failure events, so a lateral integration can no longer downgrade `degraded` back to offline behavior.

### Final media checkpoint

A recuperação de um rascunho pode reidratar uma fotografia original e a otimização final pode substituir o `File` vivo por uma versão comprimida. Antes desta release, existia uma janela em que o outbox podia serializar a versão otimizada e depois voltar a recuperar a versão original ainda guardada no IndexedDB.

2.2.475 faz um final media checkpoint depois da otimização e antes da criação do outbox, garantindo que a cópia durável e o formulário vivo representam o mesmo conjunto final de ficheiros.

### Operational date authority

Offline route prefetch used a UTC ISO date while WordPress uses the site operational timezone. Around local midnight this could request the previous operational day.

2.2.475 receives the authoritative operational date and site timezone from WordPress, learns the same date from `/offline/ping`, and uses a timezone-aware local fallback only when server/config authority is unavailable.

## Release integrity

The following controls are mandatory:

- plugin header, `FIELDFLOW_VERSION`, readme Stable tag and Phase 6 Stable release must all be 2.2.475;
- `assets/fieldflow-offline-runtime-2.2.475.js` must declare `RUNTIME_BUILD='2.2.475'`;
- the canonical `assets/fieldflow-offline-runtime.js` must be byte-equivalent to the versioned 2.2.475 runtime at build time;
- Release Manager validates the versioned runtime identity before publication;
- Release Gate validates the offline hardening contract after installation;
- canonical publication, package SHA-256, private updater, 6B revalidation and Stable certification remain mandatory.

## Compatibility

No database migration is required.

No Flutter source change is required.

No existing REST route is removed or renamed.

No persisted submission schema is changed.

No route, Analytics, Mail Center, Cardex, Shopping, stock or Azure business contract is intentionally changed by this stabilization release.

Existing historical versioned runtime assets remain in the package for release continuity, but only 2.2.475 is referenced by the active frontend asset loader.

## Mandatory Release Manager path

1. Generate the canonical ZIP with root `fieldflow/`.
2. Open `FieldFlow > Release Manager`.
3. Validate and publish the 2.2.475 package.
4. Confirm the private Stable catalog version and package SHA-256.
5. Install through the WordPress private updater.
6. Run `Revalidar cadeia 6B`.
7. Run `Testar fecho Fase 6`.
8. Run `Certificar Stable`.
9. Consider 2.2.475 closed only when the active runtime is certified Stable.
