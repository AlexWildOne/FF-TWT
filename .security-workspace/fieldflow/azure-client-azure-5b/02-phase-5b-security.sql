SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name=N'ff_client_azure_relay' AND type=N'R')
    CREATE ROLE ff_client_azure_relay AUTHORIZATION dbo;
GO

GRANT EXECUTE ON OBJECT::ctl.sp_ff_destination_upsert TO ff_client_azure_relay;
GRANT EXECUTE ON OBJECT::ctl.sp_ff_delivery_enqueue TO ff_client_azure_relay;
GRANT EXECUTE ON OBJECT::ctl.sp_ff_delivery_claim TO ff_client_azure_relay;
GRANT EXECUTE ON OBJECT::ctl.sp_ff_delivery_complete TO ff_client_azure_relay;
GRANT EXECUTE ON OBJECT::ctl.sp_ff_delivery_fail TO ff_client_azure_relay;
GRANT EXECUTE ON OBJECT::ctl.sp_ff_delivery_retry_dead TO ff_client_azure_relay;
GRANT EXECUTE ON OBJECT::ctl.sp_ff_delivery_health TO ff_client_azure_relay;
GRANT SELECT ON OBJECT::ctl.vw_ff_delivery_health TO ff_client_azure_relay;
GO

/* Existing Power BI roles intentionally receive no ctl permissions. */
