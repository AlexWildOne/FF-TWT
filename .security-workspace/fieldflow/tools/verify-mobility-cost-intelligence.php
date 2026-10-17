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
if (!function_exists('current_time')) { function current_time($type){ return $type === 'mysql' ? '2026-09-19 12:00:00' : time(); } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }

require_once $root . '/src/Services/MobilityCostService.php';

use RoutesPro\Services\MobilityCostService;

$diesel = MobilityCostService::normalizeProfile([
    'enabled'=>1,'energy_type'=>'diesel','fuel_type'=>'gasoline',
    'fuel_price_eur_per_l'=>1.70,'consumption_l_per_100km'=>5.5,
]);
$assert($diesel['fuel_type'] === 'diesel', 'direct_fuel_type_is_canonical');
$assert(MobilityCostService::profileIssues($diesel) === [], 'diesel_profile_ready');
$dieselCost = MobilityCostService::estimate(180.0, 7.40, $diesel);
$assert(abs((float)$dieselCost['estimated_litres'] - 9.9) < 0.001, 'diesel_litres');
$assert(abs((float)$dieselCost['energy_cost_eur'] - 16.83) < 0.01, 'diesel_energy_cost');
$assert(abs((float)$dieselCost['total_mobility_cost_eur'] - 24.23) < 0.01, 'diesel_total_cost');

$electric = MobilityCostService::normalizeProfile([
    'enabled'=>1,'energy_type'=>'electric',
    'electricity_price_eur_per_kwh'=>0.20,'consumption_kwh_per_100km'=>18,
]);
$assert(MobilityCostService::profileIssues($electric) === [], 'electric_profile_ready');
$electricCost = MobilityCostService::estimate(100.0, 0.0, $electric);
$assert(abs((float)$electricCost['estimated_kwh'] - 18.0) < 0.001, 'electric_kwh');
$assert(abs((float)$electricCost['energy_cost_eur'] - 3.60) < 0.01, 'electric_cost');
$assert((string)$electricCost['fuel_type'] === '', 'electric_has_no_fuel_label');

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$service = $read('src/Services/MobilityCostService.php');
$assignments = $read('src/Admin/Assignments.php');
$campaign = $read('src/Admin/CampaignLocations.php');
$routes = $read('src/Admin/Routes.php');
$repository = $read('src/Repositories/RouteAccessRepository.php');
$controller = $read('src/Rest/RoutesController.php');
$portal = $read('src/Front/ClientPortalShortcodeRenderer.php');
$stats = $read('src/Rest/StatsController.php');
$shortcodes = $read('src/Shortcodes.php');
$exports = $read('src/Admin/FormSubmissions.php');
$routeMetrics = $read('src/Services/RouteMetricsService.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8Q-MOBILITY-COST-INTELLIGENCE.md');

$assert(preg_match('/Version:\s*(\d+\.\d+\.\d+)/', $plugin, $vm) === 1, 'release_identity_header');
$currentVersion = (string)($vm[1] ?? '');
$assert($currentVersion !== '' && version_compare($currentVersion, '2.2.502', '>=') && strpos($plugin, "FIELDFLOW_VERSION', '" . $currentVersion . "'") !== false && strpos($readme, 'Stable tag: ' . $currentVersion) !== false, 'release_identity');
$assert(strpos($phase, "STABLE_RELEASE = '" . $currentVersion . "'") !== false, 'phase6_continuity');
$assert($contains($gate, ['checkMobilityCostIntelligenceContract', 'fieldflow-mobility-cost-intelligence-v1']), 'release_gate_contract');
$assert($contains($service, ["CONTRACT = 'fieldflow-mobility-cost-intelligence-v1'", 'mobility_cost_default', 'mobility_cost_profiles', 'mobility_cost_snapshot', 'profileIssues', 'backfillRoutes', 'total_mobility_cost_eur']), 'mobility_service');
$assert($contains($assignments, ["'mobility' => 'Mobilidade'", 'save_mobility_profile', 'backfill_mobility_routes', 'save_route_mobility_override', 'Aplicar perfil às rotas existentes']), 'assignment_center');
$assert($contains($campaign, ['Mobilidade e Energia', 'routespro-live-energy-cost', 'routespro-manual-live-energy', 'MobilityCostService::ensureRouteSnapshot']), 'campaign_planner');
$assert($contains($routes, ['mobility_cost_snapshot', 'Custo deslocação total EUR', 'Perfil mobilidade']), 'route_admin_export');
$assert($contains($repository, ["['mobility_cost']", 'calculateForRouteArray']) && $contains($controller, ["'mobility_cost'", 'ensureRouteSnapshot']), 'route_api');
$assert($contains($portal, ['routeMobility', 'Combustível / energia', 'Custo deslocação']), 'front_route_cost');
$assert($contains($stats, ["'mobility_summary'", 'energy_cost_eur', 'total_mobility_cost_eur']) && $contains($shortcodes, ['mobility_summary', 'Combustível / energia', 'Custo deslocação']), 'analytics_runtime');
$assert($contains($exports, ['Combustível / energia estimado', 'Perfil mobilidade', 'Custo deslocação EUR', "'Mobilidade'"]), 'analytics_exports');
$assert($contains($validator, ['mobility_cost_intelligence_ok', 'Mobility Cost Intelligence']) && $contains($manager, ['Mobility Cost Intelligence', "packageResult['mobility_cost_intelligence_ok']"]), 'release_visibility');
$assert(strpos($routeMetrics, "'metrics_version' => 'fieldflow_route_metrics_v5'") !== false, 'canonical_route_metrics_preserved');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert($contains($doc, ['fieldflow-mobility-cost-intelligence-v1', '`SOURCE_RELEASE` is `2.2.501` and `STABLE_RELEASE` is `2.2.502`', 'No P1-P4 routing or optimisation-rule change.', 'No new database table.']), 'phase_documentation');

echo "MOBILITY COST INTELLIGENCE CONTRACT OK\n";
echo "release={$currentVersion} successor_safe=1 submission_build=2.2.485\n";
echo "diesel_example=180km/9.9L/16.83EUR total_with_tolls=24.23EUR\n";
echo "storage=existing_meta_json routing_owner=RouteMetricsService\n";
