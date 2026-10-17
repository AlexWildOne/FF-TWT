# FieldFlow Phase 7A - Responsive Email Intelligence

Release: 2.2.432
Source release: 2.2.431

## Objective

Make the Intelligence reporting layer safe and readable on narrow email clients while preserving the same analytical envelope used by Email, Excel, PDF and CSV.

## Scope

- Scheduled Client and Merchandiser report emails.
- Route completed and route exception Intelligence digests.
- Decision score cards.
- Priority metrics.
- Store-level alerts.
- Client/project scope cards.

## Rendering contract

- Email content must never require horizontal scrolling on a narrow viewport.
- Score grids use at most two columns and stack to one column on narrow clients when media queries are supported.
- Tables that remain tabular use fixed layout and explicit width bounds.
- Long labels, store names and values must wrap instead of expanding the message width.
- The HTML includes a mobile viewport declaration and text-size adjustment guards.
- No JavaScript, external CSS dependency or browser-only layout primitive is required.

## Intelligence contract

This release changes presentation only. It does not change Decision Score formulas, store-alert selection, briefing relevance, Widget Intelligence ranking or export completeness. Client and Merchandiser scoping remains unchanged.

## Certification

2.2.432 must follow the canonical WordPress Release Manager path and then complete the Azure Phase 6 gate: Revalidate 6B, Test Phase 6 closure, Certify Stable. The build is not considered closed before the exact installed 2.2.432 runtime is certified.
