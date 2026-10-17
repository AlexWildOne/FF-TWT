# Phase 9BD - Form Visit Memory & Fronts Continuity

Contract: `fieldflow-form-visit-memory-fronts-continuity-v1`

## Objective

Restore the operational visit-memory behaviour of the FieldFlow form without changing the certified submission schema or the planner work completed in 9BC.

## Canonical behaviour

- A new visit resolves history from the latest **operational submission before the current report day** for the same store and campaign. A form revision or replacement inside that campaign must not erase store memory.
- Draft, void, cancelled, deleted and future/same-day submissions never replace the previous-visit source.
- Compatible normal questions expose the previous answer as informational visit memory. Media/file questions and `order_suggestion` are excluded.
- `product_matrix` does not use the generic memory popup. It consumes the previous operational matrix directly so the former `after` value becomes the new `before`, and the new `after` starts from the same value.
- Fronts rows are normalized before persistence in both Web and Mobile. `qty` always reflects the effective current `after` value while `before` and `after` are retained when the visit-pair fields exist.
- The durable submission remains the source of truth. Store-memory projection runs inline after COMMIT in the Web pipeline and legacy path. The scheduled history worker remains a fallback if projection fails.
- Product identities and Cardex metadata remain unchanged. No database migration is introduced.

## Certification continuity

`SOURCE_RELEASE` is `2.2.573` and `STABLE_RELEASE` is `2.2.574`.

The 9BC predecessor markers remain present for validation by the installed 2.2.573 Release Manager. `LATEST_SCHEMA_VERSION` remains `2.2.529` and `SubmissionPipeline::BUILD` remains `2.2.485`.
