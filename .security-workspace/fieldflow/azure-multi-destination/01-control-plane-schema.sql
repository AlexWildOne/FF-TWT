SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = N'ctl')
    EXEC(N'CREATE SCHEMA ctl AUTHORIZATION dbo;');
GO

IF OBJECT_ID(N'ctl.ff_destination_types', N'U') IS NULL
BEGIN
    CREATE TABLE ctl.ff_destination_types (
        type_key NVARCHAR(32) NOT NULL CONSTRAINT PK_ff_destination_types PRIMARY KEY,
        label NVARCHAR(96) NOT NULL,
        delivery_phase NVARCHAR(16) NOT NULL CONSTRAINT DF_ff_destination_types_phase DEFAULT N'5A',
        capability NVARCHAR(32) NOT NULL CONSTRAINT DF_ff_destination_types_capability DEFAULT N'delivery',
        supports_endpoint BIT NOT NULL CONSTRAINT DF_ff_destination_types_endpoint DEFAULT 1,
        requires_secret_ref BIT NOT NULL CONSTRAINT DF_ff_destination_types_secret DEFAULT 0,
        is_active BIT NOT NULL CONSTRAINT DF_ff_destination_types_active DEFAULT 1,
        created_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_destination_types_created DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_destination_types_updated DEFAULT SYSUTCDATETIME()
    );
END;
GO

MERGE ctl.ff_destination_types AS target
USING (VALUES
    (N'twt_azure',      N'Azure TWT',          N'5A', N'primary_hub', 1, 0),
    (N'client_azure',   N'Azure do Cliente',   N'5B', N'replica',     1, 1),
    (N'external_api',   N'API Externa',        N'5C', N'api',         1, 1),
    (N'client_powerbi', N'Power BI do Cliente',N'5C', N'reporting',   1, 0),
    (N'webhook',        N'Webhook',            N'5C', N'webhook',     1, 1),
    (N'data_warehouse', N'Data Warehouse',     N'5C', N'warehouse',   1, 1)
) AS source(type_key,label,delivery_phase,capability,supports_endpoint,requires_secret_ref)
ON target.type_key = source.type_key
WHEN MATCHED THEN UPDATE SET
    label = source.label,
    delivery_phase = source.delivery_phase,
    capability = source.capability,
    supports_endpoint = source.supports_endpoint,
    requires_secret_ref = source.requires_secret_ref,
    is_active = 1,
    updated_at = SYSUTCDATETIME()
WHEN NOT MATCHED THEN INSERT
    (type_key,label,delivery_phase,capability,supports_endpoint,requires_secret_ref,is_active)
VALUES
    (source.type_key,source.label,source.delivery_phase,source.capability,source.supports_endpoint,source.requires_secret_ref,1);
GO

IF OBJECT_ID(N'ctl.ff_destinations', N'U') IS NULL
BEGIN
    CREATE TABLE ctl.ff_destinations (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ff_destinations PRIMARY KEY,
        destination_uuid NVARCHAR(64) NOT NULL,
        tenant_id NVARCHAR(64) NOT NULL,
        client_id BIGINT NULL,
        project_id BIGINT NULL,
        type_key NVARCHAR(32) NOT NULL,
        name NVARCHAR(191) NOT NULL,
        is_active BIT NOT NULL CONSTRAINT DF_ff_destinations_active DEFAULT 0,
        mode NVARCHAR(24) NOT NULL CONSTRAINT DF_ff_destinations_mode DEFAULT N'mirror',
        endpoint_ref NVARCHAR(500) NULL,
        secret_ref NVARCHAR(500) NULL,
        retry_policy_json NVARCHAR(MAX) NULL,
        status NVARCHAR(24) NOT NULL CONSTRAINT DF_ff_destinations_status DEFAULT N'draft',
        last_success_at DATETIME2(3) NULL,
        last_error_at DATETIME2(3) NULL,
        last_error_code NVARCHAR(96) NULL,
        last_error_message NVARCHAR(500) NULL,
        source_installation_id NVARCHAR(64) NULL,
        created_by NVARCHAR(191) NULL,
        created_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_destinations_created DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_destinations_updated DEFAULT SYSUTCDATETIME(),
        CONSTRAINT UQ_ff_destinations_uuid UNIQUE(destination_uuid),
        CONSTRAINT FK_ff_destinations_type FOREIGN KEY(type_key) REFERENCES ctl.ff_destination_types(type_key),
        CONSTRAINT CK_ff_destinations_mode CHECK(mode IN (N'primary',N'mirror',N'reporting',N'optional')),
        CONSTRAINT CK_ff_destinations_status CHECK(status IN (N'draft',N'ready',N'paused',N'error',N'disabled')),
        CONSTRAINT CK_ff_destinations_scope CHECK(project_id IS NULL OR client_id IS NOT NULL),
        CONSTRAINT CK_ff_destinations_secret_ref CHECK(secret_ref IS NULL OR secret_ref LIKE N'kv://%' OR secret_ref LIKE N'https://%.vault.azure.net/secrets/%')
    );
    CREATE INDEX IX_ff_destinations_tenant_active ON ctl.ff_destinations(tenant_id,is_active,status);
    CREATE INDEX IX_ff_destinations_scope ON ctl.ff_destinations(tenant_id,client_id,project_id);
    CREATE INDEX IX_ff_destinations_type_status ON ctl.ff_destinations(type_key,status);
END;
GO

IF OBJECT_ID(N'ctl.ff_routing_rules', N'U') IS NULL
BEGIN
    CREATE TABLE ctl.ff_routing_rules (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ff_routing_rules PRIMARY KEY,
        rule_uuid UNIQUEIDENTIFIER NOT NULL,
        tenant_id NVARCHAR(64) NOT NULL,
        destination_uuid NVARCHAR(64) NOT NULL,
        client_id BIGINT NULL,
        project_id BIGINT NULL,
        event_pattern NVARCHAR(96) NOT NULL CONSTRAINT DF_ff_routing_rules_event DEFAULT N'*',
        action_name NVARCHAR(16) NOT NULL CONSTRAINT DF_ff_routing_rules_action DEFAULT N'include',
        is_active BIT NOT NULL CONSTRAINT DF_ff_routing_rules_active DEFAULT 1,
        priority INT NOT NULL CONSTRAINT DF_ff_routing_rules_priority DEFAULT 100,
        created_by NVARCHAR(191) NULL,
        created_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_routing_rules_created DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_routing_rules_updated DEFAULT SYSUTCDATETIME(),
        CONSTRAINT UQ_ff_routing_rules_uuid UNIQUE(rule_uuid),
        CONSTRAINT FK_ff_routing_rules_destination FOREIGN KEY(destination_uuid) REFERENCES ctl.ff_destinations(destination_uuid),
        CONSTRAINT CK_ff_routing_rules_action CHECK(action_name IN (N'include',N'exclude')),
        CONSTRAINT CK_ff_routing_rules_priority CHECK(priority BETWEEN 0 AND 9999),
        CONSTRAINT CK_ff_routing_rules_scope CHECK(project_id IS NULL OR client_id IS NOT NULL)
    );
    CREATE INDEX IX_ff_routing_rules_destination_active ON ctl.ff_routing_rules(destination_uuid,is_active,priority DESC);
    CREATE INDEX IX_ff_routing_rules_scope ON ctl.ff_routing_rules(tenant_id,client_id,project_id);
    CREATE INDEX IX_ff_routing_rules_event ON ctl.ff_routing_rules(event_pattern);
END;
GO

CREATE OR ALTER VIEW ctl.vw_ff_destination_overview
AS
SELECT
    d.destination_uuid,
    d.tenant_id,
    d.client_id,
    d.project_id,
    d.type_key,
    t.label AS type_label,
    t.delivery_phase,
    t.capability,
    d.name,
    d.is_active,
    d.mode,
    d.status,
    d.endpoint_ref,
    d.secret_ref,
    d.last_success_at,
    d.last_error_at,
    d.last_error_code,
    d.updated_at,
    COUNT(r.id) AS routing_rule_count
FROM ctl.ff_destinations d
JOIN ctl.ff_destination_types t ON t.type_key = d.type_key
LEFT JOIN ctl.ff_routing_rules r ON r.destination_uuid = d.destination_uuid
GROUP BY
    d.destination_uuid,d.tenant_id,d.client_id,d.project_id,d.type_key,t.label,t.delivery_phase,t.capability,
    d.name,d.is_active,d.mode,d.status,d.endpoint_ref,d.secret_ref,d.last_success_at,d.last_error_at,d.last_error_code,d.updated_at;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_destination_upsert
    @destination_uuid NVARCHAR(64),
    @tenant_id NVARCHAR(64),
    @client_id BIGINT = NULL,
    @project_id BIGINT = NULL,
    @type_key NVARCHAR(32),
    @name NVARCHAR(191),
    @is_active BIT = 0,
    @mode NVARCHAR(24) = N'mirror',
    @endpoint_ref NVARCHAR(500) = NULL,
    @secret_ref NVARCHAR(500) = NULL,
    @retry_policy_json NVARCHAR(MAX) = NULL,
    @status NVARCHAR(24) = N'draft',
    @source_installation_id NVARCHAR(64) = NULL,
    @created_by NVARCHAR(191) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @destination_uuid IS NULL OR LTRIM(RTRIM(@destination_uuid)) = N'' THROW 51001, 'destination_uuid is required', 1;
    IF @tenant_id IS NULL OR LTRIM(RTRIM(@tenant_id)) = N'' THROW 51002, 'tenant_id is required', 1;
    IF @project_id IS NOT NULL AND @client_id IS NULL THROW 51003, 'project scope requires client scope', 1;
    IF @secret_ref IS NOT NULL AND @secret_ref NOT LIKE N'kv://%' AND @secret_ref NOT LIKE N'https://%.vault.azure.net/secrets/%'
        THROW 51004, 'secret_ref must be a Key Vault reference, never a secret value', 1;

    MERGE ctl.ff_destinations WITH (HOLDLOCK) AS target
    USING (SELECT @destination_uuid AS destination_uuid) AS source
    ON target.destination_uuid = source.destination_uuid
    WHEN MATCHED THEN UPDATE SET
        tenant_id=@tenant_id,client_id=@client_id,project_id=@project_id,type_key=@type_key,name=@name,
        is_active=@is_active,mode=@mode,endpoint_ref=@endpoint_ref,secret_ref=@secret_ref,
        retry_policy_json=@retry_policy_json,status=@status,source_installation_id=@source_installation_id,
        updated_at=SYSUTCDATETIME()
    WHEN NOT MATCHED THEN INSERT
        (destination_uuid,tenant_id,client_id,project_id,type_key,name,is_active,mode,endpoint_ref,secret_ref,retry_policy_json,status,source_installation_id,created_by)
    VALUES
        (@destination_uuid,@tenant_id,@client_id,@project_id,@type_key,@name,@is_active,@mode,@endpoint_ref,@secret_ref,@retry_policy_json,@status,@source_installation_id,@created_by);
END;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_routing_rule_upsert
    @rule_uuid UNIQUEIDENTIFIER,
    @tenant_id NVARCHAR(64),
    @destination_uuid NVARCHAR(64),
    @client_id BIGINT = NULL,
    @project_id BIGINT = NULL,
    @event_pattern NVARCHAR(96) = N'*',
    @action_name NVARCHAR(16) = N'include',
    @is_active BIT = 1,
    @priority INT = 100,
    @created_by NVARCHAR(191) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @project_id IS NOT NULL AND @client_id IS NULL THROW 51011, 'project scope requires client scope', 1;

    MERGE ctl.ff_routing_rules WITH (HOLDLOCK) AS target
    USING (SELECT @rule_uuid AS rule_uuid) AS source
    ON target.rule_uuid = source.rule_uuid
    WHEN MATCHED THEN UPDATE SET
        tenant_id=@tenant_id,destination_uuid=@destination_uuid,client_id=@client_id,project_id=@project_id,
        event_pattern=@event_pattern,action_name=@action_name,is_active=@is_active,priority=@priority,updated_at=SYSUTCDATETIME()
    WHEN NOT MATCHED THEN INSERT
        (rule_uuid,tenant_id,destination_uuid,client_id,project_id,event_pattern,action_name,is_active,priority,created_by)
    VALUES
        (@rule_uuid,@tenant_id,@destination_uuid,@client_id,@project_id,@event_pattern,@action_name,@is_active,@priority,@created_by);
END;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_destination_health
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        CAST(1 AS BIT) AS control_plane_ready,
        (SELECT COUNT(*) FROM ctl.ff_destination_types WHERE is_active=1) AS destination_types,
        (SELECT COUNT(*) FROM ctl.ff_destinations) AS destinations,
        (SELECT COUNT(*) FROM ctl.ff_destinations WHERE is_active=1) AS active_destinations,
        (SELECT COUNT(*) FROM ctl.ff_routing_rules) AS routing_rules,
        (SELECT COUNT(*) FROM ctl.ff_destinations WHERE type_key=N'twt_azure' AND mode=N'primary' AND is_active=1) AS primary_twt_destinations,
        SYSUTCDATETIME() AS checked_at;
END;
GO
