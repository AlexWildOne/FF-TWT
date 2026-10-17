# Phase 9B — Client Portal Continuity, Full Width & Messaging

Contract: `fieldflow-client-portal-continuity-v1`

This phase consolidates the Client Portal after the Phase 9A shell stabilization. It intentionally groups the remaining visible transition issue, mobile navigation continuity, full-width module containment and client-team messaging eligibility in one certified release to avoid a chain of micro-updates.

## Scope

- The Client Portal no longer runs the global scroll-height clamp after a tab switch. The operational portal keeps the legacy safety behaviour where it is still useful.
- The active client tab is kept visible by scrolling only the tab strip. The page viewport is never moved as part of this operation.
- The mobile tab strip owns horizontal overflow, hides native scrollbars, preserves safe-area spacing and never creates vertical overflow inside the fixed navigation bar.
- Client panels and their primary lazy modules use the full available shell width with `min-width:0`, preventing narrow inherited containers from clipping cards or tables.
- Client-team sender eligibility follows authenticated client/project scope through `Permissions::assert_scope_or_error()`. It no longer reuses the operational project roster, which can legitimately exclude the client account when a campaign defines its own merchandiser list.
- Sender/recipient AJAX errors are surfaced as their real server message instead of being collapsed into a false “not eligible” state.

## Safety boundaries

- No P1-P4 routing or optimisation-rule change.
- No mobility or route-distance calculation change.
- No database schema change.
- No form, Conditional Engine, IndexedDB, offline queue or media ownership change.
- No client/project permission bypass. Sender eligibility still requires an authenticated user, the client-team messaging permission gate and valid scope for the selected client/project.
- `SubmissionPipeline::BUILD` remains `2.2.485`.

## Release continuity

`SOURCE_RELEASE` is `2.2.515` and `STABLE_RELEASE` is `2.2.516`.
