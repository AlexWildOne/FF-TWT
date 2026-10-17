# FieldFlow Phase 5C.5, External API and Webhook Delivery Foundation

## Objetivo da 2.2.369

A 2.2.369 abre a fundação local de delivery para `external_api` e `webhook` sem abrir tráfego de rede. O objetivo é validar routing, intake, idempotência, persistência e controlos operacionais antes de ligar um sender no Azure Broker.

## Regra de segurança

Nesta release:

- não existe modo `live`;
- `NETWORK_DELIVERY_ENABLED=false` é constante de código;
- o dispatcher devolve `network_delivery_locked_2_2_369`;
- não existe `wp_remote_*` no `ConnectorDelivery`;
- o WordPress não resolve `secret_ref`;
- access tokens, API keys e client secrets não são materializados pela fila 5C.5;
- Azure TWT e Client Azure 5B mantêm listeners e filas independentes.

## Modos

### Shadow

- calcula routing real para destinos ativos `external_api`/`webhook`;
- cria uma linha de observação idempotente na fila 5C.5;
- guarda `event_uuid`, `delivery_key`, destino, scope e hash do payload;
- o payload funcional é redigido no envelope persistido;
- nunca agenda entrega de rede.

### Queue

- persiste o envelope completo necessário para uma futura entrega;
- cria jobs `pending` apenas quando o kill switch está OFF;
- agenda o dispatcher local, mas o dispatcher continua hard-locked e não reclama nem transmite jobs;
- serve para certificar persistência/idempotência antes do sender Azure.

## Idempotência

A chave é herdada do `DestinationRouter`:

`delivery_key = SHA-256(event_uuid + destination_uuid)`

A tabela impõe `UNIQUE delivery_key_idx`, logo o mesmo evento não gera uma segunda entrega para o mesmo destino.

## Tabela dedicada

`wp_routespro_cloud_connector_jobs`

Estados previstos:

- `shadow`
- `pending`
- `running`, reservado ao sender futuro
- `completed`, reservado ao sender futuro
- `dead`, reservado ao sender futuro

A fila é deliberadamente separada de:

- `cloud_sync_jobs`, Azure TWT;
- `cloud_delivery_jobs`, Client Azure 5B.

## Segredos e autenticação

O Destination Registry pode conter apenas referências, por exemplo:

- `kv://vault/secret`
- `https://vault.vault.azure.net/secrets/name`

A 2.2.369 não resolve estas referências. O desenho aprovado para a próxima gate é resolução apenas no Azure Broker/Key Vault no momento do envio.

Para destinos Entra, tenant ID e resource scope continuam metadados públicos geridos pela 5C.2. A fila não recebe access tokens.

## Controlos preparados

- kill switch global;
- batch size;
- timeout;
- max attempts;
- exponential backoff base;
- circuit breaker threshold;
- circuit open time;
- retention;
- dead-letter recovery scaffold.

Estes parâmetros são persistidos agora para estabilizar o contrato operacional. Retry/circuit breaker de rede só entram em vigor quando o sender for aberto numa release posterior.

## Critério de conclusão da 2.2.369

1. Upgrade cria a tabela sem alterar filas existentes.
2. Diagnóstico 5C.5 fica OK.
3. Em Shadow, um evento roteado cria uma única observação e não transmite dados.
4. Repetir o mesmo `event_uuid`/destino não duplica a linha.
5. Em Queue com kill switch OFF, o job fica `pending` e o dispatcher responde `network_delivery_locked_2_2_369`.
6. Azure TWT, Client Azure 5B, Power BI TWT e Azure SQL mantêm o comportamento anterior.

## Próxima gate

A próxima release de delivery deverá adicionar um endpoint Broker dedicado, receipts assinados, retry/backoff real, circuit breaker e dead-letter operacional. Só depois de testes de sucesso, falha, recuperação e não duplicação poderá existir modo Live.

## Estado Fabric externo

O teste cross-tenant Fabric chegou a `READY, IDENTIDADE` e token Fabric válido, mas o Workspace REST devolveu `fabric_workspace_not_visible_to_principal`. O caso fica classificado como `EXTERNAL_FABRIC_RESOURCE_VISIBILITY_PENDING`, sem bloquear a 5C.5.

## Estado após 2.2.370

A certificação Shadow/Queue deixa de depender de criar dados operacionais reais. O BO inclui um probe sintético que usa routing e fila reais, prova idempotência e mantém o network sender bloqueado.
