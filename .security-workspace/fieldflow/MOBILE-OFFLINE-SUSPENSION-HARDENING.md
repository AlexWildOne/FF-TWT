# Mobile Offline Suspension Hardening

FieldFlow 2.2.365 treats the device-local draft as the first durability boundary for an in-progress field visit.

## Durability order

For normal answers:

`input/change -> localStorage checkpoint -> debounced server draft sync`

For photos:

`native picker/camera -> local snapshot + IndexedDB original-file write -> optimisation -> IndexedDB optimized-file replacement -> optional server draft upload`

For final offline submission:

`serialize full form -> IndexedDB offline queue put -> clear in-progress local draft`

The draft is never cleared before the offline queue write succeeds.

## Resume behavior

The existing mobile shell emits `fieldflow:app-resume`. The form runtime now responds by re-running draft recovery. Local and server drafts are compared using `updatedAt`; the newer copy remains authoritative. The draft snapshot already includes the wizard step, and the operational report portal already persists the selected visit in `fieldflow:last-report-visit`.

## Lifecycle events

FieldFlow continues to flush drafts on:

- `visibilitychange` to hidden
- `pagehide`
- `beforeunload`
- `freeze`

These lifecycle flushes are additional protection. Correctness no longer depends on receiving them because each answer change already has an immediate local checkpoint.
