# Phase 9BE - Planner Free Warning Continuity

Contract: `fieldflow-planner-free-warning-continuity-v1`

## Goal

Planear is a planning workspace, not an execution gate. Stores that belong to the merchandiser assignment catalogue remain plannable even when campaign lifecycle rules, visit cadence or another route create a scheduling conflict.

## Behaviour

- `assigned_to` remains the canonical authority for the merchandiser catalogue introduced by 9BC.
- Route optimisation in Planear uses planner mode and does not reapply `is_active=1` or `status='active'` as a hard eligibility gate.
- A real store outside the merchandiser assignment and outside the existing route remains forbidden.
- Same-date route overlap is returned as a warning and does not prevent optimisation or save.
- Blocked/preferred weekdays and minimum-gap conflicts are returned as warnings and do not prevent optimisation or save.
- Same-store duplication inside the same route/day, invalid dates, the eight-store route capacity and operational route protection remain unchanged.
- No schema change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Release continuity

`SOURCE_RELEASE` is `2.2.574` and `STABLE_RELEASE` is `2.2.575`.
