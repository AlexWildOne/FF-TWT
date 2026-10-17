# FieldFlow 5C.5, Azure Delivery Gate

A 2.2.369 não requer deployment Azure.

O Entra Broker 1.4.1 atualmente instalado permanece inalterado e continua dedicado a identidade/probes read-only. A fila 5C.5 é local e o network sender está bloqueado por código.

Não executar alterações em Function Apps, Managed Identities, Key Vault, App Registrations, SQL, ingest TWT ou Relay 5B para instalar a 2.2.369.

A próxima gate de delivery deverá usar um pacote Azure separado e explicitamente versionado, com endpoint de entrega, receipts, retry/circuit breaker e resolução de `secret_ref` apenas no Azure.
