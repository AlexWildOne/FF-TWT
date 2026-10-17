# 2.2.639 Operative Dock Bootstrap Recovery 9DE

Contract: `fieldflow-operative-dock-bootstrap-recovery-9de-v1`.

## Production evidence from 2.2.638

The user installed and certified 2.2.638 Stable, but the black Operative navigation remained fixed. The `ff_dock_diag=1` output reported:

- `jsExecuted: true`, `operativeRoot: true`, `tabbars: 1`, `dockFound: true`.
- `controller: ausente`, `owner: ausente`, `dockState: ausente`.
- `position: fixed`, `transform: none`, `opacity: 1`.
- Zero observed scroll and touchmove events in that snapshot. This does not establish which element scrolls because the only recorded touches originated on buttons.

The observation demonstrates that the prior 9DC owner was not mounted on the visible operative root. Potential causes in the old implementation: PHP delivery gated by the 9D9/9D5 flags; a one-shot `enhance()` return when the bar is not ready; or a dynamically replaced root that loses its owner.

## Controlled repair

- A new, Operative-only bootstrap is emitted directly inside the shortcode without depending on older `shared_dock_enabled`/9DC switches. It can be disabled with `FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE=false` to restore 9DC/9D9.
- It claims the *visible* `#routespro-app[data-fieldflow-portal="operative"] .rp-tabs` and exposes `window.FieldFlowOperativeDock9DE.snapshot()` plus `data-ffv-dock-controller-active="operative-9de"` on the root.
- A document-level MutationObserver reconnects the owner after delayed Elementor mounts or React root replacements. It does not rely on an internal scroll container being known at first render.
- It listens to real document/window scroll, vertical touch gestures (even when `scrollY=0`), wheel and touch pointer fallback. Horizontal gestures and editing controls are excluded.
- It applies inline important hidden style as a fallback for missing CSS delivery. The selector and CSS remain scoped to the Operative portal, with bottom padding and keyboard safety.
- Historical 9D6/9D7 footer enqueues are disabled while 9DE is active. Legacy 9D5 Client runtime/assets remain byte-identical. The 9DC script remains available as a rollback, but does not run concurrently with 9DE. Azure/Release Manager, data, forms, routes and API remain untouched.
- The optional 9DD panel now reports the explicit server delivery state, number of 9DE script tags, and live owner snapshot. No telemetry or personal data is transmitted.

## Validation required on an actual device

Install and certify only after local validation, then check My Route and Stocks with vertical gestures in Chrome and Android. If still fixed, open `ff_dock_diag=1`, press manual hide and copy the snapshot. Do not claim visual resolution without a witnessed device test.

## Rollback

`define('FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE', false);` restores the previous 9DC path while preserving plugin and database state. Full rollback remains the previously certified 2.2.638 ZIP.
