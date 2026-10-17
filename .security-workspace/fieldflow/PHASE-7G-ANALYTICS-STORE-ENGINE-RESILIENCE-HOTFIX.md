# FieldFlow Phase 7G - Analytics Store Engine Resilience Hotfix

Release: 2.2.438
Source: 2.2.437
Database migration: none

## Incident

After enabling Store Opportunity & Risk profiles in 2.2.437, Analytics 2.0 could return HTTP 500 on large real project scopes. The new per-store engine was being executed during the `/stats` widget-orchestration pass and then executed again by `/analytics/ai-insights`. That duplicated stock, learning and per-store processing even though the first result was discarded after widget selection.

## Fix

- `/stats` keeps the lightweight deterministic intelligence pass used by Widget Orchestration and explicitly skips Store Intelligence.
- `/analytics/ai-insights` calculates Store Intelligence once and keeps the full result for the UI.
- Store Intelligence is fail-open and falls back to DecisionScoreService priority locations if the store engine cannot complete.
- Individual store profile failures are isolated.
- Exact mapped-location stock contexts use a short cache keyed by published batch ids and location ids.
- The AI prompt receives only a compact Store Intelligence summary/top ranking envelope, never the full hundreds-of-PDVs payload.

## Safety

No score weights or business semantics change. Risk, Opportunity, Health, stock snapshot replacement, Learning feedback and recommendation contracts remain unchanged. Adaptive Intelligence remains disabled.

## Certification

2.2.438 must be published through Release Manager, installed through the private updater, then pass Revalidar cadeia 6B, Testar fecho Fase 6 and Certificar Stable before closure.
