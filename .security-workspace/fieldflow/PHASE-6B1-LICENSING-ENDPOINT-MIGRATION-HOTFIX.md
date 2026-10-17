# FieldFlow 2.2.381, Phase 6B.1 Licensing Endpoint Migration Hotfix

## Problema

A 2.2.380 ainda continha o endpoint Azure de licenciamento retirado em defaults e no host pinned de segurança. Além disso, uma constante `ROUTESPRO_LICENSE_API_BASE` definida com o valor legado tinha prioridade sobre o valor gravado no WordPress, fazendo o campo regressar ao hostname antigo depois de guardar.

## Correção

- Endpoint canónico: `https://func-fieldflow-licensing-prod-eb978f11.azurewebsites.net/api`.
- Endpoint legado: `https://func-fieldflow-licensing-a7dkgyfsfmg9dvgt.westeurope-01.azurewebsites.net/api`.
- Valores guardados com o endpoint legado são normalizados para o endpoint canónico.
- Uma constante `ROUTESPRO_LICENSE_API_BASE` com o endpoint legado é tratada como configuração obsoleta e ignorada.
- Uma constante com outro endpoint continua a ter prioridade, preservando o modelo de hardening existente.
- `RemoteLicenseClient` e `OutboundHttp` passam a confiar apenas no novo host FieldFlow para o bypass WordPress estritamente pinned.
- O ecrã de Configurações indica se o valor efetivo vem da base de dados, default, constante ou de uma constante legada ignorada.

## Segurança

Não altera Shared Secret, Admin Secret, tenant Pernod Ricard, enforcement, binding, revogação, Broker, Relay, Fase 5 ou 6A. HTTPS, porta 443, TLS, zero redirects e controlos SSRF permanecem ativos.
