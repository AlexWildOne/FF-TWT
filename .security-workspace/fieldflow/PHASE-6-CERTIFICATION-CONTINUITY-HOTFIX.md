# FieldFlow 2.2.391 - Phase 6 Certification Continuity Hotfix

## Motivo

O upgrade privado 2.2.389 -> 2.2.390 foi concluído com sucesso, mas a cadeia local de certificação ficou incoerente: 6B.3 passou a depender de uma 6B.2 por revalidar, enquanto 6B.4 ainda se apresentava como certificada. O runtime, Installation UUID, Activation ID, policy remota, SHADOW e pacote privado permaneceram saudáveis.

## Correções

- 6B.4 só é certificada se a 6B.3 estiver atualmente certificada e todos os invariantes live estiverem válidos.
- O fecho Stable mede preservação de identidade e SHADOW diretamente, sem confundir esses factos com o estado administrativo do certificado.
- `Testar fecho Fase 6` refresca heartbeat, governance e distribuição privada.
- Novo comando explícito `Revalidar cadeia 6B pós-update` reexecuta os gates 6B.2, 6B.3 e 6B.4 em sequência e para imediatamente se algum gate falhar.
- A revalidação não suspende, revoga, roda keys, ativa production, ativa runtime blocking ou processa filas.
- Certificados passam a guardar metadados de continuidade sem secrets em claro.

## Baseline

- FieldFlow: 2.2.391
- Licensing Backend: 1.0.6
- Production Enabled: OFF
- Full Operational Payload: OFF
- Governance Enforcement: SHADOW
- Runtime blocking: OFF
- Remote revocation runtime enforcement: OFF
- Forced auto-updates: OFF
