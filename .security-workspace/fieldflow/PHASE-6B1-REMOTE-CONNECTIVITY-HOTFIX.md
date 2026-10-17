# FieldFlow 2.2.380, Phase 6B.1 Remote Connectivity Hotfix

## Objetivo

Corrigir a emissão de licenças remotas quando o WordPress rejeita incorretamente a Azure Function fixa de licenciamento como URL inseguro, sem desativar a proteção SSRF genérica do FieldFlow.

## Problema observado na 2.2.379

A Foundation 6B.1 validava a presença de API Base, Shared Secret e Admin Secret, mas não executava um probe real de rede antes da certificação. O tenant podia ficar preparado e a emissão falhar depois com a mensagem `O WordPress classificou o destino como inseguro.`

## Correção

A 2.2.380 acrescenta:

1. `OutboundHttp` 1.0.1 com política estrita por defeito.
2. Exceção limitada ao hostname fixo `func-fieldflow-licensing-prod-eb978f11.azurewebsites.net`.
3. O bypass da validação URL nativa do WordPress só pode ser usado com HTTPS, porta 443, host exato, allowlist interna e zero redirects.
4. Redes privadas, reservadas, localhost, IP literals não autorizados e hosts diferentes continuam bloqueados.
5. Probe explícito à rota `/license/validate` com HMAC real e uma chave sintética inexistente.
6. O probe não cria, ativa, revoga nem altera licenças.
7. A Foundation 6B.1 passa a exigir conectividade real antes da certificação.
8. O BO mostra diagnóstico separado de host, DNS, validação URL WordPress, HTTPS, rota Function, HTTP e HMAC.

## Persistência e segurança

O probe guarda apenas metadados seguros:

- host;
- data UTC;
- estado HTTPS;
- estado DNS público;
- resultado da validação WordPress;
- indicador do bypass controlado;
- HTTP code;
- estado da rota;
- estado HMAC.

Não são guardados Shared Secret, Admin Secret, license key em bruto, body remoto ou query strings.

## Estado funcional preservado

- Tenant já criado permanece intacto.
- Nenhuma licença é emitida automaticamente.
- `LICENSE_ENFORCEMENT_ENABLED = false`.
- `INSTALLATION_BINDING_ENFORCED = false`.
- `REMOTE_REVOCATION_ENABLED = false`.
- Production Enabled continua `false`.
- Não existe novo deployment Azure nem atualização do Entra Broker.
