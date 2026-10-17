# Phase 5C.2 - Browser-only Tenant Connection

## Objetivo

Simplificar o onboarding Microsoft Entra de tenants externos para um fluxo totalmente browser-only do lado do cliente.

O cliente nao precisa de:

- Azure Cloud Shell
- Azure CLI
- subscricao Azure
- client secret
- certificado

Precisa apenas de uma conta Microsoft Entra com permissao suficiente para adicionar/autorizar a Enterprise Application no tenant alvo.

## Fluxo

1. FieldFlow gera um estado HMAC assinado, com nonce e expiracao.
2. FieldFlow cria um URL Microsoft tenant-specific em `/oauth2/authorize` com `response_type=code`.
3. O administrador do cliente abre o URL no browser e autentica-se no tenant alvo.
4. A Microsoft instancia o service principal/Enterprise Application local da app multi-tenant.
5. O browser regressa ao callback do Entra Broker TWT.
6. O Broker valida o state HMAC e ignora o authorization code como credencial.
7. O Broker executa o token probe real por Workload Identity Federation no tenant e resource scope configurados.
8. Apenas se esse probe funcionar, o Broker envia um receipt HMAC ao WordPress e o destino passa a `READY, IDENTIDADE`.

## Trust boundary

O authorization code nunca e trocado, devolvido ao WordPress, persistido ou escrito em logs. A prova autoritativa continua a ser o token probe da workload identity.

## Compatibilidade

O callback Broker continua a aceitar respostas antigas `admin_consent=true`, para nao quebrar links emitidos por builds anteriores.

## Delivery

Esta release nao ativa qualquer transporte de dados:

- `delivery_enabled=false`
- Connector Foundation em Shadow
- sem Azure SQL delivery
- sem Power BI delivery
- sem API/Webhook delivery

## Referencias Microsoft

A Microsoft documenta o uso de um URL OAuth `authorize` como uma forma de criar a Enterprise Application/service principal de uma aplicacao multi-tenant no tenant alvo:

- https://learn.microsoft.com/en-us/entra/identity/enterprise-apps/create-service-principal-cross-tenant
- https://learn.microsoft.com/en-us/entra/identity-platform/app-objects-and-service-principals

## 2.2.364 OIDC bootstrap correction

After the first real external-tenant browser test returned `AADSTS650056`, the bootstrap URL was moved from the legacy tenant-specific `/oauth2/authorize` endpoint to `/oauth2/v2.0/authorize` with only the OIDC `openid` scope. The operational destination `resource_scope` is intentionally not part of the interactive browser request. It remains inside the signed onboarding state and is used only by the Broker's authoritative Workload Identity Federation token probe after the callback.

This keeps the customer flow browser-only, does not add Graph/ARM delegated permissions, and does not require an Azure subscription, Cloud Shell or Azure CLI in the customer tenant.
