# FieldFlow Phase 5C.3, Home Tenant Resource Authorization

## Objective

Release 2.2.367 extends the Phase 5C.3 read-only resource authorization control plane so it can validate resources that live in the TWT Microsoft Entra tenant as well as resources in an external tenant already onboarded in Phase 5C.2.

This is not a delivery release.

## Identity modes

### External tenant

- destination auth strategy: `entra_cross_tenant`;
- Phase 5C.2 onboarding must be `READY, IDENTIDADE`;
- target tenant and resource scope come from the signed external-tenant identity profile;
- the 5C.3 Broker performs the concrete resource probe.

### Home TWT tenant

- destination auth strategy: `entra_home_tenant`;
- no external onboarding is required;
- target tenant is the configured TWT home tenant;
- scope is derived from the resource kind and cannot be freely overridden by the destination;
- the same Entra Broker 1.3.0 performs the concrete resource probe through Workload Identity Federation.

`entra_home_tenant` is explicit. Existing `managed_identity` destinations are not silently reinterpreted as home-tenant destinations.

## Supported resources

### Power BI / Fabric Workspace

- type key: `client_powerbi`;
- exact audience: `https://analysis.windows.net/powerbi/api/.default`;
- resource identifier: Workspace ID GUID;
- Broker operation: `GET https://api.powerbi.com/v1.0/myorg/groups/{workspaceId}`;
- response content is not returned to WordPress;
- no report refresh, publish, write or dataset mutation is executed.

### Azure SQL

- type key: `data_warehouse`;
- exact audience: `https://database.windows.net/.default`;
- server restricted to `*.database.windows.net`;
- database name is a public identifier only;
- Broker query remains `SELECT 1 AS fieldflow_probe`;
- no schema, user, role or data mutation is performed.

## BO shortcut

`Preparar Power BI TWT` creates an idempotent destination with:

- name `Power BI TWT, FieldFlow`;
- type `client_powerbi`;
- auth strategy `entra_home_tenant`;
- target reference `powerbi://twt-home`;
- mode `reporting`;
- inactive routing state;
- no secret reference.

The user still has to enter the real Workspace ID in Phase 5C.3 and explicitly run `Verificar acesso`.

## Security invariants

- WordPress never receives an access token.
- No client secret or certificate is stored in WordPress.
- The TWT HMAC continues to authenticate WordPress-to-Broker control-plane calls.
- The Broker 1.3.0 remains unchanged from 2.2.366.
- `delivery_enabled=false` remains mandatory.
- 5C destination routing remains Shadow-only.
- API and Webhook destinations remain excluded from resource probes.
- `READY, RECURSO` proves read-only access only.

## Regression boundary

The release does not alter:

- mobile/offline draft persistence;
- suspension/resume hardening;
- offline final submission queue;
- Azure TWT ingest;
- Client Azure Relay 5B;
- external tenant browser onboarding;
- Entra Broker Azure code;
- route execution/export;
- forms runtime;
- Portal Cliente;
- Desktop shell.
