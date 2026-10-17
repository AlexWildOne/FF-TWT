param(
    [string]$SubscriptionId = '60350161-0e9d-446d-b691-965176fb440b',
    [string]$ResourceGroup = 'rg-fieldflow-prod-weu',
    [string]$Location = 'westeurope'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$SourceRelayApp = 'func-fieldflow-clientrelay-prod-eb978f11'
$SourceIngestApp = 'func-fieldflow-ingest-prod-eb978f11'
$BrokerApp = 'func-fieldflow-entrabroker-prod-eb978f11'
$BrokerStorage = 'stffentraeb978f11'
$BrokerIdentity = 'id-fieldflow-entra-prod-eb978f11'
$BrokerRegistrationName = 'FieldFlow Enterprise Connector Broker'
$FederatedCredentialName = 'fieldflow-entra-broker-uami'
$BrokerTokenProbeEndpoint = "https://$BrokerApp.azurewebsites.net/api/v1/token-probe"
$BrokerHealthEndpoint = "https://$BrokerApp.azurewebsites.net/api/v1/health"
$BrokerResourceProbeEndpoint = "https://$BrokerApp.azurewebsites.net/api/v1/resource-probe"
$BrokerConsentCallback = "https://$BrokerApp.azurewebsites.net/api/v1/admin-consent/callback"
$HmacVault = 'kv-ff-prod-eb978f11'
$HmacSecretNameSetting = 'HMAC_SECRET_NAME'
$HmacSecretRef = ''

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
        $stderrRaw = $null
        if (Test-Path $stderrFile) { $stderrRaw = Get-Content -LiteralPath $stderrFile -Raw -ErrorAction SilentlyContinue }
        $stderrText = Convert-ToSafeText $stderrRaw
        $stdoutText = Convert-ToSafeText $result
        if ($exitCode -ne 0) {
            $details = @($stdoutText, $stderrText) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
            if ($details.Count -eq 0) { throw 'Azure CLI command failed without diagnostic output.' }
            throw "Azure CLI command failed. $($details -join [Environment]::NewLine)"
        }
        if (-not [string]::IsNullOrWhiteSpace($stderrText)) {
            foreach ($line in ($stderrText -split '\r?\n')) { if (-not [string]::IsNullOrWhiteSpace($line)) { Write-Warning $line } }
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

function Set-AppSettingsIfChanged {
    param([string]$FunctionName,[hashtable]$DesiredSettings)
    $json = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$FunctionName,'--output','json')
    $items = @($json | ConvertFrom-Json)
    $changes = @()
    foreach ($key in $DesiredSettings.Keys) {
        $current = $items | Where-Object { [string]$_.name -eq [string]$key } | Select-Object -First 1
        $desiredValue = [string]$DesiredSettings[$key]
        if ($null -eq $current -or [string]$current.value -ne $desiredValue) {
            $changes += ("{0}={1}" -f $key, $desiredValue)
        }
    }
    if ($changes.Count -eq 0) {
        Write-Host 'Pre-deployment Broker app settings already match. No restart-triggering settings update required.'
        return $false
    }
    $args = @('functionapp','config','appsettings','set','--resource-group',$ResourceGroup,'--name',$FunctionName,'--settings') + $changes
    Invoke-AzQuiet $args
    Write-Host 'Pre-deployment Broker app settings updated. Azure may recycle the Function host.'
    return $true
}

function Invoke-FlexCodeDeployment {
    param([string]$FunctionName,[string]$ZipPath,[int]$MaxAttempts = 3)
    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        $stderrFile = [System.IO.Path]::GetTempFileName()
        try {
            $output = & az functionapp deployment source config-zip --resource-group $ResourceGroup --name $FunctionName --src $ZipPath --only-show-errors 2>$stderrFile
            $exitCode = $LASTEXITCODE
            $stderrRaw = if (Test-Path $stderrFile) { Get-Content -LiteralPath $stderrFile -Raw -ErrorAction SilentlyContinue } else { '' }
            $combined = ((Convert-ToSafeText $output) + [Environment]::NewLine + (Convert-ToSafeText $stderrRaw)).Trim()
            if ($exitCode -eq 0) {
                Write-Host "Flex code deployment succeeded on attempt $attempt."
                return
            }
            $isKuduRecycle = $combined -match '(?i)KuduSpecializer|Kudu has been restarted|Kudu Restart Count'
            if ($isKuduRecycle -and $attempt -lt $MaxAttempts) {
                Write-Warning "Flex deployment collided with a transient Kudu recycle on attempt $attempt. Waiting 30 seconds before retrying the same package."
                Start-Sleep -Seconds 30
                continue
            }
            if ([string]::IsNullOrWhiteSpace($combined)) { throw 'Flex code deployment failed without diagnostic output.' }
            throw "Flex code deployment failed. $combined"
        } finally {
            Remove-Item -LiteralPath $stderrFile -Force -ErrorAction SilentlyContinue
        }
    }
    throw 'Flex code deployment exhausted all retry attempts.'
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
    } else {
        Write-Host "Storage account $Name already exists. Reusing it."
    }
}

function Ensure-FunctionApp {
    param([string]$Name,[string]$Storage)
    if (-not (Test-AzResource @('functionapp','show','--resource-group',$ResourceGroup,'--name',$Name))) {
        Write-Host "Creating Flex Consumption Function App $Name with Node.js 24..."
        Invoke-AzQuiet @('functionapp','create','--resource-group',$ResourceGroup,'--name',$Name,'--storage-account',$Storage,'--flexconsumption-location',$Location,'--runtime','node','--runtime-version','24','--functions-version','4','--instance-memory','2048')
    } else {
        Write-Host "Function App $Name already exists. Reusing it without changing its hosting plan."
        $siteJson = Invoke-AzText @('resource','show','--resource-group',$ResourceGroup,'--resource-type','Microsoft.Web/sites','--name',$Name,'--api-version','2024-04-01','--output','json')
        $site = $siteJson | ConvertFrom-Json
        $planId = [string]$site.properties.serverFarmId
        if ([string]::IsNullOrWhiteSpace($planId)) { throw "Existing Function App $Name has no hosting plan resource id." }
        $planJson = Invoke-AzText @('resource','show','--ids',$planId,'--api-version','2024-04-01','--output','json')
        $plan = $planJson | ConvertFrom-Json
        if ([string]$plan.sku.name -ne 'FC1' -or [string]$plan.sku.tier -ne 'FlexConsumption') {
            throw "Existing Function App $Name is not Flex Consumption FC1. Stop before changing it."
        }
        $runtimeName = [string]$site.properties.functionAppConfig.runtime.name
        $runtimeVersion = [string]$site.properties.functionAppConfig.runtime.version
        if (-not [string]::IsNullOrWhiteSpace($runtimeName) -and $runtimeName -ne 'node') { throw "Existing Function App $Name is not Node.js." }
        if (-not [string]::IsNullOrWhiteSpace($runtimeVersion) -and $runtimeVersion -ne '24') { throw "Existing Function App $Name is not Node.js 24." }
        Write-Host "Verified Flex Consumption FC1 for $Name."
    }
}

function Ensure-UserAssignedIdentity {
    if (-not (Test-AzResource @('identity','show','--resource-group',$ResourceGroup,'--name',$BrokerIdentity))) {
        Write-Host "Creating dedicated user-assigned managed identity $BrokerIdentity..."
        Invoke-AzQuiet @('identity','create','--resource-group',$ResourceGroup,'--name',$BrokerIdentity,'--location',$Location)
    } else {
        Write-Host "User-assigned managed identity $BrokerIdentity already exists. Reusing it."
    }
    $json = Invoke-AzText @('identity','show','--resource-group',$ResourceGroup,'--name',$BrokerIdentity,'--output','json')
    return ($json | ConvertFrom-Json)
}

function Get-CertifiedHmacSecretName {
    # The certified ingest Function stores only the Key Vault secret *name* in
    # HMAC_SECRET_NAME. Never request or copy the secret value through Azure CLI.
    $json = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$SourceIngestApp,'--output','json')
    $items = $json | ConvertFrom-Json
    $match = $items | Where-Object { $_.name -eq $HmacSecretNameSetting } | Select-Object -First 1
    if ($null -eq $match) { throw "Certified ingest setting $HmacSecretNameSetting was not found." }
    $secretName = Convert-ToSafeText $match.value
    if ($secretName -notmatch '^[0-9A-Za-z-]{1,127}$') {
        throw 'Certified HMAC secret name is missing or invalid. No secret value was requested.'
    }

    # Confirm the referenced secret exists using metadata only. This command does
    # not print or persist the secret value.
    $secretId = Invoke-AzText @('keyvault','secret','show','--vault-name',$HmacVault,'--name',$secretName,'--query','id','--output','tsv')
    if ([string]::IsNullOrWhiteSpace($secretId)) {
        throw 'The certified HMAC secret name does not resolve to an existing Key Vault secret.'
    }

    # Optional consistency check against the certified 5B Relay. We compare only
    # in memory and never print either value. A mismatch is a warning because 5B
    # can be hardened independently after 5C.2 is closed.
    try {
        $relayJson = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$SourceRelayApp,'--output','json')
        $relayItems = $relayJson | ConvertFrom-Json
        $relaySetting = $relayItems | Where-Object { $_.name -eq 'FIELDFLOW_HMAC_SECRET' } | Select-Object -First 1
        if ($null -ne $relaySetting) {
            $relayValue = Convert-ToSafeText $relaySetting.value
            if (-not [string]::IsNullOrWhiteSpace($relayValue) -and $relayValue -ne $secretName) {
                Write-Warning 'The 5B Relay HMAC setting does not match the ingest secret-name reference. 5C.2 will use the certified ingest reference and 5B should be revalidated separately.'
            }
        }
    } catch {
        Write-Warning 'Could not perform the non-sensitive 5B HMAC reference consistency check. Continuing with the certified ingest reference.'
    }

    return $secretName
}

function Ensure-SecretReader {
    param([string]$VaultName,[string]$SecretName,[string]$PrincipalId)
    if ([string]::IsNullOrWhiteSpace($VaultName) -or [string]::IsNullOrWhiteSpace($SecretName) -or [string]::IsNullOrWhiteSpace($PrincipalId)) { return }
    $rbac = Invoke-AzText @('keyvault','show','--name',$VaultName,'--query','properties.enableRbacAuthorization','--output','tsv')
    if ($rbac -eq 'true') {
        $vaultId = Invoke-AzText @('keyvault','show','--name',$VaultName,'--query','id','--output','tsv')
        $scope = "$vaultId/secrets/$SecretName"
        $existing = Invoke-AzText @('role','assignment','list','--assignee-object-id',$PrincipalId,'--scope',$scope,'--query',"[?roleDefinitionName=='Key Vault Secrets User'] | length(@)",'--output','tsv')
        if ($existing -eq '0') { Invoke-AzQuiet @('role','assignment','create','--assignee-object-id',$PrincipalId,'--assignee-principal-type','ServicePrincipal','--role','Key Vault Secrets User','--scope',$scope) }
    } else {
        Invoke-AzQuiet @('keyvault','set-policy','--name',$VaultName,'--object-id',$PrincipalId,'--secret-permissions','get')
    }
}

function Parse-HmacKeyVaultReference {
    param([string]$SettingValue)
    $raw = Convert-ToSafeText $SettingValue
    if ([string]::IsNullOrWhiteSpace($raw)) { return $null }

    if ($raw -match '(?i)^kv://([^/]+)/([^/]+)(?:/([^/]+))?$') {
        return [pscustomobject]@{ VaultName=$Matches[1]; SecretName=$Matches[2]; SecretVersion=$(if ($Matches.Count -gt 3) { $Matches[3] } else { '' }) }
    }
    if ($raw -match '(?i)^https://([^.]+)\.vault\.azure\.net/secrets/([^/]+)(?:/([^/]+))?/?$') {
        return [pscustomobject]@{ VaultName=$Matches[1]; SecretName=$Matches[2]; SecretVersion=$(if ($Matches.Count -gt 3) { $Matches[3] } else { '' }) }
    }
    if ($raw -match '(?i)SecretUri=https://([^.]+)\.vault\.azure\.net/secrets/([^/;)]+)(?:/([^/;)]+))?') {
        return [pscustomobject]@{ VaultName=$Matches[1]; SecretName=$Matches[2]; SecretVersion=$(if ($Matches.Count -gt 3) { $Matches[3] } else { '' }) }
    }
    if ($raw -match '(?i)VaultName=([^;\)]+);SecretName=([^;\)]+)(?:;SecretVersion=([^;\)]+))?') {
        return [pscustomobject]@{ VaultName=$Matches[1]; SecretName=$Matches[2]; SecretVersion=$(if ($Matches.Count -gt 3) { $Matches[3] } else { '' }) }
    }
    return $null
}

function Grant-HmacReferenceIfNeeded {
    param([string]$SettingValue,[string]$PrincipalId)
    $ref = Parse-HmacKeyVaultReference -SettingValue $SettingValue
    if ($null -ne $ref) {
        Ensure-SecretReader -VaultName ([string]$ref.VaultName) -SecretName ([string]$ref.SecretName) -PrincipalId $PrincipalId
    }
}

function Set-KeyVaultReferenceIdentity {
    param([string]$FunctionName,[string]$IdentityResourceId)
    if ([string]::IsNullOrWhiteSpace($FunctionName) -or [string]::IsNullOrWhiteSpace($IdentityResourceId)) { return $false }
    $appId = Invoke-AzText @('functionapp','show','--resource-group',$ResourceGroup,'--name',$FunctionName,'--query','id','--output','tsv')
    if ([string]::IsNullOrWhiteSpace($appId)) { throw 'Could not resolve Entra Broker Function resource ID for Key Vault reference identity.' }
    $currentIdentity = Invoke-AzText @('resource','show','--ids',$appId,'--api-version','2024-04-01','--query','properties.keyVaultReferenceIdentity','--output','tsv')
    if ([string]$currentIdentity -eq [string]$IdentityResourceId) {
        Write-Host 'Broker Key Vault reference identity already matches the dedicated UAMI.'
        return $false
    }
    $bodyFile = [System.IO.Path]::GetTempFileName()
    try {
        @{ properties = @{ keyVaultReferenceIdentity = $IdentityResourceId } } | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $bodyFile -Encoding utf8
        $managementUri = "https://management.azure.com${appId}?api-version=2024-04-01"
        Invoke-AzQuiet @('rest','--method','PATCH','--uri',$managementUri,'--body',"@$bodyFile")
        Write-Host 'Broker Key Vault reference identity updated. Azure may recycle the Function host.'
        return $true
    } finally {
        Remove-Item -LiteralPath $bodyFile -Force -ErrorAction SilentlyContinue
    }
}

function Ensure-BrokerApplication {
    param([string]$HomeTenantId,[string]$ManagedIdentityPrincipalId)
    Write-Host 'Ensuring the TWT multi-tenant Entra application registration...'
    $appsJson = Invoke-AzText @('ad','app','list','--display-name',$BrokerRegistrationName,'--output','json')
    $apps = @($appsJson | ConvertFrom-Json)
    if ($apps.Count -gt 1) { throw "More than one Entra application named '$BrokerRegistrationName' exists. Stop and inspect before continuing." }
    if ($apps.Count -eq 0) {
        $createdJson = Invoke-AzText @('ad','app','create','--display-name',$BrokerRegistrationName,'--sign-in-audience','AzureADMultipleOrgs','--web-redirect-uris',$BrokerConsentCallback,'--output','json')
        $appObject = $createdJson | ConvertFrom-Json
        Write-Host 'Created the multi-tenant Entra broker application.'
    } else {
        $appObject = $apps[0]
        $existingAppJson = Invoke-AzText @('ad','app','show','--id',[string]$appObject.id,'--output','json')
        $appObject = $existingAppJson | ConvertFrom-Json
        $redirects = @($appObject.web.redirectUris | Where-Object { -not [string]::IsNullOrWhiteSpace([string]$_) })
        if ($BrokerConsentCallback -notin $redirects) { $redirects += $BrokerConsentCallback }
        $updateArgs = @('ad','app','update','--id',[string]$appObject.id,'--sign-in-audience','AzureADMultipleOrgs','--web-redirect-uris') + $redirects
        Invoke-AzQuiet $updateArgs
        Write-Host 'Reusing the existing TWT multi-tenant Entra broker application.'
    }

    # Read the full application object before enforcing the secretless invariant.
    $appJson = Invoke-AzText @('ad','app','show','--id',[string]$appObject.id,'--output','json')
    $appObject = $appJson | ConvertFrom-Json
    $passwordCount = @($appObject.passwordCredentials).Count
    $certificateCount = @($appObject.keyCredentials).Count
    if ($passwordCount -gt 0 -or $certificateCount -gt 0) {
        throw 'The dedicated Entra broker application contains password/certificate credentials. This phase is secretless; stop and inspect instead of continuing.'
    }
    if ([string]$appObject.signInAudience -ne 'AzureADMultipleOrgs') { throw 'The Entra broker application is not configured as multi-tenant.' }

    $appId = [string]$appObject.appId
    $appObjectId = [string]$appObject.id
    if ([string]::IsNullOrWhiteSpace($appId) -or [string]::IsNullOrWhiteSpace($appObjectId)) { throw 'Entra application IDs were not returned.' }

    if (-not (Test-AzResource @('ad','sp','show','--id',$appId))) {
        Write-Host 'Creating the home-tenant enterprise application/service principal...'
        Invoke-AzQuiet @('ad','sp','create','--id',$appId)
        Start-Sleep -Seconds 3
    }

    $issuer = "https://login.microsoftonline.com/$HomeTenantId/v2.0"
    $ficsJson = Invoke-AzText @('ad','app','federated-credential','list','--id',$appObjectId,'--output','json')
    $fics = @($ficsJson | ConvertFrom-Json)
    $fic = $fics | Where-Object { [string]$_.name -eq $FederatedCredentialName } | Select-Object -First 1
    if ($null -eq $fic) {
        Write-Host 'Creating the federated identity credential that trusts the dedicated managed identity...'
        $temp = [System.IO.Path]::GetTempFileName()
        try {
            @{
                name = $FederatedCredentialName
                issuer = $issuer
                subject = $ManagedIdentityPrincipalId
                description = 'FieldFlow 5C.2 Entra Broker workload identity federation'
                audiences = @('api://AzureADTokenExchange')
            } | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $temp -Encoding utf8
            Invoke-AzQuiet @('ad','app','federated-credential','create','--id',$appObjectId,'--parameters',$temp)
        } finally { Remove-Item -LiteralPath $temp -Force -ErrorAction SilentlyContinue }
        Start-Sleep -Seconds 5
    } else {
        $audiences = @($fic.audiences)
        if ([string]$fic.issuer -ne $issuer -or [string]$fic.subject -ne $ManagedIdentityPrincipalId -or 'api://AzureADTokenExchange' -notin $audiences) {
            throw 'The existing FieldFlow federated identity credential does not match the dedicated managed identity. Stop and inspect before continuing.'
        }
        Write-Host 'Federated identity credential already exists and matches the dedicated managed identity.'
    }

    return [pscustomobject]@{ AppId=$appId; ObjectId=$appObjectId; Issuer=$issuer }
}

function New-DeploymentZip {
    param([string]$SourcePath,[string]$DestinationPath)
    if (Test-Path $DestinationPath) { Remove-Item $DestinationPath -Force }
    Compress-Archive -Path (Join-Path $SourcePath '*') -DestinationPath $DestinationPath -CompressionLevel Optimal
}

function Build-NodeDeploymentPackage {
    param([string]$SourcePath,[string]$DestinationPath)
    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) { throw 'npm is not available in Azure Cloud Shell.' }
    Write-Host 'Preparing the Entra Broker locally with production Node dependencies...'
    $nodeVersion = Convert-ToSafeText (& node --version)
    $npmVersion = Convert-ToSafeText (& npm --version)
    Write-Host "Cloud Shell build tools: Node $nodeVersion, npm $npmVersion. Target runtime remains Azure Node.js 24."
    Push-Location $SourcePath
    try {
        if (Test-Path (Join-Path $SourcePath 'node_modules')) { Remove-Item -LiteralPath (Join-Path $SourcePath 'node_modules') -Recurse -Force }
        if (Test-Path (Join-Path $SourcePath 'package-lock.json')) { & npm ci --omit=dev --no-audit --no-fund --engine-strict=false }
        else { & npm install --omit=dev --no-audit --no-fund --engine-strict=false }
        if ($LASTEXITCODE -ne 0) { throw 'npm dependency installation failed for the Entra Broker.' }
        if (-not (Test-Path (Join-Path $SourcePath 'node_modules'))) { throw 'node_modules was not produced for the Entra Broker.' }
    } finally { Pop-Location }
    New-DeploymentZip -SourcePath $SourcePath -DestinationPath $DestinationPath
}

Write-Host 'FieldFlow Phase 5C.2 Entra Broker deployment starting.'
Write-Host 'This deployment does not modify the certified ingest Function or the certified 5B Client Azure Relay.'
Invoke-AzQuiet @('account','set','--subscription',$SubscriptionId)
$homeTenantId = Invoke-AzText @('account','show','--query','tenantId','--output','tsv')
if ($homeTenantId -notmatch '^[0-9a-fA-F-]{36}$') { throw 'Could not resolve the TWT Microsoft Entra tenant ID.' }
Write-Host "Home tenant resolved: $homeTenantId"
Write-Host 'Hosting: Flex Consumption, Runtime: Node.js 24, Azure Functions v4.'

Ensure-Storage -Name $BrokerStorage
$uami = Ensure-UserAssignedIdentity
$uamiId = [string]$uami.id
$uamiClientId = [string]$uami.clientId
$uamiPrincipalId = [string]$uami.principalId
if ([string]::IsNullOrWhiteSpace($uamiId) -or [string]::IsNullOrWhiteSpace($uamiClientId) -or [string]::IsNullOrWhiteSpace($uamiPrincipalId)) { throw 'The dedicated user-assigned managed identity is incomplete.' }

Ensure-FunctionApp -Name $BrokerApp -Storage $BrokerStorage
Write-Host 'Assigning system identity plus the dedicated user-assigned identity to the Entra Broker...'
$identityJson = Invoke-AzText @('functionapp','identity','assign','--resource-group',$ResourceGroup,'--name',$BrokerApp,'--identities','[system]',$uamiId,'--output','json')
$identity = $identityJson | ConvertFrom-Json
$systemPrincipalId = [string]$identity.principalId
if ([string]::IsNullOrWhiteSpace($systemPrincipalId)) { throw 'The Entra Broker system identity was not returned.' }

$brokerRegistration = Ensure-BrokerApplication -HomeTenantId $homeTenantId -ManagedIdentityPrincipalId $uamiPrincipalId

Write-Host 'Resolving the existing certified HMAC Key Vault secret name from the ingest Function without reading the secret value...'
$certifiedHmacSecretName = Get-CertifiedHmacSecretName
$HmacSecretRef = "kv://$HmacVault/$certifiedHmacSecretName"
Write-Host 'Certified HMAC Key Vault reference: RESOLVED (secret value never requested).'
Ensure-SecretReader -VaultName $HmacVault -SecretName $certifiedHmacSecretName -PrincipalId $uamiPrincipalId
# The system identity receives the same single-secret read permission only as a
# controlled fallback for native App Service Key Vault resolution. Runtime code
# explicitly selects the dedicated UAMI.
Ensure-SecretReader -VaultName $HmacVault -SecretName $certifiedHmacSecretName -PrincipalId $systemPrincipalId
$keyVaultIdentityChanged = Set-KeyVaultReferenceIdentity -FunctionName $BrokerApp -IdentityResourceId $uamiId
Write-Host 'Broker Key Vault access prepared for the existing certified HMAC secret.'

Write-Host 'Removing legacy build app settings that are not supported on Flex Consumption...'
$settingsJson = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$BrokerApp,'--output','json')
$settings = $settingsJson | ConvertFrom-Json
$namesToRemove = @($settings | Where-Object { $_.name -in @('SCM_DO_BUILD_DURING_DEPLOYMENT','ENABLE_ORYX_BUILD') } | ForEach-Object { [string]$_.name })
if ($namesToRemove.Count -gt 0) {
    $deleteArgs = @('functionapp','config','appsettings','delete','--resource-group',$ResourceGroup,'--name',$BrokerApp,'--setting-names') + $namesToRemove
    Invoke-AzQuiet $deleteArgs
}

$preSettingsChanged = Set-AppSettingsIfChanged -FunctionName $BrokerApp -DesiredSettings @{
    FIELDFLOW_ENTRA_BROKER_CLIENT_ID = [string]$brokerRegistration.AppId
    FIELDFLOW_ENTRA_HOME_TENANT_ID = [string]$homeTenantId
    FIELDFLOW_ENTRA_MI_CLIENT_ID = [string]$uamiClientId
}

# Microsoft documents that Function App setting changes restart the app. On Flex
# Consumption a deployment that starts while that recycle is still in progress can
# lose its transient Kudu specializer. Give the control-plane update time to settle.
if ($preSettingsChanged -or $keyVaultIdentityChanged -or $namesToRemove.Count -gt 0) {
    Write-Host 'Waiting 40 seconds for Flex Consumption configuration recycle to settle before code deployment...'
    Start-Sleep -Seconds 40
} else {
    Write-Host 'No restart-triggering pre-deployment configuration changes detected.'
}

$source = Join-Path $PSScriptRoot 'entra-broker-function'
$zip = Join-Path ([System.IO.Path]::GetTempPath()) 'fieldflow-entrabroker-ready.zip'
Write-Host 'Building a ready-to-run Node.js package locally in Cloud Shell...'
Build-NodeDeploymentPackage -SourcePath $source -DestinationPath $zip
Write-Host 'Deploying the dedicated Entra Broker without remote build...'
Invoke-FlexCodeDeployment -FunctionName $BrokerApp -ZipPath $zip -MaxAttempts 3

# Apply only a safe Key Vault reference after package deployment. The raw HMAC
# key is never requested by this deployment and is never stored as a Broker app setting.
Write-Host 'Applying the existing certified Key Vault HMAC reference to the Entra Broker after deployment...'
Invoke-AzQuiet @('functionapp','config','appsettings','set','--resource-group',$ResourceGroup,'--name',$BrokerApp,'--settings',
    "FIELDFLOW_HMAC_SECRET=$HmacSecretRef",
    "FIELDFLOW_HMAC_KEYVAULT_URI=https://$HmacVault.vault.azure.net",
    "FIELDFLOW_HMAC_KEYVAULT_SECRET_NAME=$certifiedHmacSecretName",
    'FIELDFLOW_HMAC_KEYVAULT_SECRET_VERSION=',
    'FIELDFLOW_HMAC_SOURCE_MODE=certified_ingest_key_vault_reference')

Ensure-SecretReader -VaultName $HmacVault -SecretName $certifiedHmacSecretName -PrincipalId $uamiPrincipalId
Ensure-SecretReader -VaultName $HmacVault -SecretName $certifiedHmacSecretName -PrincipalId $systemPrincipalId

$brokerSettingsJson = Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$BrokerApp,'--output','json')
$brokerSettings = $brokerSettingsJson | ConvertFrom-Json
$hmacPresent = @($brokerSettings | Where-Object { $_.name -eq 'FIELDFLOW_HMAC_SECRET' -and -not [string]::IsNullOrWhiteSpace([string]$_.value) }).Count -eq 1
if (-not $hmacPresent) { throw 'Broker HMAC app setting was not persisted after deployment. Stop before token probe.' }
Write-Host 'Broker HMAC app setting/reference: PRESENT (value intentionally hidden).'

Write-Host 'Restarting the Entra Broker so the runtime reloads the HMAC configuration...'
Invoke-AzQuiet @('functionapp','restart','--resource-group',$ResourceGroup,'--name',$BrokerApp)
Start-Sleep -Seconds 10

Write-Host 'Verifying HMAC readiness through the safe health endpoint...'
$healthOk = $false
for ($i = 1; $i -le 24; $i++) {
    try {
        $health = Invoke-RestMethod -Uri $BrokerHealthEndpoint -Method Get -TimeoutSec 20
        if ($health.ok -eq $true -and [string]$health.version -eq '1.4.0' -and $health.hmac_runtime_ready -eq $true -and $health.external_tenant_onboarding -eq $true -and $health.browser_only_tenant_bootstrap -eq $true -and $health.client_subscription_required -eq $false -and $health.onboarding_callback_token_probe -eq $true -and $health.onboarding_receipt_hmac -eq $true -and $health.resource_authorization_control_plane -eq $true -and $health.resource_probe_read_only -eq $true -and $health.delivery_enabled -eq $false) {
            $healthOk = $true
            Write-Host "Broker HMAC runtime: READY via $($health.hmac_runtime_source)."
            Write-Host 'External tenant onboarding control plane: READY, browser-only, no client Azure subscription required.'
            Write-Host 'Resource authorization 5C.3: READY, read-only probes only, delivery disabled.'
            break
        }
    } catch { }
    Start-Sleep -Seconds 10
}
if (-not $healthOk) { throw 'Broker HMAC runtime did not become ready from the existing certified Key Vault secret. No secret value was requested or displayed; inspect the safe health source before token probing.' }

Write-Host ''
Write-Host 'Phase 5C.2 + 5C.3 Entra Broker resources deployed.'
Write-Host "Broker token probe endpoint: $BrokerTokenProbeEndpoint"
Write-Host "Broker health endpoint: $BrokerHealthEndpoint"
Write-Host "Broker resource probe endpoint: $BrokerResourceProbeEndpoint"
Write-Host "Broker Application (client) ID: $($brokerRegistration.AppId)"
Write-Host "TWT Directory (tenant) ID: $homeTenantId"
Write-Host "Dedicated managed identity client ID: $uamiClientId"
Write-Host "Microsoft connection callback: $BrokerConsentCallback"
Write-Host 'External tenant onboarding: browser-only Enterprise Application bootstrap + signed state + callback token probe + HMAC receipt to FieldFlow.'
Write-Host '5C.3 resource authorization: Azure SQL SELECT 1, Power BI REST workspace GET, and Fabric REST workspace GET. Read-only, no delivery. API/Webhook remain Shadow-only.'
Write-Host 'Credential model: user-assigned managed identity + federated identity credential. No client secret or certificate was created.'
Write-Host ''
Write-Host 'NEXT STEP: in FieldFlow 2.2.368 verify the 5C.3 Broker health, then configure a read-only resource target for a destination already READY, IDENTIDADE. Delivery remains blocked.'
