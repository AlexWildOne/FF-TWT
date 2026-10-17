# Phase 7M - Mail Center 2.0

Release: 2.2.445

## Objective
Unify FieldFlow operational email, scheduled reporting and test workflows into one governed Mail Center.

## Daily close
A `daily_close` automation is triggered only when `fieldflow_form_visit_completed` confirms `route_completed_now`. The merchandiser can receive the personal daily scope immediately. Client delivery is configurable as immediate per merchandiser close or consolidated at the configured daily time.

## Periods
- Day
- Week to date
- Rolling seven days
- Campaign cumulative

All ranges can be anchored to the actual route close date or a Test Center simulation date.

## Idempotency
Route-close dispatches use a persistent WordPress option claim keyed by schedule, audience, route, user and date. Failed sends release the claim so a retry is possible.

## Legacy completion deduplication
When a Daily Close automation is active for the exact client, project and route date, the legacy route-completed notification is skipped. Mail Center becomes the canonical completion communication and prevents double delivery.

## Reporting
Email-only delivery is valid. XLSX, PDF and CSV are optional and use the same Report Intelligence payload as the email.

## Certification
SOURCE_RELEASE 2.2.444. STABLE_RELEASE 2.2.445. Azure Phase 6 certification remains mandatory after private publication and runtime update.
