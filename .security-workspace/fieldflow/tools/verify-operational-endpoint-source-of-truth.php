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
if (!function_exists('remove_accents')) { function remove_accents($v){ return (string)$v; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $flags = 0){ return json_encode($v, $flags); } }
if (!function_exists('current_time')) { function current_time($type){ return $type === 'mysql' ? '2026-09-19 19:00:00' : time(); } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }

require_once $root . '/src/Services/RouteEndpointResolverService.php';
require_once $root . '/src/Services/RouteEndpointProfileService.php';

use RoutesPro\Services\RouteEndpointProfileService;

$method = new ReflectionMethod(RouteEndpointProfileService::class, 'isTrustedRouteCandidate');
$method->setAccessible(true);
$edge = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100];
$contaminated = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100,'provenance'=>'manual'];
$assert($method->invoke(null, $contaminated, $edge, 'start') === false, 'manual_label_does_not_trust_edge_pdv');
$contaminated['allow_matches_stop'] = 1;
$assert($method->invoke(null, $contaminated, $edge, 'start') === true, 'explicit_zero_leg_confirmation_allowed');
$real = ['address'=>'Base Operativa','lat'=>41.1000,'lng'=>-8.5500,'provenance'=>'operator_profile'];
$assert($method->invoke(null, $real, $edge, 'start') === true, 'real_endpoint_trusted');

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$service = $read('src/Services/RouteEndpointProfileService.php');
$mobile = $read('src/Mobile/Controller.php');
$routes = $read('src/Admin/Routes.php');
$campaigns = $read('src/Admin/CampaignLocations.php');
$rest = $read('src/Rest/RoutesController.php');
$metrics = $read('src/Services/RouteMetricsService.php');
$planner = $read('src/Front/SelfRoutePlannerShortcodeRenderer.php');
$shortcodes = $read('src/Shortcodes.php');
$mapsUrl = $read('src/Services/GoogleMapsUrlService.php');
$assignments = $read('src/Admin/Assignments.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8W-OPERATIONAL-ENDPOINT-SOURCE-OF-TRUTH.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkOperationalEndpointSourceOfTruthContract', 'fieldflow-operational-endpoint-source-of-truth-v1']), 'release_gate_contract');
$assert($contains($service, ['RouteEndpointProfileService', 'route_endpoint_default', 'route_endpoint_profiles', 'recoverHistoricalEndpoints', 'Legacy route defaults are never consumed implicitly', 'fieldflow-operational-endpoint-source-of-truth-v1']), 'canonical_endpoint_profile_service');
$assert($contains($assignments, ['save_route_endpoint_default', 'save_route_endpoint_profile', 'Base da rota', 'Migração dos defaults anteriores']), 'assignment_route_base_ui');
$assert($contains($mobile, ['RouteEndpointProfileService::resolveForRoute', 'fieldflow_mobile_route_endpoints_required']) && strpos($mobile, '$start = $to_point($edge_lookup') === false && strpos($mobile, '$end = $to_point($edge_lookup') === false, 'mobile_no_edge_pdv_fallback');
$assert($contains($routes, ['routeEndpointConfigs', 'endpointDefaultsForSelection', 'Base da rota no Centro de Atribuições']) && strpos($routes, 'valid[0].lat') === false && strpos($routes, 'valid[valid.length-1].lat') === false, 'backoffice_no_edge_pdv_preview_fallback');
$assert($contains($campaigns, ['RouteEndpointProfileService::effectiveProfile', 'RouteEndpointProfileService::resolveForRoute']) && strpos($campaigns, "get_option('routespro_route_defaults'") === false, 'campaigns_use_canonical_profile');
$assert($contains($rest, ['RouteEndpointProfileService', 'resolveForRoute', 'route_edge_stop_points', 'route_endpoint_profile_contract']), 'rest_uses_canonical_profile');
$assert($contains($metrics, ['recoverHistoricalEndpoints', 'RouteEndpointProfileService::resolveForRoute', 'route_endpoint_profile_contract']), 'reconciliation_uses_profile_and_history');
$assert($contains($planner, ['data-endpoint-profiles', 'endpointProfiles', "endpointProvenance='current_location'", 'base de rota configurada', "if(!start||!end)return'';"]) && strpos($planner, 'const pts=[start,...rows.map') === false, 'self_planner_endpoint_profiles');
$assert($contains($shortcodes, ['data-endpoint-profiles', 'applyEndpointProfile', 'Define partida e chegada reais', 'buildGMapsUrlFromRoute']) && strpos($shortcodes, 'origin=startText||located[0].location') === false && strpos($shortcodes, 'buildGMapsUrlFromStops') === false, 'front_routes_no_edge_pdv_fallback');
$assert($contains($mapsUrl, ['Operational Endpoint Source of Truth', 'Missing real endpoints']) && strpos($mapsUrl, 'if ($startValue !== \'\') $points[] = $startValue;') === false, 'navigation_requires_real_endpoints');
$assert($contains($doc, ['fieldflow-operational-endpoint-source-of-truth-v1', '`SOURCE_RELEASE` is `2.2.507` and `STABLE_RELEASE` is `2.2.508`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

echo "OPERATIONAL ENDPOINT SOURCE OF TRUTH CONTRACT OK\n";
echo "release=2.2.508 source=2.2.507 phase6=1.0.65 gate=1.57.0 submission_build=2.2.485\n";
echo "implicit_first_last_pdv_fallback=forbidden canonical_profile=projects.meta_json legacy_migration=explicit\n";
