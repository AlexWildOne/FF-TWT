## 2.2.659 — release candidate, NOT CERTIFIED

Package metadata follows `FIELDFLOW_VERSION` in `fieldflow.php`. The final
build, generated identity, canonical assets and manifest must be regenerated
together only after all source changes are complete. Metadata updates alone
do not certify the runtime; never reuse a predecessor's PASS result.

Use the staging, full backup/isolated restore, hash comparison, privacy and
Release Manager N+1 procedure in `../readme.txt`. The expected predecessor is
2.2.658, not a claim about the user's actual installed or certified baseline.
Do not publish directly to live; a normal rollback restores compatible code,
not database, uploads, private media or offline queues.

## 2.2.504 Route Endpoint & Distance Integrity

Frontend bundles are rebuilt with the 2.2.504 runtime identity. Route and mobility integrity remain server-owned: financial totals are only available for complete start-to-end routes, while partial kilometres stay diagnostic. No React ownership, form, offline or submission contract changes are introduced in this release.

## 2.2.503 Mobility Integrity & Canonical Metrics

Frontend bundles are rebuilt with the 2.2.503 runtime identity. Business ownership remains in the certified native/PHP services; the release itself changes canonical route-metric and mobility integrity on the server side.

## 2.2.502 Mobility Cost Intelligence

`fieldflow-mobility-cost-intelligence-v1` is a backend/operational intelligence layer over canonical route metrics. It does not move routing, P1-P4, form, offline or submission ownership into React. Front surfaces consume the resolved mobility payload already produced by WordPress/REST. Campaign defaults, operator profiles and route snapshots use existing metadata structures, and exports expose the same route-level economic values.

## 2.2.499 Certification Runtime Identity Hotfix

Contract `fieldflow-certification-runtime-identity-v1` keeps plugin, versioned frontend assets, offline runtime identity and Release Gate expectations aligned. No frontend business logic changes. IndexedDB, offline queue semantics, media transport and form ownership remain unchanged.

## 2.2.498 React Form Completion

Contract `fieldflow-react-form-completion-v1` completes the progressive UX layer around advanced form fields. React presents category navigation, contextual progress and media/order/share-space state while original FieldFlow inputs, ProductCardex, typed validation, IndexedDB/recovery, media transport and submission remain canonical. Product Matrix blanks follow the existing zero-fronts rule and are labelled `Zero automático`, not incomplete. Categorized Evidence is only visually complete when selected and backed by active media.

## 2.2.497 Briefing Progress Tabs Hardening

Contract `fieldflow-briefing-tabstrip-hardening-v1` is presentation-only. The internal Briefing tab strip explicitly preserves intrinsic button width inside the full-width operational shell, remains a single horizontally scrollable row on mobile/WebView and keeps vertical page scrolling available. Briefing data, mapping, acknowledgement, questions and all certified runtimes remain native/canonical.

## 2.2.496 Operational Action Hardening

Contract `fieldflow-react-operational-actions-hardening-v1` tightens the shared operational proxy without moving business ownership into React. Implementações quick-state chips keep stable horizontal sizing on mobile/WebView, combined search + status filtering reconciles section visibility/counts, and visible React inputs no longer double-dispatch the same edit. Native module controls/runtimes remain canonical and `wp-element` remains the only React runtime.

# FieldFlow Frontend Foundation

This directory is the source-of-truth for the typed FieldFlow browser foundation introduced in 2.2.476 and progressively extended across offline, drafts/media and form-state decisions.

## 2.2.495 React Operational Modules

Contract `fieldflow-react-operational-modules-v1` adds a single typed React island to Briefings, Implementações, Stocks, Shopping, Planear, Despesas and Mensagens. The React layer mirrors or proxies existing native controls and emits the same browser events, so the established module runtimes remain canonical. Native controls are hidden only while the island is mounted. No IndexedDB, Service Worker, submission, routing, mail or media persistence contract changes in this release. React continues to come exclusively from WordPress `wp-element`.

## Commands

- `npm run build` reads the canonical plugin version from `fieldflow.php`, generates `src/version.generated.ts`, type-checks the TypeScript sources and writes the versioned browser asset to `assets/`.
- `npm run typecheck` performs strict TypeScript validation of core, React front and React backoffice without emitting files.
- `npm test` runs dependency-free Node behavioural tests against the compiled browser bundle.
- `npm run verify` runs the complete frontend build gate.

The WordPress runtime never requires Node.js or TypeScript. Production only serves the compiled JavaScript asset from `assets/`.

React front and backoffice are progressive islands using WordPress `wp-element`, not a bundled React runtime. Native controls remain authoritative for values, events, validation and submission. Roots are registered before rendering, reused during mutations and unmounted when their DOM hosts disappear; effect cleanup restores native fallbacks. The locale selector is a non-modal disclosure with initial option focus, Escape/selection/close focus restoration and normal Tab navigation, not a modal focus trap. Device list requests cancel superseded work and ignore stale results; notices expose status/alert semantics.

Node tests use a dispatcher-enforcing `wp-element` contract harness where real React is unavailable. Real WordPress/browser mounting, assistive-technology behavior and responsive layouts still require integration validation; code and contract tests do not certify those environments.

## Typed Draft & Media Core

`src/drafts/draft-core.ts` owns the typed draft-state decisions and the IndexedDB persistence adapter for the existing `fieldflow-form-drafts` database (schema v2). The legacy inline implementation remains as a runtime fallback during the progressive migration. No destructive IndexedDB migration is performed.

## Typed Form State + Conditional Engine

`src/forms/form-core.ts` owns the strict TypeScript browser contract for conditional visibility, active required questions, active-value filtering and conditional snapshots. Shared fixtures are executed against both the compiled browser core and the PHP `ConditionalFlow` implementation so a release cannot silently diverge between frontend and backend semantics. The existing JavaScript calculations remain as fallback during the progressive rollout.

## Typed Submission & Validation Core

`frontend/src/forms/submission-core.ts` mirrors generic active/required/persistence semantics with PHP `SubmissionValidation`. Specialised media, Evidence and Cardex checks remain in their existing services.

## 2.2.482 Typed Specialized Validation Core

`src/forms/specialized-validation-core.ts` mirrors structured validation rules for Evidence, media, categorized evidence, Share Space and required product matrices. WordPress consumes the compiled browser API through `FieldFlowFrontend.specializedValidationCore`, while PHP mirrors the same contract in `RoutesPro\Forms\SpecializedValidation`.
## 2.2.485 Submission Events & Side Effects

No frontend business logic changes. The TypeScript bundle is rebuilt with the canonical 2.2.485 identity while backend post-commit effects move to isolated, idempotent lanes through `SubmissionSideEffectDispatcher`.

## 2.2.484 Submission Application Service

No frontend business logic changes. The compiled TypeScript bundle is rebuilt with the canonical 2.2.484 identity while the backend submission orchestration is centralized in `SubmissionApplicationService`.

## 2.2.483 Certification Continuity Hotfix

No frontend business logic changes. The frontend bundle is rebuilt with the canonical 2.2.483 identity so Release Manager, Phase 6 Stable and the compiled TypeScript foundation remain version-aligned while the platform certification gate is corrected.
## React Backoffice Foundation, 2.2.486

The first React layer is intentionally separate from the field frontend core. `frontend/tools/build.mjs` compiles `tsconfig.react-backoffice.json` into `assets/fieldflow-react-backoffice-<version>.js`. The runtime uses WordPress `wp-element`, so FieldFlow does not bundle React or ReactDOM. The initial `routespro-mobile` / `mobile-release-status` island is read-only and progressively replaces an equivalent PHP fallback card.



## 2.2.488 shared React BO and Analytics

`src/react-backoffice/shared.ts` owns the reusable `fieldflow-react-backoffice-shared-v1` helpers used by progressive admin islands. The Analytics tab adds `analytics-context-kpis` under `fieldflow-react-analytics-foundation-v1`: React owns universal filter state and Decision Score presentation, while `assets/fieldflow-analytics2-admin.js` remains the existing Analytics 2.0 controller/data engine through `window.FieldFlowAnalytics2`. The original PHP filter/KPI markup is hidden only after React mounts and remains the fallback. No React/ReactDOM production dependency is added.

## 2.2.489 React Front and form wizard progress

`tsconfig.react-front.json` compiles a separate operational-front bundle, `assets/fieldflow-react-front-<version>.js`, using WordPress `wp-element`. The first island, `form-wizard-progress`, mirrors the canonical FormRenderer wizard state and hides the PHP progress fallback only after a successful mount. It does not own answers, conditions, validation, media, drafts, offline queues, navigation or submission. This is the first safe step toward a broader progressive React migration of the user-facing FieldFlow experience.


## 2.2.492 React Front Operational Wave

The React Front build now includes operational shell, Minha Rota toolbar and simple-field assist islands. All remain progressive islands over the existing PHP/TypeScript runtimes, and dynamic lazy-loaded content is remounted automatically.

## 2.2.493 Modernization Stable Closure

Phase 8H closes the progressive React modernization. `fieldflow-react-media-control-v1` adds the final media capture/status surface by proxying the existing native camera/gallery/file controls. IndexedDB, offline recovery, media transport, ProductCardex, validation, submission and post-COMMIT effects remain canonical. PHP/native fallbacks are intentionally retained as resilience contracts.

## 2.2.494 Front Full Width & Navigation Consolidation

The React operational shell now mirrors every authorised canonical PHP tab instead of truncating the shortcut set. Navigation proxy clicks target the canonical tab button first, while the PHP tabbar remains the fallback and panel-state owner. Priority operational surfaces receive shared full-width mobile markers and continuous document scrolling. No submission, offline, media transport, ProductCardex or route-optimisation ownership changes.

## 2.2.501 Front Transition Stability

`fieldflow-front-transition-stability-v1` keeps progressive React fallbacks invisible during the normal mount window, restores them only on mount failure, delays lazy skeleton visuals for fast responses and keeps `fieldflow:lazy-panel-loaded` single-source. Canonical route, offline, media, form and submission runtimes remain unchanged.
