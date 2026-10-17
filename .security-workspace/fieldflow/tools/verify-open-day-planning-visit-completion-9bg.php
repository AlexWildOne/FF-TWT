<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$failed = [];
$assert = static function(bool $ok, string $name) use (&$failed): void { if (!$ok) $failed[] = $name; };

$planner = $read('src/Front/SelfRoutePlannerShortcodeRenderer.php');
$planSync = $read('src/Services/RoutePlanSyncService.php');
$completion = $read('src/Services/FormVisitCompletionService.php');
$mobile = $read('src/Mobile/Controller.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9BG-OPEN-DAY-PLANNING-VISIT-COMPLETION-SYNC.md');

$assert(strpos($planner, 'fieldflow-open-day-planning-visit-completion-sync-v1') !== false, 'planner:contract');
$assert(strpos($planner, 'const operationalToday=') !== false, 'planner:operational_today');
$assert(strpos($planner, 'function dayPlanningLocked(date)') !== false, 'planner:lock_policy');
$assert(strpos($planner, 'function canonicalStopStatus(store)') !== false, 'planner:canonical_stop_status');
$assert(strpos($planner, 'function stopIsProtected(store)') !== false, 'planner:protected_stop');
$assert(strpos($planner, 'route_stop_canonical_status') !== false, 'planner:canonical_status_loaded');
$assert(strpos($planner, "document.addEventListener('fieldflow:visit-completed'") !== false, 'planner:completion_event');
$assert(strpos($planner, 'A rota deste dia já foi iniciada ou fechada. Escolhe outro dia para planear novas visitas.') === false, 'planner:legacy_blocker_removed');
$assert(strpos($planSync, 'route_plan_operational_stop_protected') !== false, 'sync:operational_protection');
$assert(strpos($planSync, 'has_operational_state') !== false, 'sync:state_detection');
$assert(strpos($completion, 'RouteProgressService::sync') !== false, 'completion:route_sync');
$assert(strpos($mobile, 'RoutePlanSyncService::sync') !== false && strpos($mobile, 'RouteProgressService::sync') !== false, 'mobile:plan_reconcile');
$assert(strpos($phase, "SOURCE_RELEASE = '2.2.577'") !== false && strpos($phase, "STABLE_RELEASE = '2.2.578'") !== false, 'phase6:successor');
$assert(strpos($gate, "OPEN_DAY_PLANNING_VISIT_COMPLETION_9BG_CONTRACT = 'fieldflow-open-day-planning-visit-completion-sync-v1'") !== false, 'gate:contract');
$assert(strpos($gate, 'checkOpenDayPlanningVisitCompletion9BGContract') !== false, 'gate:check');
$assert(strpos($validator, 'open_day_planning_visit_completion_9bg_ok') !== false, 'validator:flag');
$assert(strpos($manager, "packageResult['open_day_planning_visit_completion_9bg_ok']") !== false, 'manager:kpi');
$assert(strpos($doc, 'fieldflow-open-day-planning-visit-completion-sync-v1') !== false, 'doc:contract');
$assert(strpos($read('src/Support/Migrations.php'), "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema:unchanged');
$assert(strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false, 'submission:unchanged');

if ($failed) {
    fwrite(STDERR, 'FAILED ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "OPEN DAY PLANNING VISIT COMPLETION 9BG CONTRACT OK\n";
