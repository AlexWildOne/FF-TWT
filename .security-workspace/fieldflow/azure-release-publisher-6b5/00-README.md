# FieldFlow Release Publisher 6B.5

One-purpose Azure Function used by the WordPress Release Manager.

- Accepts only HMAC + Admin Secret authenticated uploads.
- Accepts only canonical `fieldflow/` ZIPs with a valid production manifest.
- Uses Managed Identity for Blob Storage.
- Writes immutable blobs to `fieldflow-private-releases`.
- Does not publish licensing metadata itself. WordPress calls the existing Licensing API only after the Blob upload succeeds.
- Exposes no storage keys or SAS credentials to WordPress or browsers.

This component is bootstrapped once. Normal releases after 2.2.396 are published from FieldFlow > Release Manager.
