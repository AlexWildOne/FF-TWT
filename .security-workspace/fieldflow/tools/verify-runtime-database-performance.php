<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string) file_get_contents($path) : '';
};
$fail = static function (string $key): void { fwrite(STDERR, "FAIL {$key}\n"); exit(1); };
$assert = static function (bool $condition, string $key) use ($fail): void { if (!$condition) $fail($key); echo "PASS {$key}\n"; };

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$module = $read('src/Performance/Module.php');
$load = $read('src/Performance/LoadReadiness.php');
$health = $read('src/Support/SystemHealthDiagnostics.php');
$admin = $read('src/Admin/SecurityObservability.php');
$bootstrap = $read('frontend/src/react-front/bootstrap.ts');
$uiRuntime = $read('assets/fieldflow-ui-runtime.js');
$migrations = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-9K-RUNTIME-DATABASE-PERFORMANCE.md');

$assert(strpos($plugin, 'Version: 2.2.530') !== false && strpos($plugin, "FIELDFLOW_VERSION', '2.2.530'") !== false && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert(strpos($phase6, "VERSION = '1.0.87'") !== false && strpos($phase6, "SOURCE_RELEASE = '2.2.529'") !== false && strpos($phase6, "STABLE_RELEASE = '2.2.530'") !== false, 'phase6_continuity');
$assert(strpos($gate, "VERSION = '1.79.0'") !== false && strpos($gate, "RUNTIME_DATABASE_PERFORMANCE_CONTRACT = 'fieldflow-runtime-database-performance-v1'") !== false && strpos($gate, 'checkRuntimeDatabasePerformanceContract') !== false, 'release_gate_contract');
$assert(strpos($gate, "RUNTIME_IDENTITY_SUCCESSOR_CONTRACT = 'fieldflow-runtime-identity-successor-v1'") !== false && strpos($gate, 'currentOfflineRuntimeRelative') !== false && strpos($gate, 'currentOfflineRuntimeMarker') !== false, 'runtime_identity_successor_gate');
$assert(strpos($validator, "RUNTIME_IDENTITY_SUCCESSOR_CONTRACT = 'fieldflow-runtime-identity-successor-v1'") !== false || strpos($validator, "fieldflow-runtime-identity-successor-v1") !== false, 'runtime_identity_successor_validator');
$assert(strpos($validator, "'runtime_database_performance_ok'=>false") !== false && strpos($validator, "version_compare((string)(\$result['version'] ?? ''), '2.2.528', '>=')") !== false, 'package_validator_successor_contract');
$assert(strpos($manager, 'Runtime &amp; Database Performance') !== false && strpos($manager, "packageResult['runtime_database_performance_ok']") !== false, 'release_manager_kpi');

$assert(strpos($module, "PERFORMANCE_BATCH_CONTRACT = 'fieldflow-academy-batch-read-v1'") !== false, 'academy_batch_contract');
$assert(strpos($module, 'COUNT(DISTINCT lesson_id) lessons_done') !== false && strpos($module, 'COUNT(*) certificates') !== false, 'top_performer_batch_reads');
$assert(strpos($module, 'SELECT course_id,user_id,COUNT(DISTINCT lesson_id) done') !== false && strpos($module, 'SELECT course_id,user_id,certificate_uid,issued_at') !== false && strpos($module, 'SELECT ID,display_name,user_email') !== false, 'reset_engine_prefetch');
$assert(strpos($module, '$projectUserIdsCache') !== false && strpos($module, '$clientUserIdsCache') !== false, 'assignment_request_cache');

$assert(strpos($load, "VERSION = '1.2.0'") !== false && strpos($load, 'databaseFootprint') !== false && strpos($load, 'SHOW TABLE STATUS LIKE %s') !== false && strpos($load, "'largest_tables'") !== false && strpos($load, "'read_only'=>true") !== false, 'database_read_only_profile');
$assert(strpos($admin, 'Footprint FieldFlow') !== false && strpos($health, "'database_profile'") !== false, 'database_profile_visibility');
$assert(strpos($bootstrap, 'containsUnmountedIsland') !== false && strpos($bootstrap, 'data-fieldflow-react-front-island') !== false, 'react_observer_scope');
$assert(strpos($uiRuntime, 'pendingEnhanceScopes') !== false && strpos($uiRuntime, 'scopes.forEach((scope)=>enhance(scope))') !== false, 'ui_runtime_scoped_enhancement');

$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.525'") !== false, 'schema_frozen');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_frozen');
$assert(strpos($doc, 'fieldflow-runtime-database-performance-v1') !== false && strpos($doc, 'No database schema change.') !== false && strpos($doc, '`SOURCE_RELEASE` is `2.2.526` and `STABLE_RELEASE` is `2.2.527`') !== false, 'phase_documentation');

echo "RUNTIME DATABASE PERFORMANCE CONTRACT OK\n";
echo "academy_reads=batch\n";
echo "runtime_observers=scoped\n";
echo "database_probe=read_only\n";
echo "schema=2.2.525\n";
echo "submission_build=2.2.485\n";
