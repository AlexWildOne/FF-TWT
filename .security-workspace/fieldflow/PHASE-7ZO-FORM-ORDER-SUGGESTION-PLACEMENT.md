# Phase 7ZO - Form Order Suggestion Placement

Release: 2.2.473
Source release: 2.2.472

## Objetivo

Manter a Sugestão de encomenda no local funcional do formulário e garantir que nunca antecede a pergunta de Frentes que fornece o respetivo Cardex e valores.

## Alterações

- Repara em runtime schemas legacy em que `order_suggestion` aparece antes da sua `product_matrix` emparelhada.
- Em wizard, uma sugestão fora de `layout.steps` é colocada no passo das Frentes e ordenada pela sequência efetiva do schema.
- O Form Builder passa a atribuir perguntas BO sem passo ao vizinho configurado mais próximo, tornando a reparação persistente após Guardar.
- Mantém o comportamento de sincronização de referências e as regras de cálculo já existentes.

## Invariantes

- Zero alterações Flutter.
- Zero migrações de base de dados.
- Chaves de perguntas e histórico não são reescritos automaticamente.
- Valores manuais de encomenda não são substituídos.
- Cardex por loja, exceções de sortido e sincronização Frentes -> Encomenda permanecem ativos.
- Release Manager, production manifest SHA-256 e cadeia Azure/Phase 6 continuam obrigatórios.

## Contrato

`fieldflow-form-order-suggestion-placement-v1`
