<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$failed = [];
$assert = static function(bool $ok, string $name) use (&$failed): void { if (!$ok) $failed[] = $name; };

$permissions = $read('src/Support/Permissions.php');
$routes = $read('src/Rest/RoutesController.php');
$mobile = $read('src/Mobile/Controller.php');
$planner = $read('src/Front/SelfRoutePlannerShortcodeRenderer.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9BC-PLANNER-CANONICAL-ASSIGNMENT-CATALOG.md');

$assert(strpos($permissions, '9BC planner catalog authority: assigned_to is canonical') !== false, 'permissions:canonical_assignment');
$assert(strpos($permissions, 'WHERE cl.assigned_to=%d') !== false, 'permissions:assignment_only_query');
$assert(strpos($routes, "get_param('planner_assigned')") !== false, 'routes:planner_assigned_mode');
$assert(strpos($routes, '9BC planner_assigned bypasses lifecycle filters') !== false, 'routes:lifecycle_bypass');
$assert(strpos($routes, 'WHERE project_id=%d AND assigned_to=%d LIMIT 1') !== false, 'routes:assignment_guard');
$assert(strpos($mobile, 'all_location_ids_lookup') !== false, 'mobile:cross_day_collection');
$assert(strpos($mobile, '9BC cross-day repeat contract') !== false, 'mobile:cross_day_contract');
$assert(strpos($mobile, "return new WP_Error('fieldflow_mobile_store_duplicate'") === false, 'mobile:no_cross_day_duplicate_error');
$assert(strpos($planner, 'fieldflow-planner-canonical-assignment-catalog-v1') !== false, 'planner:contract');
$assert(strpos($planner, 'planner_assigned=1') !== false, 'planner:full_catalog_endpoint');
$assert(strpos($planner, 'dayHasStore') !== false, 'planner:same_day_guard');
$assert(strpos($planner, 'dayPlanningLocked') !== false, 'planner:locked_day_guard');
$assert(strpos($planner, 'Já no período · pode repetir') !== false, 'planner:repeat_ui');
$assert(strpos($planner, 'periodDates.filter(date=>dirtyDays.has(date))') !== false, 'planner:dirty_day_save');
$assert(strpos($phase, "SOURCE_RELEASE = '2.2.572'") !== false && strpos($phase, "STABLE_RELEASE = '2.2.573'") !== false, 'phase6:successor');
$assert(strpos($gate, "PLANNER_CANONICAL_ASSIGNMENT_9BC_CONTRACT = 'fieldflow-planner-canonical-assignment-catalog-v1'") !== false, 'gate:contract');
$assert(strpos($gate, 'checkPlannerCanonicalAssignment9BCContract') !== false, 'gate:check');
$assert(strpos($validator, 'planner_canonical_assignment_9bc_ok') !== false, 'validator:flag');
$assert(strpos($manager, "packageResult['planner_canonical_assignment_9bc_ok']") !== false, 'manager:kpi');
$assert(strpos($doc, 'fieldflow-planner-canonical-assignment-catalog-v1') !== false, 'doc:contract');
$assert(strpos($read('src/Support/Migrations.php'), "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema:unchanged');
$assert(strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false, 'submission:unchanged');

if ($failed) {
    fwrite(STDERR, 'FAILED ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "PLANNER CANONICAL ASSIGNMENT 9BC CONTRACT OK\n";
