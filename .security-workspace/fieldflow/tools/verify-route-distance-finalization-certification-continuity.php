<?php
/** Build-time verifier for FieldFlow 2.2.530 Route Distance Finalization & Certification Continuity. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$containsAll = static function(string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};
$assert = static function(bool $ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
};

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$metrics = $read('src/Services/RouteMetricsService.php');
$assignments = $read('src/Admin/Assignments.php');
$licenseUi = $read('src/Admin/LicenseTenants.php');
$foundation = $read('src/Cloud/LicenseTenantFoundation.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8ZB-ROUTE-DISTANCE-FINALIZATION-CERTIFICATION-CONTINUITY.md');

$assert($containsAll($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($containsAll($phase6, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'checkRouteDistanceFinalizationCertificationContinuityContract', 'fieldflow-route-distance-finalization-certification-continuity-v1']), 'release_gate_contract');
$assert($containsAll($metrics, [
    "DISTANCE_FINALIZATION_CONTRACT = 'fieldflow-route-distance-finalization-v1'",
    'self::maybeRefreshGoogleRoute($routeId, [], true)',
    "'routing_finalized_by'=>'google_routes_sequence'",
    "'routing_finalized_by' => 'routing_matrix_preview'",
    "'google_exact'=>0",
    "'matrix_fallback'=>0",
    "'distance_component_delta_km'",
    'GoogleRoutes::calculateRoute($points)',
    'ORDER BY rs.seq ASC, rs.id ASC',
]), 'ordered_google_distance_finalization');
$assert($containsAll($assignments, ['distance_google_exact','distance_matrix_fallback','distance_google_exact_failed','Google exato','fallback matriz']), 'reconciliation_diagnostics_ui');
$assert($containsAll($foundation, ['certification_state_present','certification_requires_revalidation']), 'foundation_certification_history');
$assert($containsAll($phase6, ['certification_state_present','certification_requires_revalidation']), 'phase6_certification_history');
$assert($containsAll($licenseUi, ['certificationVisualState','CERTIFICADA, REVALIDAR','Certificação histórica preservada.','Revalidar cadeia 6B pós-update']), 'certification_revalidation_ui');
$assert($containsAll($doc, ['fieldflow-route-distance-finalization-certification-continuity-v1','No P1-P4 routing or optimisation-rule change.','No database schema change.','No certification gate is bypassed or auto-certified.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_pipeline_unchanged');

echo "ROUTE DISTANCE FINALIZATION CERTIFICATION CONTINUITY CONTRACT OK\n";
