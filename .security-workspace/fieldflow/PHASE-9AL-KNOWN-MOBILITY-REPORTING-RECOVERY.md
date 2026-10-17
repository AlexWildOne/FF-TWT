# Phase 9AL, Known Mobility Reporting Recovery

Contract: `fieldflow-known-mobility-reporting-recovery-v1`

`SOURCE_RELEASE` is `2.2.555` and `STABLE_RELEASE` is `2.2.556`.

This release fixes a reporting-only semantic gap discovered after 9AK certification.

- Canonical complete route metrics remain authoritative and unchanged.
- When a closed route has `known_distance_km` but canonical `distance_km` is unavailable because integrity is not fully complete, Daily Close reporting no longer renders that known value as zero.
- `MobilityCostService::reportingProjection()` reuses the canonical mobility formula and the known toll value to produce an explicitly partial reporting estimate.
- The base `costs_available` flag is never upgraded or mutated. Reporting exposes separate `reporting_*` fields and marks recovered rows/periods as partial.
- Routes with neither canonical nor known distance remain unavailable and are not fabricated.
- Email, Excel and PDF never turn unavailable mobility data into numeric zero: known values are labelled as recovered/partial, while genuinely unavailable costs are shown as `Por calcular` and unavailable distance as `Por apurar`.
- No schema or SubmissionPipeline changes are introduced.
