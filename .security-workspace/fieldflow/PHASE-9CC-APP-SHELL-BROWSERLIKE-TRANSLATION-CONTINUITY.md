# Phase 9CC, App Shell & Browser-like Translation Continuity

Contract: `fieldflow-app-shell-browserlike-translation-v1`

`SOURCE_RELEASE` is `2.2.599` and `STABLE_RELEASE` is `2.2.600`.

## Scope

This release closes two field defects in the multilingual runtime without changing FlutterFlow or the data model.

1. The premium locale selector must remain visible in both native-WebView surfaces, the Client Dashboard and the Operative Dashboard, whenever the active client's BO policy allows front/app language switching.
2. Translation must behave like a browser translator rather than a finite phrase table. Known phrases still resolve locally first, while unknown visible text is translated asynchronously and cached.

## React / WebView continuity

The 2.2.599 locale island called the hook-based component function before React rendered it. In a real WordPress/Flutter WebView React runtime this can raise an `Invalid hook call`. The legacy selector could already have been removed, leaving no language control.

2.2.600 mounts the component through `wp.element.createElement`, keeps the legacy selector alive until a real `.ff-language-switcher-react` is committed, and restores the fallback if React fails or is delayed.

Both portal roots explicitly declare `data-fieldflow-locale-host="1"` and their portal type. The locale runtime reconciles on portal creation, `routespro:scope-change`, `pageshow`, focus and visibility resume. This covers the Client Dashboard and Operative Dashboard inside the existing Flutter WebView.

## Browser-like translation

The front runtime translates text nodes, button/input labels, placeholders, titles, accessibility labels and dynamically inserted React/AJAX content.

Resolution order is:

1. Exact local FieldFlow translations and deterministic dynamic rules.
2. Native browser `Translator` API when the WebView/browser exposes it and user activation allows creation.
3. FieldFlow REST translation fallback through the configured AI provider, with 30-day exact-string caching.
4. Lightweight lexical fallback for common words when neither translator is available.

Outside Simplified Chinese, store names, postal addresses and product names are intentionally preserved. For `zh-CN`, proper/store names may also be translated, matching the requested full-translation policy. URLs, emails, identifiers, SKU/EAN tokens and pure numbers remain machine-safe.

Language remains a presentation concern only. It does not change country authority, geocoding, routing, mobility costs, periodicity or operational scope.

No database schema changes.

No FlutterFlow source modification is required.

## Historical continuity

The runtime keeps the certified visit-memory authorities 9BD, 9BZ and 9CA unchanged, as well as the 9CB client-brand/scope contract. The new 9CC gate validates the compiled 2.2.600 React front asset, both WebView shell markers, browser-like translation runtime and package parity before Stable certification.
