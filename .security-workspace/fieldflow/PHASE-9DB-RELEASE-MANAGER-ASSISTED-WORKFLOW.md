# FieldFlow 2.2.636 | Release Manager Assisted Workflow 9DB

Contract: `fieldflow-release-manager-assisted-workflow-9db-v1`
Continuity: 2.2.635 -> 2.2.636

## Change scope

- The certified 2.2.635 package is the single development baseline. No portal, route, form, photo, expense, AI, licensing or dock changes.
- Add staged private ZIP storage via `ReleaseStagingWorkflow::stage`, with SHA-256, exact version and complete `ReleasePackageValidator` verification **before** saving it.
- Store the staged ZIP for 24 hours in an owner-only OS temporary directory **outside the WordPress web tree**. Reject staging if a safe private temporary directory cannot be established; retain manual Validate/Publish forms.
- Bind a 48-hex random token to a logged-in administrator, current blog, nonce and `routespro_manage` capability. Stage one ZIP per administrator. Use a nonblocking file lock around remote writes to prevent concurrent duplicate submissions.
- Publication from staging performs **another** complete package validation and N+1 preflight before any Azure request. User explicitly confirms channel and publication.
- Before uploading, inspect the existing release catalogue. A failed/ambiguous inspect cannot authorise an upload. Once a remote attempt starts, journal its state *before* the HTTP request.
- Treat ambiguous upload/catalog responses as **unknown**, not as evidence of failure. Never retry the same external write automatically. Reconcile via remote inspect and exact SHA-256, and permit safe catalog-only resume solely after an acknowledged immutable Blob when catalog has never been attempted.
- Validate the remote release SHA-256 on reconciliation before writing a durable `ReleasePublicationReceipt` and audit log. Existing receipt with contradictory channel/hash blocks completion.
- Keep 30 days of metadata on interrupted attempts after the 24-hour ZIP expiry for **read-only remote reconciliation**; never reuse expired ZIP bytes for publishing. History is bounded and omits access keys, HMAC signatures and package bytes.
- Existing direct manual upload publication, publisher HMAC, private delivery, 6B, Release Gate, Fase 6 and human Stable certification are preserved. No schema migrations, outbound AI or frontend changes.

## Operational sequence

1. **Pré-validar e guardar ZIP privado**, upload once from Backoffice Release Manager.
2. Inspect file digest, N+1 and scope with available preflight diagnostics. Publish requires explicit checkbox, channel and metadata.
3. Staging backend checks the file again, verifies publisher health, examines existing catalogue version and SHA.
4. Mark upload uncertainty before sending ZIP; persist remote Blob evidence only after verified acknowledgement.
5. Mark catalogue uncertainty before its request. After a positive response, inspect remotely and confirm exact SHA before writing local receipt.
6. Installer continues in normal WordPress private updates. Human manually approves 6B, Phase 6 and Stable.
7. On connection failures use **Verificar no catálogo** (read only). After a confirmed Blob and before any catalogue POST, **Retomar etapa do catálogo** is allowed with a new explicit approval.

## Guardrails and known limits

- OS temp must be writable and outside public hosting directories, otherwise this feature refuses staging, without affecting the legacy release process.
- The initial HTTP upload itself cannot resume if interrupted before the server stages it. A new upload is required.
- Requests with an uncertain remote effect cannot be reissued by the assistant. The operator may consult Azure and manually resolve an irrecoverable case.
- Network access, WordPress ZipArchive, actual WordPress admin UI, Azure secrets, multisite concurrency and platform updates require real-host verification. Local PHP unit tests cannot certify them.
- WordPress staging uses standard PHP upload size and request timeout limits.
- The root plugin directory remains `fieldflow/`; predecessor 2.2.635 can always be restored through the existing manual recovery process.

## Real-host acceptance

- On 2.2.635 Stable, validate/upload 2.2.636 via the old Release Manager and confirm SHA and manifest.
- After installation, upload a staged ZIP and refresh the admin page. It remains listed without another file selection; check permissions, ownership, SHA and private temp retention.
- Use a deliberately wrong or stale ZIP: blocked by the same gates. Verify no Azure POST occurred.
- Publish a test release with explicit consent; confirm remote inspect, one Blob upload, one catalog request and one audit receipt.
- Simulate catalogue timeouts and remote eventual consistency on a safe staging environment; verify no duplicate POST and manual reconciliation.
- Revalidate 6B, local Phase 6 and Stable manually after operational smoke tests and restore plan are approved.
