# FieldFlow Fase 5C.2 + 5C.3, Entra Broker 1.4.0

Esta revisão mantém o onboarding browser-only certificado da Fase 5C.2 e acrescenta a Fase 5C.3 de autorização de recursos com probes estritamente read-only.

## Recursos Azure reutilizados

- Function App: `func-fieldflow-entrabroker-prod-eb978f11`
- Storage: `stffentraeb978f11`
- User Assigned Managed Identity: `id-fieldflow-entra-prod-eb978f11`
- App Registration multi-tenant: `FieldFlow Enterprise Connector Broker`
- Key Vault HMAC: referência ao secret certificado do ingest, lido apenas pela UAMI
- Credencial da App Registration: Workload Identity Federation, sem client secret e sem certificado

A Function de ingestão, o Relay 5B, o Azure SQL TWT e o Power BI TWT não são redeployed nem alterados.

## Fase 5C.2 preservada

O cliente continua a ligar o tenant apenas no browser, sem Azure Cloud Shell, sem Azure CLI e sem exigir subscrição Azure no tenant quando o objetivo é apenas criar a Enterprise Application e validar identidade.

O estado `READY, IDENTIDADE` só é atribuído depois de um token probe real por Workload Identity Federation.

## Fase 5C.3, autorização read-only

Depois da identidade 5C.2 estar READY, o Broker pode provar acesso a um alvo concreto sem fazer delivery:

- Azure SQL: token `https://database.windows.net/.default`, ligação TLS e apenas `SELECT 1 AS fieldflow_probe`.
- Power BI REST legado: token `https://analysis.windows.net/powerbi/api/.default` e apenas `GET https://api.powerbi.com/v1.0/myorg/groups/{workspaceId}`.
- Microsoft Fabric REST: token `https://api.fabric.microsoft.com/.default` e apenas `GET https://api.fabric.microsoft.com/v1/workspaces/{workspaceId}`.
- APIs externas/webhooks: permanecem fora da 5C.3, em Shadow e sem access token/resource probe.

O access token existe apenas em memória dentro do Broker. Nunca é devolvido ao WordPress, ao browser ou ao log.

## Estado de segurança

- `delivery_enabled=false`
- `resource_authorization_control_plane=true`
- `resource_probe_read_only=true`
- `browser_only_tenant_bootstrap=true`
- `client_subscription_required=false` para onboarding de identidade
- client secrets no WordPress: 0
- certificados no WordPress: 0
- access tokens no WordPress/log/browser response: 0
- authorization codes no WordPress/log/browser response: 0

`READY, RECURSO` significa apenas que o probe read-only ao alvo concreto funcionou. Escrita em SQL, publicação Power BI, chamadas mutáveis de API e entrega de payloads continuam bloqueadas.

## Execução

No Azure Cloud Shell PowerShell do tenant TWT:

Para uma instalação inicial continua disponível o deployment completo:

```powershell
./01-deploy-phase-5c2-entra-broker.ps1
```

Para atualizar um Broker 1.3.0 já certificado para 1.4.0, usar preferencialmente o deployment de código apenas:

```powershell
./02-deploy-phase-5c3-fabric-probe-code-only.ps1
```

O script 02 não altera App Registration, federation, Managed Identity, Key Vault, HMAC, ingest ou Relay.

O deployment continua a reutilizar a infraestrutura existente e as proteções de Flex Consumption.

O health esperado inclui:

- `version=1.4.0`
- `hmac_runtime_ready=true`
- `external_tenant_onboarding=true`
- `browser_only_tenant_bootstrap=true`
- `resource_authorization_control_plane=true`
- `resource_probe_read_only=true`
- `delivery_enabled=false`

Nunca copiar ou mostrar HMAC, tokens, passwords, client secrets, authorization codes ou certificados.
