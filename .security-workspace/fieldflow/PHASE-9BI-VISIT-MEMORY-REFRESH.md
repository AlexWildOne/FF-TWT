# FieldFlow 2.2.580 — Visit memory refresh

Contract: `fieldflow-visit-memory-refresh-v1`.
Predecessor: 2.2.579. Schema remains 2.2.529 and submission build 2.2.485.

Two regressions reproduced on the preceding source are addressed:
- The global memory popup reused the first response for the same store even when its contents changed, and survived a new form without memory. The response now contains a revision derived from the selected submission and filtered notices. Changed revisions replace the old popup; repeated identical responses retain it. Switching or clearing the store removes the previous popup and its timer/listener.
- A stale `record_version_id` could fall back to values owned by another visit. Migrated answers now resolve the latest version through the selected `submission_id`; durable answers retain priority.

Media, files and order suggestions remain excluded from new-visit memory. Editing keeps the current report's own evidence and order. Shared web/native desktop pages, quick reporting and mobile payloads use the corrected services. No data migration or historic report deletion is performed.

Regression scripts exercise the production popup code, SQL/version ownership and PHP form rendering. Their failures before the fixes and passing results afterwards are recorded in the local validation report. The server login previously displayed a WordPress critical error; its PHP trace is still required to diagnose that separately observed symptom. Local validation does not certify production or establish the cause of the login error.
