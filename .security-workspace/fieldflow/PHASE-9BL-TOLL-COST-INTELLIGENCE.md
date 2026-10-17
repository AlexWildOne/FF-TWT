# Phase 9BL, Toll Cost Intelligence

Contract: `fieldflow-toll-cost-intelligence-v1`

Release: 2.2.583

## Objective

Estimate Portuguese toll costs with useful operational accuracy while keeping routine Google routing on the lowest practical billing tier. Google `extraComputations=TOLLS` remains opt-in and disabled by default.

## Runtime authority

- Standard `Compute Routes` remains the only normal Google call when a route materially changes.
- When Toll Cost Intelligence is enabled, the same standard route response requests basic step distance, navigation instruction and transient start/end coordinates.
- Raw Google step data is not persisted by the toll estimator. Only the resulting estimate, range, confidence, vehicle class, tariff version and aggregated route metrics are retained.
- Portuguese 2026 toll corridors are evaluated locally from a versioned tariff registry.
- Free former-SCUT corridors are represented explicitly, including A22, A23, A24 and A25, with guarded mixed treatment for A4, A13 and A28.
- Google `TOLLS` remains an explicit premium override and is not required for estimated tolls.

## Cost observability

API Cost Ledger records the Google Routes billing family as `compute_routes_essentials`, `compute_routes_pro`, `compute_routes_enterprise`, `compute_route_matrix_essentials` or `compute_route_matrix_pro` instead of hiding all traffic under generic route/matrix labels.

## Upgrade safety

Upgrade from 2.2.582 enables the internal toll estimator and Class 1 by default without clearing routing caches or forcing existing static route snapshots to call Google again.
