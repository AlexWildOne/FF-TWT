# Phase 9BO - Daily Operational Priority

Contract: `fieldflow-daily-operational-priority-v1`

FieldFlow 2.2.586 turns existing operational state into an explainable daily focus without mutating routes or calling external providers.

## Guarantees

- `SOURCE_RELEASE` is `2.2.585` and `STABLE_RELEASE` is `2.2.586`.
- My Route exposes a compact daily priority panel using route state plus monthly periodicity risk.
- Intelligence Center exposes the same authority in an `Operação diária` coordinator view.
- Active visits rank before pending route stops, and critical unresolved periodicity ranks before attention items.
- Justified non-visits never count as executed visits; exceptions close to expiry can be surfaced for review.
- The service is read-only and performs no Google, AI or other external API request.
- The service never creates, reorders, publishes or closes routes automatically.
- Existing 9BK, 9BL, 9BM and 9BN cost-control contracts remain unchanged.
- No database schema, SubmissionPipeline, offline protocol or routing-cost migration change.
