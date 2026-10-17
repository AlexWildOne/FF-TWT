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
$progress = $read('src/Services/RouteProgressService.php');
$completion = $read('src/Services/FormVisitCompletionService.php');

$lockStart = strpos($planner, 'function dayPlanningLocked(date)');
$lockEnd = $lockStart === false ? false : strpos($planner, 'function normalise(value)', $lockStart);
$lock = ($lockStart !== false && $lockEnd !== false) ? substr($planner, $lockStart, $lockEnd - $lockStart) : '';
$assert($lock !== '', 'planner:lock_method');
$assert(strpos($lock, "['canceled','cancelled'].includes(status)") !== false, 'planner:canceled_locked');
$assert(strpos($lock, 'date<operationalToday') !== false, 'planner:historical_guard');
$assert(strpos($lock, "!['draft','planned',''].includes(status)") !== false, 'planner:historical_operational_only');
$assert(strpos($lock, "status==='in_progress'") === false && strpos($lock, "status==='completed'") === false, 'planner:current_started_not_hard_blocked');

$assert(strpos($planner, 'route_stop_status:String(stop.status') !== false, 'planner:stop_status_merge');
$assert(strpos($planner, 'route_stop_canonical_status:String(stop.canonical_status') !== false, 'planner:canonical_merge');
$assert(strpos($planner, "return status==='completed'?'Concluída'") !== false, 'planner:completed_label');
$assert(strpos($planner, "store.route_stop_status='done'") !== false && strpos($planner, "store.route_stop_canonical_status='completed'") !== false, 'planner:live_completion_patch');
$assert(strpos($planner, 'const rows=plan[d]||[],keep=rows.filter(stopIsProtected)') !== false, 'planner:clear_preserves_operational');

$assert(strpos($planSync, "'route_plan_operational_stop_protected'") !== false, 'sync:protected_removal_guard');
$assert(strpos($planSync, "'status' => 'pending'") !== false || strpos($planSync, "'status'=>'pending'") !== false, 'sync:new_stop_pending');
$assert(strpos($progress, "'in_progress'") !== false && strpos($progress, "'completed'") !== false, 'progress:route_states');
$assert(strpos($completion, "'done'") !== false && strpos($completion, 'RouteProgressService::sync') !== false, 'completion:done_then_sync');

if ($failed) {
    fwrite(STDERR, 'FAILED ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "OPEN DAY PLANNING VISIT COMPLETION 9BG RUNTIME OK\n";
