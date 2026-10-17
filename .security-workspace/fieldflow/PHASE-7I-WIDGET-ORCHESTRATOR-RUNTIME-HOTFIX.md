# FieldFlow Phase 7I - Widget Orchestrator Runtime Hotfix

Release: 2.2.440
Source release: 2.2.439

## Root cause

The Analytics HTTP 500 regression was not caused by Store Intelligence timeout or memory pressure. During the 2.2.437 WidgetOrchestrator edit, the private `scoreMetric()` and `topic()` methods were accidentally removed while `orchestrate()` and `compactMetrics()` continued to call `scoreMetric()`.

The plugin therefore passed PHP syntax validation but failed at runtime with a missing-method Error as soon as Widget Intelligence attempted to score a metric. Because `/stats`, AI insights and the isolated Store Intelligence request all traverse WidgetOrchestrator scoring, the same fault appeared across multiple Analytics panels.

## Fix

- Restore `scoreMetric()` and `topic()` from the certified 2.2.436 contract.
- Preserve all 2.2.437 to 2.2.439 Store Intelligence work and critical-path decoupling.
- Add a runtime scoring-contract guard and release-time class self-call integrity validation so future missing helpers fail open and are caught before certification.
- No database migration and no score formula change.

## Certification

Publish through Release Manager, install through the private updater, then run Revalidar cadeia 6B, Testar fecho Fase 6 and Certificar Stable.
