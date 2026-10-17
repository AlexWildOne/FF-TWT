# Phase 9AF — Certification, Viewport Lock & Mail Template Recovery

Contract: `fieldflow-certification-viewport-lock-mail-template-recovery-v1`

Technical package succession: `SOURCE_RELEASE` is `2.2.547` and `STABLE_RELEASE` is `2.2.548`. This records the installed update predecessor only; it does not retroactively certify 2.2.547 as Stable. The last certified baseline before this candidate remains 2.2.546.

## Scope

- Restore Mobility Cost Intelligence Release Gate/package-validator parity by preserving the user-visible labels `Combustível / energia` and `Custo deslocação`.
- Prevent visible field-form viewport jumps by locking the actual scroll container throughout store/form/media transitions and releasing at the original scrollTop.
- Keep guarded `focus-after-hidden` resume semantics and forbid bare focus restore.
- Keep the React operational fallback readable on a light background.
- Recover missing `daily_close` Mail Center schedules proactively from persisted project templates without reactivating an existing paused schedule.
- Allow manual route-close dispatch to reconstruct a dormant template and preserve XLSX + PDF defaults.
- Preserve the 2.2.547 canonical operational/commercial reporting model.

## Non-goals

No database schema change. No P1-P4 routing-rule change. No FlutterFlow change. No change to `SubmissionPipeline::BUILD`.
