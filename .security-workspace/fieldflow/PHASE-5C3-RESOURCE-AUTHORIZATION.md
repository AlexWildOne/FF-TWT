# FieldFlow Phase 5C.3, Resource Authorization

## Objective

Phase 5C.2 proves Microsoft Entra identity across tenants. Phase 5C.3 proves that the same federated FieldFlow identity is authorized to a concrete client resource.

The two states are deliberately independent:

- `READY, IDENTIDADE`: the TWT multi-tenant application can obtain a federated token in the client tenant.
- `READY, RECURSO`: a read-only probe succeeded against the concrete configured resource.

Neither state enables connector delivery. Phase 5C remains `SHADOW` and `delivery_enabled=false` throughout this release.

## Security boundary

WordPress stores only non-secret public identifiers and probe state. It never stores client passwords, SQL connection strings, access tokens, refresh tokens, client secrets or certificates.

The Azure Entra Broker:

1. authenticates the WordPress control-plane request with the certified TWT HMAC;
2. obtains the User Assigned Managed Identity assertion;
3. exchanges the assertion through the existing multi-tenant Workload Identity Federation model;
4. obtains the resource access token in Broker memory only;
5. executes one bounded read-only probe;
6. discards the token and returns only safe probe metadata.

No access token is returned to WordPress or written to logs.

## Supported resource probes

### Azure SQL

Required 5C.2 resource scope:

`https://database.windows.net/.default`

Stored configuration:

- public Azure SQL server hostname ending in `.database.windows.net`;
- database name.

Probe:

`SELECT 1 AS fieldflow_probe;`

The Broker uses TLS and Entra access-token authentication. It never stores a SQL username or password.

The database administrator must separately map/authorize the FieldFlow Enterprise Application in the client database. Phase 5C.3 never creates users, alters roles or grants permissions automatically.

### Power BI / Fabric Workspace

Required 5C.2 resource scope:

`https://analysis.windows.net/powerbi/api/.default`

Stored configuration:

- Workspace ID GUID.

Probe:

`GET https://api.powerbi.com/v1.0/myorg/groups/{workspaceId}`

The tenant administrator must separately allow service-principal API access where required and grant the FieldFlow Enterprise Application an appropriate workspace role. Phase 5C.3 does not change tenant settings or workspace membership.

### APIs externas e webhooks

APIs externas e webhooks não recebem access tokens nem executam resource probes na 5C.3. Permanecem em Connector Foundation Shadow para uma fase posterior, onde a audiência/token poderá ser ligada de forma explícita e segura ao endpoint do cliente.

## Resource authorization states

- `DRAFT`: no resource target configured.
- `CONFIGURADO`: resource identifiers saved; no proof of access yet.
- `ACESSO NÃO VALIDADO`: the latest read-only resource probe failed.
- `READY, RECURSO`: the latest read-only resource probe succeeded.

Changing the 5C.2 tenant or resource scope resets the 5C.3 state to `DRAFT` because the previous proof is no longer authoritative.

Restarting 5C.2 onboarding also resets the local 5C.3 proof. It does not remove any permissions already configured in the client resource.

## Out of scope in 2.2.366

This release does not:

- write data to Azure SQL;
- publish or refresh Power BI content;
- call custom APIs with access tokens;
- create SQL users or role grants;
- modify Power BI/Fabric tenant settings;
- add members to Power BI workspaces;
- rotate or create client credentials;
- resolve client secrets in WordPress;
- enable a 5C sender hook or delivery queue.

Those actions belong to later least-privilege delivery phases and must be enabled separately per resource type.

## Regression guarantees

The 2.2.365 mobile/offline suspension hardening remains unchanged. The final offline submission queue remains unchanged. Azure TWT ingest and the certified Client Azure 5B Relay runtime are not modified by the 5C.3 Broker deployment.

## 2.2.367 extension, Home TWT

The same read-only resource authorization engine now also supports an explicit `entra_home_tenant` strategy for Azure SQL and Power BI/Fabric resources that live in the TWT tenant. This path does not use external onboarding and automatically derives the configured TWT tenant ID plus the exact audience scope for the selected resource kind. Existing `managed_identity` destinations are not automatically converted. Delivery remains disabled.
