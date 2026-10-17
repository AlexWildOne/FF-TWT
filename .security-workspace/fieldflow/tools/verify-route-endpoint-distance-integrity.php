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
require_once $root . '/src/Services/MobilityCostService.php';

use RoutesPro\Services\RouteMetricsService;
use RoutesPro\Services\MobilityCostService;

$integrity = RouteMetricsService::endpointIntegrity(['address'=>'Base A'], []);
$assert(($integrity['endpoint_status'] ?? '') === 'missing_end', 'missing_end_detected');
$assert(empty($integrity['endpoints_complete']), 'missing_end_not_complete');

$incomplete = RouteMetricsService::canonicalFromSchedule([
    'distance_km'=>50.0,
    'known_distance_km'=>50.0,
    'access_distance_km'=>12.0,
    'inter_stop_distance_km'=>38.0,
    'return_distance_km'=>null,
    'toll_cost_eur'=>3.25,
    'distance_status'=>'incomplete',
    'endpoint_status'=>'missing_end',
    'endpoints_complete'=>0,
    'missing_endpoint_count'=>1,
    'unresolved_leg_count'=>1,
]);
$assert(($incomplete['distance_km'] ?? null) === null, 'partial_distance_not_authoritative');
$assert(abs((float)($incomplete['known_distance_km'] ?? 0) - 50.0) < 0.001, 'known_distance_preserved');
$assert(($incomplete['toll_cost_eur'] ?? null) === null, 'partial_toll_not_authoritative');
$assert(($incomplete['endpoint_status'] ?? '') === 'missing_end', 'endpoint_status_preserved');

$profile = [
    'enabled'=>1,
    'energy_type'=>'diesel',
    'fuel_price_eur_per_l'=>1.70,
    'consumption_l_per_100km'=>5.5,
    'operational_cost_eur_per_km'=>0.10,
];
$blocked = MobilityCostService::estimateForMetrics($incomplete, $profile);
$assert(empty($blocked['costs_available']), 'mobility_blocked_for_partial_route');
$assert(($blocked['calculation_status'] ?? '') === 'distance_incomplete', 'mobility_incomplete_status');

$complete = RouteMetricsService::canonicalFromSchedule([
    'distance_km'=>75.0,
    'known_distance_km'=>75.0,
    'access_distance_km'=>10.0,
    'inter_stop_distance_km'=>50.0,
    'return_distance_km'=>15.0,
    'travel_min'=>100.0,
    'toll_cost_eur'=>4.20,
    'distance_status'=>'complete',
    'endpoint_status'=>'complete',
    'endpoints_complete'=>1,
    'missing_endpoint_count'=>0,
    'unresolved_leg_count'=>0,
]);
$parts = (float)$complete['access_distance_km'] + (float)$complete['inter_stop_distance_km'] + (float)$complete['return_distance_km'];
$assert(abs((float)$complete['distance_km'] - $parts) < 0.001, 'complete_distance_equals_route_legs');
$cost = MobilityCostService::estimateForMetrics($complete, $profile);
$assert(!empty($cost['costs_available']), 'mobility_available_for_complete_route');
$assert(($cost['calculation_status'] ?? '') === 'complete', 'mobility_complete_status');
$assert(abs((float)($cost['estimated_litres'] ?? 0) - 4.125) < 0.001, 'full_route_fuel_uses_75km');
$assert(abs((float)($cost['toll_cost_eur'] ?? 0) - 4.20) < 0.001, 'full_route_toll_preserved');

$legacyIncomplete = RouteMetricsService::canonicalFromRouteArray([
    'id'=>0,
    'project_id'=>7,
    'owner_user_id'=>22,
    'date'=>'2026-09-19',
    'meta_decoded'=>[
        'start_point'=>['address'=>'Base A'],
        'portal_summary'=>['total_distance_km'=>88.0,'known_distance_km'=>88.0,'toll_cost_eur'=>5.0,'distance_status'=>'complete'],
    ],
], false);
$assert(($legacyIncomplete['distance_km'] ?? null) === null, 'legacy_total_blocked_without_end');
$assert(abs((float)($legacyIncomplete['known_distance_km'] ?? 0) - 88.0) < 0.001, 'legacy_known_distance_retained');
$assert(($legacyIncomplete['endpoint_status'] ?? '') === 'missing_end', 'legacy_missing_end_traced');

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$routeMetrics = $read('src/Services/RouteMetricsService.php');
$routingMatrix = $read('src/Services/Planning/RoutingMatrixService.php');
$mobility = $read('src/Services/MobilityCostService.php');
$optimizer = $read('src/Services/RouteOptimizationService.php');
$routes = $read('src/Admin/Routes.php');
$campaigns = $read('src/Admin/CampaignLocations.php');
$selfPlanner = $read('src/Front/SelfRoutePlannerShortcodeRenderer.php');
$assignments = $read('src/Admin/Assignments.php');
$stats = $read('src/Rest/StatsController.php');
$portal = $read('src/Front/ClientPortalShortcodeRenderer.php');
$shortcodes = $read('src/Shortcodes.php');
$formSubmissions = $read('src/Admin/FormSubmissions.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8S-ROUTE-ENDPOINT-DISTANCE-INTEGRITY.md');
$coordDoc = $read('PHASE-8T-ROUTE-ENDPOINT-COORDINATE-TRUST-HOTFIX.md');
$releaseManager = $read('src/Admin/ReleaseManager.php');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkRouteEndpointDistanceIntegrityContract', 'fieldflow-route-endpoint-distance-integrity-v1']), 'release_gate_contract');
$assert($contains($routeMetrics, ['fieldflow-route-endpoint-distance-integrity-v1', 'fieldflow_route_metrics_v5', 'fieldflow-canonical-route-metrics-resolver-v2', 'endpointIntegrity', 'repairPlannedRouteIntegrity', 'route_endpoints_incomplete']), 'route_metrics_integrity');
$assert($contains($routingMatrix, ['missing_start', 'missing_end', 'missing_both', 'endpoints_complete', 'known_distance_km']), 'routing_matrix_endpoint_integrity');
$assert($contains($mobility, ['estimateForMetrics', 'costs_available', 'distance_incomplete']), 'mobility_cost_guard');
$assert($contains($optimizer, ['fieldflow_route_endpoints_required', 'access_distance_km', 'inter_stop_distance_km', 'return_distance_km']), 'optimizer_complete_route_contract');
$assert($contains($routes, ['Para publicar a rota, define o ponto de partida e o ponto de chegada', ' conhecidos', 'Por calcular', 'fieldflow-route-endpoint-coordinate-trust-v1', 'resolve_route_endpoint', 'endpoint_coordinates_refreshed', 'rp-summary-start-point', 'rp-summary-end-point']), 'routes_admin_integrity_ui');
$assert($contains($campaigns, ['skipped_endpoints', 'plan_distance_integrity_metrics', 'Por calcular']), 'campaign_route_integrity');
$assert($contains($selfPlanner, ['start_point', 'end_point', 'return_to_start']), 'self_planner_explicit_endpoints');
$assert($contains($assignments, ['repair_route_distance_integrity', 'Reparar partida, chegada e km de rotas por planear']), 'safe_route_repair');
$assert($contains($stats, ['costed_routes', 'incomplete_distance_routes']), 'analytics_distance_integrity');
$assert($contains($portal, ['routeDistanceComplete', ' conhecidos', 'fmtRouteEur']), 'client_portal_integrity');
$assert($contains($shortcodes, ['mobilityAggregate', 'Por calcular']), 'operational_analytics_integrity');
$assert($contains($formSubmissions, ['Rotas com distância incompleta', 'POR CALCULAR, DISTÂNCIA INCOMPLETA']), 'export_integrity');
$assert($contains($releaseManager, ['Route Distance Integrity', "packageResult['route_endpoint_distance_integrity_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-route-endpoint-distance-integrity-v1', '`SOURCE_RELEASE` is `2.2.503` and `STABLE_RELEASE` is `2.2.504`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.']), 'phase_documentation');
$assert($contains($coordDoc, ['fieldflow-route-endpoint-coordinate-trust-v1', '`SOURCE_RELEASE` is `2.2.504` and `STABLE_RELEASE` is `2.2.505`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.']), 'coordinate_trust_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

echo "ROUTE ENDPOINT & DISTANCE INTEGRITY CONTRACT OK\n";
echo "release=2.2.507 source=2.2.505 phase6=1.0.64 gate=1.56.0 submission_build=2.2.485\n";
echo "full_route_example=10.0+50.0+15.0=75.0km/4.125L/4.20EUR_tolls\n";
