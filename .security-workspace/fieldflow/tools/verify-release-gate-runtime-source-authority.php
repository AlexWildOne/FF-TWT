<?php
/** Build-time verifier for current FieldFlow Release Gate Runtime Source Authority. */
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
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$metrics = $read('src/Services/RouteMetricsService.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8ZC-RELEASE-GATE-RUNTIME-SOURCE-AUTHORITY.md');


$release = '';
$source = '';
$stable = '';
$phaseVersion = '';
$gateVersion = '';
if (preg_match("/define\\('FIELDFLOW_VERSION',\\s*'([^']+)'\\)/", $plugin, $m)) $release = $m[1];
if (preg_match("/public const SOURCE_RELEASE\\s*=\\s*'([^']+)'/", $phase, $m)) $source = $m[1];
if (preg_match("/public const STABLE_RELEASE\\s*=\\s*'([^']+)'/", $phase, $m)) $stable = $m[1];
if (preg_match("/public const VERSION\\s*=\\s*'([^']+)'/", $phase, $m)) $phaseVersion = $m[1];
if (preg_match("/public const VERSION\\s*=\\s*'([^']+)'/", $gate, $m)) $gateVersion = $m[1];
$previous = '';
if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $release, $m) && (int)$m[3] > 0) $previous = $m[1].'.'.$m[2].'.'.((int)$m[3]-1);

$assert($release !== '' && version_compare($release,'2.2.530','>=') && $containsAll($plugin,['Version: '.$release,"FIELDFLOW_VERSION', '".$release."'"]) && strpos($readme,'Stable tag: '.$release)!==false,'release_identity');
$assert($stable === $release && $source === $previous && version_compare($phaseVersion,'1.0.87','>='),'phase6_continuity');
$assert(version_compare($gateVersion,'1.79.0','>=') && $containsAll($gate,['checkReleaseGateRuntimeSourceAuthorityContract','fieldflow-release-gate-runtime-source-authority-v1']),'release_gate_contract');
$assert($containsAll($gate, ['inspectCanonicalRuntimeMethod', 'inspectCanonicalRuntimeConstant', 'class_exists($class, false)', 'realpath($runtimeFile)', 'runtime_origin_mismatch']), 'canonical_source_authority');
$assert($containsAll($gate, ['stale_runtime_non_blocking', 'opcache_invalidate', "'runtime_stale'=>false"]), 'stale_canonical_runtime_non_blocking');
$assert(strpos($gate, "if (\$runtimeReal === '' || \$runtimeReal !== \$canonicalReal)") !== false, 'wrong_runtime_origin_blocks');
$assert(strpos($metrics, "repairPlannedRouteIntegrity(int \$projectId, int \$ownerUserId = 0, string \$dateFrom = '', string \$dateTo = '', int \$actorUserId = 0): array") !== false, 'legacy_signature_source');
$assert($containsAll($doc, ['fieldflow-release-gate-runtime-source-authority-v1','No P1-P4 routing or optimisation-rule change.','No database schema change.','No certification gate is bypassed or auto-certified.']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_pipeline_unchanged');

echo "RELEASE GATE RUNTIME SOURCE AUTHORITY CONTRACT OK\n";
echo "canonical_source_authority=installed_source runtime_origin=guarded stale_canonical_runtime_non_blocking=1 wrong_runtime_origin_blocks=1\n";
