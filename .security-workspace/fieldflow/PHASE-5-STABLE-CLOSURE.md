# FieldFlow 2.2.376, Fecho Stable da Fase 5

## Objetivo

A 2.2.376 fecha formalmente a Fase 5 depois da certificacao 5E. A release nao abre producao automaticamente. O objetivo e congelar uma baseline verificavel de 5A a 5E, registar a certificacao no governance ledger e deixar um rollback conhecido antes de iniciar a Fase 6.

## Estado de seguranca

A Stable exige e preserva:

- ConnectorDelivery em `SHADOW`.
- Kill switch 5C.5 em `ON`.
- Kill switch global 5E em `ON`.
- `PRODUCTION ELIGIBLE = SIM` na 5E.
- `PRODUCTION ENABLED = NAO`.
- Full operational payload delivery desligado.
- Dispatcher operacional externo bloqueado.
- Entra Broker 1.8.0 preservado, sem novo deployment Azure.

## Nova gate Stable

A classe `RoutesPro\Cloud\Phase5StableRelease` agrega a matriz final:

| Gate | Evidencia minima |
| --- | --- |
| 5A | Destination Registry READY e Azure TWT principal presente |
| 5B | fila Client Azure READY, Relay configurado e sem dead letters |
| 5C.1 | contratos de conectores completos |
| 5C.2 | Entra Broker configurado com workload identity federation |
| 5C.3 | pelo menos um recurso autorizado e sem probes falhados |
| 5C.5 | fila READY, SHADOW, kill switch ON e sem dead letters |
| 5C.6 | controlled delivery certificado com receipts HMAC |
| 5C.7 | target sintetico e one-shot seguro certificados |
| 5C.8 | pelo menos uma Production Delivery Gate READY |
| 5D | pelo menos uma credencial READY e Key Vault-only |
| 5E | Production Eligible, fingerprint MATCH e ledger valido |

## Baseline e drift

A certificacao Stable cria um fingerprint SHA-256 derivado de:

- release 2.2.376;
- fingerprint 5E atual e certificado;
- contagem de gates 5C.8 READY;
- contagem de credenciais 5D READY;
- modo e kill switch da fila 5C.5;
- kill switch global 5E;
- estado Production Enabled e Full Payload.

Se esta configuracao mudar, `baseline_match` deixa de ser verdadeiro e a certificacao Stable deixa de estar READY.

## Snapshot e rollback

A certificacao guarda um snapshot seguro, apenas com metadados de control plane, hashes, contagens e estados. O snapshot nao inclui:

- passwords;
- API keys;
- client secrets;
- access tokens;
- valores de segredos Key Vault;
- payloads operacionais;
- respostas de formularios ou evidencias.

O BO permite descarregar `fieldflow-phase5-stable-baseline-2.2.376.json` com a baseline e o plano de rollback.

Rollback conhecido:

1. Confirmar `SHADOW`, kill switches ON e Production Enabled em NAO.
2. Fazer backup da base de dados e de `wp-content/uploads`.
3. Instalar `fieldflow-2.2.375-phase-5e-governance-recovery.zip` por cima da 2.2.376, sem desinstalar.
4. Executar o diagnostico local 5E.
5. Manter producao desligada ate nova certificacao Stable.

## Governance

A certificacao e o reset da Stable entram no ledger 5E:

- `phase5.stable_release.certified`
- `phase5.stable_release.reset`

O ledger e o snapshot nao sao apagados por um reset da gate Stable.

## Proximo passo

Depois da Stable certificada, a Fase 6 pode tratar onboarding e ativacao por cliente/destino. A complexidade Azure, Entra, Key Vault, routing, receipts, retry, dead letter, recovery e governance fica encapsulada por baixo do wizard de configuracao.
