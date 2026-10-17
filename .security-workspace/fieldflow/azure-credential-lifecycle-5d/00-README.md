# FieldFlow Entra Broker 1.8.0, Phase 5D

## Ordem

1. `01-prepare-phase-5d-keyvault.ps1`
2. `02-deploy-phase-5d-credential-lifecycle-code-only.ps1`

O primeiro script cria, se necessário, apenas o segredo sintético `fieldflow-5d-certification-key` no Key Vault TWT existente e confirma o acesso de leitura da Managed Identity. Em Key Vault com Azure RBAC usa `Key Vault Secrets User` no scope do segredo; em access-policy mode confirma apenas `get`. Nunca imprime o valor.

O segundo script faz apenas deployment de código do Broker 1.8.0.

## Segurança

- WordPress guarda apenas `kv://vault/secret`.
- O Broker resolve o valor com Managed Identity.
- Probes devolvem apenas metadados, versão, validade e estado.
- O valor do segredo nunca regressa ao WordPress.
- A certificação injeta a credencial apenas num receiver TWT fixo e não persistente.
- Rotação invalida a versão certificada até nova certificação.
- Expiração, disable, not-before e revogação local bloqueiam readiness.
- 5C.8 permanece preservada.
- Produção geral e full operational payload continuam bloqueados até 5E.

## Compatibilidade Azure CLI

A release 2.2.375 inclui o hotfix RBAC do helper 5D: `az role assignment list --scope` sem combinar `--all`, compatível com o Azure CLI atual. O runtime 5D e o Broker 1.8.0 permanecem inalterados.
