<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$failed = [];
$assert = static function(bool $ok, string $name) use (&$failed): void { if (!$ok) $failed[] = $name; };

$optimizer = $read('src/Services/RouteOptimizationService.php');
$planner = $read('src/Front/SelfRoutePlannerShortcodeRenderer.php');
$mobile = $read('src/Mobile/Controller.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9BE-PLANNER-FREE-WARNING-CONTINUITY.md');

$assert(strpos($optimizer, "PLANNER_WARNING_CONTRACT = 'fieldflow-planner-free-warning-continuity-v1'") !== false, 'optimizer:contract');
$assert(strpos($optimizer, "'planner_mode'") !== false, 'optimizer:planner_mode');
$assert(strpos($optimizer, '$lifecycleSql = $plannerMode ?') !== false, 'optimizer:planner_lifecycle_authority');
$assert(strpos($optimizer, 'plannerWarnings') !== false, 'optimizer:warnings');
$assert(strpos($optimizer, 'Sobreposição:') !== false, 'optimizer:overlap_warning');
$assert(strpos($optimizer, 'Periodicidade:') !== false, 'optimizer:periodicity_warning');
$assert(strpos($planner, 'fieldflow-planner-free-warning-continuity-v1') !== false, 'planner:contract');
$assert(strpos($planner, 'planner_mode:1') !== false, 'planner:mode_payload');
$assert(strpos($planner, "planning_source:'self_planner'") !== false, 'planner:source_payload');
$assert(strpos($planner, 'planningWarningText') !== false, 'planner:warning_ui');
$assert(strpos($mobile, "'planner_mode' => 1") !== false, 'mobile:planner_mode');
$assert(strpos($mobile, "'planning_source' => 'self_planner'") !== false, 'mobile:planner_source');
$assert(strpos($mobile, "'warnings' => array_values(\$planning_warnings)") !== false, 'mobile:warning_response');
$assert(strpos($phase, "SOURCE_RELEASE = '2.2.574'") !== false && strpos($phase, "STABLE_RELEASE = '2.2.575'") !== false, 'phase6:successor');
$assert(strpos($gate, "PLANNER_FREE_WARNING_9BE_CONTRACT = 'fieldflow-planner-free-warning-continuity-v1'") !== false, 'gate:contract');
$assert(strpos($gate, 'checkPlannerFreeWarning9BEContract') !== false, 'gate:check');
$assert(strpos($validator, 'planner_free_warning_9be_ok') !== false, 'validator:flag');
$assert(strpos($manager, "packageResult['planner_free_warning_9be_ok']") !== false, 'manager:kpi');
$assert(strpos($doc, 'fieldflow-planner-free-warning-continuity-v1') !== false, 'doc:contract');
$assert(strpos($read('src/Support/Migrations.php'), "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema:unchanged');
$assert(strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false, 'submission:unchanged');

if ($failed) {
    fwrite(STDERR, 'FAILED ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "PLANNER FREE WARNING 9BE CONTRACT OK\n";
