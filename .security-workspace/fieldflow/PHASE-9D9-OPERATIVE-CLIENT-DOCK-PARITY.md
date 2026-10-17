# FieldFlow 2.2.634 | 9D9 Operative Client Dock Parity

**Baseline:** 2.2.633 Stable certificada pelo utilizador em 11/10/2026. **Candidata:** 2.2.634.

## Evidencia
O Cliente esconde a barra ao deslizar para baixo e revela ao subir; o Operativo permanece fixo no Chrome e Android, mesmo com 9D6/9D7/9D8 certificados. A razao concreta para a falha em producao nao esta provada sem inspecao ao DOM vivo.

## Intervencao minima
- O mesmo ficheiro `assets/fieldflow-visual-consolidation-9d5.js` que ja controla a barra do Cliente e incorporado no shortcode Operativo e enfileirado como fallback.
- O boot do proprio 9D5 ja e idempotente por `root.dataset.ffvVisual`, impedindo dupla inicializacao.
- As camadas operativas historicas 9D6/9D7/9D8 ficam inativas na configuracao por defeito; nao existe um quarto controlador concorrente.
- A classe de recolha continua a ser `ffv-dock-hidden` aplicada a `.rp-tabs`, exatamente como no Cliente.
- O CSS usa transform e opacidade do Cliente; padding inferior respeita safe-area.
- Kill switch: `define('FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9', false);` recorre ao mecanismo anterior.
- O Portal Cliente e o ficheiro 9D5 nao foram modificados.

## Aceitacao
1. Em Chrome e Android, deslizar para baixo com a pagina realmente a mover-se, verificar barra recolhe; deslizar para cima, reaparece.
2. Stocks/Ruturas, Minha Rota, Reportar, Briefing e Implementacoes: nenhuma acao final fica inacessivel.
3. Cliente conserva comportamento atual, filtros, estados e cores; nenhuma alteracao funcional.
4. Publicar apenas pelo Release Manager apos validacoes locais e confirmar resultado em dispositivo real **antes** de aceitar a correccao visual como concluida.

Sem calls de IA, schema migrations, alteracao da base de dados, alteracao de permissões ou offline queue.
