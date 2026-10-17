# Phase 9CG - Expense Statement Mobile Ticket Delivery

Contract: `fieldflow-expense-statement-mobile-ticket-delivery-v1`

`SOURCE_RELEASE` is `2.2.603` and `STABLE_RELEASE` is `2.2.604`.

## Problem confirmed in the app

The expense statement PDF was generated through an authenticated `admin-post.php` URL. Inside the WebView that URL had a valid WordPress session and nonce, but the generic Flutter downloader could perform the real transfer outside that authenticated WebView session. The visible symptom matched the historical Evidence ZIP transport failure: `Download iniciado` followed by `Não foi possível exportar o ficheiro`.

## Delivery authority

- The authenticated WebView session prepares the PDF first through `wp_ajax_fieldflow_prepare_expense_statement_ticket`.
- The canonical 9CF filters and client/project scope are preserved exactly while the authenticated session still exists.
- The prepared PDF is stored temporarily and exposed through a 256-bit opaque, short-lived cookie-free capability URL.
- The public transport accepts GET and HEAD probes, remains reusable during the TTL, and never requires WordPress cookies or a nonce.
- The WebView hands the capability URL to `fieldflowOpenExternal` with `systemBrowser=true`, bypassing the generic Flutter downloader.
- A browser/blob fallback remains available for shells where the external-open bridge is unavailable.
- Dedicated `fieldflow.expense.statement.*` events prevent the native shell from misclassifying the statement as a generic Analytics export.

## Preserved behavior

The 9CF document remains unchanged: existing expenses are the only data source, explicit De/Até has precedence over month, TWT/client branding is preserved, daily totals remain canonical, and the receipt declaration/signature block is unchanged.

No database schema changes. No FlutterFlow source modification is required.
