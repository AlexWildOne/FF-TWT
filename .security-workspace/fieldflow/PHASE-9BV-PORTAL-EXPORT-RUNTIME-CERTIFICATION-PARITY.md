# Phase 9BV, Portal Export Runtime Integrity & Certification Parity

Contract: `fieldflow-portal-export-runtime-certification-parity-v1`
Release target: `2.2.593`

Closes the Client Portal export dependency graph for Reports, Analytics and Routes by restoring the three canonical admin-post URL builders, each bound to its own nonce and filter contract.

The Reports loader now owns only report retrieval and presentation. Export-link refresh is guarded separately, so a future export-control regression cannot erase report rows that were already loaded successfully.

The same contract audits the Operative Analytics export helper and the Operative app route bridge, without changing their runtime semantics.

Release Manager package validation and Stable certification both require the same 9BV markers, runtime tests and successor chain. Future packages therefore fail before publication or certification if these dependencies are removed again.

No database schema changes.
No Google calls.
No route, report or analytics data semantics changed.
