# FieldFlow Phase 6A Stable Closure

Release: 2.2.379

## Objetivo

Fechar formalmente a Fase 6A depois da prova real do fluxo `Cliente -> Template -> Pré-flight -> Provisioning Shadow -> Readiness -> Production Ready`.

A certificação 6A não ativa produção. Congela apenas uma baseline técnica do onboarding testado.

## Gate de certificação

A matriz exige:

1. Foundation 6A pronta e catálogo com sete templates.
2. Pelo menos um plano com pré-flight validado.
3. Pelo menos um plano provisionado em `Shadow`.
4. Pelo menos um onboarding `TWT Cloud` com `Production Ready` completo.
5. Fase 5 Stable certificada e baseline `MATCH`.
6. Connector Delivery em `Shadow`, kill switch do conector ON e kill switch de governance ON.
7. `Production Enabled = false` e `Full Operational Payload = false`.
8. `production-manifest.json` da 2.2.379 integralmente válido.

## Baseline

A certificação grava:

- fingerprint SHA-256 determinístico, ancorado num plano TWT Cloud de evidência;
- snapshot dos planos Production Ready sem segredos nem payloads operacionais;
- UUID do plano de evidência usado na certificação;
- matriz 6A;
- integridade da release;
- estado dos guardrails de segurança.

O BO permite exportar `fieldflow-phase6a-stable-baseline-2.2.379.json` depois da certificação.

## Segurança

A Fase 6A termina em `PRODUCTION_READY`. A ativação real permanece uma decisão separada e explícita das fases seguintes.

## Estabilidade da baseline

Depois da certificação, o plano TWT Cloud usado como evidência fica fixado por UUID. Criar novos onboardings no futuro não invalida a baseline 6A. A baseline só deixa de fazer `MATCH` se o próprio plano de evidência ou os guardrails certificados forem alterados.
