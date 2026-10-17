SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name=N'ff_destination_registry_reader' AND type=N'R')
    CREATE ROLE ff_destination_registry_reader AUTHORIZATION dbo;
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name=N'ff_destination_registry_writer' AND type=N'R')
    CREATE ROLE ff_destination_registry_writer AUTHORIZATION dbo;
GO

GRANT SELECT ON SCHEMA::ctl TO ff_destination_registry_reader;
GO

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::ctl TO ff_destination_registry_writer;
GO

/*
No users are added to these roles in Phase 5A.
In particular, this script does not add:
- the existing ingest Managed Identity,
- ff_powerbi_reader,
- ff_powerbi_model_builder,
- ff_reporting_security_admin.
Role membership is a later deployment decision when the runtime connector is introduced.
*/
