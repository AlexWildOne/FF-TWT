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

$start = strpos($optimizer, 'private static function validateLocations');
$end = $start === false ? false : strpos($optimizer, 'private static function plannerWarnings', $start);
$validate = ($start !== false && $end !== false) ? substr($optimizer, $start, $end - $start) : '';
$assert($validate !== '', 'optimizer:validate_method');
$assert(strpos($validate, 'bool $plannerMode = false') !== false, 'optimizer:planner_mode_argument');
$assert(strpos($validate, '$lifecycleSql = $plannerMode ?') !== false, 'optimizer:conditional_lifecycle');
$assert(strpos($validate, 'assigned_to=%d{$lifecycleSql}') !== false, 'optimizer:assignment_authority');
$assert(strpos($validate, "fieldflow_route_optimization_location_forbidden") !== false, 'optimizer:true_unassigned_guard');

$warnStart = strpos($optimizer, 'private static function plannerWarnings');
$warnEnd = $warnStart === false ? false : strpos($optimizer, 'private static function normaliseLocation', $warnStart);
$warnings = ($warnStart !== false && $warnEnd !== false) ? substr($optimizer, $warnStart, $warnEnd - $warnStart) : '';
$assert(strpos($warnings, "r.status NOT IN ('canceled','cancelled')") !== false, 'warnings:active_routes_only');
$assert(strpos($warnings, 'Sobreposição:') !== false && strpos($warnings, 'continua permitido') !== false, 'warnings:overlap_nonblocking');
$assert(strpos($warnings, 'dia configurado como bloqueado') !== false, 'warnings:blocked_weekday_advisory');
$assert(strpos($warnings, 'intervalo mínimo entre visitas') !== false && strpos($warnings, 'não bloqueia o plano') !== false, 'warnings:min_gap_advisory');

$assert(strpos($planner, 'planner_mode:1') !== false, 'planner:preview_planner_mode');
$assert(strpos($planner, 'planningWarningText') !== false, 'planner:warning_render');
$assert(strpos($planner, ".ffp-status.warning") !== false, 'planner:warning_status_style');
$assert(strpos($mobile, "'planner_mode' => 1") !== false, 'mobile:save_uses_planner_mode');
$assert(strpos($mobile, "'date' => \$day['date']") !== false, 'mobile:date_forwarded');
$assert(strpos($mobile, "fieldflow_mobile_store_not_assigned") !== false, 'mobile:true_assignment_guard_retained');

if ($failed) {
    fwrite(STDERR, 'FAILED ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "PLANNER FREE WARNING 9BE RUNTIME OK\n";
