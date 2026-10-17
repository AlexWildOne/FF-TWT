SET NOCOUNT ON;
GO

SELECT
    CASE WHEN SCHEMA_ID(N'ctl') IS NOT NULL THEN 1 ELSE 0 END AS ctl_schema_ok,
    CASE WHEN OBJECT_ID(N'ctl.ff_destination_types',N'U') IS NOT NULL THEN 1 ELSE 0 END AS destination_types_table_ok,
    CASE WHEN OBJECT_ID(N'ctl.ff_destinations',N'U') IS NOT NULL THEN 1 ELSE 0 END AS destinations_table_ok,
    CASE WHEN OBJECT_ID(N'ctl.ff_routing_rules',N'U') IS NOT NULL THEN 1 ELSE 0 END AS routing_rules_table_ok,
    CASE WHEN OBJECT_ID(N'ctl.vw_ff_destination_overview',N'V') IS NOT NULL THEN 1 ELSE 0 END AS destination_overview_view_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_destination_upsert',N'P') IS NOT NULL THEN 1 ELSE 0 END AS destination_upsert_proc_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_routing_rule_upsert',N'P') IS NOT NULL THEN 1 ELSE 0 END AS routing_rule_upsert_proc_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_destination_health',N'P') IS NOT NULL THEN 1 ELSE 0 END AS destination_health_proc_ok;
GO

SELECT type_key,label,delivery_phase,capability,requires_secret_ref,is_active
FROM ctl.ff_destination_types
ORDER BY type_key;
GO

EXEC ctl.sp_ff_destination_health;
GO

SELECT
    dp.name AS principal_name,
    p.permission_name,
    p.state_desc,
    s.name AS schema_name
FROM sys.database_permissions p
JOIN sys.database_principals dp ON dp.principal_id=p.grantee_principal_id
LEFT JOIN sys.schemas s ON p.class=3 AND s.schema_id=p.major_id
WHERE s.name=N'ctl'
  AND dp.name IN (N'ff_powerbi_reader',N'ff_powerbi_model_builder',N'ff_reporting_security_admin');
GO

SELECT
    CASE WHEN COUNT(*)=0 THEN 1 ELSE 0 END AS powerbi_roles_have_no_ctl_grants
FROM sys.database_permissions p
JOIN sys.database_principals dp ON dp.principal_id=p.grantee_principal_id
JOIN sys.schemas s ON p.class=3 AND s.schema_id=p.major_id
WHERE s.name=N'ctl'
  AND dp.name IN (N'ff_powerbi_reader',N'ff_powerbi_model_builder',N'ff_reporting_security_admin');
GO
