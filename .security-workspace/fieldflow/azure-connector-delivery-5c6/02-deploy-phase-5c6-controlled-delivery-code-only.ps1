param(
    [string]$SubscriptionId = '60350161-0e9d-446d-b691-965176fb440b',
    [string]$ResourceGroup = 'rg-fieldflow-prod-weu',
    [string]$BrokerApp = 'func-fieldflow-entrabroker-prod-eb978f11'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$ExpectedVersion = '1.5.0'
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
        if ($attempt -lt 3) {
            Write-Warning "Code deployment attempt $attempt failed. Waiting 30 seconds before one controlled retry."
            Start-Sleep -Seconds 30
        }
    }
    throw 'Broker code deployment failed after controlled retries.'
}

Assert-Command 'az'
Assert-Command 'node'
Assert-Command 'npm'

Write-Host 'FieldFlow Entra Broker 1.5.0 code-only deployment, Phase 5C.6 controlled delivery.'
Write-Host 'This script does NOT change App Registration, federation, managed identities, Key Vault, HMAC settings, ingest, Relay, SQL, or delivery settings.'

Invoke-AzQuiet @('account','set','--subscription',$SubscriptionId)
& az functionapp show --resource-group $ResourceGroup --name $BrokerApp --only-show-errors --output none
if ($LASTEXITCODE -ne 0) { throw "Existing Broker Function $BrokerApp was not found. Stop instead of creating infrastructure." }

try {
    $before = Invoke-RestMethod -Uri $HealthUrl -Method Get -TimeoutSec 20
    Write-Host "Current Broker health version: $($before.version)"
    if ($before.delivery_enabled -ne $false) { throw 'Existing Broker reports delivery enabled. Stop before deployment.' }
    if ($before.hmac_runtime_ready -ne $true) { throw 'Existing Broker HMAC runtime is not READY. Stop before deployment.' }
} catch {
    throw "Could not validate the existing certified Broker health before deployment. $($_.Exception.Message)"
}

$source = Join-Path $PSScriptRoot 'entra-broker-function'
if (-not (Test-Path (Join-Path $source 'package.json'))) { throw 'Broker source folder is incomplete.' }
$zip = Join-Path ([System.IO.Path]::GetTempPath()) 'fieldflow-entrabroker-1.5.0-code-only.zip'

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
for ($i = 1; $i -le 24; $i++) {
    Start-Sleep -Seconds 10
    try {
        $health = Invoke-RestMethod -Uri $HealthUrl -Method Get -TimeoutSec 20
        $kinds = @($health.resource_probe_kinds)
        if ($health.ok -eq $true -and [string]$health.version -eq $ExpectedVersion -and $health.hmac_runtime_ready -eq $true -and $health.delivery_enabled -eq $false -and $health.resource_probe_read_only -eq $true -and $health.fabric_rest_v1 -eq $true -and $health.controlled_delivery_foundation -eq $true -and $health.controlled_delivery_certification_only -eq $true -and $health.controlled_delivery_receiver_fixed -eq $true -and $health.controlled_delivery_signed_receipts -eq $true -and 'fabric_workspace' -in $kinds) {
            $ready = $true
            Write-Host 'Broker 1.5.0 READY.'
            Write-Host 'HMAC READY, Fabric REST v1 READY, resource probes READ-ONLY, Controlled Delivery 5C.6 CERTIFICATION-ONLY, operational delivery BLOCKED.'
            break
        }
    } catch { }
}
if (-not $ready) { throw 'Broker 1.5.0 did not reach the expected safe health state.' }

Write-Host 'Code-only deployment completed successfully.'
Write-Host 'Next: install FieldFlow 2.2.371 and run the 5C.6 controlled delivery certification. No operational payloads are enabled.'
