# FieldFlow 2.2.599, Locale Brand & Associated Client Scope Continuity 9CB

Contract: `fieldflow-locale-brand-scope-continuity-v1`

## Goal

Keep the premium language globe visually native to the active client brand and keep language availability synchronized with the client that is actually active in the Client Portal or native WebView app.

## Runtime authority

- The locale selector remains a React + TypeScript island using WordPress `wp-element`.
- The selector reads `--ff-primary`, `--ff-accent`, surface, text, border and button variables from the active FieldFlow portal and projects them into its own fixed body-level island.
- Hover, focus, active, language badge and premium menu use the same active client/campaign brand authority.
- The locale runtime listens to the canonical `routespro:scope-change` event.
- When the active client changes, the runtime calls the authenticated `fieldflow/v1/language?client_id=...` endpoint and replaces the client-specific locale policy without a page reload.
- The React root remains mounted even when a particular client has language switching disabled or has fewer than two languages. This lets the globe appear immediately when the user changes to another associated client where switching is allowed.
- The legacy fallback receives the same brand variables when React is temporarily unavailable.

## Scope and security

The endpoint keeps `Permissions::is_allowed_client()` as the authorization boundary. A client outside the signed-in user's FieldFlow scope cannot be selected through the locale runtime.

The locale runtime publishes `scopeClientIds` only as UI context. It does not grant access and never replaces server-side permission checks.

## Preserved contracts

- 9BD Form Visit Memory & Fronts Continuity.
- 9BZ Premium Locale & Visit Memory Continuity.
- 9CA Certification Marker Continuity.
- Country, routing, geocoding, mobility and periodicity remain independent of interface locale.
- No database schema changes.
- No FlutterFlow source modification is required.

## Release continuity

`SOURCE_RELEASE` is `2.2.598` and `STABLE_RELEASE` is `2.2.599`.
