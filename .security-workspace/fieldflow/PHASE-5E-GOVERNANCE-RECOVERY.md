# FieldFlow Phase 5E, Governance, Recovery & Final Eligibility Gate

## Objetivo

A Fase 5E fecha a arquitetura de segurança e recuperação antes da release stable final da Fase 5.

A 5E **não ativa produção**. O objetivo é provar que a plataforma pode ser considerada `PRODUCTION ELIGIBLE` sem abrir o dispatcher operacional nem permitir full operational payload.

## Pré-condições

- Fase 5C.8 com pelo menos um destino `PRODUCTION READY`.
- Fase 5D com pelo menos uma credencial `READY`.
- 5C.5 em `SHADOW`.
- Kill switch 5C.5 `ON`.
- Entra Broker 1.8.0 `READY`.
- Delivery operacional e full payload bloqueados.

## 1. Governance Ledger

Nova tabela:

`routespro_governance_ledger`

Cada entrada contém:

- UUID próprio;
- nome do evento;
- entidade e referência;
- utilizador/actor;
- source;
- scope cliente/projeto;
- destination UUID;
- correlation ID;
- razão;
- details sanitizados;
- `previous_hash`;
- `entry_hash`;
- timestamp UTC.

O hash de cada linha inclui o hash da linha anterior. Assim, alterações retroativas no conteúdo ou sequência quebram a verificação da cadeia.

O ledger nunca guarda valores de passwords, API keys, Bearer tokens, client secrets ou payloads operacionais. Chaves sensíveis em details são redigidas.

## 2. Kill controls

A 2.2.375 mantém o kill switch global da governance forçado em `ON`.

Também ficam preparados controlos explícitos por:

- cliente;
- destination UUID;
- event name.

A certificação usa scopes sintéticos e repõe a configuração original no final.

## 3. Dead Letter & Controlled Replay

O replay de um job `dead` exige:

1. capability `routespro_manage`;
2. justificação humana com pelo menos 10 caracteres;
3. job ainda em estado `dead`;
4. ticket UUID de autorização;
5. ticket com TTL de 15 minutos;
6. ticket ligado ao actor que autorizou;
7. ticket ligado ao `delivery_key` original;
8. ticket single-use.

A execução volta o mesmo job para `pending`, repõe attempts a zero e não cria uma segunda linha com o mesmo delivery key.

Na 2.2.375 a rede operacional continua bloqueada, por isso um replay não transmite dados externamente.

## 4. Recovery

A 5E introduz:

- deteção de `running` locks stale;
- recuperação para `pending` ou `dead` consoante o número de tentativas;
- limpeza de `locked_at` e `lock_token`;
- preservação de idempotência;
- reset controlado de circuit breaker;
- snapshot determinístico do control plane.

O snapshot contém apenas metadata segura, hashes de endpoint/secret references e estados de readiness. Nunca contém valores secretos.

## 5. Eligibility fingerprint

A final gate calcula um SHA-256 determinístico a partir de:

- destinos e estado ativo;
- endpoint fingerprints;
- hashes de secret references;
- regras de routing;
- policy 5C.8;
- estado Production Ready;
- versões certificadas das credenciais 5D;
- expiry/revocation/rotation state;
- kill controls;
- modo 5C.5 e kill switch.

Se qualquer uma destas peças mudar depois da certificação, o fingerprint deixa de coincidir e `PRODUCTION ELIGIBLE` cai automaticamente para false.

## 6. Final gate

A plataforma só passa a `PRODUCTION ELIGIBLE` quando todos os checks seguintes estão verdadeiros:

- governance ledger ready;
- hash chain válida;
- ledger/kill controls certificados;
- Dead Letter + Replay certificados;
- Recovery certificado;
- pelo menos uma gate 5C.8 Production Ready;
- pelo menos uma credencial 5D Ready;
- 5C.5 em Shadow;
- kill switch 5C.5 ON;
- kill switch governance ON;
- dispatcher operacional bloqueado;
- full operational payload bloqueado.

`PRODUCTION ELIGIBLE` **não é** `PRODUCTION ENABLED`.

Na 2.2.375:

- `PRODUCTION ENABLED = false`;
- `FULL OPERATIONAL PAYLOAD = false`;
- normal 5C.5 network dispatcher = hard-locked.

A ativação real pertence à release stable final, após a certificação 5E.

## 7. Azure / Broker

A Fase 5E não necessita de novo deployment Azure.

O Entra Broker 1.8.0 já certificado na 5D é reutilizado apenas como pré-condição de saúde. A governance, replay, recovery e eligibility são control plane WordPress/Azure-state metadata, sem novo endpoint de Broker.
