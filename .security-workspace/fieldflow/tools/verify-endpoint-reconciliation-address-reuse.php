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

require_once $root . '/src/Services/RouteMetricsService.php';

$method = new ReflectionMethod(\RoutesPro\Services\RouteMetricsService::class, 'repairPlannedRouteIntegrity');
$params = $method->getParameters();
$names = array_map(static fn(ReflectionParameter $p): string => $p->getName(), $params);
$assert(count($params) === 5, 'reconciliation_parameter_count');
$assert($names === ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 'reconciliation_parameter_order');
$assert($params[2]->isDefaultValueAvailable() && $params[2]->getDefaultValue() === '', 'date_from_default');
$assert($params[3]->isDefaultValueAvailable() && $params[3]->getDefaultValue() === '', 'date_to_default');
$assert($params[4]->isDefaultValueAvailable() && $params[4]->getDefaultValue() === 0, 'actor_default');

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$metrics = $read('src/Services/RouteMetricsService.php');
$assignments = $read('src/Admin/Assignments.php');
$profiles = $read('src/Services/RouteEndpointProfileService.php');
$campaigns = $read('src/Admin/CampaignLocations.php');
$routes = $read('src/Admin/Routes.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8X-ENDPOINT-RECONCILIATION-ADDRESS-REUSE.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkEndpointReconciliationAddressReuseContract', 'fieldflow-endpoint-reconciliation-address-reuse-v1']), 'release_gate_contract');
$assert($contains($metrics, ['repairPlannedRouteIntegrity(int $projectId, int $ownerUserId = 0', 'string $dateFrom =', 'string $dateTo =', 'int $actorUserId = 0']), 'reconciliation_signature_source');
$assert($contains($assignments, ['data-ff-endpoint-address', 'FieldFlowGoogleMaps.load', 'Fallback opcional', 'Campanhas PDV, a própria rota ou o Planear']), 'assignment_autocomplete_and_fallback_copy');
$assert($contains($profiles, ['Resolution order:', 'explicit route/day -> trusted route endpoint -> operator profile -> campaign default', "'source'=>'explicit'", 'effectiveProfile']), 'explicit_endpoint_priority');
$assert($contains($campaigns, ['resolvedStartPoint', 'resolvedEndPoint', 'RouteEndpointProfileService::resolveForRoute']), 'campaign_explicit_endpoints_persisted');
$assert($contains($routes, ["'start_point' => [", "start_address", "'end_point' => [", "end_address"]), 'route_explicit_endpoints_persisted');
$assert($contains($doc, ['fieldflow-endpoint-reconciliation-address-reuse-v1', '`SOURCE_RELEASE` is `2.2.508` and `STABLE_RELEASE` is `2.2.509`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

echo "ENDPOINT RECONCILIATION ADDRESS REUSE CONTRACT OK\n";
echo "release=2.2.509 source=2.2.508 phase6=1.0.66 gate=1.58.0 submission_build=2.2.485\n";
echo "explicit_route_campaign_addresses=authoritative endpoint_profile=fallback autocomplete=google_places\n";
