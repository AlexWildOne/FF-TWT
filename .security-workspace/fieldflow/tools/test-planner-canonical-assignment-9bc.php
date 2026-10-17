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

$scopeStart = strpos($permissions, 'private static function assignedStoreScopeForUser');
$scopeEnd = $scopeStart === false ? false : strpos($permissions, 'public static function get_associated_user_ids', $scopeStart);
$scope = ($scopeStart !== false && $scopeEnd !== false) ? substr($permissions, $scopeStart, $scopeEnd - $scopeStart) : '';
$assert($scope !== '', 'scope:method');
$assert(strpos($scope, 'WHERE cl.assigned_to=%d') !== false, 'scope:assignment_authority');
$assert(strpos($scope, "WHERE cl.assigned_to=%d AND cl.is_active=1") === false, 'scope:no_active_filter');
$assert(strpos($scope, "WHERE cl.assigned_to=%d AND cl.status='active'") === false, 'scope:no_status_filter');

$listStart = strpos($routes, 'public function list_campaign_pdvs');
$listEnd = $listStart === false ? false : strpos($routes, 'public function update_campaign_pdv', $listStart);
$list = ($listStart !== false && $listEnd !== false) ? substr($routes, $listStart, $listEnd - $listStart) : '';
$assert(strpos($list, '$plannerAssigned') !== false, 'routes:planner_mode');
$assert(strpos($list, 'if (!$plannerAssigned)') !== false, 'routes:filters_conditional');
$assert(strpos($list, "if (!current_user_can('routespro_manage')) \$owner = get_current_user_id();") !== false, 'routes:self_owner');

$assert(strpos($mobile, '$all_location_ids_lookup') !== false, 'mobile:all_locations');
$assert(strpos($mobile, "return new WP_Error('fieldflow_mobile_store_duplicate'") === false, 'mobile:cross_day_repeat_allowed');
$assert(strpos($mobile, 'array_unique(array_filter(array_map(\'absint\'') !== false, 'mobile:same_day_unique');

$assert(strpos($planner, 'inActiveDay=dayHasStore(activeDate(),id)') !== false, 'planner:disable_only_active_day');
$assert(strpos($planner, "used?'Já no período · pode repetir'") !== false, 'planner:prior_period_not_blocking');
$assert(strpos($planner, "if(!dayHasStore(activeDate(),storeId(s)))selected.add(storeId(s))") !== false, 'planner:select_visible_repeat');
$assert(strpos($planner, 'periodDates.filter(date=>dirtyDays.has(date))') !== false, 'planner:save_dirty_only');
// 9D4: locked routes are protected at the authoritative server write boundary.
// The frontend saves only changed days and the backend refuses unsafe mutation.
$assert(strpos($planner, 'periodDates.filter(date=>dirtyDays.has(date))') !== false
    && strpos($mobile, 'RouteOptimizationService::routeSafety($route_id, $route)') !== false
    && strpos($mobile, 'if (empty($safety[\'can_plan\']))') !== false
    && strpos($mobile, "fieldflow_mobile_route_locked") !== false, 'planner:preserve_locked_routes');
$assert(strpos($planner, 'planner_assigned=1&per_page=100&page=') !== false, 'planner:catalog_mode');

if ($failed) {
    fwrite(STDERR, 'FAILED ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "PLANNER CANONICAL ASSIGNMENT 9BC RUNTIME OK\n";
