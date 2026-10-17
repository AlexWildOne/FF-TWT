# FieldFlow 2.2.641 | Fase 4B: Cost & Performance Drivers 9DG

Contrato: `fieldflow-phase4-cost-performance-drivers-9dg-v1`

Continuidade estrita da **2.2.640 Stable**, instalada e certificada. O comportamento do dock do Operativo foi validado em Android na 9DF e permanece integralmente congelado, bem como os assets do Portal Cliente.

## Objetivo

O painel existente **Segurança e Observabilidade > Performance e Carga** passa a distinguir quais os grupos de chamadas API que merecem investigação e quais os fluxos com latência P95 em aviso/bloqueio. Antes de otimizar caches, rotas ou fornecedores, é necessário conhecer o padrão real de consumo e a faturação externa.

## Funcionalidades

- Lista das operações agregadas nos últimos 30 dias, ordenada por riscos técnicos: tarifa de referência em falta, Google premium, erros recorrentes, candidata de cache elegível, estimativa financeira e bloqueios.
- Nunca classifica uma chamada registada sem tarifa de referência como gratuita. Nesses casos mostra **n/d (sem tarifa)** e recomenda comparação com os SKUs faturados.
- Deteta candidatos de cache apenas para tipos de operação concretos, sem introduzir qualquer cache, reutilizar dados entre clientes ou alterar políticas.
- Identifica fluxos com estado de aviso/bloqueio e pelo menos 10 amostras reais, apresentando P95 e taxa de erros. Não cria artificialmente métricas de desempenho.
- Mostra uma tendência estimada 7 dias contra os 7 anteriores apenas quando existem pelo menos 4 dias com registos em cada janela. A comparação inclui o dia UTC atual incompleto, pelo que é meramente orientadora.
- Integra sem novas tabelas, cron, chamadas HTTP, serviços, transações de IA ou mudanças de configuração.

## Segurança e limites

O novo serviço `src/Performance/Phase4Drivers.php` reutiliza as operações já agregadas pelo GoogleCostControlCenter/Phase4Baseline, com fallback para `ApiUsageLedger::byOperation(30, 80)`, e lê `ApiUsageLedger::daily(14)` e o gate de performance já carregado na página. Não recolhe coordenadas, moradas, payloads, chaves API, nomes de utilizadores nem fotografias.

A avaliação é apenas **diagnóstico**. Não reduz ainda consumos reais de Google Maps ou Azure. Não altera políticas Google Cost Control Center, custos de IA, valores de rotas, cache operacional, formulários, dados, Release Manager, certificados Phase6/6B nem portais. A faturação dos fornecedores é a autoridade financeira.

## Testes de continuidade

- Teste independente `tools/test-phase4-cost-performance-drivers-9dg.php` sobre tarifas em falta, prioridade, P95, tendência e ausência de dados.
- Contrato 9DG no ReleasePackageValidator e ReleaseGate, sem enfraquecer contratos 9DF/9DE anteriores.
- A candidata deve ser aceite pelo validador **original** 2.2.640; simular 2.2.642 com validador 2.2.641.
- Em produção confirmar 6B, Phase6 e certificação Stable, validar o novo painel e contrastar com Google Cloud Billing/Azure antes de ativar alterações de custo.
