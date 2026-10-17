# FieldFlow 2.2.640 | Phase 4A + Operative Dock Calm Motion 9DF

Contrato: `fieldflow-operative-dock-calm-motion-9df-v1` e `fieldflow-phase4-observability-cost-baseline-9df-v1`.

## Âmbito

Baseline 2.2.639 Stable certificada, sem alterações ao Portal Cliente ou aos dados.

A barra do Operativo já é controlada pelo runtime 9DE. O novo 9DF mantém a inicialização que funcionou e ajusta apenas o estado e a animação:

- A descida deliberada de 54 px esconde. A subida deliberada de 25 px mostra.
- Ao mostrar, guarda 900 ms de estabilidade; ao ocultar, guarda 430 ms. Movimentos inferiores ao limiar são ignorados.
- Movimentos touch e os respetivos scrolls nativos têm uma janela de deduplicação de 480 ms para não contar duas vezes o mesmo gesto.
- Transição CSS de opacidade de 360 ms e deslocação de apenas 14 px, com visibilidade e pointer-events sincronizados. Honra `prefers-reduced-motion`.
- Teclado e inputs, gestos horizontais, desktop e modos de recuperação 9DE/9DC mantidos.

## Fase 4A, recolha de baseline comercial

Na página Backoffice > Segurança e Observabilidade > Performance e Carga, apresenta KPIs com fontes já disponíveis:

- Amostras e P95 mobile e web, originados em `LoadReadiness`.
- Custos estimados em EUR, últimos 30 dias, por Google / Azure / outras APIs em `ApiUsageLedger`.
- Bloqueios/uso premium e desvios de política em `GoogleCostControlCenter`.
- Críticos ativos em `Monitor`.

Estes dados são estimativas e não comprovam fatura ou poupança. Sem tabela ou amostras suficientes, o UI indica **sem dados**/**a recolher**. Não regista dados novos nem faz chamadas pagas. Objetivos subsequentes da Fase 4: confrontar faturas reais, medir gargalos por fluxo real e otimizar unicamente o que estiver demonstrado.

## Aceitação obrigatória em dispositivo real

1. Chrome Android, Minha Rota e Stocks & Ruturas: descer esconde suavemente, subir revela suavemente.
2. Pequenas oscilações e final do gesto não devem esconder a barra imediatamente após a revelar.
3. Formulários e teclado não ficam tapados. Gestos horizontais e navegação funcionam.
4. Portal Cliente permanece idêntico à Stable.
5. Com `ff_dock_diag=1`, verificar `dock9de.runtime.motionContract=fieldflow-operative-dock-calm-motion-9df-v1`, `mounted=true` e `movementCount` coerente.
6. Em BO, Performance e Carga mostra estimativas sem pedidos HTTP adicionais, e períodos sem dados identificados corretamente.
7. Gate 6B / Fase 6 / SHA-256 e N+1 obrigatórios. Não declarar barra resolvida antes de teste real.

## Recuperação

Preservar ZIP 2.2.639 Stable, sem o modificar. Desligar o bootstrap 9DE apenas se for indispensável regressar ao controlador 9DC, que permanece no pacote.
