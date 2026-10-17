# FieldFlow 2.2.474, Order Suggestion Conditional Mail Guard

Release: 2.2.474
Source: 2.2.473
Contract: `fieldflow-order-suggestion-conditional-mail-v1`

## Problema

Uma `order_suggestion` podia ser ocultada no formulário por uma condição anterior e, ainda assim, uma automação posterior reconstruir conteúdo de encomenda e disparar email. A visibilidade do front e a decisão do Mail Center não partilhavam uma prova persistida do mesmo estado condicional.

## Correção

- `ConditionalFlow::submission_meta()` grava o estado condicional resolvido no momento da submissão.
- Web e Mobile persistem `conditional_flow.hidden_question_keys`, `order_suggestion_keys` e `active_order_suggestion_keys` em `meta_json`.
- `FormSubmissions::activeOrderSuggestionKeys()` usa primeiro esse snapshot.
- Em submissões legacy sem snapshot, o fallback reavalia o schema e as respostas persistidas.
- `getOrderSuggestionSubmission()` devolve vazio quando nenhuma pergunta de sugestão estava ativa, bloqueando envio automático, retry e reenvio manual.

## Invariantes

- Resposta que oculta a sugestão, não envia email.
- Resposta que mostra a sugestão, com linhas efetivas, mantém o envio normal.
- Quantidades manuais continuam prioritárias.
- Cardex e Frentes não são alterados.
- Sem migração de base de dados.
- Sem alteração Flutter.
