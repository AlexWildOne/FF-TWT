# Phase 7ZM - Analytics Mobile Full-Width & Download State Continuity

Release: 2.2.471

## Objetivo

Tornar o Analytics mobile orientado a leitura de dados, usando praticamente toda a largura util do viewport, removendo scrolls verticais internos que interrompiam a leitura e unificando o estado de downloads de evidencias entre o front e a ponte WebView.

## Alteracoes

- Full-bleed controlado do painel Analytics dentro da app mobile, sem alterar os restantes modulos.
- Todos os dashboards e widgets configurados no BO passam a 100% de largura no viewport mobile.
- Ask FieldFlow deixa de ter scroll vertical proprio em mobile.
- Graficos mantem apenas scroll horizontal quando a densidade de dados o exige.
- Tabelas responsivas e detalhes deixam o scroll vertical para a pagina principal.
- O ZIP preparado usa diretamente `FieldFlowNativeBridge.downloadPrepared()` quando a ponte mobile existe.
- O botao apenas mostra sucesso depois do handoff confirmado ou do fallback browser concluido.
- Erros de ZIP deixam de sobrescrever a nota global da tabela de rotas.
- Zero alteracoes Flutter e zero migracoes de base de dados.

## Client Portal parity

- The client-facing Analytics panel uses the same mobile full-width viewport policy.
- Ask FieldFlow and analytics tables no longer own vertical scroll on mobile.
- Charts may scroll horizontally when their data density requires it, while vertical gestures remain page-owned.
