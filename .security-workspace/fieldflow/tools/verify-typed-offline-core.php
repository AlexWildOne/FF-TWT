<?php
/** Build-time verifier for FieldFlow 2.2.530 Typed Offline Core. */
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

$fieldflow = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$connectivity = $read('frontend/src/offline/connectivity.ts');
$syncLock = $read('frontend/src/offline/sync-lock.ts');
$outbox = $read('frontend/src/offline/outbox.ts');
$core = $read('frontend/src/offline/offline-core.ts');
$bootstrap = $read('frontend/src/bootstrap.ts');
$tests = $read('frontend/tests/frontend-build.test.mjs');
$asset = $read('assets/fieldflow-frontend-core-2.2.530.js');
$runtime = $read('assets/fieldflow-offline-runtime-2.2.530.js');
$doc = $read('PHASE-7ZS-TYPED-OFFLINE-CORE.md');

$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'checkTypedOfflineCoreContract', 'fieldflow-typed-offline-core-v1']), 'release_gate_contract');
$assert($containsAll($connectivity, ['FieldFlowNetworkState', 'fieldFlowIsUsableConnectivity', "normalized === 'online' || normalized === 'degraded'"]), 'typed_connectivity_contract');
$assert($containsAll($syncLock, ['FieldFlowSyncLeaseRecord', 'fieldFlowParseSyncLease', 'fieldFlowCreateSyncLease', 'isForeignActive', 'renew:']), 'typed_sync_lock_contract');
$assert($containsAll($outbox, ['FieldFlowOutboxApi', 'fieldFlowRetryDelay', 'isEligible:', 'shouldRecoverStaleSyncing:', 'compareCreatedAt:']), 'typed_outbox_contract');
$assert($containsAll($core, ['fieldflow-typed-offline-core-v1', 'fieldFlowTypedOfflineApi', 'Object.freeze']), 'typed_core_publication');
$assert($containsAll($bootstrap, ['offlineCore: fieldFlowTypedOfflineApi']), 'typed_core_bootstrap');
$assert($containsAll($tests, ['typed offline core preserves degraded connectivity as usable', 'typed sync lease enforces owner and token with renewable ttl', 'typed outbox keeps retry and stale recovery semantics compatible']), 'typed_behavioural_tests');
$assert($containsAll($asset, ['FieldFlow 2.2.530', 'fieldflow-typed-offline-core-v1', 'fieldFlowConnectivityApi', 'fieldFlowSyncLockApi', 'fieldFlowOutboxApi']), 'typed_compiled_bundle');
$assert($containsAll($runtime, ["const RUNTIME_BUILD='2.2.530'", 'typedOfflineCore()', 'core.connectivity.isUsable', 'core.syncLock', 'core.outbox']), 'runtime_progressive_integration');
$assert($containsAll($validator, ['typed_offline_core_ok', 'O Typed Offline Core não está integralmente compilado e ligado ao runtime offline da release.']) && $containsAll($manager, ['Typed Offline Core', "packageResult['typed_offline_core_ok']"]), 'release_manager_typed_offline_gate');
$assert($containsAll($doc, ['fieldflow-typed-offline-core-v1', 'connectivity', 'sync-lock', 'outbox', 'Release Manager', 'No database migration']), 'release_documentation_contract');

$failed = array_keys(array_filter($checks, static fn($status) => $status !== 'PASS'));
foreach ($checks as $name => $status) echo $status . ' ' . $name . PHP_EOL;
echo $failed ? ('FAILED ' . implode(',', $failed) . PHP_EOL) : ('TYPED OFFLINE CORE CONTRACT OK' . PHP_EOL);
exit($failed ? 1 : 0);
