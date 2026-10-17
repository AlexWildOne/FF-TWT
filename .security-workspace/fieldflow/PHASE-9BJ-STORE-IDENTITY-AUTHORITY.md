# Phase 9BJ — Store Identity Authority & Historical Media Isolation

Contract: `fieldflow-store-identity-authority-v1`

Release continuity: `SOURCE_RELEASE` is `2.2.580` and `STABLE_RELEASE` is `2.2.581`.

## Objective

Make the route stop and its location snapshot the canonical authority for the store opened in a field form, while preserving historical data and preventing answers or media from one store from being rendered in another store when legacy numeric identifiers collide.

## Runtime guarantees

- A valid `route_stop_id`/`stop_id` overrides stale route, store, client and campaign identifiers received from the browser or WebView.
- `DailySubmissionService` resolves an effective canonical location through route snapshot/current route stop before querying daily reports or previous visits.
- Legacy records affected by the historical `location_id = route_stop_id` defect remain readable under their real store.
- A daily report whose route, stop or canonical store conflicts with the current form context is never used as full prefill, so its photos, files and order evidence cannot leak into the current visit.
- Web and Mobile use the same conflict rule.
- Store Profile latest-visit queries use the same canonical store identity.
- Draft snapshots carry an identity fingerprint for form, binding, client, campaign, route, stop, store and report day.
- Automatic draft recovery fails closed when stable store identity is missing or mismatched. Legacy/mismatched evidence is retained for recovery rather than deleted.
- Route/stop rebasing is allowed only when form, binding, client, campaign, store and report day remain identical.

## Data policy

No destructive migration is performed. Historical submissions and drafts remain intact. The correction is read-time and context-authority hardening, allowing later targeted reconciliation of legacy rows without risking field evidence.

## Certification

The Release Gate and Package Validator require `fieldflow-store-identity-authority-v1`, canonical store resolution, conflict-safe Web/Mobile prefill, draft identity checks, Store Profile parity, the verifier, and Phase 6 continuity `2.2.580 -> 2.2.581`.
