SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF SCHEMA_ID(N'ctl') IS NULL
    THROW 52000, 'Phase 5A ctl schema is missing. Install and certify Phase 5A first.', 1;
GO

IF OBJECT_ID(N'ctl.ff_delivery_jobs', N'U') IS NULL
BEGIN
    CREATE TABLE ctl.ff_delivery_jobs (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ff_delivery_jobs PRIMARY KEY,
        delivery_key NVARCHAR(64) NOT NULL,
        tenant_id NVARCHAR(64) NOT NULL,
        installation_id NVARCHAR(64) NOT NULL,
        destination_uuid NVARCHAR(64) NOT NULL,
        event_uuid UNIQUEIDENTIFIER NOT NULL,
        event_name NVARCHAR(96) NOT NULL,
        client_id BIGINT NULL,
        project_id BIGINT NULL,
        status NVARCHAR(24) NOT NULL CONSTRAINT DF_ff_delivery_jobs_status DEFAULT N'pending',
        attempts SMALLINT NOT NULL CONSTRAINT DF_ff_delivery_jobs_attempts DEFAULT 0,
        max_attempts SMALLINT NOT NULL CONSTRAINT DF_ff_delivery_jobs_max_attempts DEFAULT 5,
        base_delay_seconds INT NOT NULL CONSTRAINT DF_ff_delivery_jobs_base_delay DEFAULT 60,
        available_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_delivery_jobs_available DEFAULT SYSUTCDATETIME(),
        locked_at DATETIME2(3) NULL,
        lock_token UNIQUEIDENTIFIER NULL,
        completed_at DATETIME2(3) NULL,
        last_http_code SMALLINT NULL,
        last_error_code NVARCHAR(96) NULL,
        last_error_message NVARCHAR(500) NULL,
        payload_json NVARCHAR(MAX) NOT NULL,
        created_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_delivery_jobs_created DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2(3) NOT NULL CONSTRAINT DF_ff_delivery_jobs_updated DEFAULT SYSUTCDATETIME(),
        CONSTRAINT UQ_ff_delivery_jobs_key UNIQUE(delivery_key),
        CONSTRAINT FK_ff_delivery_jobs_destination FOREIGN KEY(destination_uuid) REFERENCES ctl.ff_destinations(destination_uuid),
        CONSTRAINT CK_ff_delivery_jobs_status CHECK(status IN (N'pending',N'processing',N'completed',N'dead')),
        CONSTRAINT CK_ff_delivery_jobs_attempts CHECK(attempts >= 0 AND max_attempts BETWEEN 1 AND 12),
        CONSTRAINT CK_ff_delivery_jobs_scope CHECK(project_id IS NULL OR client_id IS NOT NULL)
    );
    CREATE INDEX IX_ff_delivery_jobs_status_available ON ctl.ff_delivery_jobs(status,available_at,id);
    CREATE INDEX IX_ff_delivery_jobs_destination_status ON ctl.ff_delivery_jobs(destination_uuid,status,available_at);
    CREATE INDEX IX_ff_delivery_jobs_scope ON ctl.ff_delivery_jobs(tenant_id,client_id,project_id,status);
    CREATE INDEX IX_ff_delivery_jobs_event ON ctl.ff_delivery_jobs(event_name,event_uuid);
    CREATE INDEX IX_ff_delivery_jobs_completed ON ctl.ff_delivery_jobs(completed_at);
END;
GO

CREATE OR ALTER VIEW ctl.vw_ff_delivery_health
AS
SELECT
    d.destination_uuid,
    d.name,
    d.tenant_id,
    d.client_id,
    d.project_id,
    d.type_key,
    d.is_active,
    d.status AS destination_status,
    SUM(CASE WHEN j.status=N'pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN j.status=N'processing' THEN 1 ELSE 0 END) AS processing,
    SUM(CASE WHEN j.status=N'completed' AND j.completed_at >= DATEADD(day,-1,SYSUTCDATETIME()) THEN 1 ELSE 0 END) AS completed_24h,
    SUM(CASE WHEN j.status=N'dead' THEN 1 ELSE 0 END) AS dead,
    MAX(CASE WHEN j.status=N'completed' THEN j.completed_at END) AS last_success_at,
    MAX(CASE WHEN j.last_error_message IS NOT NULL THEN j.updated_at END) AS last_error_at
FROM ctl.ff_destinations d
LEFT JOIN ctl.ff_delivery_jobs j ON j.destination_uuid=d.destination_uuid
WHERE d.type_key=N'client_azure'
GROUP BY d.destination_uuid,d.name,d.tenant_id,d.client_id,d.project_id,d.type_key,d.is_active,d.status;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_delivery_enqueue
    @delivery_key NVARCHAR(64),
    @tenant_id NVARCHAR(64),
    @installation_id NVARCHAR(64),
    @destination_uuid NVARCHAR(64),
    @destination_name NVARCHAR(191),
    @client_id BIGINT = NULL,
    @project_id BIGINT = NULL,
    @mode NVARCHAR(24) = N'mirror',
    @endpoint_ref NVARCHAR(500),
    @secret_ref NVARCHAR(500),
    @retry_policy_json NVARCHAR(MAX) = NULL,
    @event_uuid UNIQUEIDENTIFIER,
    @event_name NVARCHAR(96),
    @payload_json NVARCHAR(MAX),
    @source_installation_id NVARCHAR(64) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @delivery_key IS NULL OR LTRIM(RTRIM(@delivery_key))=N'' THROW 52001, 'delivery_key is required', 1;
    IF @tenant_id IS NULL OR LTRIM(RTRIM(@tenant_id))=N'' THROW 52002, 'tenant_id is required', 1;
    IF @destination_uuid IS NULL OR LTRIM(RTRIM(@destination_uuid))=N'' THROW 52003, 'destination_uuid is required', 1;
    IF @project_id IS NOT NULL AND @client_id IS NULL THROW 52004, 'project scope requires client scope', 1;
    IF @endpoint_ref IS NULL OR @endpoint_ref NOT LIKE N'https://%' THROW 52005, 'Phase 5B client Azure endpoint must use HTTPS', 1;
    IF @secret_ref IS NULL OR (@secret_ref NOT LIKE N'kv://%' AND @secret_ref NOT LIKE N'https://%.vault.azure.net/secrets/%')
        THROW 52006, 'Phase 5B requires a Key Vault secret reference, never the secret value', 1;
    IF ISJSON(@payload_json)<>1 THROW 52007, 'payload_json must contain valid JSON', 1;

    DECLARE @max_attempts SMALLINT = 5;
    DECLARE @base_delay INT = 60;
    IF ISJSON(@retry_policy_json)=1
    BEGIN
        SET @max_attempts = TRY_CONVERT(SMALLINT,JSON_VALUE(@retry_policy_json,'$.max_attempts'));
        SET @base_delay = TRY_CONVERT(INT,JSON_VALUE(@retry_policy_json,'$.base_delay_seconds'));
    END
    SET @max_attempts = CASE WHEN @max_attempts BETWEEN 1 AND 12 THEN @max_attempts ELSE 5 END;
    SET @base_delay = CASE WHEN @base_delay BETWEEN 30 AND 3600 THEN @base_delay ELSE 60 END;

    BEGIN TRANSACTION;

    EXEC ctl.sp_ff_destination_upsert
        @destination_uuid=@destination_uuid,
        @tenant_id=@tenant_id,
        @client_id=@client_id,
        @project_id=@project_id,
        @type_key=N'client_azure',
        @name=@destination_name,
        @is_active=1,
        @mode=@mode,
        @endpoint_ref=@endpoint_ref,
        @secret_ref=@secret_ref,
        @retry_policy_json=@retry_policy_json,
        @status=N'ready',
        @source_installation_id=@source_installation_id,
        @created_by=N'fieldflow-relay';

    IF NOT EXISTS (SELECT 1 FROM ctl.ff_delivery_jobs WITH (UPDLOCK,HOLDLOCK) WHERE delivery_key=@delivery_key)
    BEGIN
        INSERT ctl.ff_delivery_jobs
            (delivery_key,tenant_id,installation_id,destination_uuid,event_uuid,event_name,client_id,project_id,status,attempts,max_attempts,base_delay_seconds,available_at,payload_json)
        VALUES
            (@delivery_key,@tenant_id,@installation_id,@destination_uuid,@event_uuid,@event_name,@client_id,@project_id,N'pending',0,@max_attempts,@base_delay,SYSUTCDATETIME(),@payload_json);
    END

    COMMIT TRANSACTION;

    SELECT id,delivery_key,status,attempts,max_attempts,available_at
    FROM ctl.ff_delivery_jobs
    WHERE delivery_key=@delivery_key;
END;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_delivery_claim
    @lock_token UNIQUEIDENTIFIER
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @id BIGINT;
    BEGIN TRANSACTION;

    /* Recover a job if a relay instance died after claiming it. */
    UPDATE ctl.ff_delivery_jobs WITH (ROWLOCK)
    SET status=N'pending',locked_at=NULL,lock_token=NULL,available_at=SYSUTCDATETIME(),updated_at=SYSUTCDATETIME(),
        last_error_code=COALESCE(last_error_code,N'stale_lock_recovered'),
        last_error_message=COALESCE(last_error_message,N'A stale relay lock was recovered automatically.')
    WHERE status=N'processing' AND locked_at < DATEADD(minute,-10,SYSUTCDATETIME());

    SELECT TOP(1) @id=id
    FROM ctl.ff_delivery_jobs WITH (UPDLOCK,READPAST,ROWLOCK)
    WHERE status=N'pending' AND available_at<=SYSUTCDATETIME()
    ORDER BY id ASC;

    IF @id IS NOT NULL
    BEGIN
        UPDATE ctl.ff_delivery_jobs
        SET status=N'processing',attempts=attempts+1,locked_at=SYSUTCDATETIME(),lock_token=@lock_token,updated_at=SYSUTCDATETIME()
        WHERE id=@id AND status=N'pending';
    END
    COMMIT TRANSACTION;

    IF @id IS NOT NULL
    BEGIN
        SELECT j.*,d.endpoint_ref,d.secret_ref,d.retry_policy_json,d.name AS destination_name,d.mode AS destination_mode
        FROM ctl.ff_delivery_jobs j
        JOIN ctl.ff_destinations d ON d.destination_uuid=j.destination_uuid
        WHERE j.id=@id AND j.lock_token=@lock_token;
    END
END;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_delivery_complete
    @id BIGINT,
    @lock_token UNIQUEIDENTIFIER,
    @http_code SMALLINT
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE ctl.ff_delivery_jobs
    SET status=N'completed',completed_at=SYSUTCDATETIME(),locked_at=NULL,lock_token=NULL,last_http_code=@http_code,
        last_error_code=NULL,last_error_message=NULL,updated_at=SYSUTCDATETIME()
    WHERE id=@id AND status=N'processing' AND lock_token=@lock_token;

    IF @@ROWCOUNT=1
    BEGIN
        UPDATE d
        SET last_success_at=SYSUTCDATETIME(),last_error_at=NULL,last_error_code=NULL,last_error_message=NULL,status=N'ready',updated_at=SYSUTCDATETIME()
        FROM ctl.ff_destinations d
        JOIN ctl.ff_delivery_jobs j ON j.destination_uuid=d.destination_uuid
        WHERE j.id=@id;
    END
END;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_delivery_fail
    @id BIGINT,
    @lock_token UNIQUEIDENTIFIER,
    @http_code SMALLINT = NULL,
    @error_code NVARCHAR(96),
    @error_message NVARCHAR(500)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @attempts INT,@max_attempts INT,@base_delay INT,@destination_uuid NVARCHAR(64);
    SELECT @attempts=attempts,@max_attempts=max_attempts,@base_delay=base_delay_seconds,@destination_uuid=destination_uuid
    FROM ctl.ff_delivery_jobs
    WHERE id=@id AND status=N'processing' AND lock_token=@lock_token;

    IF @attempts IS NULL RETURN;

    DECLARE @terminal BIT = CASE WHEN @attempts>=@max_attempts THEN 1 ELSE 0 END;
    DECLARE @delay INT = @base_delay * CONVERT(INT,POWER(CAST(2 AS FLOAT),CASE WHEN @attempts>1 THEN @attempts-1 ELSE 0 END));
    IF @delay>21600 SET @delay=21600;

    UPDATE ctl.ff_delivery_jobs
    SET status=CASE WHEN @terminal=1 THEN N'dead' ELSE N'pending' END,
        available_at=CASE WHEN @terminal=1 THEN available_at ELSE DATEADD(second,@delay,SYSUTCDATETIME()) END,
        completed_at=CASE WHEN @terminal=1 THEN SYSUTCDATETIME() ELSE NULL END,
        locked_at=NULL,lock_token=NULL,last_http_code=@http_code,
        last_error_code=LEFT(COALESCE(@error_code,N'delivery_failed'),96),last_error_message=LEFT(COALESCE(@error_message,N'Delivery failed'),500),updated_at=SYSUTCDATETIME()
    WHERE id=@id;

    UPDATE ctl.ff_destinations
    SET last_error_at=SYSUTCDATETIME(),last_error_code=LEFT(COALESCE(@error_code,N'delivery_failed'),96),last_error_message=LEFT(COALESCE(@error_message,N'Delivery failed'),500),
        status=CASE WHEN @terminal=1 THEN N'error' ELSE status END,updated_at=SYSUTCDATETIME()
    WHERE destination_uuid=@destination_uuid;
END;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_delivery_retry_dead
    @delivery_key NVARCHAR(64) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE ctl.ff_delivery_jobs
    SET status=N'pending',attempts=0,available_at=SYSUTCDATETIME(),locked_at=NULL,lock_token=NULL,completed_at=NULL,
        last_http_code=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=SYSUTCDATETIME()
    WHERE status=N'dead' AND (@delivery_key IS NULL OR delivery_key=@delivery_key);
    SELECT @@ROWCOUNT AS retried;
END;
GO

CREATE OR ALTER PROCEDURE ctl.sp_ff_delivery_health
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        CAST(1 AS BIT) AS phase_5b_ready,
        (SELECT COUNT(*) FROM ctl.ff_destinations WHERE type_key=N'client_azure') AS client_azure_destinations,
        (SELECT COUNT(*) FROM ctl.ff_destinations WHERE type_key=N'client_azure' AND is_active=1) AS active_client_azure_destinations,
        (SELECT COUNT(*) FROM ctl.ff_delivery_jobs WHERE status=N'pending') AS pending,
        (SELECT COUNT(*) FROM ctl.ff_delivery_jobs WHERE status=N'processing') AS processing,
        (SELECT COUNT(*) FROM ctl.ff_delivery_jobs WHERE status=N'dead') AS dead,
        (SELECT COUNT(*) FROM ctl.ff_delivery_jobs WHERE status=N'completed' AND completed_at>=DATEADD(day,-1,SYSUTCDATETIME())) AS completed_24h,
        SYSUTCDATETIME() AS checked_at;
END;
GO
