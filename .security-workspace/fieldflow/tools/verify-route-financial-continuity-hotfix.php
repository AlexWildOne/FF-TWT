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
if (!function_exists('current_time')) { function current_time($type){ return $type === 'mysql' ? '2026-09-19 18:40:00' : time(); } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $flags = 0){ return json_encode($v, $flags); } }
if (!function_exists('remove_accents')) { function remove_accents($v){ return (string)$v; } }

require_once $root . '/src/Support/TollEstimator.php';
require_once $root . '/src/Services/RouteEndpointResolverService.php';
require_once $root . '/src/Services/RouteMetricsService.php';
require_once $root . '/src/Services/MobilityCostService.php';

use RoutesPro\Services\RouteEndpointResolverService;
use RoutesPro\Services\RouteMetricsService;
use RoutesPro\Services\MobilityCostService;

$first = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100];
$last = ['address'=>'Loja E','lat'=>41.1800,'lng'=>-8.6500];
$start = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100,'provenance'=>'legacy'];
$end = ['address'=>'Loja E','lat'=>41.1800,'lng'=>-8.6500,'provenance'=>'legacy'];
$trust = RouteEndpointResolverService::integrity($start, $end, $first, $last);
$assert(($trust['endpoint_status'] ?? '') === 'suspicious_both', 'suspicious_both_preserved');
$assert(empty($trust['endpoints_complete']), 'suspicious_not_trusted');
$assert(!empty($trust['endpoints_routable']), 'suspicious_still_routable');

$suspiciousMetrics = RouteMetricsService::canonicalFromSchedule([
    'distance_km'=>39.3,
    'known_distance_km'=>39.3,
    'access_distance_km'=>0.0,
    'inter_stop_distance_km'=>38.6,
    'return_distance_km'=>0.7,
    'travel_min'=>54.0,
    'toll_cost_eur'=>1.20,
    'distance_status'=>'complete',
    'endpoint_status'=>'suspicious_both',
    'endpoints_complete'=>0,
    'endpoints_routable'=>1,
    'suspicious_endpoint_count'=>2,
    'missing_endpoint_count'=>0,
    'unresolved_leg_count'=>0,
]);
$assert(($suspiciousMetrics['distance_status'] ?? '') === 'complete', 'trust_warning_does_not_hide_distance');
$assert(abs((float)($suspiciousMetrics['distance_km'] ?? 0) - 39.3) < 0.001, 'routable_distance_authoritative');

$profile = [
    'enabled'=>1,
    'energy_type'=>'gasoline',
    'fuel_price_eur_per_l'=>1.80,
    'consumption_l_per_100km'=>7.2,
    'operational_cost_eur_per_km'=>0.10,
];
$suspiciousCost = MobilityCostService::estimateForMetrics($suspiciousMetrics, $profile);
$assert(!empty($suspiciousCost['costs_available']), 'costs_available_for_routable_warning');
$assert(($suspiciousCost['calculation_status'] ?? '') === 'complete', 'financial_continuity_status');
$assert((float)($suspiciousCost['fuel_cost_eur'] ?? 0) > 0, 'fuel_cost_calculated');
$assert(abs((float)($suspiciousCost['toll_cost_eur'] ?? 0) - 1.20) < 0.001, 'toll_preserved');

$legacyRecovered = RouteMetricsService::canonicalFromRouteArray([
    'id'=>0,
    'project_id'=>7,
    'owner_user_id'=>22,
    'date'=>'2026-09-19',
    'meta_decoded'=>[
        'start_point'=>['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100],
        'end_point'=>['address'=>'Loja E','lat'=>41.1800,'lng'=>-8.6500],
        'route_metrics'=>[
            'distance_km'=>null,
            'known_distance_km'=>78.1,
            'access_distance_km'=>0.1,
            'inter_stop_distance_km'=>77.9,
            'return_distance_km'=>0.1,
            'known_toll_cost_eur'=>1.50,
            'distance_status'=>'incomplete',
            'endpoint_status'=>'suspicious_both',
            'endpoints_complete'=>0,
            'missing_endpoint_count'=>0,
            'suspicious_endpoint_count'=>2,
            'unresolved_leg_count'=>0,
        ],
    ],
], false);
$assert(($legacyRecovered['distance_status'] ?? '') === 'complete', '22506_known_distance_recovered');
$assert(abs((float)($legacyRecovered['distance_km'] ?? 0) - 78.1) < 0.001, '22506_distance_value_recovered');
$assert(!empty($legacyRecovered['endpoints_routable']), '22506_route_routable');
$assert(empty($legacyRecovered['endpoints_complete']), '22506_trust_warning_preserved');
$assert(abs((float)($legacyRecovered['toll_cost_eur'] ?? 0) - 1.50) < 0.001, '22506_known_toll_recovered');

$missing = RouteMetricsService::canonicalFromSchedule([
    'known_distance_km'=>50.0,
    'distance_status'=>'incomplete',
    'endpoint_status'=>'missing_end',
    'endpoints_complete'=>0,
    'endpoints_routable'=>0,
    'missing_endpoint_count'=>1,
    'unresolved_leg_count'=>1,
]);
$missingCost = MobilityCostService::estimateForMetrics($missing, $profile);
$assert(($missing['distance_km'] ?? null) === null, 'missing_endpoint_distance_blocked');
$assert(empty($missingCost['costs_available']), 'missing_endpoint_cost_blocked');

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$metrics = $read('src/Services/RouteMetricsService.php');
$mobility = $read('src/Services/MobilityCostService.php');
$optimizer = $read('src/Services/RouteOptimizationService.php');
$routes = $read('src/Admin/Routes.php');
$portal = $read('src/Front/ClientPortalShortcodeRenderer.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8V-ROUTE-FINANCIAL-CONTINUITY-HOTFIX.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkRouteFinancialContinuityContract', 'fieldflow-route-financial-continuity-v1']), 'release_gate_contract');
$assert($contains($metrics, ['fieldflow-route-financial-continuity-v1', 'endpoints_routable', 'known_toll_recovered', 'route_endpoints_untrusted']), 'canonical_financial_metrics');
$assert($contains($mobility, ['estimateForMetrics', 'endpoints_routable', 'costs_available']), 'mobility_routable_guard');
$assert($contains($optimizer, ['endpoints_routable', 'endpoints_trusted', 'fieldflow_route_endpoints_untrusted']), 'optimizer_trust_decoupling');
$assert($contains($routes, ['endpointsRoutable', 'Calculável', 'suspicious_endpoint_count']), 'routes_financial_continuity_ui');
$assert($contains($portal, ['routeDistanceComplete', 'Distância calculada, validar proximidade dos extremos']), 'portal_financial_continuity');
$assert($contains($doc, ['fieldflow-route-financial-continuity-v1', '`SOURCE_RELEASE` is `2.2.506` and `STABLE_RELEASE` is `2.2.507`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

echo "ROUTE FINANCIAL CONTINUITY HOTFIX CONTRACT OK\n";
echo "release=2.2.507 source=2.2.506 phase6=1.0.64 gate=1.56.0 submission_build=2.2.485\n";
echo "suspicious_route=39.3km/costs_available; legacy_22506=78.1km/recovered; missing_endpoint=blocked\n";
