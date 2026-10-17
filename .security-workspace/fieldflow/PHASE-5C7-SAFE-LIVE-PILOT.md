# FieldFlow Phase 5C.7, Safe Live Pilot

## Objetivo

A 5C.7 introduz o primeiro piloto para um endpoint HTTPS configurado no Destination Registry sem abrir o dispatcher operacional completo da 5C.5.

A gate serve para provar de forma segura:

- preparação de um target HTTPS real;
- validação de DNS e rede pública no Entra Broker;
- bloqueio de IP literals, redes privadas/reservadas, userinfo, portas diferentes de 443 e redirects;
- DNS pinning durante a chamada de rede para reduzir risco de DNS rebinding;
- token de curta duração ligado a `destination_uuid + type + endpoint_sha256`;
- envio de uma projeção estrita de metadados, nunca do payload operacional original;
- receipt HMAC ligado ao endpoint, evento, destino e delivery key;
- piloto sintético end-to-end;
- one-shot operacional com consumo antes da chamada de rede e sem retry automático;
- manutenção do dispatcher 5C.5 operacional bloqueado.

## Estado seguro obrigatório

A 5C.7 trabalha com:

- Modo 5C.5 `SHADOW`;
- Kill switch global `ON`;
- dispatcher normal 5C.5 hard-locked;
- Broker 1.6.0 com `delivery_enabled=false`;
- full operational payload delivery bloqueado.

A 5C.7 não transforma um destino em Live e não cria automaticamente uma regra de routing operacional.

## Projeção segura

O WordPress ignora deliberadamente o payload do evento quando um one-shot é consumido.

O contrato 5C.7 pode enviar apenas campos técnicos previamente permitidos, por exemplo:

- `event_name`;
- `event_uuid`;
- `occurred_at`;
- `entity_type` e `entity_id`;
- `client_id` e `project_id`;
- `route_id` e `route_stop_id`;
- `location_id`;
- `submission_id`;
- `briefing_id`.

Não podem sair por este caminho:

- respostas de formulários;
- observações livres;
- fotografias;
- uploads;
- ficheiros;
- passwords;
- API keys;
- access tokens;
- client secrets;
- credenciais Azure ou Microsoft.

O Broker reconstrói novamente a projeção permitida antes de contactar o target. Campos inesperados são descartados.

## Segurança do target

O endpoint é preparado pelo Broker 1.6.0.

O Broker exige HTTPS, porta 443, hostname DNS público e resolução apenas para endereços públicos. O target preparado recebe um SHA-256 do URL normalizado.

O token do piloto:

- expira em 15 minutos por defeito;
- é assinado por HMAC no Broker;
- fica ligado ao destination UUID;
- fica ligado ao tipo de destino;
- fica ligado ao SHA-256 exato do endpoint.

Na entrega, o Broker volta a validar DNS e usa a resolução pública validada como lookup fixado na ligação HTTPS. Isto impede que a validação inicial aceite um IP público e a ligação seguinte seja desviada por DNS para uma rede privada.

## Receiver TWT de certificação

A 2.2.372 pode criar o destino:

`TWT Safe Live Pilot Receiver`

Endpoint:

`https://func-fieldflow-entrabroker-prod-eb978f11.azurewebsites.net/api/v1/pilot-sink`

Este receiver:

- existe apenas para certificar a 5C.7;
- não persiste o payload;
- recebe apenas a projeção segura;
- não cria regra de routing operacional;
- permite completar a certificação sem serviço externo de terceiros.

## Certificação sintética

A certificação sintética prova:

1. target preparado e tokenizado;
2. chamada real ao endpoint HTTPS;
3. HTTP 2xx remoto;
4. receipt HMAC válido;
5. receipt ligado ao endpoint SHA-256 e ao destination UUID;
6. safe projection only;
7. dispatcher operacional continua bloqueado.

## One-shot seguro

Depois de o target ficar certificado, pode ser armado um único evento por um período máximo de 15 minutos.

Regras:

- `*` não é aceite como padrão de evento;
- o destino tem de estar ativo;
- o scope client/project do evento tem de ser compatível com o destino;
- o one-shot é marcado como consumido antes de qualquer chamada de rede;
- um erro remoto não provoca retry automático do evento operacional;
- o payload original do EventBus nunca é serializado para o Broker;
- apenas a projeção segura é enviada.

A ação `Certificar one-shot seguro` usa um evento interno `submission.certification` com um payload deliberadamente sensível de teste para provar que esse conteúdo não atravessa a projeção segura.

## Contratos

WordPress para Broker, preparação:

`fieldflow.connector.safe_live_pilot.prepare` v1.0

WordPress para Broker, entrega:

`fieldflow.connector.safe_live_pilot.delivery` v1.0

Broker para target:

`fieldflow.connector.safe_live_pilot.target` v1.0

Receipt:

`fieldflow.connector.safe_live_pilot.receipt` v1.0

## Estado após 2.2.372

5C.5 Shadow/Queue: CERTIFICADO.

5C.6 Controlled External Delivery certification-only: CERTIFICADO.

5C.7 Safe Live Pilot: pronto para certificação após deployment do Entra Broker 1.6.0.

Delivery operacional completo de API Externa/Webhook: BLOQUEADO.

A passagem de payload operacional completo, gestão de credenciais por destino e ativação Live permanente pertencem às gates seguintes.
