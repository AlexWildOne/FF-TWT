# FieldFlow Phase 5B, Client Azure Relay

## Objetivo
Adicionar replicação assíncrona para um Azure do cliente sem alterar o caminho certificado WordPress -> Azure TWT.

## Arquitetura
1. FieldFlow continua a entregar o evento principal ao Azure TWT através de `AzureSyncFoundation` e HMAC v1.
2. Uma fila WordPress separada trata apenas destinos `client_azure`.
3. Essa fila fala com uma nova Relay Function controlada pela TWT, autenticada com o mesmo trust HMAC já certificado.
4. O Relay persiste cada entrega em `ctl.ff_delivery_jobs` no Azure SQL.
5. Um timer do Relay resolve a credencial do receiver no Azure Key Vault com Managed Identity.
6. O evento é enviado ao receiver Azure do cliente.
7. Se o receiver falhar, o retry ocorre no Relay e não bloqueia FieldFlow nem o Azure TWT.

## Segurança
O WordPress nunca deve conter password, Function key, client secret, access token ou outra credencial do cliente.

No WordPress ficam apenas:
- destination UUID
- scope cliente/projeto
- endpoint HTTPS
- Key Vault secret reference
- política de retry não sensível

A 5B aceita receivers HTTPS alojados em Azure Functions ou Azure Container Apps. O modo de autenticação desta primeira implementação é Function key guardada no Key Vault. Cross-tenant Entra e integrações genéricas ficam para a Fase 5C.

## Recursos novos
- Relay: `func-fieldflow-clientrelay-prod-eb978f11`
- Storage Relay: `stffrelayeb978f11`
- Receiver de certificação: `func-fieldflow-clientreceiver-test-eb978f11`
- Storage receiver: `stffreceivereb978f11`

São reutilizados, sem alterar o respetivo contrato:
- Resource Group `rg-fieldflow-prod-weu`
- Key Vault `kv-ff-prod-eb978f11`
- Azure SQL `sqldb-fieldflow-core`
- Function de ingestão certificada `func-fieldflow-ingest-prod-eb978f11`

A Function de ingestão não é redeployada pelo script 5B.

## Ordem de instalação
Só executar esta pasta depois de o diagnóstico local WordPress 2.2.356 estar verde.

1. No Query Editor da base `sqldb-fieldflow-core`, executar `01-phase-5b-delivery-schema.sql`.
2. Executar `02-phase-5b-security.sql`.
3. No Azure Cloud Shell em modo PowerShell, executar `05-deploy-phase-5b.ps1`.
4. Voltar ao Query Editor e executar `03-phase-5b-relay-identity.sql`.
5. Executar `04-phase-5b-validation.sql`.
6. Não ativar `Live` no WordPress até todos os checks de validação serem 1.

## Valores seguros usados no teste
Relay endpoint:
`https://func-fieldflow-clientrelay-prod-eb978f11.azurewebsites.net/api/v1/client-azure-relay`

Receiver endpoint:
`https://func-fieldflow-clientreceiver-test-eb978f11.azurewebsites.net/api/v1/receive`

Secret reference, não é o segredo:
`kv://kv-ff-prod-eb978f11/fieldflow-clientreceiver-test-key`

## Segredos
O deployment lê o HMAC já configurado na Function de ingestão e copia o trust para o Relay sem o apresentar no ecrã. Nunca é necessário escrever, pedir ou mostrar o segredo HMAC.

O script gera também uma Function key para o receiver e grava-a diretamente no Key Vault sem a imprimir.


## Hotfix Azure runtime, setembro 2026
Este pacote revisto usa **Flex Consumption + Node.js 24** para as duas novas Function Apps da Fase 5B.

Motivo: Node.js 20 terminou suporte no Azure Functions. O deployment inicial podia criar os Storage Accounts e parar antes da criação das Functions. O script é idempotente e reutiliza os Storage Accounts existentes. Não é necessário apagá-los.

A Function de ingestão certificada continua sem qualquer alteração.

## Hotfix Flex deployment v3
O Flex Consumption rejeita as app settings legadas `SCM_DO_BUILD_DURING_DEPLOYMENT` e `ENABLE_ORYX_BUILD` quando estas são gravadas diretamente na Function App.

O script v3:
- reutiliza as Function Apps Flex já criadas;
- remove automaticamente essas duas settings caso tenham ficado da tentativa anterior;
- mantém `--build-remote true`, que é o mecanismo suportado pelo Azure CLI para remote build no Flex Consumption;
- não toca na Function de ingestão certificada.

Se a tentativa v2 tiver parado no erro `InvalidAppSettingsException`, basta executar o script v3. Não é necessário apagar Function Apps, Storage Accounts ou Application Insights.


## Hotfix Flex detection v4
A valid Flex Consumption Function App can return an empty `functionAppConfig.runtime` when queried through `az functionapp show`, even though the underlying ARM resource was created correctly.

The v4 script no longer treats that CLI projection as authoritative. It validates the real `Microsoft.Web/sites` resource and the attached `Microsoft.Web/serverfarms` plan. The deployment continues only when the plan is definitively `FC1 / FlexConsumption`. When ARM also exposes runtime metadata, the script additionally confirms `node / 24`.

This is a detection-only recovery. Existing Function Apps, Storage Accounts, Application Insights, the certified ingest Function, SQL objects and WordPress configuration are not recreated or removed.

## v5 Cloud Shell stdout/stderr hardening

This package hardens Azure CLI parsing for Cloud Shell. Azure CLI warnings emitted on stderr are now kept separate from stdout, so JSON and TSV responses can be parsed reliably. Existing Flex Consumption FC1 / Node.js 24 Function Apps, Storage Accounts and Application Insights resources are reused. No deletion or recreation is required.

## Cloud Shell hotfix v6

The v6 package hardens Azure CLI output handling in PowerShell Cloud Shell. Empty stderr files can be returned as `$null`; v6 normalises both stdout and stderr before trimming/parsing and uses `--only-show-errors` for machine-readable CLI calls. No Azure resources are recreated and the certified ingest Function is not modified.


## v7 Cloud Shell deployment note

For Flex Consumption, the deployment script now installs production Node.js dependencies locally in Azure Cloud Shell and deploys ready-to-run ZIP packages without requesting remote build. This avoids the unsupported `SCM_DO_BUILD_DURING_DEPLOYMENT` and `ENABLE_ORYX_BUILD` app settings that Azure CLI can inject when `--build-remote true` is used. Existing Functions, storage accounts, identities, and the certified ingest Function are preserved.

## v8 HMAC hardening, setembro 2026

A revalidacao da Fase 5B encontrou duas incompatibilidades no Relay antigo:

1. O signer certificado do WordPress envia `X-FieldFlow-Signature: v1=<hex>`, enquanto o Relay antigo aceitava apenas `hmac-v1=<hex>`.
2. O deployment antigo podia copiar `HMAC_SECRET_NAME` como se fosse o valor do HMAC. Esse setting contem apenas o nome do secret no Key Vault.

O v8 corrige ambos sem ler ou apresentar o segredo HMAC:

- Relay 1.1.0 aceita `v1=` e `hmac-v1=` mantendo o canonical `fieldflow-hmac-v1`.
- `FIELDFLOW_HMAC_SECRET` passa a guardar apenas `kv://kv-ff-prod-eb978f11/<secret-name>`.
- O Relay resolve o segredo no runtime via a sua Managed Identity e Key Vault.
- A Managed Identity recebe apenas leitura do secret HMAC certificado.
- Novo endpoint seguro `GET /api/v1/health` mostra apenas readiness, versao e origem do trust, nunca o segredo.
- `06-hardening-phase-5b-hmac.ps1` atualiza apenas o Relay existente. Nao redeploya ingest, receiver, SQL, WordPress nem roda a Function key do receiver.

Para uma instalacao 5B ja existente, usar preferencialmente `06-hardening-phase-5b-hmac.ps1` e manter o WordPress 5B em Shadow ate o health e um probe `Testar 5B` ficarem verdes.
