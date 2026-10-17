# 2.2.649 | Daily Close Recipient Reliability 9DO

Contrato: `fieldflow-daily-close-recipient-reliability-9do-v1`.

## Causa comprovada no pacote 2.2.648

- Uma falha individual na geracao de reportes podia deixar a audiencia reclamada mesmo com estado `partial`.
- A chave idempotente da mensagem nao incluia `route_id`.
- Um envio manual de fecho de rota nao partilhava a chave da entrega automatica e ignorava extras configurados.
- A insercao da Outbox podia cair para transporte direto sem idempotencia segura.
- A recuperacao automatica de `sending` expirado reenviava mensagens com resultado SMTP desconhecido.

## Mudancas, sem intervencao nos cardex

1. No Daily Close, a existencia de qualquer falha de destinatario devolve erro ao motor de reclamacoes. A reclamacao da audiencia e libertada para retry limitado. Os destinatarios ja aceites na Outbox mantem idempotencia individual.
2. Chave de envio por automacao + email + trigger canonico `daily_close` + data + perfil/merchandiser + rota. Um ID legado 2.2.648 e considerado duplicado apenas quando a Outbox comprova o mesmo `route_id`.
3. Envio manual de rota utiliza a mesma chave quando nao e teste. Destinatarios extra sao incluidos no envio explicito ao cliente. Testes continuam isolados.
4. Daily Close requer Outbox persistente. Uma falha de persistencia e comunicada como bloqueio, sem envio direto oculto.
5. Novas mensagens sincronas entram em `sending` com lock antes de `wp_mail()`. `sending` expirado passa a `uncertain` e requer conciliacao SMTP, nao retry cego. O estado incerto permite reabertura administrativa apenas mediante confirmacao explicita.
6. Maior rastreabilidade: route_id, Outbox ID e status, indicador de parcial, historico curto de tentativas e falhas de preflight no Mail Center.
7. `sent` significa `wp_mail()` aceite pelo transporte WordPress, nunca comprovativo de entrega no destino. A entrega real depende de eventos do fornecedor SMTP.

## Limites

- Nenhum reenvio automatico de emails antigos que ja tenham sido enviados manualmente fora da Outbox, pois nao existe prova de entrega persistida para os associar.
- Emails de resultado `uncertain` necessitam de verificacao externa para reenvio; estado nao e convertido automaticamente em retry.
- O cron WordPress deve continuar operacional. Erros de autenticacao, reputacao de dominio e rejeicoes remotas requerem logs e eventos do fornecedor SMTP.
- Nenhuma alteracao a stocks, mapping 9DN, perfis de cardex, periodos, rotas, dock 9DF, Android/iOS ou Azure.

## Testes e release

- `php tools/test-daily-close-recipient-reliability-9do.php`: chaves independentes por rota, dedupe manual, compatibilidade legacy, bloqueio sem Outbox, transporte nao critico, tratamento incerto de DB e recuperacao sem replay.
- `php tools/verify-mail-reliability-outbox.php`, `php tools/test-visit-closure-daily-mail-9w.php`, `php tools/test-cardex-store-identity-9dn.php` e testes de regressao.
- Build TypeScript, sintaxe PHP, manifesto fechado e sucessao `2.2.648` para `2.2.649` obrigatorios.
- ZIP candidato nao implica instalacao nem certificacao de producao. Confirmar pelos passos do Release Manager e validar com uma rota real.
