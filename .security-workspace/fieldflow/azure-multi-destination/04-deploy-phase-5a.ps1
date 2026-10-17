$ErrorActionPreference = "Stop"

$Server = "sql-fieldflow-prod-eb978f11.database.windows.net"
$Database = "sqldb-fieldflow-core"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path

if (-not (Get-Module -ListAvailable -Name SqlServer)) {
    throw "PowerShell module SqlServer is required. Install-Module SqlServer -Scope CurrentUser"
}

if (-not (Get-Command az -ErrorAction SilentlyContinue)) {
    throw "Azure CLI is required for Entra token acquisition."
}

$token = az account get-access-token --resource https://database.windows.net/ --query accessToken -o tsv
if ([string]::IsNullOrWhiteSpace($token)) {
    throw "Could not obtain an Azure SQL access token. Run az login first."
}

$files = @(
    "01-control-plane-schema.sql",
    "02-control-plane-security.sql",
    "03-control-plane-validation.sql"
)

foreach ($file in $files) {
    $path = Join-Path $Root $file
    Write-Host "Running $file"
    Invoke-Sqlcmd -ServerInstance $Server -Database $Database -AccessToken $token -InputFile $path -AbortOnError
}

Write-Host "FieldFlow Phase 5A Azure control plane completed."
