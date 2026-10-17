# FieldFlow Phase 7W, Shopping Mail & Merchandising Flow

Release: 2.2.455
Source release: 2.2.454
Release Gate: 1.9.0
Shopping schema: 1.1.0

## Objective

Make Shopping price verification operationally reliable in two places that directly affect field execution: recipient control after submission and the physical order in which a merchandiser checks products in store.

## Recipient contract

Each Shopping batch has an explicit recipient configuration. The allowed first-party recipients are the primary email stored on the FieldFlow client plus WordPress users returned by the existing client association resolver. Administrators may also add sanitized external email addresses. User IDs submitted through the admin UI are revalidated against the current client association before persistence or resend, preventing cross-client recipient selection.

New imports may preselect associated client users through the global Shopping email template. Existing batches are not silently expanded to new recipients during upgrade, so no legacy batch starts emailing additional people without an administrator reviewing its recipient configuration.

## Delivery and resend contract

Shopping mail is sent once per resolved recipient. Each attempt is written to the existing Mail Center with recipient identity, recipient source, submission ID, assignment ID, original merchandiser ID, resend flag and any synchronous `wp_mail_failed` detail. The aggregate Shopping submission state is `sent`, `partial`, `failed` or `no_recipients`.

A manual resend operates on the original saved Shopping submission. It does not rewrite prices, notes, store assignment or submission time. The administrator chooses a fresh recipient set for that resend, and the attempt counter plus last accepted send time are updated.

`wp_mail` acceptance is not treated as proof of inbox delivery. The UI deliberately says `accepted for sending` and directs operational diagnosis to Mail Center/SMTP when required. Provider-level delivered/bounced confirmation remains outside this release because it requires a provider webhook contract.

## Merchandising flow contract

The Excel importer already preserves each product's original `source_row`. The Shopping front now uses that order by default in `Percurso do linear`, so Pernod and competitor products remain interleaved in the same physical sequence in which the source file describes the shelf. This prevents the previous grouped-by-type behaviour from forcing the merchandiser to traverse the same section repeatedly.

`Agrupar por tipo` remains available as an explicit alternate view. Switching views first captures current input values, so no entered price is lost. Product steps are numbered, type-labelled and responsive for mobile execution.

## Database migration

Shopping remains self-managed and upgrades through `ShoppingService::ensureSchema()` plus WordPress `dbDelta`. Schema 1.1.0 adds:

- `routespro_shopping_batches.recipient_user_ids`
- `routespro_shopping_submissions.notification_attempts`
- `routespro_shopping_submissions.notification_last_sent_at`

The global FieldFlow schema baseline is not advanced, so the runtime-only WordPress upgrade path remains intact.

## Certification contract

Release Gate 1.9.0 validates the Shopping service, results layer, admin resend UI, front imported-order view, Mail Center integration, schema option and required database columns. Phase 6 Stable advances from 2.2.454 to 2.2.455.

The package must still be installed with the canonical `fieldflow/fieldflow.php` root, verified against `production-manifest.json`, published through WordPress Release Manager, present in the private Stable catalog, then revalidated and certified through the existing Azure-backed Phase 6/6B chain.

## Safety invariants

Production Enabled remains OFF. Full Operational Payload remains OFF. Licensing governance remains SHADOW. Runtime license blocking, forced auto-updates, remote runtime revocation and automatic downgrade remain OFF. Offline Zero-Loss, Resumable Media Transport, media integrity and Offline Trust are unchanged by this Shopping release.
