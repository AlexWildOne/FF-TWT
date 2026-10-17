SET NOCOUNT ON;
GO

SELECT
    CASE WHEN OBJECT_ID(N'ctl.ff_delivery_jobs',N'U') IS NOT NULL THEN 1 ELSE 0 END AS delivery_jobs_table_ok,
    CASE WHEN OBJECT_ID(N'ctl.vw_ff_delivery_health',N'V') IS NOT NULL THEN 1 ELSE 0 END AS delivery_health_view_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_delivery_enqueue',N'P') IS NOT NULL THEN 1 ELSE 0 END AS delivery_enqueue_proc_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_delivery_claim',N'P') IS NOT NULL THEN 1 ELSE 0 END AS delivery_claim_proc_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_delivery_complete',N'P') IS NOT NULL THEN 1 ELSE 0 END AS delivery_complete_proc_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_delivery_fail',N'P') IS NOT NULL THEN 1 ELSE 0 END AS delivery_fail_proc_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_delivery_retry_dead',N'P') IS NOT NULL THEN 1 ELSE 0 END AS delivery_retry_proc_ok,
    CASE WHEN OBJECT_ID(N'ctl.sp_ff_delivery_health',N'P') IS NOT NULL THEN 1 ELSE 0 END AS delivery_health_proc_ok;
GO

EXEC ctl.sp_ff_delivery_health;
GO

SELECT
    CASE WHEN EXISTS(SELECT 1 FROM sys.database_principals WHERE name=N'ff_client_azure_relay' AND type=N'R') THEN 1 ELSE 0 END AS relay_role_ok,
    CASE WHEN EXISTS(SELECT 1 FROM sys.database_principals WHERE name=N'func-fieldflow-clientrelay-prod-eb978f11') THEN 1 ELSE 0 END AS relay_identity_user_ok,
    CASE WHEN EXISTS(
        SELECT 1
        FROM sys.database_role_members drm
        JOIN sys.database_principals rp ON rp.principal_id=drm.role_principal_id
        JOIN sys.database_principals mp ON mp.principal_id=drm.member_principal_id
        WHERE rp.name=N'ff_client_azure_relay' AND mp.name=N'func-fieldflow-clientrelay-prod-eb978f11'
    ) THEN 1 ELSE 0 END AS relay_identity_role_ok,
    CASE WHEN NOT EXISTS (
        SELECT 1
        FROM sys.database_permissions p
        JOIN sys.database_principals dp ON dp.principal_id=p.grantee_principal_id
        LEFT JOIN sys.objects o ON p.class=1 AND o.object_id=p.major_id
        LEFT JOIN sys.schemas object_schema ON o.schema_id=object_schema.schema_id
        LEFT JOIN sys.schemas schema_scope ON p.class=3 AND schema_scope.schema_id=p.major_id
        WHERE dp.name IN (N'ff_powerbi_reader',N'ff_powerbi_model_builder',N'ff_reporting_security_admin')
          AND (object_schema.name=N'ctl' OR schema_scope.name=N'ctl')
    ) THEN 1 ELSE 0 END AS powerbi_roles_have_no_ctl_grants;
GO

SELECT dp.name AS principal_name,rp.name AS role_name
FROM sys.database_role_members drm
JOIN sys.database_principals rp ON rp.principal_id=drm.role_principal_id
JOIN sys.database_principals dp ON dp.principal_id=drm.member_principal_id
WHERE rp.name=N'ff_client_azure_relay';
GO
