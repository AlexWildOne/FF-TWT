# FieldFlow 2.2.378, Fase 6A, Client Onboarding & Provisioning


## Integração de navegação 2.2.378

A 2.2.378 mantém o motor 6A da 2.2.377 e corrige a integração visual do novo módulo:

- `Onboarding de Clientes` passa a pertencer ao grupo `Gestão` do menu FieldFlow;
- a página entra no `BackofficeUIFoundation` com scope `management`;
- o FieldFlow Desktop passa a expor a mesma página no grupo `Gestão`;
- a navegação Desktop continua dentro da shell, sem abrir o WordPress técnico;
- não existe alteração Azure, Broker, schema ou ativação de produção.

## Objetivo

A Fase 6A transforma a infraestrutura certificada da Fase 5 num fluxo guiado por cliente e projeto. O administrador deixa de montar manualmente cada combinação de destino, identidade, recurso e credencial no BO técnico.

A 6A não ativa produção. Todos os destinos provisionados nascem desativados, as regras de routing nascem inativas e o control plane continua em SHADOW com kill switches ativos.

## Pré-condição obrigatória

- Stable 2.2.376 certificada.
- Baseline da Fase 5 em MATCH.
- 5E em PRODUCTION ELIGIBLE.
- Connector Delivery em SHADOW.
- Kill switch 5C.5 ON.
- Kill switch governance ON.
- Production Enabled NÃO.

Se a baseline 2.2.376 deixar de estar certificada ou MATCH, novos onboardings 6A ficam bloqueados.

## Novo BO

`FieldFlow > Onboarding de Clientes`

O ecrã apresenta:

1. contexto, cliente e projeto/campanha;
2. template de integração;
3. ligação, tenant, resource scope ou referência Key Vault;
4. pré-flight local;
5. provisioning seguro em SHADOW;
6. readiness consolidada por identidade, recurso, credencial e delivery;
7. próxima ação calculada a partir das gates reais.

## Templates 6A

- TWT Cloud, recomendado;
- Azure SQL do cliente;
- Power BI do cliente;
- Microsoft Fabric do cliente;
- API externa;
- Webhook;
- Azure do cliente via Relay 5B.

## Modelo de segurança

- WordPress nunca aceita o valor de uma API key ou token no campo de credencial.
- Apenas referências `kv://vault/secret` são aceites.
- Microsoft Entra cross-tenant reutiliza o onboarding browser-only 5C.2.
- O cliente não precisa de acesso ao WordPress para consentir a Enterprise Application.
- Azure SQL, Power BI e Fabric usam apenas probes read-only na 6A.
- API/Webhook podem reutilizar a certificação Key Vault 5D.
- Nenhum botão 6A altera `Production Enabled`.
- Full operational payload continua bloqueado.

## Provisioning seguro

Para destinos externos, a 6A cria:

- destino no Destination Registry com `is_active=0`;
- estado `draft`;
- modo `mirror` ou `reporting`, conforme o conector;
- regra de routing específica por cliente/projeto com `is_active=0`;
- referências técnicas não sensíveis;
- perfil de autenticação;
- policy metadata necessária ao onboarding.

O template TWT Cloud reutiliza o destino Azure TWT principal certificado e não cria um destino externo duplicado.

## Readiness

A 6A calcula em tempo real:

- Contexto;
- Provisioning;
- Identidade;
- Recurso;
- Credencial;
- Delivery;
- Production Ready.

`Production Ready` não é `Production Enabled`.

Para API/Webhook, Delivery Ready reutiliza a Production Delivery Gate 5C.8. Para Azure SQL e Power BI/Fabric, a 6A fecha identidade e autorização read-only, enquanto o adapter de escrita/publicação permanece para as fases seguintes da Fase 6.

## Persistência

Nova tabela:

`routespro_client_onboarding_plans`

Guarda apenas configuração e metadados de readiness. Não guarda valores secretos nem payload operacional.

## Azure

A 2.2.378 não requer novo deployment Azure.

Mantém:

- Entra Broker 1.8.0;
- Relay 5B existente;
- Key Vault existente;
- Workload Identity Federation existente;
- receivers e gates 5C certificados.

## Rollback

Rollback recomendado para a baseline da Fase 5:

1. garantir Production Enabled NÃO;
2. manter SHADOW e kill switches ON;
3. remover/reiniciar qualquer plano 6A staged que não deva permanecer;
4. instalar `fieldflow-2.2.376-phase-5-stable-closure.zip` por cima da 2.2.378, sem desinstalar;
5. confirmar Stable 2.2.376 CERTIFICADA e baseline MATCH.

A tabela 6A pode permanecer na base de dados sem afetar a 2.2.376.
