param(
    [string]$SubscriptionId = '60350161-0e9d-446d-b691-965176fb440b',
    [string]$ResourceGroup = 'rg-fieldflow-prod-weu',
    [string]$BrokerApp = 'func-fieldflow-entrabroker-prod-eb978f11'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$ExpectedVersion = '1.8.0'
$HealthUrl = "https://$BrokerApp.azurewebsites.net/api/v1/health"

function Assert-Command {
    param([string]$Name)
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) { throw "$Name is required in Azure Cloud Shell." }
}
function Invoke-AzQuiet {
    param([string[]]$Arguments)
    & az @Arguments --only-show-errors --output none | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Azure CLI command failed.' }
}
function Invoke-DeployZip {
    param([string]$ZipPath)
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        & az functionapp deployment source config-zip --resource-group $ResourceGroup --name $BrokerApp --src $ZipPath --only-show-errors --output none
        if ($LASTEXITCODE -eq 0) { return }
        if ($attempt -lt 3) { Write-Warning "Code deployment attempt $attempt failed. Waiting 30 seconds before retry."; Start-Sleep -Seconds 30 }
    }
    throw 'Broker code deployment failed after controlled retries.'
}

Assert-Command 'az'; Assert-Command 'node'; Assert-Command 'npm'
Write-Host 'FieldFlow Entra Broker 1.8.0 code-only deployment, Phase 5D Credential Lifecycle.'
Write-Host '5C.6, 5C.7 and 5C.8 are preserved. General production activation and operational full-payload delivery remain blocked.'

Invoke-AzQuiet @('account','set','--subscription',$SubscriptionId)
& az functionapp show --resource-group $ResourceGroup --name $BrokerApp --only-show-errors --output none
if ($LASTEXITCODE -ne 0) { throw "Existing Broker Function $BrokerApp was not found." }

try {
    $before = Invoke-RestMethod -Uri $HealthUrl -Method Get -TimeoutSec 20
    Write-Host "Current Broker health version: $($before.version)"
    if ($before.delivery_enabled -ne $false) { throw 'Existing Broker reports delivery enabled. Stop before deployment.' }
    if ($before.hmac_runtime_ready -ne $true) { throw 'Existing Broker HMAC runtime is not READY. Stop before deployment.' }
} catch { throw "Could not validate the existing Broker health. $($_.Exception.Message)" }

$settingsJson = & az functionapp config appsettings list --resource-group $ResourceGroup --name $BrokerApp --only-show-errors --output json
if ($LASTEXITCODE -ne 0) { throw 'Could not read Broker app settings.' }
$settings = $settingsJson | ConvertFrom-Json
$credentialVault = [string](($settings | Where-Object { $_.name -eq 'FIELDFLOW_CREDENTIAL_KEYVAULT_NAME' } | Select-Object -First 1).value)
$credentialSecret = [string](($settings | Where-Object { $_.name -eq 'FIELDFLOW_CREDENTIAL_TEST_SECRET_NAME' } | Select-Object -First 1).value)
if ([string]::IsNullOrWhiteSpace($credentialVault) -or [string]::IsNullOrWhiteSpace($credentialSecret)) {
    throw 'Phase 5D Key Vault settings are missing. Run 01-prepare-phase-5d-keyvault.ps1 first.'
}

$source = Join-Path $PSScriptRoot 'entra-broker-function'
if (-not (Test-Path (Join-Path $source 'package.json'))) { throw 'Broker source folder is incomplete.' }
$zip = Join-Path ([System.IO.Path]::GetTempPath()) 'fieldflow-entrabroker-1.8.0-code-only.zip'

Push-Location $source
try {
    if (Test-Path 'node_modules') { Remove-Item 'node_modules' -Recurse -Force }
    if (Test-Path 'package-lock.json') { & npm ci --omit=dev --no-audit --no-fund --engine-strict=false }
    else { & npm install --omit=dev --no-audit --no-fund --engine-strict=false }
    if ($LASTEXITCODE -ne 0) { throw 'npm dependency installation failed.' }
} finally { Pop-Location }

if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $source '*') -DestinationPath $zip -CompressionLevel Optimal
Write-Host 'Deploying code only to the existing Entra Broker...'
Invoke-DeployZip -ZipPath $zip
Write-Host 'Restarting only the Entra Broker Function...'
Invoke-AzQuiet @('functionapp','restart','--resource-group',$ResourceGroup,'--name',$BrokerApp)

$ready = $false
for ($i = 1; $i -le 30; $i++) {
    Start-Sleep -Seconds 10
    try {
        $health = Invoke-RestMethod -Uri $HealthUrl -Method Get -TimeoutSec 20
        if (
            $health.ok -eq $true -and
            [string]$health.version -eq $ExpectedVersion -and
            $health.hmac_runtime_ready -eq $true -and
            $health.delivery_enabled -eq $false -and
            $health.production_delivery_gate_foundation -eq $true -and
            $health.credential_lifecycle_foundation -eq $true -and
            $health.credential_lifecycle_key_vault_only -eq $true -and
            $health.credential_lifecycle_secret_values_never_returned -eq $true -and
            $health.credential_lifecycle_metadata_probe -eq $true -and
            $health.credential_lifecycle_rotation_detection -eq $true -and
            $health.credential_lifecycle_expiry_enforcement -eq $true -and
            $health.credential_lifecycle_revocation_guard -eq $true -and
            $health.credential_lifecycle_signed_receipts -eq $true -and
            $health.credential_lifecycle_sink_available -eq $true -and
            $health.credential_lifecycle_test_secret_ready -eq $true -and
            $health.production_general_activation_enabled -eq $false -and
            $health.operational_payload_delivery_blocked -eq $true
        ) {
            $ready = $true
            Write-Host 'Broker 1.8.0 READY.'
            Write-Host 'Credential Lifecycle 5D READY, Key Vault-only, metadata probe READY, rotation/expiry/revocation guards READY, signed receipts READY.'
            Write-Host 'General production activation and operational full-payload delivery remain BLOCKED.'
            break
        }
    } catch { }
}

if (-not $ready) {
    try { $lastHealth = Invoke-RestMethod -Uri $HealthUrl -Method Get -TimeoutSec 20; Write-Host 'Last Broker health received:'; $lastHealth | ConvertTo-Json -Depth 8 | Write-Host }
    catch { Write-Warning "Could not read final Broker health: $($_.Exception.Message)" }
    throw 'Broker 1.8.0 did not reach the expected safe 5D health state.'
}

Write-Host 'Code-only deployment completed successfully.'
Write-Host 'Next: install FieldFlow 2.2.374 and certify Credential Lifecycle 5D from Destinos de Dados.'
