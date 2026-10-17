# Fase 5B, Azure do Cliente

## Objetivo
Replicar eventos FieldFlow para um Azure controlado pelo cliente, mantendo sempre o Azure TWT como Data Hub primário.

## Fluxo

FieldFlow WordPress
1. Azure TWT, caminho existente e obrigatório via `AzureSyncFoundation`.
2. Fila `client_azure`, separada e opcional.

Fila `client_azure`
1. Persiste o mesmo Event UUID e uma delivery key específica do destino.
2. Em Live envia para o Relay TWT com HMAC v1.
3. Se o Relay estiver indisponível, o job local entra em retry sem tocar no Azure TWT.

Relay Azure TWT
1. Valida HMAC, tenant, installation, Event UUID, body hash e delivery key.
2. Persiste idempotentemente em `ctl.ff_delivery_jobs`.
3. Responde 202 ao WordPress.
4. Um timer independente resolve a credencial no Key Vault.
5. Entrega ao receiver Azure do cliente.
6. Em falha aplica retry exponencial e, no limite, dead state.

## Limite funcional desta fase
A 5B suporta receiver HTTPS alojado em Azure Functions ou Azure Container Apps, autenticado por Function key referenciada no Key Vault.

Ficam para fases seguintes:
- 5C, cross-tenant Microsoft Entra, Power BI externo, Azure SQL cliente, Data Warehouse e APIs genéricas.
- 5D, rotação, expiração e lifecycle completo de segredos.
- 5E, audit/replay/DR avançado e dead-letter operacional completo.

## Princípio de segurança
Nunca colocar uma Function key ou segredo do cliente no WordPress. O campo `Secret reference` recebe apenas algo como:

`kv://kv-ff-prod-eb978f11/fieldflow-clientreceiver-test-key`

## Recursos de certificação previstos
- Relay TWT: `func-fieldflow-clientrelay-prod-eb978f11`
- Storage Relay: `stffrelayeb978f11`
- Receiver Azure de teste: `func-fieldflow-clientreceiver-test-eb978f11`
- Storage receiver: `stffreceivereb978f11`
- Key Vault existente: `kv-ff-prod-eb978f11`
- Azure SQL existente: `sqldb-fieldflow-core`

A Function de ingestão certificada `func-fieldflow-ingest-prod-eb978f11` não é redeployada nem modificada.


## Hotfix 2.2.357, intake real
Durante a certificação Queue, o Preview de routing resolveu corretamente o destino 5B mas uma submissão real não criou job local. A 2.2.357 endurece o intake sem alterar o canal Azure TWT: o observer client_azure corre em modo fail-open antes do listener principal, reidrata scope de submissão/rota quando necessário e expõe apenas metadados sanitizados de diagnóstico no BO.
