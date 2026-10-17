# FieldFlow Phase 7T, Resilient Media & Azure Offline Trust Foundation

Release: 2.2.452
Source release: 2.2.451
Release Gate: 1.6.0
Trust protocol: `fieldflow-offline-trust-v1`
Media protocol: `fieldflow-media-integrity-v1`

## Objective

Extend the 2.2.449 Offline Zero-Loss and 2.2.451 Offline Operations Intelligence contracts without making live connectivity a prerequisite for field work. The release adds scalable device administration, integrity evidence for offline media and a bounded trust lease derived from the exact current Phase 6 Stable certification state.

## Device control pagination

`FieldFlow > Dispositivos` uses server-side pagination with 25 rows by default and 25, 50 or 100 row choices. Search covers user, email, device identity, platform and app version. Administrative actions preserve the active page, search and page-size context.

## Resilient media foundation

The runtime builds a lightweight bounded media manifest and persists the form in the durable outbox first. Only after the outbox row is protected does replay complete SHA-256 calculation when Web Crypto is available. Each file records its sanitized name, byte length, MIME type, last-modified timestamp and optional SHA-256. The replay request carries the completed manifest with the protected form payload.

The server validates file count, names, sizes, total bytes and any supplied SHA-256 digests before accepting the replay. A mismatch returns a terminal integrity error while the Zero-Loss outbox retains the local report in a recoverable attention state.

This release does not implement chunked or resumable byte-range upload. It improves integrity, diagnostics and retry timing while preserving whole-payload durable replay. Chunked media transport can be introduced later without changing the Zero-Loss acknowledgement contract.

## Adaptive replay timeout

Form replay timeout is adjusted to pending media volume and effective connection quality, with a bounded maximum. This reduces false timeouts on large photographic reports while retaining network-degraded store-and-forward behavior.

## Azure-backed Offline Trust foundation

The `/offline/trust-lease` endpoint can issue a locally signed `fieldflow-offline-trust-v1` lease only when the active runtime has a current Phase 6 Stable certification snapshot and fingerprint for the exact release. The lease is bound to user, device hash, release and certification fingerprint and has a limited lifetime.

The lease is a local continuity artefact derived from Azure-backed Stable certification. It is not an Azure cryptographic signature and it does not replace the Release Manager, 6B revalidation or Stable certification gate.

In 2.2.452 the lease runs in shadow mode. Missing, expired or pending lease state does not block field work. This prevents temporary lack of network from becoming an operational dependency while giving the server and backoffice verifiable evidence of which certified state protected an offline report.

## Operations Intelligence additions

Per-device telemetry now includes lease state, expiry, certified release and pending media file/byte totals. The control center surfaces this evidence without copying form answers or photographs into telemetry.

## Certification

2.2.452 remains a candidate until the exact canonical ZIP is published by the WordPress Release Manager, installed through the private updater, revalidated through 6B, tested through Phase 6 closure and certified Stable. Release Gate 1.6.0 rejects the release if the pagination, media-integrity or trust-foundation markers are incomplete.

## Database

No database migration is required. Device telemetry continues to use the existing bounded `meta_json` channel and the runtime-only upgrade path remains active with schema baseline 2.2.436.
