# FieldFlow Phase 5D, Credential Lifecycle & Secret Governance

## Objetivo

A 5D fecha o ciclo de vida de credenciais dos destinos `external_api` e `webhook` antes da gate final 5E.

O princípio é simples: o WordPress pode conhecer a referência de uma credencial, mas nunca o seu valor.

A release certifica:

- referências `kv://vault/secret` ou `kv://vault/secret/version`;
- resolução do segredo apenas no Entra Broker TWT com Managed Identity;
- metadados de validade sem devolver o valor secreto;
- deteção de rotação por versão imutável do segredo;
- bloqueio por expiração, disabled e not-before;
- warning configurável antes de expirar;
- revogação local imediata do estado de readiness;
- certificação de autenticação num sink TWT fixo e não persistente;
- receipt HMAC ligado ao destino, referência e versão da credencial;
- ativação geral e full operational payload continuam bloqueados até 5E.

## Modelo de segredo

O WordPress guarda apenas:

- `secret_ref`, por exemplo `kv://kv-ff-prod-eb978f11/fieldflow-5d-certification-key`;
- SHA-256 da referência;
- modo de autenticação;
- nome do header quando aplicável;
- estado de validade;
- versão atual e versão certificada;
- timestamps e informação de expiração;
- estado de revogação e rotação.

O WordPress nunca recebe nem persiste o valor do segredo.

## Modos de autenticação 5D

Nesta release são certificados:

- API key em header configurável;
- Bearer token em `Authorization`.

Headers reservados são rejeitados. Para Bearer, o header é sempre `Authorization`.

## Broker 1.8.0

O Entra Broker resolve a referência com a user-assigned Managed Identity já existente.

O endpoint de probe devolve apenas metadados seguros:

- hash da referência;
- versão do segredo;
- enabled;
- created/updated;
- expires;
- not-before;
- status;
- dias restantes.

O valor secreto existe apenas em memória durante a resolução e, na certificação, é injetado no receiver fixo TWT. Nunca é incluído no JSON de resposta.

## Rotação

A certificação liga a readiness à versão atual do Key Vault.

Se um novo valor criar uma nova versão do segredo:

1. o probe deteta uma versão diferente da `certified_version`;
2. `rotation_detected` passa a true;
3. a certificação anterior deixa de ser válida;
4. o destino deixa de estar 5D READY;
5. é necessário voltar a certificar a nova versão.

Isto permite rotação sem alterar o WordPress ou revelar o segredo.

## Expiração e validade

Estados possíveis incluem:

- `ready`;
- `warning`;
- `expired`;
- `disabled`;
- `not_yet_valid`;
- `missing`/`forbidden`/`error` quando a resolução não é possível.

Apenas `ready` e `warning`, com versão certificada igual à versão atual e sem revogação, podem ser considerados 5D READY.

## Revogação

`Revogar localmente` bloqueia imediatamente a readiness no FieldFlow sem apagar nem modificar o segredo no Key Vault.

A remoção ou desativação real do segredo no Key Vault continua a ser uma ação administrativa Azure. O próximo probe refletirá esse estado.

## Receiver TWT de certificação

A release pode criar automaticamente:

`TWT Credential Lifecycle Receiver`

Endpoint:

`https://func-fieldflow-entrabroker-prod-eb978f11.azurewebsites.net/api/v1/credential-sink`

O receiver:

- exige o HMAC FieldFlow;
- resolve independentemente a mesma referência Key Vault;
- compara a credencial recebida em tempo constante;
- não persiste payload nem credencial;
- devolve apenas resultado técnico e metadados não sensíveis.

## Credencial sintética TWT

O script 5D prepara, se necessário:

`fieldflow-5d-certification-key`

no Key Vault:

`kv-ff-prod-eb978f11`

A credencial é sintética, tem expiração e serve apenas para certificar o lifecycle. O script nunca imprime o valor.

## Contratos

WordPress para Broker, probe:

`fieldflow.credential.lifecycle.probe` v1.0

WordPress para Broker, certificação:

`fieldflow.credential.lifecycle.certify` v1.0

Broker para sink fixo:

`fieldflow.credential.lifecycle.sink` v1.0

Receipt:

`fieldflow.credential.lifecycle.receipt` v1.0

## Estado de segurança obrigatório

Durante toda a 5D:

- 5C.5 permanece `SHADOW`;
- Kill switch global permanece `ON`;
- dispatcher operacional normal permanece bloqueado;
- full operational payload permanece bloqueado;
- produção geral permanece bloqueada até 5E.

## O que 5D READY significa

`5D READY` significa que a referência Key Vault é válida, o Broker a consegue resolver, o segredo está dentro da validade, a versão atual foi certificada, a autenticação foi provada num sink fixo e o estado não foi revogado.

Não significa que o tráfego operacional geral está ativo.
