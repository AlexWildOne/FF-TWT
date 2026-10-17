# Phase 8I, Front Full Width & Navigation Consolidation

Release: **2.2.494**

Contract: `fieldflow-front-fullwidth-navigation-v1`

## Scope

This release consolidates the mobile operational shell and presentation layer for Briefings, Implementations, Stocks, Shopping, Planear, Despesas and Mensagens. It also exposes the complete authorised panel set in the progressive React operational navigation.

The PHP tabbar remains the canonical panel authority and fallback. React proxies navigation to the existing canonical buttons and does not create a second routing model.

## Full width mobile contract

The seven priority surfaces receive a shared `ff-operational-surface` presentation marker and module-specific markers. On phone widths, the priority panels use almost the complete viewport width, reduce nested card padding and preserve horizontal overflow only for content that genuinely requires it, such as wide tables and chip navigation.

Nested vertical scrollers are disabled on mobile for Briefings execution lists, Messages lists, planner store lists, Shopping lists and Stocks lists so document scrolling remains continuous.

Tap targets in these operational surfaces keep a minimum touch height and the React shortcut navigation remains horizontally scrollable.

## React navigation contract

The React operational shell mirrors the complete canonical tab order allowed by the user's entitlements:

- Minha Rota
- Briefing
- Implementações
- Stocks
- Shopping
- Descobrir or Planear
- Reportar
- Academy
- Despesas
- Mensagens
- Analytics

The previous five-item truncation is removed. Activation targets `button[data-panel]` first, preventing a passive `.rp-panel[data-panel]` element from intercepting the proxy click. `aria-current` exposes the active shortcut without changing the legacy navigation state machine.

## Ownership and safety

This phase is presentation and navigation consolidation only.

- No IndexedDB schema change.
- No Service Worker ownership change.
- No offline recovery rewrite.
- No media transport rewrite.
- No submission persistence change.
- No `SubmissionPipeline BUILD` bump.
- No ProductCardex business-rule change.
- No Suggestion mail eligibility change.
- No P1-P4 routing or optimisation-rule change.
- No FlutterFlow change.
- No REST payload contract change.

Canonical ownership remains:

`React UX -> canonical PHP/native controls -> Typed Core -> Offline Runtime -> SubmissionApplicationService -> COMMIT -> SubmissionSideEffectDispatcher`

## Release continuity

`SOURCE_RELEASE` is `2.2.493` and `STABLE_RELEASE` is `2.2.494`. The package must be accepted by the ReleasePackageValidator shipped in the certified 2.2.493 installation before private updater installation.

The normal release flow remains mandatory: Release Manager validation and publication, private updater installation, 6B revalidation, Phase 6 closure test, then Stable certification.
