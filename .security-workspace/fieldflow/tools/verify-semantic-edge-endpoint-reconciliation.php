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
if (!function_exists('remove_accents')) { function remove_accents($v){ return (string)$v; } }

require_once $root . '/src/Services/RouteEndpointResolverService.php';
require_once $root . '/src/Services/RouteEndpointProfileService.php';

use RoutesPro\Services\RouteEndpointResolverService;
use RoutesPro\Services\RouteEndpointProfileService;

$edge = ['address'=>'Rua da Loja, 10 - Porto','lat'=>41.1500,'lng'=>-8.6100];
$sameAddressDrifted = ['address'=>'Rua da Loja 10 Porto','lat'=>41.1512,'lng'=>-8.6100,'provenance'=>'legacy'];
$differentNearby = ['address'=>'Rua Vizinha 22 Porto','lat'=>41.1512,'lng'=>-8.6100,'provenance'=>'route_existing'];
$normalEnd = ['address'=>'Base Sul','lat'=>41.2000,'lng'=>-8.7000,'provenance'=>'operator_profile'];

$assert(RouteEndpointResolverService::pointsCoincide($sameAddressDrifted, $edge), 'same_normalized_address_wins_over_coordinate_drift');
$integrity = RouteEndpointResolverService::integrity($sameAddressDrifted, $normalEnd, $edge, []);
$assert(($integrity['endpoint_status'] ?? '') === 'suspicious_start', 'same_address_drift_is_legacy_edge_suspicion');
$assert((float)($integrity['start_to_first_air_km'] ?? 0) > RouteEndpointResolverService::COINCIDENCE_KM, 'test_exceeds_30m_threshold');
$assert(!RouteEndpointResolverService::pointsCoincide($differentNearby, $edge), 'different_address_does_not_widen_geometric_threshold');

$method = new ReflectionMethod(RouteEndpointProfileService::class, 'isTrustedRouteCandidate');
$method->setAccessible(true);
$assert($method->invoke(null, $sameAddressDrifted, $edge, 'start') === false, 'contaminated_route_candidate_rejected');
$assert($method->invoke(null, $differentNearby, $edge, 'start') === true, 'legitimate_nearby_route_candidate_preserved');
$sameAddressDrifted['allow_matches_stop'] = 1;
$assert($method->invoke(null, $sameAddressDrifted, $edge, 'start') === true, 'explicit_zero_leg_confirmation_preserved');

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$resolver = $read('src/Services/RouteEndpointResolverService.php');
$profiles = $read('src/Services/RouteEndpointProfileService.php');
$metrics = $read('src/Services/RouteMetricsService.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8Y-SEMANTIC-EDGE-ENDPOINT-RECONCILIATION.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkSemanticEdgeEndpointReconciliationContract', 'fieldflow-semantic-edge-endpoint-reconciliation-v1']), 'release_gate_contract');
$assert($contains($resolver, ['SEMANTIC_EDGE_CONTRACT', 'fieldflow-semantic-edge-endpoint-reconciliation-v1', 'Address identity must win over', 'pointsCoincide']), 'semantic_edge_resolver');
$assert($contains($profiles, ['isTrustedRouteCandidate', 'RouteEndpointResolverService::pointsCoincide', 'operator profile -> campaign default']), 'profile_fallback_path');
$assert($contains($metrics, ['repairPlannedRouteIntegrity', 'suspicious_endpoint_count', 'RouteEndpointProfileService::resolveForRoute']), 'reconciliation_path');
$assert($contains($doc, ['fieldflow-semantic-edge-endpoint-reconciliation-v1', '`SOURCE_RELEASE` is `2.2.509` and `STABLE_RELEASE` is `2.2.510`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.', 'No widening of the 30 metre coordinate coincidence threshold.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

echo "SEMANTIC EDGE ENDPOINT RECONCILIATION CONTRACT OK\n";
echo "release=2.2.517 source=2.2.509 phase6=1.0.67 gate=1.59.0 submission_build=2.2.485\n";
echo "same_address_coordinate_drift=rejected_as_legacy different_nearby_address=preserved threshold_km=0.03\n";
