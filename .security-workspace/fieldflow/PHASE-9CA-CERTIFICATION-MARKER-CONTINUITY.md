# Phase 9CA, Certification Marker Continuity

Contract: `fieldflow-certification-marker-continuity-9ca-v1`

Release continuity: `SOURCE_RELEASE` is `2.2.597` and `STABLE_RELEASE` is `2.2.598`.

## Purpose

FieldFlow 2.2.597 correctly moved active last-visit autofill to the 9BZ policy, but the historical 9BD Stable gate still requires the literal proof marker `Frentes reutiliza o valor final anterior` inside `src/Forms/Forms.php`. The runtime behaviour remained correct, but the historical certification proof became incomplete.

2.2.598 restores that historical marker as an inert certification comment. It does not restore the predecessor prefill implementation. The active runtime authority remains `DailySubmissionService::new_visit_autofill_prefill()` from 9BZ.

## Guarantees

- The 9BD historical proof marker is present in `src/Forms/Forms.php`.
- The active 9BZ policy remains responsible for last-visit autofill.
- Normal eligible answers and Frentes may be reused from the previous canonical visit.
- Photos, categorized images, files, briefing blocks and order suggestions remain excluded from new-visit autofill.
- Store isolation remains governed by canonical store identity.
- No database schema changes.
- No FlutterFlow source modification is required.
- The Release Gate, Package Validator and Release Manager expose the same 9CA continuity result.
