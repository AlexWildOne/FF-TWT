# FieldFlow Phase 7ZK, Evidence ZIP Runtime v3

Release: 2.2.469  
Source release: 2.2.468  
Release Gate: 1.20.0  
Contract: `fieldflow-evidence-zip-runtime-v3`

## Objective

Remove the structural HTTP 500 path from Analytics evidence ZIP downloads without changing Flutter, the database schema, route logic, forms, Cardex, Mail Center or the 2.2.467 Operational Intelligence behaviour.

A production reproduction with a single image still returned HTTP 500 after 2.2.468. That ruled out archive volume as the primary fault and showed that the create-and-download request path itself remained fragile.

## Runtime design

1. Analytics keeps generating a user-bound evidence token for every authorised store/date group.
2. The browser sends only that token to `POST /routespro/v1/analytics/evidence-archive`. It never posts an arbitrary list of media URLs.
3. The server resolves the original scoped transient and builds the archive from the exact files already authorised by Analytics.
4. A portable STORE-only ZIP32 writer is the primary engine. It streams source media in 1 MiB chunks and does not depend on ZipArchive/libzip.
5. ZipArchive and PclZip remain compatibility fallbacks only if the portable writer cannot complete.
6. The completed archive is validated independently through the Local File Header, Central Directory and EOCD structures.
7. The prepare response returns a second, short-lived, user-bound download token. The final `admin-post.php` request only streams the already-created ZIP and deletes it after delivery. It no longer performs archive generation.
8. Historical/recovered evidence may resolve through Media Registry `storage_path` when WordPress attachment mapping no longer resolves the local file.

## Security invariants

- The REST prepare endpoint accepts a server-issued 40-character evidence token, not client-selected file URLs.
- Both prepare and final download remain bound to the authenticated WordPress user and existing FieldFlow front permissions.
- The prepared-download URL uses its own nonce and transient, with a short TTL.
- The final path is revalidated against `uploads/fieldflow-evidence-exports` before streaming.
- Prepared ZIP names include a long random suffix and are not returned as public upload URLs.
- Invalid, expired or missing tokens use explicit 4xx responses instead of collapsing controlled failures into a generic HTTP 500.
- The archive is removed after a successful stream. Existing stale-file cleanup remains as a secondary safety net.

## Build verification

`tools/verify-evidence-zip-runtime-v3.php` validates the portable writer, independent ZIP validation, Media Registry recovery, server-bound prepare token, temporary download token, runtime hook, front and Client Portal token-only requests, version continuity and an executable two-file ZIP fixture.

No database migration is required. No Flutter source is changed.
