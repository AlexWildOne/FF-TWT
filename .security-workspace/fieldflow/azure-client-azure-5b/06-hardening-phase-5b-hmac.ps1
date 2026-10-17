param(
    [string]$SubscriptionId = '60350161-0e9d-446d-b691-965176fb440b',
    [string]$ResourceGroup = 'rg-fieldflow-prod-weu'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$SourceIngestApp = 'func-fieldflow-ingest-prod-eb978f11'
$RelayApp = 'func-fieldflow-clientrelay-prod-eb978f11'
$KeyVault = 'kv-ff-prod-eb978f11'
$HmacSecretNameSetting = 'HMAC_SECRET_NAME'
$RelayHealthEndpoint = "https://$RelayApp.azurewebsites.net/api/v1/health"

function Convert-ToSafeText {
    param($Value)
    if ($null -eq $Value) { return '' }
    if ($Value -is [System.Array]) {
        $parts = @($Value | ForEach-Object { if ($null -eq $_) { '' } else { [string]$_ } })
        return (($parts -join [Environment]::NewLine).Trim())
    }
    return (([string]$Value).Trim())
}

function Invoke-AzText {
    param([string[]]$Arguments)
    $stderrFile = [System.IO.Path]::GetTempFileName()
    try {
        $result = & az @Arguments --only-show-errors 2>$stderrFile
        $exitCode = $LASTEXITCODE
        $stderrRaw = if (Test-Path $stderrFile) { Get-Content -LiteralPath $stderrFile -Raw -ErrorAction SilentlyContinue } else { '' }
        $stderrText = Convert-ToSafeText $stderrRaw
        $stdoutText = Convert-ToSafeText $result
        if ($exitCode -ne 0) {
            $details = @($stdoutText, $stderrText) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
            if ($details.Count -eq 0) { throw 'Azure CLI command failed without diagnostic output.' }
            throw "Azure CLI command failed. $($details -join [Environment]::NewLine)"
        }
        if (-not [string]::IsNullOrWhiteSpace($stderrText)) {
            foreach ($line in ($stderrText -split '\r?\n')) {
                if (-not [string]::IsNullOrWhiteSpace($line)) { Write-Warning $line }
            }
        }
        return $stdoutText
    } finally {
        Remove-Item -LiteralPath $stderrFile -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-AzQuiet {
    param([string[]]$Arguments)
    & az @Arguments --only-show-errors --output none | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Azure CLI command failed.' }
}

function Test-AzResource {
    param([string[]]$Arguments)
    & az @Arguments --only-show-errors --output none 2>$null | Out-Null
    return ($LASTEXITCODE -eq 0)
}

function Assert-RelayFlex {
    if (-not (Test-AzResource @('functionapp','show','--resource-group',$ResourceGroup,'--name',$RelayApp))) {
        throw "Relay Function App $RelayApp does not exist. Run the certified Phase 5B deployment first."
    }
    $siteJson = Invoke-AzText @('resource','show','--resource-group',$ResourceGroup,'--resource-type','Microsoft.Web/sites','--name',$RelayApp,'--api-version','2024-04-01','--output','json')
    $site = $siteJson | ConvertFrom-Json
    $planId = [string]$site.properties.serverFarmId
    if ([string]::IsNullOrWhiteSpace($planId)) { throw 'Relay hosting plan resource id is missing.' }
    $planJson = Invoke-AzText @('resource','show','--ids',$planId,'--api-version','2024-04-01','--output','json')
    $plan = $planJson | ConvertFrom-Json
    if ([string]$plan.sku.name -ne 'FC1' -or [string]$plan.sku.tier -ne 'FlexConsumption') {
        throw "Relay is not hosted on Flex Consumption FC1. Current SKU: $([string]$plan.sku.name) / $([string]$plan.sku.tier)."
    }
    Write-Host "Verified existing Flex Consumption FC1 Relay $RelayApp."
}

function Get-CertifiedHmacSecretReference {
    Write-Host 'Resolving the certified HMAC secret name from the ingest Function without reading the secret value...'
    $json = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$SourceIngestApp,'--output','json')
    $items = @($json | ConvertFrom-Json)
    $setting = $items | Where-Object { [string]$_.name -eq $HmacSecretNameSetting } | Select-Object -First 1
    $secretName = if ($null -eq $setting) { '' } else { ([string]$setting.value).Trim() }
    if ($secretName -notmatch '^[A-Za-z0-9-]{1,127}$') {
        throw "The ingest Function does not expose a valid $HmacSecretNameSetting value. No HMAC secret value was requested."
    }
    $secretId = Invoke-AzText @('keyvault','secret','show','--vault-name',$KeyVault,'--name',$secretName,'--query','id','--output','tsv')
    if ([string]::IsNullOrWhiteSpace($secretId)) { throw 'The certified HMAC secret name was found, but the Key Vault secret could not be confirmed.' }
    Write-Host 'Certified HMAC Key Vault reference: RESOLVED (secret value never requested).'
    return [pscustomobject]@{
        SecretName = $secretName
        Reference = "kv://$KeyVault/$secretName"
    }
}

function Ensure-SecretReader {
    param([string]$VaultName,[string]$SecretName,[string]$PrincipalId)
    $rbac = Invoke-AzText @('keyvault','show','--name',$VaultName,'--query','properties.enableRbacAuthorization','--output','tsv')
    if ($rbac -eq 'true') {
        $vaultId = Invoke-AzText @('keyvault','show','--name',$VaultName,'--query','id','--output','tsv')
        $scope = "$vaultId/secrets/$SecretName"
        $existing = Invoke-AzText @('role','assignment','list','--assignee-object-id',$PrincipalId,'--scope',$scope,'--query',"[?roleDefinitionName=='Key Vault Secrets User'] | length(@)",'--output','tsv')
        if ($existing -eq '0') {
            Invoke-AzQuiet @('role','assignment','create','--assignee-object-id',$PrincipalId,'--assignee-principal-type','ServicePrincipal','--role','Key Vault Secrets User','--scope',$scope)
            Write-Host 'Granted Relay least-privilege read access to the certified HMAC secret.'
            return $true
        }
        Write-Host 'Relay already has least-privilege read access to the certified HMAC secret.'
        return $false
    }
    Invoke-AzQuiet @('keyvault','set-policy','--name',$VaultName,'--object-id',$PrincipalId,'--secret-permissions','get')
    Write-Host 'Confirmed Relay secret read policy on the Key Vault.'
    return $true
}

function Remove-UnsupportedFlexSettings {
    $json = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$RelayApp,'--output','json')
    $items = @($json | ConvertFrom-Json)
    $names = @($items | Where-Object { $_.name -in @('SCM_DO_BUILD_DURING_DEPLOYMENT','ENABLE_ORYX_BUILD') } | ForEach-Object { [string]$_.name })
    if ($names.Count -eq 0) { return $false }
    $args = @('functionapp','config','appsettings','delete','--resource-group',$ResourceGroup,'--name',$RelayApp,'--setting-names') + $names
    Invoke-AzQuiet $args
    Write-Host 'Removed unsupported legacy Flex build settings from the Relay.'
    return $true
}

function Set-AppSettingsIfChanged {
    param([hashtable]$DesiredSettings)
    $json = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$RelayApp,'--output','json')
    $items = @($json | ConvertFrom-Json)
    $changes = @()
    foreach ($key in $DesiredSettings.Keys) {
        $current = $items | Where-Object { [string]$_.name -eq [string]$key } | Select-Object -First 1
        $desired = [string]$DesiredSettings[$key]
        if ($null -eq $current -or [string]$current.value -ne $desired) { $changes += ("{0}={1}" -f $key, $desired) }
    }
    if ($changes.Count -eq 0) {
        Write-Host 'Relay HMAC app setting already uses the certified Key Vault reference.'
        return $false
    }
    $args = @('functionapp','config','appsettings','set','--resource-group',$ResourceGroup,'--name',$RelayApp,'--settings') + $changes
    Invoke-AzQuiet $args
    Write-Host 'Relay HMAC app setting updated to the certified Key Vault reference. Azure may recycle the Function host.'
    return $true
}

function New-DeploymentZip {
    param([string]$SourcePath,[string]$DestinationPath)
    if (Test-Path $DestinationPath) { Remove-Item $DestinationPath -Force }
    Compress-Archive -Path (Join-Path $SourcePath '*') -DestinationPath $DestinationPath -CompressionLevel Optimal
}

function Build-RelayPackage {
    param([string]$SourcePath,[string]$DestinationPath)
    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) { throw 'npm is not available in Azure Cloud Shell.' }
    Write-Host 'Preparing the hardened Relay locally with production Node dependencies...'
    $nodeVersion = Convert-ToSafeText (& node --version)
    $npmVersion = Convert-ToSafeText (& npm --version)
    Write-Host "Cloud Shell build tools: Node $nodeVersion, npm $npmVersion. Target runtime remains Azure Node.js 24."
    Push-Location $SourcePath
    try {
        if (Test-Path (Join-Path $SourcePath 'node_modules')) { Remove-Item -LiteralPath (Join-Path $SourcePath 'node_modules') -Recurse -Force }
        if (Test-Path (Join-Path $SourcePath 'package-lock.json')) { & npm ci --omit=dev --no-audit --no-fund --engine-strict=false }
        else { & npm install --omit=dev --no-audit --no-fund --engine-strict=false }
        if ($LASTEXITCODE -ne 0) { throw 'npm dependency installation failed for the Relay.' }
        if (-not (Test-Path (Join-Path $SourcePath 'node_modules'))) { throw 'node_modules was not produced for the Relay.' }
    } finally { Pop-Location }
    New-DeploymentZip -SourcePath $SourcePath -DestinationPath $DestinationPath
}

function Invoke-FlexCodeDeployment {
    param([string]$ZipPath,[int]$MaxAttempts = 3)
    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        $stderrFile = [System.IO.Path]::GetTempFileName()
        try {
            $output = & az functionapp deployment source config-zip --resource-group $ResourceGroup --name $RelayApp --src $ZipPath --only-show-errors 2>$stderrFile
            $exitCode = $LASTEXITCODE
            $stderrRaw = if (Test-Path $stderrFile) { Get-Content -LiteralPath $stderrFile -Raw -ErrorAction SilentlyContinue } else { '' }
            $combined = ((Convert-ToSafeText $output) + [Environment]::NewLine + (Convert-ToSafeText $stderrRaw)).Trim()
            if ($exitCode -eq 0) {
                Write-Host "Flex Relay deployment succeeded on attempt $attempt."
                return
            }
            $isKuduRecycle = $combined -match '(?i)KuduSpecializer|Kudu has been restarted|Kudu Restart Count'
            if ($isKuduRecycle -and $attempt -lt $MaxAttempts) {
                Write-Warning "Flex deployment collided with a transient Kudu recycle on attempt $attempt. Waiting 30 seconds before retrying the same package."
                Start-Sleep -Seconds 30
                continue
            }
            if ([string]::IsNullOrWhiteSpace($combined)) { throw 'Flex Relay deployment failed without diagnostic output.' }
            throw "Flex Relay deployment failed. $combined"
        } finally {
            Remove-Item -LiteralPath $stderrFile -Force -ErrorAction SilentlyContinue
        }
    }
    throw 'Flex Relay deployment exhausted all retry attempts.'
}

function Assert-RelayHealth {
    Write-Host 'Verifying Relay HMAC readiness through the safe health endpoint...'
    for ($attempt = 1; $attempt -le 18; $attempt++) {
        try {
            $health = Invoke-RestMethod -Uri $RelayHealthEndpoint -Method Get -TimeoutSec 15 -Headers @{ 'Cache-Control' = 'no-cache' }
            if ($health.version -eq '1.1.0' -and $health.hmac_runtime_ready -eq $true -and $health.hmac_runtime_source -eq 'key_vault_managed_identity') {
                Write-Host 'Relay HMAC runtime: READY via key_vault_managed_identity.'
                return
            }
        } catch {
            # The Flex host can briefly return 404/5xx while the new package is warming up.
        }
        Start-Sleep -Seconds 5
    }
    throw 'Relay HMAC runtime did not become ready. No secret was displayed; keep 5B in Shadow and inspect Key Vault access before retrying delivery.'
}

Write-Host 'FieldFlow Phase 5B HMAC hardening starting.'
Write-Host 'This script updates only the existing TWT Client Azure Relay. It does not modify ingest, SQL schema, receiver, receiver key, or WordPress data.'
Invoke-AzQuiet @('account','set','--subscription',$SubscriptionId)
Assert-RelayFlex

Write-Host 'Confirming the existing Relay system managed identity...'
$relayPrincipal = Invoke-AzText @('functionapp','identity','assign','--resource-group',$ResourceGroup,'--name',$RelayApp,'--query','principalId','--output','tsv')
if ([string]::IsNullOrWhiteSpace($relayPrincipal)) { throw 'Relay managed identity was not returned.' }

$hmac = Get-CertifiedHmacSecretReference
$rbacChanged = Ensure-SecretReader -VaultName $KeyVault -SecretName $hmac.SecretName -PrincipalId $relayPrincipal
$legacyChanged = Remove-UnsupportedFlexSettings
$settingChanged = Set-AppSettingsIfChanged -DesiredSettings @{ FIELDFLOW_HMAC_SECRET = $hmac.Reference }

$relaySource = Join-Path $PSScriptRoot 'relay-function'
$relayZip = Join-Path ([System.IO.Path]::GetTempPath()) 'fieldflow-clientrelay-hardened-ready.zip'
Build-RelayPackage -SourcePath $relaySource -DestinationPath $relayZip

if ($legacyChanged -or $settingChanged) {
    Write-Host 'Waiting 40 seconds for Flex Consumption configuration recycle to settle before code deployment...'
    Start-Sleep -Seconds 40
}

Write-Host 'Deploying the hardened Relay Function without remote build...'
Invoke-FlexCodeDeployment -ZipPath $relayZip
Assert-RelayHealth

Write-Host ''
Write-Host 'Phase 5B HMAC hardening deployed.'
Write-Host "Relay health endpoint: $RelayHealthEndpoint"
Write-Host 'HMAC header compatibility: v1 + hmac-v1.'
Write-Host 'HMAC runtime source: certified Key Vault secret via Relay managed identity.'
Write-Host 'No HMAC secret value was requested, printed, or copied into WordPress.'
Write-Host 'Keep WordPress 5B in Shadow until the health endpoint is green, then run one Testar 5B probe before considering dead-job recovery.'
