<?php
/** Build-time verifier for FieldFlow 2.2.530 React Client Wave 1 & Analytics Stability. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$contains = static function(string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};
$failures = [];
$assert = static function(bool $ok, string $label) use (&$failures): void { if (!$ok) $failures[] = $label; };

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$client = $read('src/Front/ClientPortalShortcodeRenderer.php');
$ui = $read('assets/fieldflow-ui-runtime.js');
$premiumCss = $read('assets/fieldflow-premium-shell-v2.css');
$reactCss = $read('assets/fieldflow-react-front.css');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$moduleControls = $read('frontend/src/react-front/module-controls.ts');
$build = $read('frontend/tools/build.mjs');
$asset = $read('assets/fieldflow-react-front-2.2.530.js');
$submission = $read('src/Forms/SubmissionPipeline.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9C-REACT-CLIENT-WAVE1-ANALYTICS-STABILITY.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($client, [
    'fieldflow-client-panel-lifecycle-v1', 'fieldflow-react-client-wave1-v1', 'function setPanelLifecycle(key,status)',
    'clientAIScopeKey()', 'await Promise.all([', 'renderClientAI(data);', 'keepClientTabVisible(activeButton);',
    '9B compatibility marker: window.requestAnimationFrame(()=>keepClientTabVisible(activeButton))'
]), 'client_lifecycle_single_render');
$aiStart = strpos($client, 'async function loadClientAI(refresh=false)');
$aiEnd = $aiStart !== false ? strpos($client, 'function renderAnalytics(stats)', $aiStart) : false;
$aiFn = ($aiStart !== false && $aiEnd !== false) ? substr($client, $aiStart, $aiEnd - $aiStart) : '';
$assert($aiFn !== '' && substr_count($aiFn, 'renderClientAI(data);') === 1, 'analytics_ai_single_render');
$assert($contains($ui, [
    'Height-only growth from lazy data must not wake every chart', 'const portalPanelSize=new WeakMap();', 'if(Math.abs(width-previous.width)>3) shouldDispatch=true;'
]), 'client_resize_width_only');
$assert($contains($premiumCss, ['fieldflow-client-panel-lifecycle-v1', 'fieldflow-react-client-wave1-v1', 'rp-ai-stable-placeholder']), 'client_stable_layout_css');
$assert($contains($reactCss, ['Phase 9C, React Client Wave 1', 'data-client-wave1="fieldflow-react-client-wave1-v1"', 'data-client-wave1-status="loading"']), 'react_wave1_css');
$assert($contains($foundation, ["CLIENT_WAVE1_CONTRACT = 'fieldflow-react-client-wave1-v1'", "in_array(\$module, ['stocks','shopping','messages'], true)", 'client_wave1_contract']), 'react_wave1_php_contract');
$assert($contains($responsive, ['clientWave1Contract', 'ReactFrontFoundation::CLIENT_WAVE1_CONTRACT', 'fieldflow-react-front-2.2.530.js']), 'react_wave1_enqueue');
$assert($contains($moduleControls, [
    "FIELD_FLOW_REACT_CLIENT_WAVE1_CONTRACT = 'fieldflow-react-client-wave1-v1'", "['stocks', 'shopping', 'messages'].includes(props.module)",
    'fieldflow:client-panel-lifecycle', "'data-client-wave1'", "'data-client-wave1-status'", 'React Client Wave 1'
]), 'react_wave1_ts_ownership');
$assert($contains($build, ['reactClientWave1Contract', 'clientPanelLifecycleContract', 'data-client-wave1']), 'frontend_build_contract');
$assert($contains($asset, ['FieldFlow 2.2.530', 'fieldflow-react-client-wave1-v1', 'fieldflow-client-panel-lifecycle-v1', 'data-client-wave1']), 'compiled_react_wave1_asset');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert($contains($gate, ['checkReactClientWave1AnalyticsStabilityContract', 'React Client Wave 1 & Analytics Stability']), 'release_gate_guard');
$assert($contains($validator, ['react_client_wave1_analytics_stability_ok', 'O React Client Wave 1 ou a estabilidade do Analytics não estão integralmente ligados.']), 'package_validator_guard');
$assert($contains($manager, ['React Client Wave 1', "packageResult['react_client_wave1_analytics_stability_ok']"]), 'release_manager_visibility');
$assert($contains($doc, [
    'fieldflow-react-client-wave1-analytics-stability-v1', '`SOURCE_RELEASE` is `2.2.516` and `STABLE_RELEASE` is `2.2.517`',
    'No P1-P4 routing or optimisation-rule change.', 'No database schema change.', '`SubmissionPipeline::BUILD` remains `2.2.485`.'
]), 'phase_documentation');

if ($failures) {
    fwrite(STDERR, "REACT CLIENT WAVE 1 ANALYTICS STABILITY CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "REACT CLIENT WAVE 1 ANALYTICS STABILITY CONTRACT OK\n";
echo "release=2.2.530\n";
echo "contract=fieldflow-react-client-wave1-analytics-stability-v1\n";
echo "analytics_resize=width_only\n";
echo "analytics_ai_render=single_commit\n";
echo "react_client_wave1=stocks,shopping,messages\n";
echo "submission_build=2.2.485\n";
