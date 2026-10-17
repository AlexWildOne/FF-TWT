# Phase 7X - Shopping Insignia, Store Selection, Resend & Certification Diagnostics

Release: 2.2.456
Source release: 2.2.455
Shopping schema: 1.2.0
Release Gate: 1.10.0

## Objective

Make Shopping operationally chain-first rather than fixed-store-first, while preserving exact store evidence, recipient auditability and the mandatory Stable certification path.

## Insignia execution model

- Excel Shopping import is normalised to one assignment per canonical retail insignia.
- The assignment no longer stores a mandatory physical `location_id`.
- The merchandiser opens the insignia and must select the physical store before the Shopping workspace is enabled.
- Candidate stores are limited to the client/project and matching retail family, then narrowed by the operative's allowed store scope.
- The selected `location_id` is persisted on every submission and `last_location_id` is only an operational convenience, never an implicit submission target.
- Draft storage keys include both assignment and physical store so values cannot bleed between PDVs.

## Legacy migration

Schema 1.2.0 backfills a canonical insignia and safely consolidates duplicate legacy assignments that resolve to the same batch + insignia. Historic submissions are re-pointed to the retained assignment while preserving their original `location_id`, timestamps, prices and audit evidence. When multiple old store-specific seeds disagree or become ambiguous, the generic seed is cleared instead of promoting one store as the chain baseline.

## Recipient and resend contract

- Batch configuration remains the source of truth for future and manual resend recipient choices.
- `Último envio` is historic evidence only.
- `Configuração atual` reflects the current client primary email, selected client users and external addresses.
- Opening `Reenviar email` starts from that current selection and permits a fresh explicit choice.
- Mail is attempted per recipient and every attempt remains independently visible in Mail Center.
- `Aceite para envio` means the WordPress mailer accepted the message, not guaranteed Inbox delivery.

## Certification diagnostics

Phase 6 failure notices enumerate the concrete failed conditions, such as missing current Stable catalog version, missing canonical Release Manager publication/SHA-256, 6B.2 identity revalidation, 6B.3 SHADOW invariants, 6B.4 distribution state, manifest integrity or a platform Release Gate failure.

The diagnostics are explanatory only. They do not weaken certification. Release 2.2.456 still requires canonical publication, matching private Stable catalog evidence, exact package SHA-256, the installed runtime manifest and revalidation of the Azure-backed 6B chain before `Certificar Stable` can close.

## Safety invariants

- Production Enabled remains OFF.
- Full Operational Payload remains OFF.
- Governance enforcement remains SHADOW.
- Runtime blocking remains OFF.
- Forced auto-updates remain OFF.
- Automatic downgrade remains OFF.
- Offline Zero-Loss and Resumable Media Transport contracts are unchanged.
