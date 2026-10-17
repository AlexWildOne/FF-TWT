# Phase 9CH - Expense Settlement, Payment & Signature Authority

Contract: `fieldflow-expense-settlement-signature-authority-v1`

`SOURCE_RELEASE` is `2.2.604` and `STABLE_RELEASE` is `2.2.605`.

## Purpose

Close the FieldFlow expense lifecycle without duplicating operational expense data:

1. expenses are submitted by the operative;
2. BO reviews them as Pending / Approved / Rejected;
3. BO marks an operative + explicit period as `Paga / Reembolsada`, individually or in bulk;
4. payment metadata records who closed the payment and when;
5. the operative can either upload an already signed statement or draw a handwritten signature directly inside the Expenses tab;
6. direct signature produces a signed-ready PDF from the canonical paid expense snapshot;
7. signed evidence is centrally visible and downloadable from BO and included in the expense ZIP export.

## Payment authority

`paid` is a distinct canonical expense status. Approval never implies payment.

The BO bulk action is deliberately guarded by both:
- one selected operative; and
- an explicit month or De/Ate period.

Rejected and already paid rows are not changed by the bulk action. Payment transitions are audit-stamped in each expense `meta_json` with actor, timestamp, source and bounded transition history. No schema migration is required.

## Signed settlement authority

Signed evidence is represented by a private FieldFlow settlement record using a non-public WordPress post type. This is intentionally low-volume monthly business evidence and avoids adding release-time DDL to the certified expense table.

Every settlement stores:
- operative, client and project;
- normalized period/filter authority;
- exact paid expense IDs and amount snapshot;
- snapshot SHA-256;
- evidence method;
- evidence private path/mime/name/size;
- submitter and timestamp;
- statement document ID.

Private evidence bytes live below `fieldflow-private-expense-settlements/` with random filenames and are only streamed through permission-checked FieldFlow endpoints.

## Two signature paths

### Upload signed file

The operative may upload a signed PDF, JPG or PNG. The upload is bound to the current paid period snapshot.

### Sign directly in FieldFlow

A touch/mouse signature canvas is available in the Expenses tab. The operative confirms the receipt declaration, signs, and submits. The server stores the signature privately and generates the canonical statement PDF with the captured signature and registered timestamp embedded in the receipt block.

This is a captured handwritten acknowledgement inside FieldFlow. It is not represented as a qualified cryptographic signature.

## BO centralization

BO > Despesas shows:
- paid state and payment audit information;
- bulk payment closure for the selected operative + period;
- signed evidence list for the active filters;
- View and Export actions for each signed statement.

The existing expense ZIP export also includes signed settlement evidence under `folhas-assinadas/`.

## Mobile / Flutter

The workflow runs inside the existing WordPress/WebView runtime. No FlutterFlow source modification is required.

The existing 9CG system-browser ticket authority for ordinary statement PDF downloads is preserved unchanged.

## Continuity

9CH preserves 9CF statement layout/filter authority, 9CG mobile PDF delivery, 9CE analytics date export authority, 9BZ visit-memory isolation, 9CC browser-like translation and 9CD locale dock continuity.
