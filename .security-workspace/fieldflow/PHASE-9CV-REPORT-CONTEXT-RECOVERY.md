# FieldFlow 9CV: Report Context Database Outage Recovery

Contract: `fieldflow-report-context-recovery-9cv-v1`

Successor: `2.2.618 -> 2.2.619`. Candidate only, not certified until the normal Release Manager process completes.

## Scope

1. **No leaking infrastructure data.** Errors from `routespro_context_form`, routes REST and report focus are classified before presenting to the operative. Plain text, HTML and JSON WordPress database failures yield a safe Portuguese message. The transport still reports failure, not a fake completed visit.
2. **Manual retry.** Accessible `Tentar novamente` button on Reportar. One explicit retry at a time, minimum 4-second throttle and no polling or auto retry against a congested database.
3. **Restore draft and isolate visit.** Existing same-visit form survives a failed reload, including its draft; old shop form is removed when the operative selects another shop to prevent accidental cross-store submission. If a different day fails to load, no previous-day form survives. A previously loaded store list is only kept when the date is unchanged.
4. **Offline scope.** In Reportar and Quick Report, cached rendered form is keyed by frontend version, WordPress user ID, client, project, route, stop, location and date. On fallback the server-provided context is also compared with the selected visit. Old unscoped cache is deliberately not reused. Cache availability depends on a successful load on this release, and does not promise a form on first offline entry.
5. **Server response.** If the authenticated WordPress AJAX handler begins and catches a throwable, it logs a sanitized operational context server-side and returns a structured 503 without exposing exception details. WordPress bootstrap failure is outside the plugin and still requires the host to restore MySQL.
6. No schema migration, paid AI call, Google Maps change or native Flutter update. The 9CU camera review, 9CT deletion prompts and 9CS budget gate are preserved.

## Verification

- `node tools/test-report-context-recovery-9cv.mjs`
- `php tools/verify-report-context-recovery-9cv.php`
- `node tools/test-fronts-capture-review-9cu.mjs` and `node tools/test-form-delete-confirm-9ct.mjs`
- `npm run verify` from `frontend/`, all PHP lint, package validator and production-manifest integrity.
- Android and iOS: open a shop with server available, fill without submitting, interrupt DB/network, switch shops, retry, reopen same visit, validate no cross-store form or data loss; then restore DB and submit once. Exercise a new installation before first cache is available.

## Known limitation

**The plugin cannot repair MySQL downtime.** If WordPress itself returns `Error establishing a database connection`, Hostinger must investigate connection availability, limits, resource saturation and log timestamps. Client recovery cannot make a missing database available.
