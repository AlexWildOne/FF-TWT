# Phase 6B.3 Runtime Performance Hotfix, FieldFlow 2.2.386

## Scope

Performance-only hardening after the 2.2.384/2.2.385 rollout.

## Root cause

The admin notice renderer executed the full SystemHealth graph on every FieldFlow admin request. With 6B.3, that graph recursively evaluated 6B.1, 6B.2 and 6B.3 multiple times, including release integrity, table summaries and certification fingerprints. The previous 2.2.385 request-only manifest cache removed duplicate file hashing inside a request but still left the expensive health graph on every page.

## Changes

- Admin notices no longer execute full SystemHealth. They use only cheap local licensing checks, migration state and the last explicit Release Gate result.
- 6B.1, 6B.2 and 6B.3 health snapshots are cached inside the current PHP request.
- Production manifest verification has a five-minute cross-request cache keyed by runtime version and manifest metadata.
- Release Gate and 6B certification actions force a fresh SHA-256 verification.

## Safety

No schema or migration changes. No Azure contract changes. Enforcement remains SHADOW/OFF as previously certified. Phase 5 delivery gates and pending 5B jobs are untouched.
