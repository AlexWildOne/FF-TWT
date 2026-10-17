# FieldFlow 2.2.644 | Phase 4E 9DJ

Contrato: `fieldflow-phase4e-admin-cert-readonly-mobile-layout-9dj-v1`. Baseline: 2.2.643 Stable certificada.

## Performance: GET read-only e autoridade da certificacao

- `LicenseTenants::render()` pede `Phase6StableRelease::localProbe(false, false)` apenas em GET, reutilizando o manifesto existente e estados de health 6B do mesmo pedido.
- POST, testes formais, `Phase6StableRelease::certify()` e o Release Manager continuam a executar `localProbe()` com fresh scan. Nao altera matriz, scores, permissoes ou qualquer decisao de certificacao.
- O custo elevado nas paginas de Licenciamento e Release Manager foi observado por amostras: 27.723 e 18.164 queries P95, respetivamente. Esta alteracao reduz trabalho redundante comprovado no codigo, mas nao promete um P95 ate ser medido com dados reais, incl. PHP e plugins terceiros.

## Mobile: Performance e Carga

- `.ff-ops-performance-9dj` e CSS com escopo local, KPIs e processos em auto-fit independentemente do tamanho do viewport, nav horizontal e tabelas legiveis por cartoes ate 900 CSS px, com `data-label` por celula.
- Desktop continua com tabelas completas, formulários com `name`, `nonce`, limites e thresholds originais, e rolagem horizontal onde aplicavel.
- Quando `unpriced_requests > 0`, total estimado deixa de mostrar falsamente 0 EUR; subtotais com tarifa continuam indicados. Nao configura tariffs, Google API, budgets nem Google Cost Center.
- O status vermelho Load Readiness permanece observacional; nunca altera os criterios de `Phase6StableRelease`.

## Protecoes e testes

- Novo teste dedicado 9DJ e Release Gate/Package Validator fail-closed sobre os marcadores de continuidade da release. Pacotes incompletos rejeitados. `tools/build-production-manifest-9dh.py` continua autoridade de empacotamento e hash.
- Mesmo codigo funcional dos quatro bundles 9DI, apenas stamps 2.2.644. Sem front-end operacional ou schema alterado.
- Comparar 24h apos instalar usando o mesmo periodo de amostragem e experiencia mobile real. Preservar 2.2.643 como ultima Stable ate certificacao da 2.2.644.
