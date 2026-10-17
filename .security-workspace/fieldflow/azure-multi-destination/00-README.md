# FieldFlow Phase 5A, Azure Multi-Destination Foundation

This package prepares the Azure SQL control plane for destination metadata and routing rules.

## Safety boundary

- It does not change the existing ingestion Function App.
- It does not change the HMAC secret or ask for it.
- It does not change `dbo` operational materializers.
- It does not change `sec`, `rpt` or `rpt_admin`.
- It does not grant any permission to the current ingest Managed Identity.
- It does not grant any permission to Power BI roles.
- It stores only destination metadata and Key Vault references. It never stores secret values.
- External delivery remains disabled until Phase 5B/5C.

## Target

- Server: `sql-fieldflow-prod-eb978f11.database.windows.net`
- Database: `sqldb-fieldflow-core`
- New SQL schema: `ctl`

## Run order

1. `01-control-plane-schema.sql`
2. `02-control-plane-security.sql`
3. `03-control-plane-validation.sql`

Run with an Entra identity that already has rights to create schema objects and database roles in this database.

## Expected validation

- `ctl` exists.
- 6 destination types exist.
- 3 control-plane tables exist.
- 1 overview view exists.
- 3 stored procedures exist.
- Power BI roles have zero explicit grants on `ctl`.

Phase 5A does not require a redeploy of the existing Azure Function.
