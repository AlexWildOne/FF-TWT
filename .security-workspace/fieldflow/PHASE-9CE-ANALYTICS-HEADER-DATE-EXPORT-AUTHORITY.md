# Phase 9CE, Analytics Header Date Export Authority

Contract: `fieldflow-analytics-header-date-export-authority-v1`

`SOURCE_RELEASE` is `2.2.601` and `STABLE_RELEASE` is `2.2.602`.

## Scope

Analytics exports must use one temporal authority across Client and Operative surfaces.

Precedence is deterministic:

1. The current visible `De` and `Até` header values are authoritative whenever they contain dates.
2. Period preset buttons remain supported. They work by updating those same canonical header fields, after which the export uses the resulting dates.
3. Only when no explicit valid dates are supplied may the existing default range be used.

Header dates override stale export URLs. Export links are refreshed immediately on `input` and `change`, not only after the asynchronous Analytics reload completes. The mobile WebView also reads the live header fields at capture time before requesting a download ticket, so the native bridge cannot export an earlier month because an anchor still contains its initial URL.

The server validates ISO dates and normalizes inverted ranges before preparing the shared Analytics dataset. CSV, full Excel, mobile Excel and PDF therefore share the exact same `from` / `to` interval.

Preset buttons remain supported and no existing shortcut is removed.

No database schema changes.

No FlutterFlow source modification is required.

## Historical continuity

9CE preserves 9BV export runtime parity, 9BZ visit memory, 9CC browser-like translation and 9CD adaptive locale dock behavior. Routing, geocoding, mobility costs, periodicity and report visit semantics remain unchanged.
