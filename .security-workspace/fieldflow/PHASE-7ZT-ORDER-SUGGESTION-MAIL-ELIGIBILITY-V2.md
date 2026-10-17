# Phase 7ZT, Order Suggestion Mail Eligibility v2

Release: 2.2.478

Contract: `fieldflow-order-suggestion-mail-eligibility-v2`

## Objective

Prevent false-positive order-suggestion emails when a conditional controller answer disables the `order_suggestion` branch, including submissions recovered or edited through offline/mobile flows.

## Runtime contract

- Submission-time conditional snapshot remains the first source of truth.
- Persisted answers are re-evaluated when the current form definition matches the definition recorded on the submission.
- Email eligibility fails closed when either trustworthy source reports the order-suggestion branch as inactive.
- Contradictory snapshot state, including an active key also recorded as hidden, is not allowed to trigger mail.
- Conditional inactivity is a valid no-send state and must not schedule automatic mail retries.
- Historical submissions whose form definition has changed keep the saved snapshot as authority rather than being reinterpreted by a newer schema.

## Release Manager

Package validation exposes `order_suggestion_mail_eligibility_ok` and rejects releases that omit the persisted-answer veto, automatic-dispatch guard or behavioural verifier.

## Compatibility

No database migration. No REST contract change. No Flutter change. No IndexedDB schema change. Typed Offline Core remains unchanged.
