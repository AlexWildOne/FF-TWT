# Phase 9CJ - Security & Hygiene Authority

Contract: `fieldflow-security-hygiene-authority-v1`

`SOURCE_RELEASE` is `2.2.606` and `STABLE_RELEASE` is `2.2.607`.

## Purpose

Close read-side and operational hygiene gaps found in the 2.2.605 audit, without changing operational behaviour for authorised users.

### 1. Context form scope authority (`fieldflow-context-form-scope-authority-v1`)

`routespro_context_form` renders the store form with the previous-visit prefill of that store. Until 2.2.606 any authenticated user could request it for any `route_id` / `stop_id` / `location_id`, because the context was resolved from the database without a permission check. Submission was already protected by `SubmissionContext`.

From 2.2.607 the read path applies the same authority as submission:

- a resolved route must pass `Permissions::can_access_route()` (owner, active assignment, manager or client/project scope);
- the resolved client/project must pass `Permissions::assert_scope_or_error()`.

Denied requests return HTTP 403 with `fieldflow_context_form_forbidden`. Authorised operatives, managers and quick-report contexts are unaffected.

### 2. Build tooling web isolation (`fieldflow-build-tooling-web-isolation-v1`)

`tools/`, `frontend/` and every `azure-*` folder now ship an `.htaccess` that denies web access and directory listing (Apache 2.4, Apache 2.2 and LiteSpeed). These folders exist for certification and deployment only. On Nginx the equivalent rule must be added to the server block:

```
location ~* /wp-content/plugins/fieldflow/(tools|frontend|azure-[^/]+)/ { deny all; }
```

### 3. Site timezone date authority

Default dates that used PHP `date('Y-m-d')` (UTC) now use `current_time('Y-m-d')`, so between 00:00 and 01:00 in Portugal (summer time) the default day is today and not yesterday. Covered: My Route, Descobrir, Client Portal, analytics and stats defaults, BO routes, planner week defaults and submission filters.

### 4. AI model currency (`fieldflow-ai-model-currency-v1`)

- The default Gemini model changes from `gemini-1.5-flash` (retired by Google in 2025) to `gemini-2.5-flash-lite`.
- Retired Gemini identifiers stored in settings (1.0, 1.5 and 2.0 families) are resolved at runtime to a current model, so an old configuration stops failing with 404. Any other configured model is used exactly as written.
- The Gemini API key travels in the `x-goog-api-key` header and never in the request URL, keeping it out of proxy and server logs.
- The AI Control Center shows the effective model.

## Non-regression

- No database schema changes. `SubmissionPipeline::BUILD` and `LATEST_SCHEMA_VERSION` are unchanged.
- No FlutterFlow source modification is required.
- 9CI visit closure selector return, 9CH expenses, AI governance (budgets, quotas, fallback) and Google cost authority are preserved.

## Certification

Validate and publish in Release Manager, install with the private updater, then run `Revalidar cadeia 6B`, `Testar fecho Fase 6` and `Certificar Stable`.
