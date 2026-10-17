# Phase 9BN, Google Cost Control Center

Release: 2.2.585  
Contract: `fieldflow-google-cost-control-center-v1`

## Objective

Make Google Maps Platform spend observable and fail closed before premium routing requests leave WordPress, while keeping normal Compute Routes Essentials available for route changes that genuinely require Google road-network validation.

## Runtime policy

- Google Cost Control Center is enabled by default.
- Compute Routes Essentials remains allowed.
- Compute Routes Pro is blocked by default.
- Compute Routes Enterprise, including Google TOLLS, is blocked by default.
- Compute Route Matrix is blocked by default, including its origin x destination unit multiplication.
- Existing cached route snapshots can still be reused without a billable request.
- A blocked operation is written to the privacy-safe API Cost Ledger with `request_count=0`, so it is measured as avoided cost rather than a transport error.
- Configuration drift is surfaced when traffic-aware routing, Google waypoint optimisation, Google TOLLS, Matrix or Places contact enrichment conflicts with the low-cost policy.
- No addresses, coordinates, route payloads, API keys or provider responses are stored by the cost controller.

## Backoffice

Security and Observability now exposes a Google Cost Control Center showing:

- health state;
- Compute Routes Essentials requests;
- Pro and Enterprise requests;
- Route Matrix units;
- calls blocked before HTTP;
- calls avoided through cache or policy;
- provider and operation breakdown;
- budget usage and configurable reference rates;
- financial circuit-breaker toggles.

## Upgrade behaviour

The 2.2.585 migration does not purge routing caches and does not generate a Google request. It restores the low-cost baseline to `TRAFFIC_UNAWARE`, Matrix off, waypoint optimisation off and Google TOLLS off, then enables the circuit breaker.
