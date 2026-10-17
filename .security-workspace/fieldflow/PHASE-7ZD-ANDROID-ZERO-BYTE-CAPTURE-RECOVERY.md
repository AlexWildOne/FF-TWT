# Phase 7ZD - Android Zero-byte Capture Recovery

FieldFlow 2.2.462 closes the remaining Android/WebView media failure where image optimisation could receive a valid source File but `canvas.toBlob()` returned a zero-byte Blob under memory pressure.

## Runtime contract
- `fieldflow-image-zero-byte-fallback-v1` preserves the original image whenever the optimised Blob is empty.
- `fieldflow-zero-byte-media-draft-recovery-v1` recovers legacy queued media from `fieldflow-form-drafts` before retrying.
- Empty selections never overwrite a previously durable draft image.
- Required evidence counts only files with real bytes.
- Offline runtime is loaded from `fieldflow-offline-runtime-2.2.462.js` to bypass stale WebView/PWA/proxy asset caches.
- Real photos retain `storage-capacity-only` sizing, 512 KiB resumable chunks, SHA-256 integrity and installation certification.

No database migration is required.
