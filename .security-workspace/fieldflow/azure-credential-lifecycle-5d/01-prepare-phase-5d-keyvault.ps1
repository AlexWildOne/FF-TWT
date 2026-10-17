param(
    [string]$SubscriptionId = '60350161-0e9d-446d-b691-965176fb440b',
    [string]$ResourceGroup = 'rg-fieldflow-prod-weu',
    [string]$BrokerApp = 'func-fieldflow-entrabroker-prod-eb978f11',
    [string]$KeyVault = 'kv-ff-prod-eb978f11',
    [string]$TestSecretName = 'fieldflow-5d-certification-key'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

function Assert-Command {
    param([string]$Name)
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) { throw "$Name is required in Azure Cloud Shell." }
}

function Invoke-AzText {
    param([string[]]$Arguments)
    $value = & az @Arguments --only-show-errors --output tsv
    if ($LASTEXITCODE -ne 0) { throw 'Azure CLI command failed.' }
    return [string]$value
}

function Invoke-AzQuiet {
    param([string[]]$Arguments)
    & az @Arguments --only-show-errors --output none | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Azure CLI command failed.' }
}

Assert-Command 'az'
Write-Host 'FieldFlow Phase 5D, preparing the dedicated Key Vault certification credential.'
Write-Host 'No secret value is printed or stored in WordPress. The secret is synthetic and exists only to certify the credential lifecycle.'

Invoke-AzQuiet @('account','set','--subscription',$SubscriptionId)
& az functionapp show --resource-group $ResourceGroup --name $BrokerApp --only-show-errors --output none
if ($LASTEXITCODE -ne 0) { throw "Existing Broker Function $BrokerApp was not found." }
& az keyvault show --resource-group $ResourceGroup --name $KeyVault --only-show-errors --output none
if ($LASTEXITCODE -ne 0) { throw "Existing Key Vault $KeyVault was not found." }

$miClientId = (Invoke-AzText @('functionapp','config','appsettings','list','--resource-group',$ResourceGroup,'--name',$BrokerApp,'--query',"[?name=='FIELDFLOW_ENTRA_MI_CLIENT_ID'].value | [0]")).Trim()
if ($miClientId -notmatch '^[0-9a-fA-F-]{36}$') { throw 'FIELDFLOW_ENTRA_MI_CLIENT_ID is missing on the existing Broker.' }
$principalId = (Invoke-AzText @('identity','list','--resource-group',$ResourceGroup,'--query',"[?clientId=='$miClientId'].principalId | [0]")).Trim()
if ($principalId -notmatch '^[0-9a-fA-F-]{36}$') { throw 'Could not resolve the Broker user-assigned managed identity principal ID.' }

$secretExists = $true
& az keyvault secret show --vault-name $KeyVault --name $TestSecretName --only-show-errors --output none 2>$null
if ($LASTEXITCODE -ne 0) { $secretExists = $false }

$expires = (Get-Date).ToUniversalTime().AddDays(365).ToString('yyyy-MM-ddTHH:mm:ssZ')
if (-not $secretExists) {
    $bytes = New-Object byte[] 48
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    $value = [Convert]::ToBase64String($bytes)
    & az keyvault secret set --vault-name $KeyVault --name $TestSecretName --value $value --expires $expires --only-show-errors --output none
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the synthetic 5D certification secret.' }
    $value = $null
    Write-Host 'Synthetic 5D certification secret created with a 365-day expiry.'
} else {
    $secretMetaJson = & az keyvault secret show --vault-name $KeyVault --name $TestSecretName --query attributes --only-show-errors --output json
    if ($LASTEXITCODE -ne 0) { throw 'Could not read safe metadata for the existing 5D certification secret.' }
    $secretMeta = $secretMetaJson | ConvertFrom-Json
    $currentExpiryText = [string]$secretMeta.expires
    $needsFutureExpiry = [string]::IsNullOrWhiteSpace($currentExpiryText)
    if (-not $needsFutureExpiry) {
        try { $needsFutureExpiry = ([DateTimeOffset]::Parse($currentExpiryText).UtcDateTime -le (Get-Date).ToUniversalTime().AddDays(30)) } catch { $needsFutureExpiry = $true }
    }
    if ($needsFutureExpiry) {
        Invoke-AzQuiet @('keyvault','secret','set-attributes','--vault-name',$KeyVault,'--name',$TestSecretName,'--expires',$expires)
        Write-Host 'Existing 5D certification secret received a fresh 365-day expiry window. Its value was not changed.'
    }
    if ($secretMeta.enabled -eq $false) {
        Invoke-AzQuiet @('keyvault','secret','set-attributes','--vault-name',$KeyVault,'--name',$TestSecretName,'--enabled','true')
        Write-Host 'Existing synthetic 5D certification secret was re-enabled. Its value was not changed.'
    }
    if (-not $needsFutureExpiry -and $secretMeta.enabled -ne $false) {
        Write-Host 'Existing 5D certification secret found with a safe validity window. Its value was not changed.'
    }
}

$vaultId = (Invoke-AzText @('keyvault','show','--resource-group',$ResourceGroup,'--name',$KeyVault,'--query','id')).Trim()
if (-not $vaultId) { throw 'Could not resolve Key Vault ARM ID.' }
$rbac = (Invoke-AzText @('keyvault','show','--resource-group',$ResourceGroup,'--name',$KeyVault,'--query','properties.enableRbacAuthorization')).Trim().ToLowerInvariant()
if ($rbac -eq 'true') {
    $secretScope = "$vaultId/secrets/$TestSecretName"
    $assignmentCount = (Invoke-AzText @('role','assignment','list','--scope',$secretScope,'--query',"[?principalId=='$principalId' && roleDefinitionName=='Key Vault Secrets User'] | length(@)")).Trim()
    if ([int]($assignmentCount -as [int]) -lt 1) {
        & az role assignment create --assignee-object-id $principalId --assignee-principal-type ServicePrincipal --role 'Key Vault Secrets User' --scope $secretScope --only-show-errors --output none
        if ($LASTEXITCODE -ne 0) { throw 'Could not grant the Broker least-privilege Key Vault Secrets User access to the 5D test secret.' }
        Write-Host 'Least-privilege Key Vault Secrets User role assigned to the Broker for the 5D test secret.'
    } else {
        Write-Host 'Broker already has Key Vault Secrets User access to the 5D test secret.'
    }
} else {
    Invoke-AzQuiet @('keyvault','set-policy','--name',$KeyVault,'--object-id',$principalId,'--secret-permissions','get')
    Write-Host 'Key Vault access-policy mode detected. Confirmed Broker get permission; no secret value was requested.'
}

Invoke-AzQuiet @('functionapp','config','appsettings','set','--resource-group',$ResourceGroup,'--name',$BrokerApp,'--settings',"FIELDFLOW_CREDENTIAL_KEYVAULT_NAME=$KeyVault","FIELDFLOW_CREDENTIAL_TEST_SECRET_NAME=$TestSecretName")

Write-Host 'Phase 5D Key Vault preparation READY.'
Write-Host "Safe reference: kv://$KeyVault/$TestSecretName"
Write-Host 'Next: run 02-deploy-phase-5d-credential-lifecycle-code-only.ps1.'
