<?php
/** Build-time verifier for FieldFlow 2.2.477 Offline Runtime Hardening. */
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
    foreach ($needles as $needle) {
        if (strpos($haystack, $needle) === false) return false;
    }
    return true;
};

$fieldflow = $read('fieldflow.php');
$readme = $read('readme.txt');
$runtime = $read('assets/fieldflow-offline-runtime-2.2.530.js');
$runtimeCanonical = $read('assets/fieldflow-offline-runtime.js');
$responsive = $read('src/Front/ResponsiveAssets.php');
$offline = $read('src/Mobile/OfflineSyncService.php');
$formRenderer = $read('src/Forms/FormRenderer.php');
$shortcodes = $read('src/Shortcodes.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$gate = $read('src/Support/ReleaseGate.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$doc = $read('PHASE-7ZQ-OFFLINE-RUNTIME-HARDENING.md');

$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'checkOfflineRuntimeHardeningContract', 'fieldflow-offline-runtime-hardening-v1']), 'release_gate_contract');
$assert($containsAll($runtime, [
    "const RUNTIME_BUILD='2.2.530'",
    'if(previousApi.__runtimeBuild===RUNTIME_BUILD) return;',
    'SYNC_LOCK_TTL=Math.max(120000',
    'SYNC_LOCK_HEARTBEAT',
    'syncLeaseActive',
    'syncLeaseToken',
    'navigator.locks',
    'startSyncLockHeartbeat',
    'core.syncLock.owns',
    'function isUsableConnectivity(state){',
    "return ['online','degraded'].includes",
    'core.connectivity.isUsable',
    'operationalDate()',
    'addOperationalDays',
    'canSync:()=>isUsableConnectivity(connectivityState)',
    '__runtimeBuild:RUNTIME_BUILD',
]), 'runtime_hardening_markers');
$assert(strpos($runtime, "__runtimeBuild:'2.2.472'") === false && strpos($runtime, "version:'2.2.472'") === false, 'stale_runtime_identity_absent');
$assert(strpos($runtime, 'toISOString().slice(0,10)') === false, 'utc_operational_day_absent');
$assert(strpos($runtime, "if(connectivity.state!=='online')") === false && strpos($runtime, "if(probe.state!=='online')") === false, 'degraded_sync_hard_block_absent');
$assert($runtime !== '' && hash_equals(hash('sha256', $runtime), hash('sha256', $runtimeCanonical)), 'canonical_runtime_matches_versioned_runtime');
$assert($containsAll($responsive, [
    'fieldflow-offline-runtime-2.2.530.js',
    "'syncLockTtlMs' => 120000",
    "'syncLockHeartbeatMs' => 10000",
    "'operationalDate' => current_time('Y-m-d')",
    "'siteTimezone' => function_exists('wp_timezone_string')",
]), 'responsive_runtime_config');
$assert($containsAll($offline, [
    "'operational_date' => current_time('Y-m-d')",
    "'server_local_time' => current_time('mysql')",
    "'renewable_sync_lease' => true",
    "'degraded_network_sync' => true",
    "'server_operational_date' => true",
]), 'offline_server_contract');
$assert($containsAll($formRenderer, [
    'routesproPrepareFinalSubmit=function()',
    'return checkpointAllDraftFiles();',
    'otherwise a restored pre-optimisation Blob could be merged as',
]), 'final_media_checkpoint_before_outbox');
$assert(substr_count($shortcodes, "['online','degraded'].includes(String(connectivity.state||''))") >= 4 && strpos($shortcodes, "if(connectivity.state!=='online')") === false, 'route_actions_degraded_network_sync');
$assert($containsAll($validator, [
    'offline_runtime_identity_ok',
    'offline_runtime_reference_ok',
    'O runtime offline versionado não declara a mesma identidade da release.',
    'ResponsiveAssets não referencia o runtime offline versionado da release.',
]), 'release_manager_runtime_identity_gate');
$assert($containsAll($doc, [
    'fieldflow-offline-runtime-hardening-v1',
    'Release Manager',
    'No database migration',
]), 'release_documentation_contract');

$failed = array_keys(array_filter($checks, static fn($status) => $status !== 'PASS'));
foreach ($checks as $name => $status) echo $status . ' ' . $name . PHP_EOL;
echo $failed ? ('FAILED ' . implode(',', $failed) . PHP_EOL) : ('PASS fieldflow-offline-runtime-hardening-v1' . PHP_EOL);
exit($failed ? 1 : 0);
