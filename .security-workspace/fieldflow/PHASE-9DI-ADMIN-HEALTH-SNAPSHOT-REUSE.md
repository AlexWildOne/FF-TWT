# FieldFlow 2.2.643 | Phase 4D Admin Health Snapshot Reuse 9DI

Contrato: `fieldflow-phase4-admin-health-snapshot-reuse-9di-v1`.

## Causa demonstrada por inspecao de codigo

O ecrã Saúde do sistema chama `SystemHealth::status()`, que já calcula `OfflineSyncService::stats()` e `BackofficeUIFoundation::status()`. Antes voltava a chamar os dois para construir KPIs. Havia tambem duas verificacoes separadas de tabelas SaaS, mais leituras integrais de ate 500 chaves API, 200 webhooks e 500 entregas para mostrar **apenas contagens**.

## Mudancas

- Reutilizacao por pedido dos dois resumos ja calculados (sem caches persistentes, valores antigos ou efeitos laterais).
- Os metadados offline agregados e nao identificativos passam a ser expostos tambem no array de saude para o BO reutilizar. O verificador continua a calcular os dados reais.
- Readiness de subscricoes SaaS executado uma vez no render.
- `Phase4AdminHealth::integrationCounts()` executa selects limitados de `id` e `status`, nunca `SELECT *`, `key_hash`, `secret`, `payload_json` ou dados de contacto. Conserva limites da UI (500, 200, 500) e conta apenas falhas nas ultimas 500 entregas.
- Nenhuma tabela, indice, schema, endpoint, politica, AI, rotas, formularios, relatorios, certificados ou estado de producao foi alterado.

## Medicao e limitacoes

O ganho comprovavel pela inspecao de fluxo e de pelo menos uma varredura offline eliminada, uma chamada extra de estado UI e uma verificacao adicional de tabelas SaaS; nas integracoes caem os retornos de linhas completas e checagens internas redundantes. Isto nao prova melhoria de P95 global: testar no BO em producao e recolher métricas das paginas antes/depois com amostras comparaveis. Os dados da 2.2.642 por pagina ainda nao foram disponibilizados. Sem conclusoes inventadas.

## Release workflow

Validar **2.2.642 Stable -> 2.2.643 candidata**, publicar no Azure so depois de validar N+1, instalar no mesmo diretorio, confirmar manifesto, executar Revalidar 6B, Testar Fase 6 e Certificar Stable. Backups fora da pasta do plugin. Nao contornar qualquer gate.
