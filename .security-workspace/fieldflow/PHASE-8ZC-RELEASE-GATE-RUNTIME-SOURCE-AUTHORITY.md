# Phase 8ZC, Release Gate Runtime Source Authority

Contract: `fieldflow-release-gate-runtime-source-authority-v1`

`SOURCE_RELEASE` is `2.2.513` and `STABLE_RELEASE` is `2.2.514`.

## Problem

Private plugin updates can replace the canonical PHP source on disk while the current PHP request, or an OPcache entry, still exposes a previously loaded class definition. The 2.2.513 Release Gate reflected that stale in-memory class and could report `runtime:repair_signature` even when `src/Services/RouteMetricsService.php` already contained the certified five-parameter contract.

## Authority rule

The canonical installed source is authoritative only when all of the following are true:

- the exact certified method signature exists in the canonical plugin file;
- any already-loaded runtime class reports the same canonical file as its origin;
- no alternate plugin copy or foreign path supplied the class.

If the canonical source is correct but the already-loaded class from that same path is stale, the gate records a warning, invalidates OPcache when available and does not block Stable certification. A class loaded from any other path remains a hard failure.

## Safety boundaries

- No P1-P4 routing or optimisation-rule change.
- No route-distance calculation change.
- No endpoint migration change.
- No database schema change.
- No certification gate is bypassed or auto-certified.
- A wrong runtime origin still blocks certification.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
