# FieldFlow Phase 5C.2, Microsoft Entra cross-tenant

## Objetivo deste bloco

A 5C.2 separa identidade de delivery. O FieldFlow passa a ter uma fundacao para autenticar conectores empresariais em tenants Microsoft Entra de clientes sem guardar client secrets, certificados ou access tokens no WordPress.

Nesta release o delivery 5C continua bloqueado em Shadow. O unico trafego novo permitido e um token probe de control-plane para o Entra Broker TWT.

## Arquitetura

```text
WordPress FieldFlow
  | HMAC v1 TWT, apenas token probe
  v
Entra Broker TWT, Azure Function dedicada
  | User Assigned Managed Identity
  | workload identity federation
  v
App Registration TWT multi-tenant
  | token request para tenant alvo
  v
Microsoft Entra do tenant alvo
```

O access token obtido no Broker e usado apenas para provar que a federacao funciona. A 2.2.360 nao o devolve ao WordPress, nao o persiste e nao executa delivery com ele.

## Recursos Azure 5C.2

- Function App: `func-fieldflow-entrabroker-prod-eb978f11`
- Storage: `stffentraeb978f11`
- User Assigned Managed Identity: `id-fieldflow-entra-prod-eb978f11`
- App Registration: `FieldFlow Enterprise Connector Broker`
- Sign-in audience: `AzureADMultipleOrgs`
- Federated credential: `fieldflow-entra-broker-uami`
- Audience da federacao: `api://AzureADTokenExchange`
- Token probe: `https://func-fieldflow-entrabroker-prod-eb978f11.azurewebsites.net/api/v1/token-probe`
- Health: `https://func-fieldflow-entrabroker-prod-eb978f11.azurewebsites.net/api/v1/health`
- Callback preparado para o proximo bloco: `https://func-fieldflow-entrabroker-prod-eb978f11.azurewebsites.net/api/v1/admin-consent/callback`

O script nao modifica nem redeploya:

- `func-fieldflow-ingest-prod-eb978f11`
- `func-fieldflow-clientrelay-prod-eb978f11`
- Azure SQL / Power BI certificados

## Modelo de credencial

A App Registration TWT confia numa User Assigned Managed Identity do mesmo tenant atraves de uma federated identity credential. A Function pede uma assertion a Managed Identity e troca-a por um token da App Registration para o tenant alvo.

Nao existe:

- client secret da App Registration
- certificado da App Registration
- password SQL no WordPress
- access token no WordPress
- token em logs do Broker

## Dados nao sensiveis guardados no WordPress

Globais 5C.2:

- endpoint do Entra Broker TWT
- Application client ID do Broker
- Directory tenant ID TWT
- timeout

Por destino 5C Entra:

- Directory tenant ID do cliente
- resource scope, por exemplo `https://database.windows.net/.default`
- estrategia `entra_cross_tenant`

## Scopes aceites nesta fundacao

- Azure Resource Manager: `https://management.azure.com/.default`
- Azure SQL: `https://database.windows.net/.default`
- Power BI: `https://analysis.windows.net/powerbi/api/.default`
- API propria: `api://.../.default`

## Travas de seguranca 2.2.360

- Connector Foundation continua `shadow`.
- `delivery_ready=false` para todos os tipos 5C.
- Nao existe sender 5C.
- Token probe e HMAC-authenticated e limitado ao host/path do Broker TWT.
- Broker aceita apenas tenants GUID especificos e scopes allow-listed.
- Broker nunca devolve o token.
- Callback de admin consent e apenas informativo neste bloco e nao valida um tenant por si so.

## Proximo bloco 5C.2

Depois de certificarmos a federacao no tenant TWT, o proximo incremento adiciona onboarding do tenant do cliente: consentimento administrativo, provisionamento da Enterprise Application no tenant externo e validacao por token probe. O delivery continuara fechado ate esse onboarding passar.
