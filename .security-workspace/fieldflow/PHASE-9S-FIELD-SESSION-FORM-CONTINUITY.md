# Phase 9S — Field Session & Form Continuity

Contract: `fieldflow-field-session-form-continuity-v1`

This is an emergency field hotfix built directly from the certified 2.2.534 baseline. `SOURCE_RELEASE` is `2.2.534` and `STABLE_RELEASE` is `2.2.535`.

## Scope

- Rotating mobile refresh remains enabled, but concurrent refresh requests can replay the same newly-issued token pair for 30 seconds through an encrypted transient. This prevents a duplicate refresh from being interpreted as session loss.
- Non-privileged field users no longer receive the privileged 60-minute WebView idle expiry. Their WebView remains bounded by the parent mobile session, the configured WebView lifetime and device/platform controls. Privileged users retain the stricter session policy.
- Native app resume, `visibilitychange -> visible`, `pageshow` and `focus` converge on the same debounced WebView resume path.
- Form drafts use the same resume signals and restore the last local wizard step synchronously before IndexedDB/server reconciliation.
- `order_suggestion` keeps a renderable shell even when Cardex resolution is late or temporarily unavailable. `product_matrix` still requires a valid Cardex when configured to use one.

## Frozen contracts

- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No Routing/P1-P4, route optimisation, Mail Outbox, Analytics calculation, order-email eligibility, Cardex persistence or database schema change.
- The 2.2.534 Phase 9R terrain continuity protections remain mandatory.

## Certification

The release must be validated and published through Field Flow Release Manager, installed with the private updater, followed by Revalidate 6B, Test Phase 6 closure and Certify Stable. Direct `2.2.534 -> 2.2.535` package validation is mandatory before delivery.
