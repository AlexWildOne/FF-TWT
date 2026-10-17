/* FieldFlow Phase 6B.2, optional Azure SQL metadata extension.
   Safe to run repeatedly on the licensing database. */

IF COL_LENGTH('dbo.license_activations', 'installation_uuid') IS NULL
    ALTER TABLE dbo.license_activations ADD installation_uuid NVARCHAR(64) NULL;

IF COL_LENGTH('dbo.license_activations', 'installation_fingerprint') IS NULL
    ALTER TABLE dbo.license_activations ADD installation_fingerprint NVARCHAR(64) NULL;

IF COL_LENGTH('dbo.license_activations', 'plugin_version') IS NULL
    ALTER TABLE dbo.license_activations ADD plugin_version NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.license_activations', 'wp_version') IS NULL
    ALTER TABLE dbo.license_activations ADD wp_version NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.license_activations', 'php_version') IS NULL
    ALTER TABLE dbo.license_activations ADD php_version NVARCHAR(32) NULL;

IF COL_LENGTH('dbo.license_activations', 'environment_hash') IS NULL
    ALTER TABLE dbo.license_activations ADD environment_hash NVARCHAR(64) NULL;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_license_activations_installation_uuid'
      AND object_id = OBJECT_ID('dbo.license_activations')
)
    CREATE INDEX IX_license_activations_installation_uuid
        ON dbo.license_activations (installation_uuid);
