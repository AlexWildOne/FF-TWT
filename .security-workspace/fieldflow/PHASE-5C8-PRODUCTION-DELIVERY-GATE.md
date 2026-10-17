# FieldFlow Phase 5C.8, Production Delivery Gate

## Objetivo

A 5C.8 transforma a prova one-shot da 5C.7 numa gate repetível de readiness de produção para destinos `external_api` e `webhook`, sem abrir ainda o dispatcher operacional geral.

A gate certifica:

- o mesmo target HTTPS já provado na 5C.7;
- uma política explícita de evento e rate limit;
- fingerprint SHA-256 do endpoint;
- fingerprint SHA-256 da policy;
- token HMAC ligado a `destination_uuid + destination_type + endpoint_sha256 + policy_sha256 + expiração`;
- safe projection `metadata_v1`;
- target real HTTPS com DNS pinning e redirects bloqueados;
- receipt HMAC ligado ao target e à policy;
- caminho real da fila de conectores com `delivery_key` determinística e supressão de duplicados;
- estado final `PRODUCTION READY` sem ativar produção geral.

## Estado de segurança obrigatório

A 5C.8 exige:

- 5C.5 em `SHADOW`;
- Kill switch global `ON`;
- dispatcher normal 5C.5 hard-locked;
- 5C.7 sintética certificada no mesmo destino;
- 5C.7 one-shot certificada no mesmo destino;
- Broker 1.7.0 READY;
- full operational payload delivery bloqueado;
- general production activation bloqueada.

A 2.2.373 não introduz modo Live geral.

## Política 5C.8

Cada gate fica vinculada a uma policy imutável durante a validade do token:

- `event_pattern`: evento explícito, por exemplo `submission.created`, sem `*`;
- `payload_profile`: `metadata_v1`;
- `max_per_hour`: 1 a 60;
- `retry_profile`: `bounded_v1`;
- `receipt_required`: true;
- `safe_projection_only`: true.

O Broker normaliza a policy, calcula `policy_sha256` e liga esse hash ao token de curta duração. Alterar endpoint ou policy invalida o token.

## Targets autenticados

Na 2.2.373, a 5C.8 certifica apenas targets com `auth_strategy=none`.

API keys, OAuth, Entra, client credentials, secrets e rotação por destino ficam deliberadamente reservados à Fase 5D Credential Lifecycle. Isto evita abrir produção antes de o ciclo de vida de credenciais estar certificado.

## Projeção de dados

A 5C.8 continua a usar apenas metadados técnicos allowlisted:

- `event_name`;
- `event_uuid`;
- `occurred_at`;
- `entity_type` e `entity_id`;
- `client_id` e `project_id`;
- `route_id` e `route_stop_id`;
- `location_id`;
- `submission_id`;
- `briefing_id`;
- marcador sintético de certificação.

Não entram nesta gate respostas de formulários, texto livre operacional, fotos, uploads, ficheiros, passwords, API keys, access tokens ou client secrets.

## Receiver TWT

A 2.2.373 pode criar automaticamente:

`TWT Production Gate Receiver`

Endpoint:

`https://func-fieldflow-entrabroker-prod-eb978f11.azurewebsites.net/api/v1/production-sink`

O receiver não persiste dados. Ao ser criado pelo BO, a pré-condição 5C.7 é certificada automaticamente com os testes sintéticos internos, sem gerar uma submissão real.

## Passos de certificação

### 1. Preparar gate

Prova:

- 5C.7 completa no target;
- endpoint HTTPS válido;
- policy válida;
- endpoint SHA-256;
- policy SHA-256;
- token HMAC de 1 hora;
- safe projection only;
- ativação geral continua bloqueada.

### 2. Certificar target

Executa uma chamada HTTPS real com payload sintético `metadata_v1` e valida:

- HTTP 2xx;
- receipt HMAC;
- binding ao endpoint SHA-256;
- binding à policy SHA-256;
- safe projection only;
- full payload bloqueado.

### 3. Certificar queue de produção

Usa a tabela real `cloud_connector_jobs`, mas exclusivamente com `execution_mode=production_gate_test` e evento sintético.

Prova:

- criação de job;
- `delivery_key` determinística;
- segunda inserção ignorada;
- uma única linha por delivery key;
- claim do dispatcher 5C.8 isolado;
- chamada ao Broker 1.7.0;
- HTTP 200;
- receipt assinado;
- payload persistido contém apenas a safe projection;
- estado final `completed`;
- `PRODUCTION READY` no control plane;
- dispatcher operacional normal continua bloqueado.

## Contratos

WordPress para Broker, preparação:

`fieldflow.connector.production_gate.prepare` v1.0

WordPress para Broker, entrega:

`fieldflow.connector.production_gate.delivery` v1.0

Broker para target:

`fieldflow.connector.production_gate.target` v1.0

Receipt:

`fieldflow.connector.production_gate.receipt` v1.0

## O que PRODUCTION READY significa nesta release

`PRODUCTION READY` significa que identidade técnica, target, policy, safe projection, queue, idempotência, chamada HTTPS e receipt foram certificados.

Não significa que o destino está a receber tráfego operacional permanente.

A ativação geral só poderá ser aberta depois de:

1. 5D Credential Lifecycle, para autenticação, Key Vault, rotação, revogação e validade;
2. 5E Governance & Recovery, para auditoria, replay controlado, dead-letter operacional e recovery;
3. release gate final da Fase 5.
