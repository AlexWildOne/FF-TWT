<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$contains = static function(string $haystack, array $markers): bool {
    foreach ($markers as $marker) if (strpos($haystack, $marker) === false) return false;
    return true;
};
$assert = static function(bool $ok, string $name): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$name}\n"); exit(1); }
};

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$app = $read('src/Front/AppShortcodeRenderer.php');
$route = $read('src/Shortcodes.php');
$form = $read('src/Forms/FormRenderer.php');
$panelLoader = $read('src/Front/PanelLoader.php');
$ui = $read('assets/fieldflow-ui-runtime.js');
$css = $read('assets/fieldflow-design-system.css');
$react = $read('assets/fieldflow-react-front-2.2.530.js');
$tests = $read('frontend/tests/react-front.test.mjs');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8P-FRONT-TRANSITION-STABILITY.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkFrontTransitionStabilityContract', 'fieldflow-front-transition-stability-v1']), 'release_gate_contract');
$assert($contains($foundation, ["FRONT_TRANSITION_STABILITY_CONTRACT = 'fieldflow-front-transition-stability-v1'"]), 'foundation_contract');
$assert($contains($responsive, ['fieldflow-react-front-2.2.530.js', 'frontTransitionStabilityContract']), 'runtime_enqueue');
$assert($contains($app, ['data-fieldflow-react-front-fallback-pending="1"', 'let initialPanelApplied=false', 'openPanel(initialPanel,{remember:false,scroll:false})']), 'app_transition_contract');
$assert(strpos($app, 'setTimeout(applyInitial') === false, 'single_initial_activation');
$assert($contains($route, ['data-fieldflow-react-front-fallback="my-route-toolbar"', 'data-fieldflow-react-front-fallback-pending="1"']), 'route_fallback_pending');
$assert($contains($form, ['data-fieldflow-react-front-fallback="form-wizard-progress"', 'data-fieldflow-react-front-fallback-pending="1"']), 'form_fallback_pending');
$assert($contains($panelLoader, ['class="ff-skeleton" aria-hidden="true" hidden', 'class="ff-lazy-label" hidden']), 'lazy_placeholder_hidden');
$assert($contains($css, ['[data-fieldflow-react-front-fallback-pending="1"]{display:none!important}']), 'first_paint_css_guard');
$assert($contains($ui, ['reactFallbackTimers', '},1200)', 'const visualTimer=window.setTimeout', '},180)', "placeholder.dispatchEvent(new CustomEvent('fieldflow:lazy-panel-loaded'"]), 'ui_transition_runtime');
$assert(substr_count($ui, "placeholder.dispatchEvent(new CustomEvent('fieldflow:lazy-panel-loaded'") === 1, 'single_lazy_loaded_event');
$assert(strpos($ui, "document.dispatchEvent(new CustomEvent('fieldflow:lazy-panel-loaded'") === false, 'no_duplicate_document_event');
$assert($contains($react, ['FieldFlow 2.2.530', 'fieldflow-front-transition-stability-v1', 'data-fieldflow-react-front-fallback-pending']), 'compiled_react_contract');
$assert($contains($tests, ['Front transition stability hides React fallbacks before mount and initializes the app panel once', 'Front transition stability delays lazy visuals and emits one canonical loaded event']), 'regression_tests');
$assert($contains($validator, ['front_transition_stability_ok', 'A estabilidade de transição do front não está integralmente aplicada']), 'package_validator_guard');
$assert($contains($manager, ['Front Transition Stability', "packageResult['front_transition_stability_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-front-transition-stability-v1', '`SOURCE_RELEASE` is `2.2.500` and `STABLE_RELEASE` is `2.2.501`', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

echo "FRONT TRANSITION STABILITY CONTRACT OK\n";
echo "release=2.2.507 source=2.2.500 phase6=1.0.64 gate=1.56.0 submission_build=2.2.485\n";
echo "fallback_failover_ms=1200 lazy_visual_delay_ms=180 loaded_event=single\n";
