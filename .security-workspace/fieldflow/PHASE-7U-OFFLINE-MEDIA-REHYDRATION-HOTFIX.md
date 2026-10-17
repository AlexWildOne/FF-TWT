# FieldFlow Phase 7U - Offline Media Rehydration Hotfix

Release: 2.2.453
Source release: 2.2.452
Release Gate: 1.7.0

## Problem

Offline photographs were correctly persisted and restored into `input.files`, but browsers do not emit a `change` event when `input.files` is assigned programmatically. The upload preview therefore remained empty after reopening the form. Selecting one new photograph triggered the normal change handler, which rebuilt the preview and made both the recovered and new files visible.

## Fix

`FormRenderer` now performs an explicit media UI rehydration pass after local IndexedDB restoration, after server-draft restoration and once more after both asynchronous restore branches settle. The pass rebuilds upload previews, refreshes media metrics and resynchronizes evidence categories.

The fix deliberately does not dispatch `change`, so recovered files are not re-optimised, duplicated or unnecessarily written again. Durable Zero-Loss storage remains the source of truth.

## Certification

Release Gate 1.7.0 requires the rehydration helper, preview rebuild path and diagnostic event before Stable certification can close. The exact 2.2.453 ZIP must still be published by the WordPress Release Manager and certified through the Azure-backed 6B chain.

## Database

No database migration is required.
