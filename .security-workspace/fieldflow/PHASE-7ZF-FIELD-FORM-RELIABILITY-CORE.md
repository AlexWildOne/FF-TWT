# Phase 7ZF - Field Form Reliability Core

FieldFlow 2.2.464 hardens the existing 2.2.463 zero-loss form foundation without replacing the installation, licensing, private update or Azure certification model.

## Multi-photo recovery
- Durable file groups are recovered by input name and ordinal, including dynamically cloned camera/gallery inputs that no longer exist after an app or WebView restart.
- The renderer recreates every missing ordinal slot before assigning IndexedDB files back to the form.
- Recovery reports partial counts such as 4/5 instead of presenting an incomplete restore as successful.
- Server draft publishing counts every `input_name|input_ordinal` group instead of assuming ordinal zero.
- Briefing image questions and native form image questions continue to use the same durable media path and final strict ACK contract.

## Operational terminal route
- `done` and `completed` remain executed visits.
- `failed` becomes operationally terminal only when a non-empty server-validated justification exists.
- A route is operationally complete when every stop is either executed or justified, while the execution KPI continues to count only real executed visits.
- `route.completed` is the canonical event for route closure.
- Daily close mail is triggered from canonical route completion and deferred until the request closes, so a final justified non-visit can close the route and still send the normal mail flow.

## Operational content revision
- Briefing publication and archive, weekly Cardex synchronization, form schema changes and form binding changes bump one project-level operational content revision.
- Offline bundle cache keys include the revision so question-only Briefing changes no longer depend on Cardex activity or a fixed cache timeout.
- Automatic Cardex-to-store links are reconciled, adding valid stores and removing obsolete project associations.

## Order suggestion
- Order suggestion is a first-class persisted reporting dataset.
- Analytics Excel includes a dedicated `Sugestões Encomenda` sheet, and CSV/PDF exports include the same normalized order rows.
- Every persisted suggestion can trigger a Mail Center `order_suggestion` event.
- The operative who authored the persisted submission receives the personal notification.
- Client recipients include client-associated users, active project responsibles and the client record email when configured.
- Excel and PDF attachments are mandatory for this automation type and are generated from the same normalized order dataset used by exports.
- Subject, introduction, brand colour, extra recipients, test flow, activation and mail logs remain managed by the existing Mail Center.
- Dispatch claims include submission identity, order-content fingerprint and recipient email to avoid duplicate sends during offline retries with unchanged data.

### Per-recipient delivery
- Every required operative/client address gets its own idempotent delivery claim.
- A partial SMTP failure releases only the failed recipient claim, preserving successful deliveries without duplicates.
- Failed recipients are retried through a controlled WordPress single-event queue, up to four retries after the initial send.
- Excel and PDF remain generated from the same persisted suggestion for every retry.

## Certification continuity
- Release Gate 1.15.0 adds the `fieldflow-form-reliability-core-v1` contract.
- A build-time verifier exercises the known five-photo ordinal fixture, justified-failure terminal semantics and source contracts for operational revision, exports and Mail Center triggers.
- The existing Release Manager, private Stable catalog, production manifest SHA-256, licensing heartbeat, governance checks and Azure-backed Phase 6 closure remain mandatory.
- SOURCE_RELEASE is 2.2.463 and STABLE_RELEASE is 2.2.464.

No database migration is required.
