# FieldFlow 2.2.545, Resume Certification & Media Integrity 9AC

Contract: `fieldflow-resume-certification-media-integrity-v1`

This successor release closes the certification drift found after 2.2.544 without restoring bare `focus` resume behaviour.

- `SOURCE_RELEASE` is `2.2.544` and `STABLE_RELEASE` is `2.2.545`.
- Historical 9S resume validation is capability-based and accepts the guarded successor implementation `focus-after-hidden` only when a real background/resume arm was consumed.
- Bare `focus` restore remains prohibited by 9AB and 9AC.
- ReleasePackageValidator and installed ReleaseGate validate the same successor-safe resume capability.
- Inert 2.2.544 N to N+1 compatibility markers remain only where the already-installed validator requires them; 9AC verifies that no executable bare-focus restore exists.
- Explicitly clearing the report store clears the durable previous report context and resume checkpoint visit.
- Media checkpoints include the operational report day so camera resume cannot silently change a historical visit to today.
- Camera/gallery originals are persisted in an isolated IndexedDB recovery namespace before image optimisation.
- The optimised transport copy is committed once. The original remains available as a local fallback and is not exposed as a fake form field.
- Offline recovery prefers the active optimised group and falls back to the original only when the active group is missing.
- IndexedDB schema remains compatible. No database migration is introduced.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- `NATIVE_SHELL_VERSION` remains `1.10`.

Required closure flow remains Release Manager Validate, Publish, private updater install, 6B revalidation, Phase 6 closure tests, functional terrain tests, then Stable certification.
