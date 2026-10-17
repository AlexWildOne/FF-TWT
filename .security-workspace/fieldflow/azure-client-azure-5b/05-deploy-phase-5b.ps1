param(
    [string]$SubscriptionId = '60350161-0e9d-446d-b691-965176fb440b',
    [string]$ResourceGroup = 'rg-fieldflow-prod-weu',
    [string]$Location = 'westeurope'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$SourceIngestApp = 'func-fieldflow-ingest-prod-eb978f11'
$RelayApp = 'func-fieldflow-clientrelay-prod-eb978f11'
$ReceiverApp = 'func-fieldflow-clientreceiver-test-eb978f11'
$RelayStorage = 'stffrelayeb978f11'
$ReceiverStorage = 'stffreceivereb978f11'
$KeyVault = 'kv-ff-prod-eb978f11'
$SqlServer = 'sql-fieldflow-prod-eb978f11.database.windows.net'
$SqlDatabase = 'sqldb-fieldflow-core'
$ReceiverSecretName = 'fieldflow-clientreceiver-test-key'
$RelayEndpoint = "https://$RelayApp.azurewebsites.net/api/v1/client-azure-relay"
$ReceiverEndpoint = "https://$ReceiverApp.azurewebsites.net/api/v1/receive"
$ReceiverSecretRef = "kv://$KeyVault/$ReceiverSecretName"

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

    # Azure CLI can write informational warnings to stderr even on success.
    # Cloud Shell can also return an empty stderr file as $null when read with -Raw.
    # Keep streams separate and normalise null values before any Trim()/JSON parsing.
    $stderrFile = [System.IO.Path]::GetTempFileName()
    try {
        $result = & az @Arguments --only-show-errors 2>$stderrFile
        $exitCode = $LASTEXITCODE

        $stderrRaw = $null
        if (Test-Path $stderrFile) {
            $stderrRaw = Get-Content -LiteralPath $stderrFile -Raw -ErrorAction SilentlyContinue
        }
        $stderrText = Convert-ToSafeText $stderrRaw
        $stdoutText = Convert-ToSafeText $result

        if ($exitCode -ne 0) {
            $details = @($stdoutText, $stderrText) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
            if ($details.Count -eq 0) {
                throw 'Azure CLI command failed without diagnostic output.'
            }
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

function Ensure-Storage {
    param([string]$Name)
    if (-not (Test-AzResource @('storage','account','show','--resource-group',$ResourceGroup,'--name',$Name))) {
        Write-Host "Creating storage account $Name..."
        Invoke-AzQuiet @('storage','account','create','--resource-group',$ResourceGroup,'--name',$Name,'--location',$Location,'--sku','Standard_LRS','--kind','StorageV2','--https-only','true','--min-tls-version','TLS1_2','--allow-blob-public-access','false')
    }
}

function Ensure-FunctionApp {
    param([string]$Name,[string]$Storage)
    if (-not (Test-AzResource @('functionapp','show','--resource-group',$ResourceGroup,'--name',$Name))) {
        Write-Host "Creating Flex Consumption Function App $Name with Node.js 24..."
        Invoke-AzQuiet @('functionapp','create','--resource-group',$ResourceGroup,'--name',$Name,'--storage-account',$Storage,'--flexconsumption-location',$Location,'--runtime','node','--runtime-version','24','--functions-version','4','--instance-memory','2048')
    } else {
        Write-Host "Function App $Name already exists. Reusing it without changing its hosting plan."

        # az functionapp show does not consistently surface functionAppConfig for Flex apps.
        # Verify the actual ARM site resource and its hosting plan instead.
        $siteJson = Invoke-AzText @('resource','show','--resource-group',$ResourceGroup,'--resource-type','Microsoft.Web/sites','--name',$Name,'--api-version','2024-04-01','--output','json')
        $site = $siteJson | ConvertFrom-Json
        $planId = [string]$site.properties.serverFarmId
        if ([string]::IsNullOrWhiteSpace($planId)) {
            throw "Existing Function App $Name has no hosting plan resource id. Stop and inspect before continuing."
        }

        $planJson = Invoke-AzText @('resource','show','--ids',$planId,'--api-version','2024-04-01','--output','json')
        $plan = $planJson | ConvertFrom-Json
        $planSku = [string]$plan.sku.name
        $planTier = [string]$plan.sku.tier
        if ($planSku -ne 'FC1' -or $planTier -ne 'FlexConsumption') {
            throw "Existing Function App $Name is hosted on SKU $planSku / $planTier, not Flex Consumption FC1. Stop and inspect before continuing."
        }

        $runtimeName = [string]$site.properties.functionAppConfig.runtime.name
        $runtimeVersion = [string]$site.properties.functionAppConfig.runtime.version
        if (-not [string]::IsNullOrWhiteSpace($runtimeName) -and $runtimeName -ne 'node') {
            throw "Existing Function App $Name uses runtime $runtimeName instead of Node.js. Stop and inspect before continuing."
        }
        if (-not [string]::IsNullOrWhiteSpace($runtimeVersion) -and $runtimeVersion -ne '24') {
            throw "Existing Function App $Name uses Node.js $runtimeVersion instead of 24. Stop and inspect before continuing."
        }

        if ([string]::IsNullOrWhiteSpace($runtimeName) -or [string]::IsNullOrWhiteSpace($runtimeVersion)) {
            Write-Host "Verified Flex Consumption FC1 for $Name. Runtime metadata is not exposed by this Azure CLI response; continuing because the hosting plan is definitively Flex."
        } else {
            Write-Host "Verified Flex Consumption FC1, runtime $runtimeName $runtimeVersion for $Name."
        }
    }
}

function Get-CertifiedHmacSecretReference {
    Write-Host 'Resolving the certified HMAC secret name from the ingest Function without reading the secret value...'
    $json = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$SourceIngestApp,'--output','json')
    $items = @($json | ConvertFrom-Json)
    $setting = $items | Where-Object { [string]$_.name -eq 'HMAC_SECRET_NAME' } | Select-Object -First 1
    $secretName = if ($null -eq $setting) { '' } else { ([string]$setting.value).Trim() }
    if ($secretName -notmatch '^[A-Za-z0-9-]{1,127}$') {
        throw 'The ingest Function does not expose a valid HMAC_SECRET_NAME. No HMAC secret value was requested.'
    }
    $secretId = Invoke-AzText @('keyvault','secret','show','--vault-name',$KeyVault,'--name',$secretName,'--query','id','--output','tsv')
    if ([string]::IsNullOrWhiteSpace($secretId)) { throw 'The certified HMAC Key Vault secret could not be confirmed.' }
    Write-Host 'Certified HMAC Key Vault reference: RESOLVED (secret value never requested).'
    return [pscustomobject]@{ SecretName=$secretName; Reference="kv://$KeyVault/$secretName" }
}

function Ensure-SecretReader {
    param([string]$VaultName,[string]$SecretName,[string]$PrincipalId)
    if ([string]::IsNullOrWhiteSpace($VaultName) -or [string]::IsNullOrWhiteSpace($SecretName)) { return }
    $rbac = Invoke-AzText @('keyvault','show','--name',$VaultName,'--query','properties.enableRbacAuthorization','--output','tsv')
    if ($rbac -eq 'true') {
        $vaultId = Invoke-AzText @('keyvault','show','--name',$VaultName,'--query','id','--output','tsv')
        $scope = "$vaultId/secrets/$SecretName"
        $existing = Invoke-AzText @('role','assignment','list','--assignee-object-id',$PrincipalId,'--scope',$scope,'--query',"[?roleDefinitionName=='Key Vault Secrets User'] | length(@)",'--output','tsv')
        if ($existing -eq '0') {
            Invoke-AzQuiet @('role','assignment','create','--assignee-object-id',$PrincipalId,'--assignee-principal-type','ServicePrincipal','--role','Key Vault Secrets User','--scope',$scope)
        }
    } else {
        Invoke-AzQuiet @('keyvault','set-policy','--name',$VaultName,'--object-id',$PrincipalId,'--secret-permissions','get')
    }
}


function New-DeploymentZip {
    param([string]$SourcePath,[string]$DestinationPath)
    if (Test-Path $DestinationPath) { Remove-Item $DestinationPath -Force }
    Compress-Archive -Path (Join-Path $SourcePath '*') -DestinationPath $DestinationPath -CompressionLevel Optimal
}

function Build-NodeDeploymentPackage {
    param([string]$SourcePath,[string]$DestinationPath,[string]$Label)

    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
        throw 'npm is not available in Azure Cloud Shell. Stop before deployment.'
    }

    Write-Host "Preparing $Label locally with production Node dependencies..."
    $nodeVersion = Convert-ToSafeText (& node --version)
    $npmVersion = Convert-ToSafeText (& npm --version)
    Write-Host "Cloud Shell build tools: Node $nodeVersion, npm $npmVersion. Target runtime remains Azure Node.js 24."
    Push-Location $SourcePath
    try {
        if (Test-Path (Join-Path $SourcePath 'node_modules')) {
            Remove-Item -LiteralPath (Join-Path $SourcePath 'node_modules') -Recurse -Force
        }

        if (Test-Path (Join-Path $SourcePath 'package-lock.json')) {
            & npm ci --omit=dev --no-audit --no-fund --engine-strict=false
        } else {
            & npm install --omit=dev --no-audit --no-fund --engine-strict=false
        }
        if ($LASTEXITCODE -ne 0) {
            throw "npm dependency installation failed for $Label."
        }

        if (-not (Test-Path (Join-Path $SourcePath 'node_modules'))) {
            throw "node_modules was not produced for $Label."
        }
    } finally {
        Pop-Location
    }

    New-DeploymentZip -SourcePath $SourcePath -DestinationPath $DestinationPath
}

Write-Host 'FieldFlow Phase 5B deployment starting.'
Write-Host 'The certified ingest Function will not be redeployed or modified.'
Invoke-AzQuiet @('account','set','--subscription',$SubscriptionId)
Write-Host 'Hosting: Flex Consumption, Runtime: Node.js 24, Azure Functions v4.'

Ensure-Storage -Name $RelayStorage
Ensure-Storage -Name $ReceiverStorage
Ensure-FunctionApp -Name $RelayApp -Storage $RelayStorage
Ensure-FunctionApp -Name $ReceiverApp -Storage $ReceiverStorage

Write-Host 'Assigning the Relay managed identity...'
$relayPrincipal = Invoke-AzText @('functionapp','identity','assign','--resource-group',$ResourceGroup,'--name',$RelayApp,'--query','principalId','--output','tsv')
if ([string]::IsNullOrWhiteSpace($relayPrincipal)) { throw 'Relay managed identity was not returned.' }

Write-Host 'Reusing the existing certified TWT HMAC trust through its Key Vault reference...'
$hmac = Get-CertifiedHmacSecretReference
Write-Host 'Removing legacy build app settings that are not supported on Flex Consumption...'
foreach ($appName in @($RelayApp, $ReceiverApp)) {
    $settingsJson = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$appName,'--output','json')
    $settings = $settingsJson | ConvertFrom-Json
    $namesToRemove = @($settings | Where-Object { $_.name -in @('SCM_DO_BUILD_DURING_DEPLOYMENT','ENABLE_ORYX_BUILD') } | ForEach-Object { [string]$_.name })
    if ($namesToRemove.Count -gt 0) {
        $deleteArgs = @('functionapp','config','appsettings','delete','--resource-group',$ResourceGroup,'--name',$appName,'--setting-names') + $namesToRemove
        Invoke-AzQuiet $deleteArgs
    }
}

Invoke-AzQuiet @('functionapp','config','appsettings','set','--resource-group',$ResourceGroup,'--name',$RelayApp,'--settings',
    "FIELDFLOW_HMAC_SECRET=$($hmac.Reference)",
    "FIELDFLOW_SQL_SERVER=$SqlServer",
    "FIELDFLOW_SQL_DATABASE=$SqlDatabase",
    'FIELDFLOW_CLIENT_RELAY_BATCH=20',
    'FIELDFLOW_CLIENT_TIMEOUT_MS=15000')
Ensure-SecretReader -VaultName $KeyVault -SecretName $hmac.SecretName -PrincipalId $relayPrincipal

foreach ($appName in @($RelayApp, $ReceiverApp)) {
    $settingsJson = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$appName,'--output','json')
    $settings = $settingsJson | ConvertFrom-Json
    $unsupportedLeft = @($settings | Where-Object { $_.name -in @('SCM_DO_BUILD_DURING_DEPLOYMENT','ENABLE_ORYX_BUILD') }).Count
    if ($unsupportedLeft -ne 0) { throw "Unsupported Flex build settings are still present on $appName." }
}

$relaySource = Join-Path $PSScriptRoot 'relay-function'
$receiverSource = Join-Path $PSScriptRoot 'test-receiver-function'
$relayZip = Join-Path ([System.IO.Path]::GetTempPath()) 'fieldflow-clientrelay-ready.zip'
$receiverZip = Join-Path ([System.IO.Path]::GetTempPath()) 'fieldflow-clientreceiver-ready.zip'

Write-Host 'Building ready-to-run Node.js packages locally in Cloud Shell...'
Build-NodeDeploymentPackage -SourcePath $relaySource -DestinationPath $relayZip -Label 'Relay Function'
Build-NodeDeploymentPackage -SourcePath $receiverSource -DestinationPath $receiverZip -Label 'test Client Azure receiver'

# Flex Consumption runs from a mounted package. These ZIPs already include all runtime
# dependencies, so remote build must NOT be requested. Asking Azure CLI for remote build
# re-introduces SCM_DO_BUILD_DURING_DEPLOYMENT and ENABLE_ORYX_BUILD, which Flex rejects.
Write-Host 'Deploying the dedicated Relay Function without remote build...'
Invoke-AzQuiet @('functionapp','deployment','source','config-zip','--resource-group',$ResourceGroup,'--name',$RelayApp,'--src',$relayZip)
Write-Host 'Deploying the isolated test Client Azure receiver without remote build...'
Invoke-AzQuiet @('functionapp','deployment','source','config-zip','--resource-group',$ResourceGroup,'--name',$ReceiverApp,'--src',$receiverZip)

Write-Host 'Creating a receiver Function key and storing it directly in Key Vault...'
$keyBytes = New-Object byte[] 48
[Security.Cryptography.RandomNumberGenerator]::Fill($keyBytes)
$receiverKey = [Convert]::ToBase64String($keyBytes)
Invoke-AzQuiet @('functionapp','keys','set','--resource-group',$ResourceGroup,'--name',$ReceiverApp,'--key-type','functionKeys','--key-name','fieldflow-relay','--key-value',$receiverKey)
Invoke-AzQuiet @('keyvault','secret','set','--vault-name',$KeyVault,'--name',$ReceiverSecretName,'--value',$receiverKey)
$receiverKey = $null
$keyBytes = $null

Ensure-SecretReader -VaultName $KeyVault -SecretName $ReceiverSecretName -PrincipalId $relayPrincipal

Write-Host ''
Write-Host 'Phase 5B Azure resources deployed.'
Write-Host "Relay endpoint: $RelayEndpoint"
Write-Host "Test receiver endpoint: $ReceiverEndpoint"
Write-Host "Safe Key Vault reference: $ReceiverSecretRef"
Write-Host ''
Write-Host 'NEXT STEP: run 03-phase-5b-relay-identity.sql in sqldb-fieldflow-core with your Microsoft Entra admin identity.'
Write-Host 'Then run 04-phase-5b-validation.sql. Do not enable WordPress Live mode before those checks are green.'
