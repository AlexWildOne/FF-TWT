# FieldFlow Phase 5A, Multi-Destination Foundation

## Baseline

Built only from FieldFlow 2.2.353. Release output is 2.2.354.

## Design principle

The certified TWT Azure path is not replaced. `AzureSyncFoundation` remains the only production delivery path in Phase 5A.

The new foundation adds a control plane beside it:

1. Destination Types
2. Destination Registry
3. Routing Rules
4. Deterministic delivery keys
5. Local routing preview and health checks

External connectors remain in shadow mode until Phase 5B/5C.

## WordPress tables

- `wp_routespro_cloud_destination_types`
- `wp_routespro_cloud_destinations`
- `wp_routespro_cloud_routing_rules`

The actual WordPress prefix can differ.

## Scope model

Every destination and routing rule can be scoped to:

- tenant
- client
- client + project/campaign

A project scope without a client scope is rejected.

## Destination types

- `twt_azure`
- `client_azure`
- `external_api`
- `client_powerbi`
- `webhook`
- `data_warehouse`

## Primary TWT destination

A deterministic destination UUID is generated from the FieldFlow tenant ID and the logical `twt_azure|primary` identity.

It is automatically registered and cannot be disabled or deleted from the Phase 5A UI.

Its endpoint and HMAC values are not copied into the new registry. The primary row only contains a non-sensitive legacy connector reference. Actual delivery continues through the existing HMAC v1 code.

## Idempotency

The EventBus algorithm is not changed.

Phase 5A adds a deterministic future delivery key:

`SHA256(fieldflow-delivery-v1 | event_uuid | destination_uuid)`

This means one Event UUID can fan out safely to multiple destination UUIDs in later phases without using the same idempotency key across destinations.

## Secrets

New external destinations never accept raw secrets in the registry.

Accepted `secret_ref` forms:

- `kv://vault-name/secret-name`
- `https://vault-name.vault.azure.net/secrets/secret-name`

The actual secret value belongs in Azure Key Vault.

## Backoffice

New menu:

`FieldFlow > Destinos de Dados`

It provides:

- foundation health
- local probe
- destination registry
- routing rules
- route preview by event/client/project

No preview or local probe sends data outside WordPress.

## Azure package

Folder `azure-multi-destination` creates a separate `ctl` schema in Azure SQL.

It does not alter `dbo`, `sec`, `rpt` or `rpt_admin`, and it does not change existing Power BI or ingest permissions.
