# FieldFlow Phase 5C.1, Connector Foundation

## Objetivo

A Fase 5C.1 formaliza os contratos dos próximos conectores empresariais sem abrir qualquer canal de delivery novo.

Tipos suportados:

- `external_api`, API Externa
- `webhook`, Webhook
- `data_warehouse`, Azure SQL / Data Warehouse
- `client_powerbi`, Power BI do Cliente

## Regra de segurança da 5C.1

Todos os conectores 5C.1 estão bloqueados em `shadow`.

Mesmo quando um destino está ativo no Destination Registry e uma Routing Rule o seleciona:

- `delivery_ready=false`
- não existe sender hook 5C
- não existe chamada HTTP 5C
- não existe ligação SQL 5C
- não existe chamada Power BI 5C
- não existe aquisição de token Microsoft Entra
- não existe resolução de segredos no WordPress

O WordPress pode guardar uma referência Azure Key Vault, mas nunca a resolve nesta fase.

## Contratos

| Tipo | Contrato | Transporte | Auth por defeito | Subfase de delivery |
|---|---|---|---|---|
| external_api | `fieldflow.connector.external_api.v1` | `https_api` | `entra_cross_tenant` | 5C.5 |
| webhook | `fieldflow.connector.webhook.v1` | `https_webhook` | `key_vault_reference` | 5C.5 |
| data_warehouse | `fieldflow.connector.data_warehouse.v1` | `azure_data` | `managed_identity` | 5C.3 |
| client_powerbi | `fieldflow.connector.client_powerbi.v1` | `powerbi_reporting` | `entra_cross_tenant` | 5C.4 |

## Referências técnicas permitidas

A 5C.1 aceita referências não sensíveis para desenhar o destino antes da autenticação real:

- `api://planned/client-erp`
- `webhook://planned/client-events`
- `azure-sql://server.database.windows.net/database`
- `warehouse://client/data-platform`
- `synapse://workspace/database`
- `fabric://workspace/lakehouse`
- `powerbi://workspace/report`
- HTTPS quando o tipo o permite

Não guardar connection strings, passwords, bearer tokens, API keys ou client secrets.

## Compatibilidade com 5A e 5B

- Azure TWT continua exclusivamente em `AzureSyncFoundation`.
- Azure do Cliente continua exclusivamente em `ClientAzureDelivery` + Relay TWT 5B.
- A 5C.1 não altera nenhum destes listeners.
- O registry passa para versão 1.1.0 apenas para voltar a semear os metadados dos tipos e normalizar destinos 5C existentes de forma idempotente.

## Próximas subfases

- 5C.2: Microsoft Entra cross-tenant e consentimento empresarial.
- 5C.3: Azure SQL / Data Warehouse do cliente.
- 5C.4: Power BI do cliente.
- 5C.5: APIs externas e Webhooks com delivery, retries e idempotência.

## Estado após 2.2.369

A 5C.5 já abriu a intake e a fila local para `external_api` e `webhook`, com Shadow/Queue e network hard-locked. O contrato 5C.1 permanece a fonte de verdade do tipo/autenticação, mas a fase apresentada no Registry para estes dois tipos passa a `5C.5`.
