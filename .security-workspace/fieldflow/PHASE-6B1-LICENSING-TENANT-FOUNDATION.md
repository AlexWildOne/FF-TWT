# FieldFlow Phase 6B.1, Licensing & Tenant Foundation

Release: 2.2.379

## Objetivo

Iniciar a distribuição controlada do FieldFlow por cliente e instalação, reutilizando o licenciamento remoto Azure já existente sem ativar enforcement nesta release.

## Registry central

A 6B.1 introduz duas tabelas:

- `routespro_license_tenants`, um tenant comercial/técnico por cliente;
- `routespro_license_installations`, preparada para o binding de domínios e instalações na 6B.2.

O tenant guarda apenas metadados seguros:

- UUID do tenant;
- cliente;
- plano SaaS;
- código de cliente;
- máximo de instalações;
- expiração;
- remote license id;
- máscara da key;
- SHA-256 da key;
- fingerprint de binding;
- capabilities e limits herdados do plano.

O valor bruto da key não é persistido no registry 6B.

## Emissão de chave

Depois de a Foundation 6B.1 estar certificada, e quando a API remota de licenciamento, shared secret e admin secret estão configurados, um administrador pode criar um tenant e emitir uma key real de forma explícita.

A key em claro:

1. é recebida da API remota numa ação explícita;
2. é guardada apenas num transient curto ligado ao utilizador;
3. é mostrada uma única vez no BO;
4. é removida imediatamente depois da apresentação;
5. nunca entra no governance ledger ou nas tabelas 6B.

O registry conserva apenas máscara e fingerprint SHA-256.

## Foundation Gate

A certificação 6B.1 exige:

- Fase 6A Stable certificada e baseline `MATCH`;
- tabelas 6B prontas;
- tabelas SaaS prontas;
- base URL da API remota configurada;
- shared secret configurado;
- admin secret configurado;
- ausência de raw keys no registry;
- enforcement desligado;
- binding obrigatório desligado;
- revogação remota desligada.

## Ordem segura de utilização

1. Certificar formalmente a 6A.
2. Validar e certificar a Foundation 6B.1.
3. Criar o tenant do cliente.
4. Emitir a key apenas quando necessário.
5. Não aplicar binding, revogação nem enforcement antes da 6B.2 e 6B.3.

## O que ainda não faz

A 6B.1 não:

- bloqueia uma instalação sem licença;
- força binding ao domínio;
- executa heartbeat;
- revoga remotamente uma licença;
- distribui updates privados;
- ativa production delivery.

Essas funções entram nas fases 6B.2 a 6B.4.
