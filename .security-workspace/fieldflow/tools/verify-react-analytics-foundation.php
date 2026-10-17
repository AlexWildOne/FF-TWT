<?php
/** Build-time verifier for FieldFlow 2.2.530 React Analytics Foundation. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$containsAll = static function(string $content, array $markers): bool {
    foreach ($markers as $marker) if (strpos($content, $marker) === false) return false;
    return true;
};
$ok = true;
$assert = static function(bool $condition, string $label) use (&$ok): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) $ok = false;
};
$foundation = $read('src/Admin/ReactBackofficeFoundation.php');
$assignments = $read('src/Admin/Assignments.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$analytics = $read('assets/fieldflow-analytics2-admin.js');
$reactSource = $read('frontend/src/react-backoffice/islands.ts');
$sharedSource = $read('frontend/src/react-backoffice/shared.ts');
$types = $read('frontend/src/react-backoffice/types.ts');
$tests = $read('frontend/tests/react-backoffice.test.mjs');
$asset = $read('assets/fieldflow-react-backoffice-2.2.530.js');
$css = $read('assets/fieldflow-react-backoffice.css');
$validator = $read('src/Support/ReleasePackageValidator.php');
$gate = $read('src/Support/ReleaseGate.php');
$manager = $read('src/Admin/ReleaseManager.php');
$package = $read('frontend/package.json');
$doc = $read('PHASE-8C-RELEASE-CONTINUITY-REACT-ANALYTICS.md');

$assert($containsAll($foundation, ["SHARED_CONTRACT = 'fieldflow-react-backoffice-shared-v1'", "ANALYTICS_PAGE = 'routespro-assignments-hub'", "ANALYTICS_ISLAND = 'analytics-context-kpis'", "BackofficeUIFoundation::scope(\$page) === 'analytics'"]), 'react_foundation_scope');
$assert($containsAll($assignments, ['data-fieldflow-react-island="analytics-context-kpis"', 'ff-a2-legacy-control-fallback', 'ReactBackofficeFoundation::SHARED_CONTRACT', 'Altera o contexto sem recarregar a página']), 'analytics_php_mount_and_fallback');
$assert($containsAll($responsive, ['fieldflow-react-backoffice-2.2.530.js', 'ReactBackofficeFoundation::ANALYTICS_ISLAND', "BackofficeUIFoundation::scope() === 'analytics'"]), 'analytics_asset_enqueue');
$assert($containsAll($sharedSource, ['fieldflow-react-backoffice-shared-v1', 'fieldFlowReactPost', 'fieldFlowReactTodayRange', "credentials: 'same-origin'"]), 'shared_react_bo_core');
$assert($containsAll($types, ['interface FieldFlowAnalyticsControlProps', 'interface FieldFlowAnalytics2Api', 'useEffect']), 'typed_analytics_contract');
$assert($containsAll($reactSource, ['fieldflow-react-analytics-foundation-v1', 'analytics-context-kpis', 'FieldFlowAnalyticsControlIsland', 'fieldflow:analytics2-data', 'fieldflow:analytics2-state', 'FieldFlowAnalytics2.applyContext']), 'react_analytics_source');
$assert($containsAll($analytics, ['window.FieldFlowAnalytics2', 'applyContext', "emit('fieldflow:analytics2-data'", "emit('fieldflow:analytics2-state'", 'window.history.replaceState', 'loadGeneration']), 'analytics_controller_no_refresh');
$assert($containsAll($css, ['ff-react-analytics-control', 'ff-a2-react-fallback.is-react-hidden', 'ff-react-analytics-score-grid']), 'analytics_react_css');
$assert($containsAll($tests, ['React shared backoffice foundation centralizes transport and date helpers', 'React Analytics context and KPI island is compiled with no-refresh controller contract']), 'react_tests');
$assert($containsAll($asset, ['FieldFlow 2.2.530', 'fieldflow-react-backoffice-shared-v1', 'fieldflow-react-analytics-foundation-v1', 'analytics-context-kpis', 'FieldFlowAnalytics2']), 'compiled_react_analytics_asset');
$assert($containsAll($validator, ['react_backoffice_shared_ok', 'react_analytics_foundation_ok']), 'release_package_validator');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'checkReactBackofficeSharedContract', 'checkReactAnalyticsFoundationContract']), 'release_gate');
$assert($containsAll($manager, ['React BO Shared', 'React Analytics', "packageResult['react_analytics_foundation_ok']"]), 'release_manager_kpis');
$assert(strpos($package, '"react"') === false && strpos($package, '"react-dom"') === false, 'no_bundled_react');
$assert($containsAll($doc, ['fieldflow-react-analytics-foundation-v1', 'Analytics 2.0', 'PHP fallback', 'No REST contract change']), 'phase_documentation');

if (!$ok) exit(1);
echo 'REACT ANALYTICS FOUNDATION CONTRACT OK' . PHP_EOL;
echo 'no_refresh_context=enabled' . PHP_EOL;
echo 'php_fallback=preserved' . PHP_EOL;
echo 'analytics_engine=existing_analytics_2_0' . PHP_EOL;
