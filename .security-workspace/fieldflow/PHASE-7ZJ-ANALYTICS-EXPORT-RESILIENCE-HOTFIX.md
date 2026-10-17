# FieldFlow 2.2.468 - Analytics Export & Evidence ZIP Resilience Hotfix

FieldFlow 2.2.468 is a narrow production hotfix built on 2.2.467. It changes the WordPress plugin only. No Flutter source, API route name, database schema or persisted operational data is migrated.

## Incident

Analytics evidence downloads could terminate with HTTP 500 when a store/date contained many photographs. The existing archive path copied every source image into a staging directory and only then built the ZIP. That doubled disk I/O and temporary storage, while ZipArchive also attempted to deflate already-compressed images. Media Shadow observability additionally performed per-file database work during render/export paths.

## Fix

- Normal ZipArchive flow adds validated source media directly to an atomic `.part` archive. Full staging remains only as a legacy PclZip fallback when ZipArchive is unavailable.
- Unreadable and zero-byte media are skipped instead of poisoning the archive.
- JPEG, PNG, GIF, WebP, HEIC/HEIF, PDF and other compressed formats use STORE instead of redundant deflate.
- The temporary ZIP is consistency-checked and entry-count validated before promotion to the final filename.
- Final downloads stream in 1 MiB chunks with binary-safe headers, disabled HTTP compression and the PHP session lock released.
- Expired `.zip`, `.part` and legacy staging directories are removed automatically.
- Media Shadow remains observe-only, but rendering is sampled and export_full avoids O(n) telemetry database writes because authorization has already been enforced by Analytics scope.
- Long Analytics exports also release the PHP session lock before report preparation/streaming.

## Safety invariants

- Existing Analytics permissions and client/project/user/location filters remain authoritative.
- CSV, XLSX, XLSX mobile and PDF structures remain unchanged.
- Evidence bytes are not transformed or recompressed when stored in the ZIP.
- The 500-file and 500 MiB default archive ceilings remain configurable through filters, preventing unbounded requests.
- No database migration.
- No Flutter modification.

## Certification

- Release Gate 1.19.0 adds `fieldflow-analytics-export-resilience-v1`.
- Build verifier: `tools/verify-analytics-export-resilience.php`.
- SOURCE_RELEASE 2.2.467. STABLE_RELEASE 2.2.468.
- The exact canonical ZIP must still be published through WordPress Release Manager, installed from the private Stable catalog and revalidated through 6B / Phase 6 Stable closure.
