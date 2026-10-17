# FieldFlow Phase 6B.4, Private Distribution & Updates

Release: 2.2.389
Licensing backend target: 1.0.5

## Objective

Close the functional implementation of Phase 6 with a private, licensed software distribution channel for FieldFlow. The WordPress installation can discover newer FieldFlow releases through the existing HMAC-protected licensing service, expose them through the native WordPress plugin updater and download the ZIP only for an active, bound installation.

## Safety state

2.2.389 deliberately keeps runtime licensing enforcement unchanged:

- governance enforcement: SHADOW;
- runtime blocking: OFF;
- forced auto-updates: OFF;
- automatic downgrades: OFF;
- mandatory installation binding enforcement: OFF;
- Azure outage behaviour: fail-open for the FieldFlow application runtime;
- Production Enabled: OFF;
- full operational payload delivery: OFF.

Private update failure never disables the current FieldFlow installation.

## Private release catalog

Backend 1.0.5 adds `FieldFlowUpdateReleases` in the existing Azure Table Storage account. No Azure SQL or WordPress schema migration is required.

A published release records:

- version and channel, `stable` or `beta`;
- immutable versioned Blob path;
- SHA-256 and byte size calculated by the backend from the Blob content;
- WordPress/PHP compatibility metadata;
- release notes;
- allowed plan list;
- publication state and timestamp.

The release package remains in a private Blob container. A client never receives storage keys or a permanent Blob URL.

## Licensed update discovery

`POST /license/updates` with `operation=check` requires the same HMAC contract as the rest of licensing plus:

- active license key;
- valid Activation ID;
- matching Installation UUID/fingerprint;
- allowed plan;
- requested release channel;
- current FieldFlow, WordPress and PHP versions.

The response includes a canonical release manifest authenticated by HMAC-SHA256. The WordPress client independently recalculates that signature before trusting release metadata.

## Package delivery

When an actual download is requested, the backend returns a short-lived signed token URL under the trusted licensing Function hostname. The GET endpoint validates the token, license, activation and installation again, then reads the private Blob server-side.

Before returning bytes, the backend recalculates package SHA-256 and validates the recorded byte size. The WordPress client repeats SHA-256 validation after download and checks that the ZIP contains the expected FieldFlow version.

No raw license key, Azure storage key or permanent package credential is placed in the download URL.

## WordPress update integration

`PrivatePluginUpdater` integrates with the standard WordPress plugin update flow through:

- `pre_set_site_transient_update_plugins`;
- `plugins_api`;
- `upgrader_pre_download`;
- `upgrader_process_complete`.

Normal update discovery is cached for four hours and does not perform package downloads. When WordPress actually starts an update, FieldFlow requests a fresh short-lived package URL, downloads the package itself, verifies its signed manifest and SHA-256, and only then returns the verified temporary ZIP to the WordPress upgrader.

This avoids expired URLs inside WordPress update transients.

## Downgrade and rollback policy

The private updater only advertises a release when `latest_version > installed_version`. A catalog older than the current runtime is never injected as an update.

Rollback is deliberately `manual_explicit`: older published releases may remain retained centrally, but 2.2.389 never performs or offers an automatic downgrade. Manual ZIP replacement remains the emergency fallback and is an explicit administrator action.

## Update channels

The local site can select `stable` or `beta`. The channel is stored as a non-secret WordPress option. Changing channel clears only the update discovery cache and does not install or remove anything.

Release access is also constrained server-side by each release's allowed plan list.

## Certification gate

6B.4 certification requires:

- 6B.3 still certified;
- production manifest valid;
- remote licensing and Activation ID ready;
- WordPress private updater hooks registered;
- backend 1.0.5 acknowledgement of Phase 6B.4;
- private catalog, licensed downloads, signed manifest and package SHA-256 capabilities acknowledged;
- signed release manifest verified locally;
- trusted HTTPS package host;
- end-to-end temporary download with exact SHA-256 and byte size;
- ZIP contains the expected FieldFlow version;
- remote catalog is not behind the installed runtime;
- downgrade protection active;
- forced auto-updates off;
- runtime blocking still off and governance still SHADOW;
- rollback mode remains manual/explicit.

The test downloads a package only to a temporary file, validates it, deletes it and does not install it.

## Next step

After 6B.4 is deployed and certified, no functional 6B subphase remains. The next step is the final Phase 6 Stable Closure, combining 6A, 6B.1, 6B.2, 6B.3 and 6B.4 into a single certified release baseline and documenting the supported upgrade, rollback and production distribution procedure.
