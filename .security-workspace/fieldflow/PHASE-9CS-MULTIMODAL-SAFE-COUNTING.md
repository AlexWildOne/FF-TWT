# 9CS | Contagem Multimodal Segura | FieldFlow 2.2.616

Sucessão: `2.2.615 -> 2.2.616`.

Contrato: `fieldflow-fronts-safe-multimodal-9cs-v1`; quota: `fieldflow-vision-usage-reservation-v1`.

## Âmbito

Sobre a 2.2.615 certificada, sem alteração de schema ou FlutterFlow. Contagens por fotografia/câmara, seleção, escrita e voz/dictado partilham os campos de `product_matrix` e `share_space_matrix`; não criam um sistema de valores paralelo. Manual continua a funcionar sem Internet e sem consumo da IA do FieldFlow.

## Custos e autorização

Em **Cardex e Catálogo → Leitura por câmara** definir, por cliente:

- Orçamento operacional de reserva de IA, por mês, em EUR. **Valor inicial 0 €: IA bloqueada até autorização expressa**.
- Reserva por tentativa (predefinido 1,00 EUR), limite diário (8) e limite mensal (300). São controlos de autorização, e não preços ou faturas do fornecedor.
- A contagem de chamadas começa antes da chamada ao fornecedor e inclui timeouts/falhas. Uma tentativa por fotografia; sem fallback de modelo ou fornecedor para a mesma leitura. Quota atómica protegida por GET_LOCK MySQL. Se não houver garantia de gravação ou bloqueio, a IA é recusada.
- Três referências de produto por pedido por defeito. Redução da fotografia para 1280 px no cliente.
- Estes limites aplicam-se somente a `fronts_vision`. Não controlam outras funcionalidades de IA ou Google Maps.
- **O orçamento do BO não é um limite financeiro garantido**: definir também alertas e quotas no fornecedor Azure/OpenAI/Google, acompanhar o custo real e ajustar a reserva por leitura depois de obter dados reais. O orçamento em EUR é hipotético e conservador.

## Correções de integridade

- Produto ausente da resposta IA é `null`, não zero. Um 0 confirmado exige revisão explícita no ecrã.
- Guardar confirmação no servidor antes de preencher o formulário; confirmações repetidas são idempotentes.
- Servidor revalida pergunta, Cardex/briefing, route stop e permissões, sem aceitar produtos arbitrários do browser.
- Ligação entre leitura confirmada e submissão exata por utilizador/cliente/projeto/loja/formulário/data/rota/paragem. Se dados não forem suficientes, não ligar.
- Fotos de prateleira são temporárias e não deixam URL público, incluindo galeria e fotografia normal. Imagens de referência adicionadas ao catálogo continuam persistentes. Fotografias públicas antigas precisam de auditoria e limpeza separadas, sem eliminação automática de dados históricos.

## Testes mínimos antes da certificação Stable

1. BO, cliente sem orçamento: IA bloqueada e seleção/texto operacionais. Aprovar orçamento pequeno e confirmar exibição da câmara.
2. `product_matrix`: selecionar dois artigos, escrever «cinco Jameson 70, três Absolut 70», testar voz pelo teclado/microfone; conferir preenchimento e histórico.
3. `share_space_matrix`: dois cartões diferentes, confirmar que cada um só atualiza os próprios campos.
4. IA: resposta sem um SKU, valor fica indeterminado e exige correção; zero explícito permanece zero. Falha da confirmação não preenche a visita.
5. Leitura em loja/rota/dia diferente não associa à submissão errada.
6. Orçamento atingido, limite diário, mensal e falha de rede; fallback manual sempre disponível.
7. Testar Firefox/Chrome/Android WebView e iOS WebView para ditado; suporte de voz depende da plataforma e de permissões.
8. Revalidar cadeia 6B, testar fecho da Fase 6, certificar Stable.

O pacote entregue é uma **candidata**, não uma certificação em produção.
