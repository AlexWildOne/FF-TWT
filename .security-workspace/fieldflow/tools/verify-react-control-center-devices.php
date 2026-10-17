<?php
/** Build-time verifier for FieldFlow 2.2.530 React Control Center Devices. */
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
$devices = $read('src/Admin/Devices.php');
$manager = $read('src/Mobile/DeviceManager.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$islands = $read('frontend/src/react-backoffice/islands.ts');
$types = $read('frontend/src/react-backoffice/types.ts');
$shared = $read('frontend/src/react-backoffice/shared.ts');
$tests = $read('frontend/tests/react-backoffice.test.mjs');
$build = $read('frontend/tools/build.mjs');
$asset = $read('assets/fieldflow-react-backoffice-2.2.530.js');
$css = $read('assets/fieldflow-react-backoffice.css');
$package = $read('frontend/package.json');
$validator = $read('src/Support/ReleasePackageValidator.php');
$managerUi = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-8B-REACT-CONTROL-CENTER-DEVICES.md');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');

$assert($containsAll($foundation, ["DEVICES_PAGE = 'routespro-devices'", "DEVICES_ISLAND = 'device-control-table'", 'in_array($page, [self::PILOT_PAGE, self::DEVICES_PAGE]', 'FIELDFLOW_REACT_BACKOFFICE']), 'react_devices_scope');
$assert($containsAll($devices, ['data-fieldflow-react-island="device-control-table"', 'DeviceManager::adminListPayload($listing)', "ReactBackofficeFoundation::islandEnabled('device-control-table',self::SLUG)", 'ffdev_platform', '<table class="widefat striped">']), 'devices_php_fallback_and_mount');
$assert($containsAll($manager, ["wp_ajax_fieldflow_react_devices_list", "wp_ajax_fieldflow_react_device_action", 'ajaxListDevices', 'ajaxDeviceAction', 'performAdminAction', "check_ajax_referer('fieldflow_react_devices','nonce')", 'adminListPayload', "['platform'=>\$platform]"]), 'device_manager_ajax_and_single_action_logic');
$assert($containsAll($manager, ["AuditLog::record('device.'.\$action", "EventBus::emit('device.'.\$action"]), 'device_actions_remain_audited');
$assert($containsAll($responsive, ['fieldflow-react-backoffice-2.2.530.js', 'ReactBackofficeFoundation::pageEnabled()', 'ReactBackofficeFoundation::DEVICES_ISLAND']), 'react_devices_enqueue');
$assert($containsAll($types, ['interface FieldFlowReactDeviceRow', 'interface FieldFlowReactDeviceListing', 'interface FieldFlowDeviceControlProps']), 'react_devices_types');
$assert($containsAll($islands, ['FIELD_FLOW_DEVICE_CONTROL_ISLAND', 'FieldFlowDeviceControlIsland', 'fieldflow_react_devices_list', 'fieldflow_react_device_action', 'fieldFlowReactPost', 'device-control-table'])
    && $containsAll($shared, ["credentials: 'same-origin'", 'fieldFlowReactPost']), 'react_devices_island_source');
$assert($containsAll($tests, ['React device control island is compiled with async list/action contracts', 'device-control-table', 'fieldflow_react_devices_list', 'fieldflow_react_device_action']), 'react_devices_behavioural_test');
$assert($containsAll($build, ['device-control-table', 'fieldflow_react_devices_list', 'fieldflow_react_device_action']), 'react_devices_build_gate');
$assert($containsAll($asset, ['FieldFlow 2.2.530', 'device-control-table', 'fieldflow_react_devices_list', 'fieldflow_react_device_action', 'wordpress-wp-element']), 'react_devices_compiled_asset');
$assert($containsAll($css, ['ff-react-device-control', 'ff-react-device-toolbar', 'ff-react-device-pagination', 'ff-react-device-actions']), 'react_devices_styles');
$assert(strpos($package, '"react"') === false && strpos($package, '"react-dom"') === false, 'no_bundled_react_dependency');
$assert($containsAll($validator, ['react_devices_control_ok', 'O React Control Center Devices não está integralmente ligado à release.']), 'release_package_gate');
$assert($containsAll($managerUi, ['React Devices', "packageResult['react_devices_control_ok']"]), 'release_manager_kpi');
$assert($containsAll($doc, ['fieldflow-react-control-center-devices-v1', 'routespro-devices', 'PHP fallback', 'No database migration', 'SubmissionPipeline component build remains `2.2.485`']), 'release_documentation');
$assert($containsAll($phase6, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');

$failed = array_keys(array_filter($checks, static fn($status) => $status !== 'PASS'));
foreach ($checks as $name => $status) echo $status . ' ' . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, 'REACT CONTROL CENTER DEVICES CONTRACT FAILED' . PHP_EOL . implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}
echo "REACT CONTROL CENTER DEVICES CONTRACT OK\n";
echo "contract=fieldflow-react-control-center-devices-v1\n";
echo "page=routespro-devices\n";
echo "island=device-control-table\n";
echo "transport=authenticated-admin-ajax\n";
echo "php_fallback=preserved\n";
