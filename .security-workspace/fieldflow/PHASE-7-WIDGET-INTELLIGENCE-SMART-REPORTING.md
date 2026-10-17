# FieldFlow Widget Intelligence + Smart Reporting

Release: 2.2.431
Source release: 2.2.430

## Objective

Reduce dashboard noise without deleting analytical capability. The complete widget registry remains available, while FieldFlow automatically chooses a compact, explainable set for Client and Merchandiser front ends according to briefing priority, question semantics, available data, audience relevance and active operational alerts.

The same intelligence envelope is reused in email, Excel, PDF and CSV so recipients receive a consistent reading of the operation.

## Widget orchestration modes

- Automatic: the front shows only the highest ranked existing widgets for the active audience.
- Assisted: FieldFlow ranks and recommends widgets, while the configured dashboard remains visible for human review.
- Manual: the administrator controls the dashboard directly.

Each dashboard can configure maximum Client, Merchandiser and Email widget counts, and can pin widgets that must always remain visible. Alert boosting can be disabled per dashboard.

## Deterministic selection policy

The orchestrator never invents metrics. It ranks only widgets already present in the FieldFlow Analytics registry. Ranking uses traceable signals including briefing priority, briefing origin, strategic metric family, data availability, audience relevance, administrator pinning and active operational alerts.

A second non-persisting intelligence pass can adapt the front ranking to current alerts without creating or changing operational alerts merely because somebody opened a dashboard.

## Reporting intelligence

Email, Excel, PDF and CSV share one Report Intelligence envelope containing Decision Scores, recommended metrics and store alerts. Full exports preserve the complete underlying analytical detail even when the front dashboard is intentionally compact.

Scheduled emails add a compact analytical summary for each selected period. Client recipients receive campaign scope. Merchandiser recipients are limited to their own operational scope. Store alerts identify concrete stores only when FieldFlow has store-level evidence.

Route completion and exception emails add a small route/day intelligence digest with execution, risk, opportunity and available store alerts.

## Export contract

- Excel adds Intelligence and Alertas Lojas sheets before the detailed analytical sheets.
- PDF adds Field Intelligence scores, prioritised widgets and store alerts before the detailed report.
- CSV adds explicit Intelligence, Smart Widget and Store Alert records while preserving the existing summary and detail records.
- Daily is available as a selectable reporting period.
- CSV is available as a selectable attachment format.
- New automations keep the safer existing default of Weekly + Cumulative with Excel + PDF, avoiding an unnecessary attachment explosion. Daily and CSV are opt-in.

## Scope safety

StockService currently exposes a Client/Project aggregate snapshot. FieldFlow therefore does not attribute campaign-wide stock intelligence to an individual route, merchandiser or store. Narrow-scope reports omit that stock aggregate until a future stock contract can prove the exact store/person/route scope.

## Learning boundary

This release does not enable autonomous self-learning. Widget ranking is deterministic and auditable. The next learning phase can capture recommendation, human decision, action and outcome as a separate feedback dataset without silently changing the certified scoring contract.

## Certification

2.2.431 must complete the mandatory WordPress Release Manager publication path and Azure Phase 6 gate: Revalidate 6B, Test Phase 6 closure, Certify Stable. The build is not considered closed before the exact installed 2.2.431 runtime is certified.
