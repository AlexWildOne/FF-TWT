## 2.2.494 front full width and navigation gate

Release Gate 1.45.0 adds `fieldflow-front-fullwidth-navigation-v1`. Certification requires the full authorised React shortcut set, canonical button-targeted proxy navigation, shared full-width mobile markers for Briefings, Implementações, Stocks, Shopping, Planear, Despesas and Mensagens, continuous mobile document scrolling, exact 2.2.494 runtime assets, N→N+1 continuity from 2.2.493, and an unchanged certified `SubmissionPipeline BUILD` of 2.2.485.

## 2.2.493 modernization closure gate

Release Gate 1.44.0 adds `fieldflow-modernization-stable-closure-v1` and `fieldflow-react-media-control-v1`. Certification now requires the final React media proxy surface, intentional PHP/native fallbacks, exact 2.2.493 runtime assets, N→N+1 continuity from 2.2.492, and preserved canonical ownership for IndexedDB/recovery, ProductCardex, submission persistence and side effects.

## 2.2.486 gate

The stable matrix keeps the complete Phase 5/6 chain and adds the certified `fieldflow-react-backoffice-foundation-v1` contract. Release Manager must validate the exact 2.2.486 offline runtime, TypeScript frontend core and versioned React backoffice bundle, plus WordPress `wp-element` integration, the `routespro-mobile` pilot mount and the server-rendered PHP fallback before publication. Release Gate 1.37.0 blocks certification if the React island is bundled but not mounted, if FieldFlow ships its own React dependency, or if the fallback is removed.

# FieldFlow Release Certification Gate

Introduced in FieldFlow 2.2.429.

Continuity: FieldFlow 2.2.485 Submission Events & Side Effects and all inherited contracts remain protected by this mandatory gate, including FieldFlow 2.2.459 Shopping Attachments, Store Learning & Stable Continuity, FieldFlow 2.2.458 Shopping Outlook Newsletter & Resumable Gate Hotfix, FieldFlow 2.2.457 Shopping Shared Team Scope, 2.2.456 Shopping Insignia & Resend, 2.2.455 Shopping Mail & Merchandising Flow, 2.2.454 Resumable Media Transport, 2.2.453 Offline Media Rehydration Hotfix, 2.2.452 Resilient Media & Azure Offline Trust Foundation, 2.2.451 Offline Operations Intelligence, the 2.2.450 Upgrade Bootstrap Timeout Hotfix and the 2.2.449 Offline Zero-Loss Foundation remain fully protected by this mandatory gate. Runtime-only upgrades, offline form persistence, strict server acknowledgement, connectivity probing and PWA resilience cannot bypass canonical publication evidence, exact-runtime integrity or Stable certification.

## Rule

Every new build has two different states: published and certified. Publishing makes the package available. Certification proves that the exact package is installed, its manifest is intact, the private updater and licensing chain are healthy, and the Phase 6 Stable safety invariants still hold.

## Release Manager status

The pipeline is complete only when all of the following are true:

- canonical `fieldflow/fieldflow.php` installation;
- Release Publisher health is OK;
- publication was recorded by WordPress;
- private catalog contains the published version;
- package SHA-256 is recorded;
- runtime received the published version;
- Phase 6 baseline fingerprint matches;
- Stable certification belongs to the active runtime version.

## Certification sequence

### 1. Revalidate 6B

Refreshes heartbeat and explicitly re-certifies 6B.2 Activation/Identity/Heartbeat, 6B.3 Governance/Rotation/Limits and 6B.4 Private Distribution/Updates.

### 2. Test Phase 6 closure

Runs the Stable local probe against the current manifest, private catalog, installation identity, activation binding, governance SHADOW invariants, update protections and Client Azure safety state.

### 3. Certify Stable

Creates a new Phase 6 Stable snapshot and fingerprint for the exact active release. The snapshot includes canonical Release Manager publication evidence and package SHA-256.

## Non-goals

This gate does not enable production delivery, full operational payloads, runtime license blocking, forced updates, remote runtime revocation or automatic rollback.


## 2.2.485 Submission Events & Side Effects continuity
SOURCE_RELEASE 2.2.484. STABLE_RELEASE 2.2.485. Release Gate 1.36.0 requires `fieldflow-submission-side-effects-v1`, `RoutesPro\Forms\SubmissionSideEffectDispatcher`, isolated post-commit lanes with per-revision idempotency and per-lane retry, non-blocking post-COMMIT mobile media/audit observers, preserved public WordPress hooks and Release Manager `submission_side_effects_ok`. Canonical Release Manager publication, private Stable catalog evidence, package SHA-256 and Azure-backed 6B revalidation remain mandatory.

## 2.2.484 Submission Application Service continuity
SOURCE_RELEASE 2.2.483. STABLE_RELEASE 2.2.484. Release Gate 1.35.0 requires the shared `RoutesPro\Forms\SubmissionApplicationService`, web/mobile durable persistence delegation, preserved legacy mobile transaction semantics, canonical pipeline-envelope construction and Release Manager `submission_application_service_ok`. Secondary mail, Analytics, Azure, export and route-notification effects remain outside the service. Canonical Release Manager publication, private Stable catalog evidence, package SHA-256 and Azure-backed 6B revalidation remain mandatory.




## 2.2.479 Typed Draft & Media Core continuity
SOURCE_RELEASE 2.2.478. STABLE_RELEASE 2.2.479. Release Gate 1.30.0 requires the compiled `fieldflow-typed-draft-media-core-v1`, progressive FormRenderer integration, unchanged `fieldflow-form-drafts` schema v2, Release Manager `typed_draft_media_core_ok`, exact runtime/frontend identity and the complete inherited 6B certification chain.

## 2.2.475 Offline Runtime Hardening continuity
SOURCE_RELEASE 2.2.474. STABLE_RELEASE 2.2.475. Release Gate 1.26.0 requires the exact 2.2.475 offline runtime identity, renewable synchronization lease markers, degraded-network synchronization, WordPress operational-date authority and Release Manager runtime-identity validation. All canonical publication, package SHA-256, private Stable catalog and Azure-backed 6B requirements remain unchanged.

## 2.2.459 Shopping Attachments, Store Learning & Stable Continuity
SOURCE_RELEASE 2.2.458. STABLE_RELEASE 2.2.459. Release Gate 1.13.0 requires Shopping schema 1.3.0, the learned insignia-location table, XLSX/PDF attachment generation and the guarded front association endpoint. The existing Release Manager, private Stable catalog, SHA-256 and Azure-backed 6B requirements remain unchanged.

## 2.2.458 Shopping Outlook Newsletter & Resumable Gate Hotfix continuity
SOURCE_RELEASE 2.2.457. STABLE_RELEASE 2.2.458. Release Gate 1.12.0 preserves every 2.2.454 Resumable Media Transport marker but fixes the PHP string representation used to search for `hash_file('sha256',`, eliminating a false-negative gate result. Shopping delivery now uses an Outlook-safe newsletter shell without changing recipient scope, resend auditing, shared-team Shopping visibility or any Azure-backed 6B requirement.

## 2.2.457 Shopping Shared Team Scope continuity
SOURCE_RELEASE 2.2.456. STABLE_RELEASE 2.2.457. Release Gate 1.11.0 blocks certification if any active/draft Shopping assignment is still restricted to one merchandiser, if the front store selector filters valid stores by campaign-location ownership, or if Shopping schema 1.2.1 is not active. Project membership remains the access boundary. Canonical Release Manager publication, private Stable catalog evidence, package SHA-256 and the Azure-backed 6B chain remain mandatory.

## 2.2.456 Shopping Insignia & Resend continuity
SOURCE_RELEASE 2.2.455. STABLE_RELEASE 2.2.456. Release Gate 1.10.0 blocks certification if Shopping still depends on a fixed PDV, if the front does not require an explicit store within the selected insignia, if manual resend does not use the current batch recipient selection, if legacy assignments cannot be consolidated safely, or if Shopping schema 1.2.0 is not active. Certification remains strict: direct ZIP installation alone is not canonical Stable evidence and the current package must still be published through Release Manager and revalidated through 6B before Stable closure.

## 2.2.455 Shopping Mail & Merchandising Flow continuity
SOURCE_RELEASE 2.2.454. STABLE_RELEASE 2.2.455. Release Gate 1.9.0 blocks certification if the Shopping recipient directory, per-recipient audited mail flow, manual resend action, Mail Center visibility, imported-order merchandising view or Shopping schema 1.1.0 columns are missing. The 2.2.454 resumable-media contract and all inherited Azure 6B, licensing, Offline Trust and Zero-Loss requirements remain mandatory.

## 2.2.454 Resumable Media Transport continuity
SOURCE_RELEASE 2.2.453. STABLE_RELEASE 2.2.454. Release Gate 1.8.0 blocks certification if authenticated chunk sessions, missing-chunk resume, SHA-256 reconstruction, signed media references or integration with the normal form upload pipeline are missing. The 2.2.453 media rehydration, 2.2.452 media-integrity and shadow Offline Trust, 2.2.451 device intelligence, 2.2.450 runtime-only upgrade and 2.2.449 Zero-Loss contracts remain mandatory.

## 2.2.453 Offline Media Rehydration continuity
SOURCE_RELEASE 2.2.452. STABLE_RELEASE 2.2.453. Release Gate 1.7.0 blocks certification if media restored from IndexedDB or the server draft cannot rebuild the visible upload preview immediately. This hotfix preserves the 2.2.452 media-integrity and shadow Offline Trust contracts unchanged.

## 2.2.452 Resilient Media & Azure Offline Trust continuity
SOURCE_RELEASE 2.2.451. STABLE_RELEASE 2.2.452. Release Gate 1.6.0 blocks certification if server-side device pagination, media integrity validation, the shadow Offline Trust lease or the inherited Zero-Loss and Offline Operations Intelligence contracts are missing. The lease is derived from the current Phase 6 Stable state and remains non-enforcing.

## 2.2.451 Offline Operations Intelligence continuity
SOURCE_RELEASE 2.2.450. STABLE_RELEASE 2.2.451. Release Gate 1.5.0 blocks certification if device telemetry, bounded sync history, form definition identity or the Offline Zero-Loss safety markers are missing.

## 2.2.450 Upgrade Bootstrap Timeout Hotfix continuity
SOURCE_RELEASE 2.2.449. STABLE_RELEASE 2.2.450. Release Gate 1.4.0 blocks certification if runtime and schema versions are not separated, if the runtime-only path is not evaluated before the schema upgrade lock, or if the installed runtime/schema evidence does not match the certified release.

## 2.2.449 Offline Zero-Loss continuity
SOURCE_RELEASE 2.2.448. STABLE_RELEASE 2.2.449. Release Gate 1.3.0 blocks certification if the outbox-first runtime, strict `fieldflow-form-ack-v1`, authenticated `/offline/ping` contract, form integration or hardened PWA service worker is missing.

## 2.2.448 Front Analytics Parity continuity
SOURCE_RELEASE 2.2.447. STABLE_RELEASE 2.2.448. Front Client and Merchandiser Analytics share the same grounded intelligence contract while preserving audience scope, fail-open Store enrichment, read-only Ask FieldFlow and the exact-runtime Azure Stable certification gate.
