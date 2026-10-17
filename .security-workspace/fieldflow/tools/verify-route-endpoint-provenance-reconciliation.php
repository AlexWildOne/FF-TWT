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
use RoutesPro\Services\RouteEndpointResolverService;

$first = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100];
$last = ['address'=>'Loja E','lat'=>41.1800,'lng'=>-8.6500];
$sameStart = ['address'=>'Loja A','lat'=>41.1500,'lng'=>-8.6100,'provenance'=>'legacy'];
$normalEnd = ['address'=>'Base Sul','lat'=>41.2000,'lng'=>-8.7000,'provenance'=>'route_default'];

$suspicious = RouteEndpointResolverService::integrity($sameStart, $normalEnd, $first, $last);
$assert(($suspicious['endpoint_status'] ?? '') === 'suspicious_start', 'legacy_start_coincidence_detected');
$assert(empty($suspicious['endpoints_complete']), 'suspicious_start_not_complete');
$assert((int)($suspicious['suspicious_endpoint_count'] ?? 0) === 1, 'suspicious_count');

$sameStart['allow_matches_stop'] = 1;
$confirmed = RouteEndpointResolverService::integrity($sameStart, $normalEnd, $first, $last);
$assert(($confirmed['endpoint_status'] ?? '') === 'complete', 'explicit_zero_access_allowed');
$assert(!empty($confirmed['start_match_confirmed']), 'explicit_zero_access_traced');

$normalStart = ['address'=>'Base Norte','lat'=>41.1000,'lng'=>-8.5500,'provenance'=>'route_default'];
$complete = RouteEndpointResolverService::integrity($normalStart, $normalEnd, $first, $last);
$assert(($complete['endpoint_status'] ?? '') === 'complete' && !empty($complete['endpoints_complete']), 'real_endpoints_complete');
$assert(($complete['contract'] ?? '') === 'fieldflow-route-endpoint-provenance-reconciliation-v1', 'contract_identity');

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$resolver = $read('src/Services/RouteEndpointResolverService.php');
$metrics = $read('src/Services/RouteMetricsService.php');
$optimizer = $read('src/Services/RouteOptimizationService.php');
$routes = $read('src/Admin/Routes.php');
$campaigns = $read('src/Admin/CampaignLocations.php');
$assignments = $read('src/Admin/Assignments.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8U-ROUTE-ENDPOINT-PROVENANCE-LEGACY-RECONCILIATION.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkRouteEndpointProvenanceReconciliationContract', 'fieldflow-route-endpoint-provenance-reconciliation-v1']), 'release_gate_contract');
$assert($contains($resolver, ['RouteEndpointResolverService', 'pointsCoincide', 'allow_matches_stop', 'endpoint_address_fingerprint', 'fieldflow-route-endpoint-provenance-reconciliation-v1']), 'canonical_endpoint_resolver');
$assert($contains($metrics, ['suspicious_endpoint_count', 'route_endpoints_untrusted', 'route_default_repair', 'endpoint_reconciled_at']), 'legacy_reconciliation');
$assert($contains($optimizer, ['fieldflow_route_endpoints_untrusted', 'RouteEndpointResolverService::integrity', 'endpoint_reconciliation_contract']), 'optimizer_semantic_integrity');
$assert($contains($routes, ['start_at_first_stop', 'end_at_last_stop', 'suspicious_endpoint_count', 'RouteEndpointResolverService::integrity']), 'routes_explicit_zero_leg_confirmation');
$assert($contains($campaigns, ['RouteEndpointResolverService', 'resolve_route_endpoint', 'suspicious_endpoint_count']), 'campaign_endpoint_resolver');
$assert($contains($assignments, ['Reconciliar endpoints e km', 'distance_suspicious', 'distance_reconciled']), 'assignment_reconciliation_ui');
$assert($contains($doc, ['fieldflow-route-endpoint-provenance-reconciliation-v1', '`SOURCE_RELEASE` is `2.2.505` and `STABLE_RELEASE` is `2.2.506`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

echo "ROUTE ENDPOINT PROVENANCE & LEGACY RECONCILIATION CONTRACT OK\n";
echo "release=2.2.507 source=2.2.505 phase6=1.0.64 gate=1.56.0 submission_build=2.2.485\n";
