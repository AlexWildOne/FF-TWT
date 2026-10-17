# FieldFlow Phase 5C.6, Controlled External Delivery

## Objetivo

A 5C.6 abre pela primeira vez um caminho de rede real para o motor API Externa/Webhook, mas apenas para certificação sintética e através do Entra Broker TWT.

O objetivo não é ativar delivery operacional. O objetivo é provar de forma segura:

- saída WordPress -> Entra Broker TWT com HMAC v1;
- saída Broker -> receiver fixo de certificação;
- receipt HMAC assinado e verificado no WordPress;
- propagação de `delivery_key`, `event_uuid` e `destination_uuid`;
- retry exponencial;
- dead letter;
- circuit breaker;
- ausência de targets arbitrários;
- ausência de segredos/tokens no WordPress;
- dispatcher operacional 5C.5 continua hard-locked.

## Limite de segurança

A 2.2.371 **não** envia dados operacionais para APIs externas ou Webhooks.

O sender 5C.6 só aceita jobs que cumpram simultaneamente:

- `execution_mode = controlled_test`;
- payload com `data.certification.phase = 5C.6`;
- tipo `external_api` ou `webhook`;
- Modo 5C.5 `QUEUE`;
- Kill switch global `OFF` durante a janela de certificação.

O Entra Broker 1.5.0:

- rejeita qualquer pedido que não seja `certification_only=true`;
- ignora endpoint/ref do destino;
- não aceita URL arbitrária do WordPress;
- envia apenas para `/api/v1/controlled-receiver` no próprio host Broker;
- o receiver não persiste dados;
- não devolve access tokens, client secrets ou API keys;
- mantém `delivery_enabled=false` para delivery operacional.

## Contratos

WordPress -> Broker:

`fieldflow.connector.controlled_delivery` v1.0

Broker -> receiver fixo:

`fieldflow.connector.controlled_receiver` v1.0

Receipt:

`fieldflow.connector.controlled_receipt` v1.0

O receipt é assinado por HMAC e fica ligado a:

- `delivery_key`;
- `event_uuid`;
- `destination_uuid`;
- HTTP remoto;
- outcome;
- receiver request ID;
- timestamp.

## Cenários certificados

### Delivery

- job sintético criado;
- segundo insert com a mesma delivery key é ignorado;
- Broker recebe o pedido;
- receiver fixo responde 200;
- receipt é verificado;
- job termina `completed` com uma tentativa.

### Retry + Dead Letter

`fail_first`:

- primeira tentativa devolve 503;
- job regressa a `pending`;
- `available_at` recebe backoff exponencial;
- segunda tentativa responde 200;
- job termina `completed` com duas tentativas.

`fail_always`:

- falhas repetidas até `max_attempts`;
- job termina `dead`;
- o job sintético de dead-letter é limpo após uma certificação bem-sucedida para não contaminar a saúde operacional.

### Circuit Breaker

- `fail_always` é executado até ao threshold configurado;
- o circuito abre durante `circuit_open_seconds`;
- a tentativa seguinte é bloqueada antes da chamada de rede;
- o contador de tentativas não aumenta nessa chamada bloqueada;
- o job sintético e o estado do circuito são limpos no fim da certificação.

## Estado após 2.2.371

5C.5 Shadow/Queue: CERTIFICADO.

5C.6 Controlled Delivery certification-only: pronto para certificação end-to-end após deployment do Entra Broker 1.5.0.

Delivery operacional API Externa/Webhook: BLOQUEADO.

A abertura de targets reais, resolução de `secret_ref` e Live pertencem à gate seguinte, depois da certificação 5C.6.
