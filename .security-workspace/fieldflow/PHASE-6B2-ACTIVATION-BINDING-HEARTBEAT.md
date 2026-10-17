# FieldFlow Phase 6B.2, Activation, Installation Binding & Heartbeat

Release: 2.2.383

## Objective

Extend the certified 6B.1 licensing foundation with a safe installation lifecycle. 6B.2 gives each FieldFlow installation a persistent technical identity, sends that identity during remote activation, performs periodic heartbeats against the existing Azure licensing validation route and records a local registry view when the current installation belongs to a tenant known by the TWT master site.

## Safety state

6B.2 remains observational. The following controls stay hard disabled:

- license runtime enforcement;
- mandatory installation binding;
- remote revocation;
- Production Enabled;
- full operational payload delivery.

A failed heartbeat never blocks FieldFlow and never changes an active licence into a revoked or disabled state.

## Installation identity

A persistent UUID is stored in `fieldflow_phase6b2_installation_identity`.

The installation fingerprint is a non-reversible SHA-256 derived from:

- product id;
- installation UUID;
- normalized current domain.

If a WordPress database is cloned to a different normalized domain, 6B.2 rotates the installation UUID and keeps only the SHA-256 of the previous UUID for audit. This prevents two domains from silently sharing one installation identity.

## Activation contract

The existing `/license/activate` request remains backwards compatible and now also carries:

- `installation_id` / `installation_uuid`;
- `installation_fingerprint`;
- plugin version;
- WordPress version;
- PHP version;
- environment hash.

Legacy `domain`, `site_url` and `fingerprint` are preserved.

## Heartbeat contract

For compatibility with the deployed licensing service, heartbeat uses `POST /license/validate` with:

- the encrypted-at-rest licence key loaded only in memory for the request;
- activation id when available;
- current domain and legacy fingerprint;
- installation UUID and SHA-256 installation fingerprint;
- `heartbeat=true`;
- Phase 6B.2 version markers.

The default outbound cadence is every 6 hours and can be configured from 1 to 72 hours. WordPress performs an hourly scheduling sweep and sends nothing while the configured cadence is not due.

A transport-successful heartbeat requires:

- HTTP 2xx;
- `success=true`;
- compatible active/trial status when returned;
- returned domain to match when present;
- returned activation id to match when both sides provide one.

For **final 6B.2 certification**, the Azure service must additionally acknowledge the 6B.2 contract by returning the current `installation_uuid`, its `installation_fingerprint`, and `heartbeat_recorded=true`. This deliberately prevents the phase from being certified against an older server that merely ignores the new metadata.

## Local installation registry

The table `routespro_license_installations` was already created in 6B.1. 6B.2 starts using it without a new WordPress schema migration.

When the current site can match its licence to a local 6B tenant by remote licence id or SHA-256 key fingerprint, FieldFlow upserts:

- installation UUID;
- tenant id;
- domain and non-reversible fingerprint;
- activation id;
- plugin version;
- activated / last seen timestamps;
- safe environment metadata.

No raw licence key is written to this table or to the governance ledger.

## Certification gate

6B.2 certification requires:

- 6B.1 still certified;
- production manifest valid;
- installation identity valid;
- remote licensing mode and API ready;
- a real active remote licence with licence id and activation id;
- current domain binding match;
- a recent accepted heartbeat;
- heartbeat installation UUID and fingerprint match the current installation;
- Azure acknowledges the same installation UUID and SHA-256 fingerprint;
- Azure confirms `heartbeat_recorded=true`;
- raw key not persisted by 6B.2;
- enforcement, mandatory binding and remote revocation all OFF.

## Deferred to 6B.3

6B.2 does not:

- revoke an installation automatically;
- block duplicated installations;
- rotate licence keys;
- enforce max installations or SaaS plan limits;
- disable FieldFlow because Azure is unavailable.
