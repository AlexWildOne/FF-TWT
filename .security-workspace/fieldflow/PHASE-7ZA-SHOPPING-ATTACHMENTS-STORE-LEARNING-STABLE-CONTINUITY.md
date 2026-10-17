# Phase 7ZA, Shopping Attachments, Store Learning & Stable Continuity

Release: 2.2.459
Shopping schema: 1.3.0
Release Gate: 1.13.0
Phase 6 source: 2.2.458
Phase 6 stable: 2.2.459

## Scope

This release remains deliberately limited to Shopping delivery, store-to-insignia discovery and Phase 6 release continuity.

## Shopping attachments

Each Shopping submission can generate one XLSX and one PDF for the exact submission/store. The files are generated once per mail operation, reused for every selected recipient, logged by filename/size, and deleted from temporary storage after the send operation. Batch and default-template controls independently enable Excel and PDF. Manual resend uses the current batch attachment configuration.

## Store learning

Shopping remains scoped to client, project and insignia and remains available to the complete operational team associated with the project. A persistent mapping table records a project store's canonical Shopping insignia. Historical Shopping submissions are backfilled automatically. Category metadata and mapped stock history are also used as evidence. Stores with an explicit conflicting brand are never offered as fallback candidates. Unclassified project stores may be explicitly associated by an operational user in front, after confirmation, and the association is then reused by the whole project team.

## Stable continuity

The Phase 6 Stable contract advances from source 2.2.458 to stable 2.2.459. Exact runtime equality, valid production manifest, canonical WordPress Release Manager publication with SHA-256, private Stable catalog, installation identity, activation binding, governance SHADOW, Production OFF, runtime blocking OFF, forced auto-updates OFF and downgrade protection remain mandatory.
