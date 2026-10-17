<?php
/** Build-time verifier for FieldFlow 2.2.530 React Backoffice Foundation. */
$root = dirname(__DIR__);
$checks = [];
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $condition, string $name) use (&$checks): void {
    $checks[$name] = $condition ? 'PASS' : 'FAIL';
};
$containsAll = static function(string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};

$foundation = $read('src/Admin/ReactBackofficeFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$mobile = $read('src/Admin/MobileControlCenter.php');
$loader = $read('src/Support/Loader.php');
$build = $read('frontend/tools/build.mjs');
$tsconfig = $read('frontend/tsconfig.react-backoffice.json');
$types = $read('frontend/src/react-backoffice/types.ts');
$islands = $read('frontend/src/react-backoffice/islands.ts');
$bootstrap = $read('frontend/src/react-backoffice/bootstrap.ts');
$tests = $read('frontend/tests/react-backoffice.test.mjs');
$package = $read('frontend/package.json');
$asset = $read('assets/fieldflow-react-backoffice-2.2.530.js');
$css = $read('assets/fieldflow-react-backoffice.css');
$doc = $read('PHASE-8A-REACT-BACKOFFICE-FOUNDATION.md');

$assert($containsAll($foundation, ["CONTRACT = 'fieldflow-react-backoffice-foundation-v1'", "PILOT_PAGE = 'routespro-mobile'", "PILOT_ISLAND = 'mobile-release-status'", 'FIELDFLOW_REACT_BACKOFFICE', "'php_fallback' => true", "'wordpress_react' => 'wp-element'"]), 'foundation_contract_and_kill_switch');
$assert($containsAll($responsive, ['fieldflow-react-backoffice-2.2.530.js', "[self::FRONTEND_CORE_SCRIPT_HANDLE, 'wp-element']", 'fieldflow-react-backoffice.css', 'ReactBackofficeFoundation::pageEnabled()']), 'wordpress_wp_element_enqueue');
$assert($containsAll($mobile, ['publication_status_island', 'data-fieldflow-react-island="mobile-release-status"', 'data-fieldflow-react-props', "ReactBackofficeFoundation::islandEnabled('mobile-release-status')"]), 'mobile_control_center_pilot_island');
$assert($containsAll($loader, ['src/Admin/ReactBackofficeFoundation.php']), 'loader_registration');
$assert($containsAll($build, ['tsconfig.react-backoffice.json', 'fieldflow-react-backoffice-${version}.js', 'fieldflow-react-backoffice-foundation-v1', 'wordpress-wp-element']), 'react_build_pipeline');
$assert($containsAll($tsconfig, ['"strict": true', 'src/react-backoffice/types.ts', 'src/react-backoffice/islands.ts', 'src/react-backoffice/bootstrap.ts']), 'react_strict_typescript');
$assert($containsAll($types, ['interface FieldFlowWpElementRuntime', 'interface FieldFlowReactBackofficeApi']), 'react_runtime_types');
$assert($containsAll($islands, ['FIELD_FLOW_REACT_BACKOFFICE_CONTRACT', 'FieldFlowMobileReleaseStatusIsland', 'fieldFlowReactMountAll', 'mobile-release-status']), 'react_island_source');
$assert($containsAll($bootstrap, ['FieldFlowReactBackoffice', "source: 'wordpress-wp-element'", 'fieldFlowReactMountAll']), 'react_bootstrap_api');
$assert($containsAll($tests, ['React backoffice bundle uses WordPress wp-element', 'React mobile release island mounts progressively']), 'react_behavioural_tests');
$assert($containsAll($asset, ['FieldFlow 2.2.530', 'fieldflow-react-backoffice-foundation-v1', 'mobile-release-status', 'wordpress-wp-element']), 'react_compiled_asset_identity');
$assert($containsAll($css, ['ff-react-mobile-release-status', 'ff-react-platform-tab', 'ff-react-release-panel']), 'react_pilot_styles');
$assert(strpos($package, '"react"') === false && strpos($package, '"react-dom"') === false, 'no_bundled_react_dependency');
$assert($containsAll($doc, ['fieldflow-react-backoffice-foundation-v1', 'wp-element', 'PHP fallback', 'routespro-mobile', 'No database migration']), 'release_documentation_contract');

$failed = array_keys(array_filter($checks, static fn($status) => $status !== 'PASS'));
foreach ($checks as $name => $status) echo $status . ' ' . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, 'REACT BACKOFFICE FOUNDATION CONTRACT FAILED' . PHP_EOL . implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}
echo "REACT BACKOFFICE FOUNDATION CONTRACT OK\n";
echo "contract=fieldflow-react-backoffice-foundation-v1\n";
echo "runtime=wordpress-wp-element\n";
echo "pilot_page=routespro-mobile\n";
echo "pilot_island=mobile-release-status\n";
echo "php_fallback=preserved\n";
