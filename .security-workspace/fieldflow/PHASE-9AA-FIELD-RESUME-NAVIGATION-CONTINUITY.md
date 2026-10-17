# Phase 9AA — Field Resume Navigation Continuity

Contract: `fieldflow-field-resume-navigation-continuity-v1`

Release target: 2.2.543

This phase restores the exact operational navigation context when the native WebView is suspended or recreated by camera, gallery, Maps, screen lock or another external app.

## Contract

- The operational portal persists a durable, user-scoped resume checkpoint in localStorage while keeping sessionStorage as the fast path.
- The checkpoint contains the active panel plus the last reporting visit context and route view, with a bounded 12-hour TTL.
- Explicit URL/query navigation remains authoritative over a stored checkpoint.
- Before camera/gallery handoff the active dynamic form flushes its draft immediately, including the current wizard step, and emits `fieldflow:form-external-handoff`.
- After a WebView reload the shell restores the `report` panel from the durable checkpoint and re-selects the stored route/stop/location.
- FormRenderer remains authoritative for answers, photos and wizard-step restoration through the existing durable draft/IndexedDB flow.
- Native/browser resume signals reconcile the checkpoint without navigating upward to the default `route` panel.
- A real logout clears the operational resume checkpoint, the legacy last-report context and the session active-panel marker.
- No FlutterFlow change is required for this release. FlutterFlow investigation is only necessary if the native shell destroys origin storage itself.
- No database migration is introduced.
- `LATEST_SCHEMA_VERSION` remains `2.2.529`.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- `NATIVE_SHELL_VERSION` remains `1.10`.
- `SOURCE_RELEASE` is `2.2.542` and `STABLE_RELEASE` is `2.2.543`.

## Terrain acceptance

1. Open a visit and advance to an intermediate form step.
2. Tap **Tirar fotografia**, capture an image and return to FieldFlow.
3. The app must return to the same reporting visit and the same wizard step with existing answers intact.
4. Repeat with gallery and an external app such as Maps.
5. Repeat after a WebView reload/recreation.
6. Perform a real logout and confirm that no previous reporting context is restored after the next login.

## Certification

Run:

- `php tools/verify-field-resume-navigation-9aa.php`
- `php tools/test-field-resume-navigation-9aa.php`

Then follow the mandatory release flow:

Release Manager → Validate → Publish → private updater → revalidate 6B → Phase 6 closure test → terrain camera/resume tests → certify Stable.
