# Fronts Safe Budget Reconciliation 9D0
Contract: `fieldflow-fronts-budget-reconciliation-9d0-v1`.
Release: `2.2.623 -> 2.2.624`.

## Incident
Pernod Ricard has 5/500 monthly requests but 5.00/5.00 EUR locked as pre-call reserves,
so the camera was unavailable. The 9CZ metering records provider tokens and tariff
provenance but deliberately did not reconcile reservations. The frontend conflated
budget, network and provider error in a single string.

## Fix
- Keep pre-call authorized reserve and daily/monthly request guards under same SQL lock.
- Assign a cryptographically random reservation id to each new attempt and persist
  it with the monthly option. The duplicate image path still deduplicates first.
- On a successfully measured provider response with exact provider/model tariff,
  convert that one reservation to a guarded cost envelope equal to 130% of the
  estimated cost, rounded **up** to cents, minimum 1 cent. The estimate remains
  separate from actual Azure invoice. No automatic model retry or second AI call.
- If missing tariff, tokens, trustworthy provider provenance or a response error,
  keep the full original reservation. Settlement failures remain fail closed.
- Reconciliation is idempotent and atomic, so retries cannot release more twice.
- Legacy amounts accrued before this release are **not** reset automatically.
  Backoffice has an explicit once-per-month per-client review with verified EUR
  amount and justification. It records manager identity, timestamp and discount;
  the monthly quota counters are unchanged. Only the manager may authorize it,
  with CSRF protection and an explicit confirmation.
- In the mobile modal, reveal specific server-side block reason or actual offline
  state. Reopen a previously cached form to refresh the state after edits.
- Normal manual count remains freely available offline; no form/database migration,
  no change to Azure SQL S0, routes, 9CY visual identification or existing report.

## Operational caveats
- Historical reconciliation requires a manually verified source, ideally Azure
  Cost Management for the period. It is an administrative correction, not
  a statement of actual invoicing. Do not enter zero without evidence.
- Even the settlement 130% buffer cannot guarantee an Azure invoice ceiling;
  other AI modules have separate charges and rate agreements can change.
- A timeout may have consumed tokens while the client received no response;
  therefore it retains the full reserve.
- The stored budget envelope remains conservative and may exceed invoices.

## Validation
`php tools/test-fronts-budget-reconciliation-9d0.php`,
`node tools/test-fronts-budget-availability-9d0.mjs`, historical tests,
PHP lint, frontend verification, old 2.2.623 ReleasePackageValidator and new
2.2.624 validator, then Package Manifest SHA-256. Server certification required
before promoting from candidate to Stable.
