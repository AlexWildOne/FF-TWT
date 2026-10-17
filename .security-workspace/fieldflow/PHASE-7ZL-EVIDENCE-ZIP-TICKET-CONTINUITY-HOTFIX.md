# Phase 7ZL - Evidence ZIP Ticket Continuity Hotfix

Release: 2.2.470

## Problem confirmed

The 2.2.469 runtime successfully separated ZIP preparation from download, but the final prepared URL still used `admin-post.php`, a WordPress session and a user nonce. Android and WebView download handlers can probe or retry the URL with HEAD/GET requests outside the exact browser request context. The first probe could therefore invalidate or consume the one-time transport and the following request surfaced as HTTP 410.

The field screenshot confirmed this path after the previous HTTP 500 had disappeared.

## Fix

- Prepared evidence ZIPs now receive a 256-bit opaque capability ticket.
- Final delivery uses a `template_redirect` endpoint on the same site and does not depend on WordPress cookies or a WordPress nonce.
- Tickets are short-lived and reusable during their TTL so native HEAD probes and GET retries cannot consume the file.
- HEAD returns only headers and never removes the ZIP.
- GET streams the already prepared ZIP in 1 MiB chunks.
- The prepared ZIP is not deleted after the first GET. Old export files are removed by the evidence export cleanup after the safe retention window.
- Evidence group tokens shown by Analytics now remain valid for up to four hours by default, avoiding 410 errors on an Analytics screen left open in the field.
- Legacy authenticated admin-post delivery remains registered for backward compatibility but is not used by new prepared URLs.

## Scope

No database migration. No Flutter change. No change to route logic, forms, Cardex, AI, Mail Center or evidence data.

## Certification

Release Gate 1.21.0 adds `fieldflow-evidence-zip-ticket-continuity-v1` and the build-time verifier `tools/verify-evidence-zip-ticket-continuity.php`.
