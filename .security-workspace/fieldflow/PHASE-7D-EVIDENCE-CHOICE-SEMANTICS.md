# FieldFlow Phase 7D, Evidence Choice Semantics

Release: 2.2.435
Source release: 2.2.434

## Objective

Make the primary Sim / Não / Não aplicável answer authoritative. Conditional evidence companions must never become mandatory merely because a branch was selected.

## Contract

- Parent question required = user must choose Sim, Não or Não aplicável.
- Parent question optional = user may leave it unanswered.
- Sim branch exposes photographs, optional by default.
- Não branch exposes a non-conformity reason, optional by default.
- Não aplicável exposes neither companion.
- Explicit schema flags may require a branch companion in future controlled forms, but implicit strictness is forbidden.
- Analytics classification is unchanged.

## Compatibility

The change is schema-only and requires no database migration. Existing answers, dashboards, exports and briefing analytics remain compatible. Web/server and native mobile share the same EvidenceFlow v2 expansion and validation contract.

## Release gate

2.2.435 follows the canonical WordPress Release Manager flow and remains subject to the mandatory Azure Phase 6 certification gate: `Revalidar cadeia 6B`, `Testar fecho Fase 6`, `Certificar Stable`.
