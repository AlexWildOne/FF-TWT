SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

/* Run only after the Relay Function App exists with system-assigned identity. */
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name=N'func-fieldflow-clientrelay-prod-eb978f11')
    CREATE USER [func-fieldflow-clientrelay-prod-eb978f11] FROM EXTERNAL PROVIDER;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.database_role_members drm
    JOIN sys.database_principals rp ON rp.principal_id=drm.role_principal_id
    JOIN sys.database_principals mp ON mp.principal_id=drm.member_principal_id
    WHERE rp.name=N'ff_client_azure_relay' AND mp.name=N'func-fieldflow-clientrelay-prod-eb978f11'
)
    ALTER ROLE ff_client_azure_relay ADD MEMBER [func-fieldflow-clientrelay-prod-eb978f11];
GO
