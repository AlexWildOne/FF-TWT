# FieldFlow 2.2.466 - Responsive Mail Rendering Hotfix

FieldFlow 2.2.466 fixes horizontal clipping observed on Gmail mobile and hardens all main HTML email surfaces against narrow viewports.

## Responsive contract

- `Emails::responsive_email_css()` is the shared email presentation contract.
- The outer viewport and message shell use `width="100%"` inline and as HTML attributes. `max-width` only limits desktop expansion.
- Fixed desktop widths are restricted to MSO conditional tables for Outlook desktop.
- No FieldFlow email requires horizontal scrolling to expose operational data.
- Arbitrary content inside the common email body is bounded by `.ff-email-content`; images and tables cannot exceed the message shell when CSS is honored.
- `Emails::harden_email_content_html()` normalizes fixed-width tables, nowrap cells and oversized images at render time, without changing the template stored in WordPress.

## Order suggestion

- Removes flexbox and `min-width` KPI cards from the message body.
- Uses a three-cell fixed-layout summary table.
- Replaces the four-column wide product table with one full-width product card per reference.
- Category, boxes and units are rendered inside a fixed-layout nested table that remains readable on narrow devices.
- Excel and PDF attachments, recipients, retry semantics and historical manual replay are unchanged.

## Shopping and other mail

- Shopping price tables use `ff-data-table`, `table-layout:fixed`, safe wrapping and no forced nowrap price cells.
- Route notifications, automatic Intelligence reports, test messages and manual historical replay inherit the same fluid shell.
- The Outlook newsletter path keeps MSO conditional markup while removing the fixed 640px HTML width attribute from non-Outlook clients.

## Certification continuity

- Release Gate 1.17.0 adds `fieldflow-responsive-email-rendering-v1`.
- `tools/verify-responsive-email-rendering.php` checks the fluid shell, data-table contract, order-card rendering and Shopping wrapping markers.
- SOURCE_RELEASE is 2.2.465 and STABLE_RELEASE is 2.2.466.
- No database migration is required.
