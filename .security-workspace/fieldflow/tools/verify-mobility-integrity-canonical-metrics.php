<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$contains = static function(string $haystack, array $markers): bool {
    foreach ($markers as $marker) if (strpos($haystack, $marker) === false) return false;
    return true;
};
$assert = static function(bool $ok, string $name): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$name}\n"); exit(1); }
};

if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string)$v)); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return trim(strip_tags((string)$v)); } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
if (!function_exists('current_time')) { function current_time($type){ return $type === 'mysql' ? '2026-09-19 16:30:00' : time(); } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $flags = 0){ return json_encode($v, $flags); } }

require_once $root . '/src/Support/TollEstimator.php';
require_once $root . '/src/Services/RouteMetricsService.php';
require_once $root . '/src/Support/RouteStatus.php';
require_once $root . '/src/Services/MobilityCostService.php';

use RoutesPro\Services\RouteMetricsService;
use RoutesPro\Services\MobilityCostService;
use RoutesPro\Support\RouteStatus;

$route = [
    'project_id'=>7,
    'owner_user_id'=>22,
    'date'=>'2026-09-19',
    'meta_decoded'=>[
        'start_point'=>['address'=>'Base Norte'],
        'end_point'=>['address'=>'Base Sul'],
        'portal_summary'=>['total_distance_km'=>134.2,'toll_cost_eur'=>7.35,'distance_status'=>'complete'],
        'mobility_cost_snapshot'=>[
            'profile_source'=>'operator_profile',
            'profile'=>[
                'enabled'=>1,
                'energy_type'=>'diesel',
                'fuel_price_eur_per_l'=>1.70,
                'consumption_l_per_100km'=>5.5,
            ],
        ],
    ],
];
$metrics = RouteMetricsService::canonicalFromRouteArray($route, false);
$assert(abs((float)$metrics['distance_km'] - 134.2) < 0.001, 'legacy_bucket_distance_resolved');
$assert(abs((float)$metrics['toll_cost_eur'] - 7.35) < 0.001, 'legacy_bucket_toll_resolved');
$assert((string)$metrics['distance_value_source'] === 'portal_summary.total_distance_km', 'distance_source_traced');
$assert((string)$metrics['resolver_contract'] === 'fieldflow-canonical-route-metrics-resolver-v2', 'canonical_resolver_contract');

$cost = MobilityCostService::calculateForRouteArray($route);
$assert(abs((float)$cost['distance_km'] - 134.2) < 0.001, 'mobility_uses_canonical_distance');
$assert(abs((float)$cost['estimated_litres'] - 7.381) < 0.001, 'mobility_litres_from_canonical_distance');
$assert(abs((float)$cost['toll_cost_eur'] - 7.35) < 0.001, 'mobility_uses_canonical_toll');
$assert((string)$cost['route_metrics_resolver'] === 'fieldflow-canonical-route-metrics-resolver-v2', 'mobility_resolver_trace');

$assert(RouteStatus::normalize('done') === 'completed', 'done_alias_normalized');
$assert(RouteStatus::normalize('cancelled') === 'canceled', 'cancelled_alias_normalized');
$assert(RouteStatus::isSnapshotMutable('planned'), 'planned_snapshot_mutable');
$assert(!RouteStatus::isSnapshotMutable('completed'), 'completed_snapshot_protected');
$assert(RouteStatus::isTerminal('done') && RouteStatus::isTerminal('cancelled'), 'terminal_aliases_protected');

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$service = $read('src/Services/MobilityCostService.php');
$routeMetrics = $read('src/Services/RouteMetricsService.php');
$routeStatus = $read('src/Support/RouteStatus.php');
$assignments = $read('src/Admin/Assignments.php');
$routes = $read('src/Admin/Routes.php');
$controller = $read('src/Rest/RoutesController.php');
$repository = $read('src/Repositories/RouteAccessRepository.php');
$portal = $read('src/Front/ClientPortalShortcodeRenderer.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8R-MOBILITY-INTEGRITY-CANONICAL-METRICS.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkMobilityIntegrityCanonicalMetricsContract', 'fieldflow-mobility-integrity-canonical-metrics-v1']), 'release_gate_contract');
$assert($contains($routeMetrics, ['canonicalFromRouteArray', 'fieldflow-canonical-route-metrics-resolver-v2', 'distanceFromStoredRouteLegs']), 'canonical_metrics_service');
$assert($contains($service, ['RouteMetricsService::canonicalFromRouteArray', 'profilesEquivalent', 'refreshSnapshotForIdentityChange', "status NOT IN ('completed','done','canceled','cancelled')", "unset(\$meta['mobility_cost_snapshot'])"]), 'mobility_integrity_service');
$assert($contains($routeStatus, ["if (\$status === 'done')", "if (\$status === 'cancelled')", 'isSnapshotMutable', 'isTerminal']), 'route_status_contract');
$assert($contains($assignments, ['mobility_persist_failed', 'mobility_issue', 'profileIssues']), 'assignment_readback_visibility');
$assert($contains($routes, ['RouteStatus::isSnapshotMutable', '$refresh_mobility_snapshot', '$identity_changed']), 'backoffice_snapshot_refresh');
$assert($contains($controller, ['RouteStatus::normalize', 'refreshSnapshotForIdentityChange']), 'rest_status_snapshot_integrity');
$assert($contains($repository, ['RouteMetricsService::canonicalFromRouteArray', 'distance_value_source', 'toll_value_source']), 'repository_canonical_metrics');
$assert(strpos($portal, 'km*0.58*0.075') === false, 'client_portal_has_no_toll_formula');
$assert($contains($doc, ['fieldflow-mobility-integrity-canonical-metrics-v1', '`SOURCE_RELEASE` is `2.2.502` and `STABLE_RELEASE` is `2.2.503`', 'No P1-P4 routing or optimisation-rule change.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

echo "MOBILITY INTEGRITY & CANONICAL METRICS CONTRACT OK\n";
echo "release=2.2.507 source=2.2.503 phase6=1.0.64 gate=1.56.0 submission_build=2.2.485\n";
echo "canonical_example=134.2km/7.381L/7.35EUR_tolls\n";
