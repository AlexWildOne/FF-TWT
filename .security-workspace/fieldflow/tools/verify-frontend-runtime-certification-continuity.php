<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $ok, string $name): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
};
$plugin = $read('fieldflow.php');
$assert((bool)preg_match("/define\\('FIELDFLOW_VERSION', '([^']+)'\\)/", $plugin, $match), 'release_identity_parse');
$release = (string)($match[1] ?? '');
$package = $read('frontend/package.json');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$runtimeRelative = 'assets/fieldflow-offline-runtime-' . $release . '.js';
$runtime = $read($runtimeRelative);
$canonical = $read('assets/fieldflow-offline-runtime.js');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-9P-FRONTEND-RUNTIME-CERTIFICATION-CONTINUITY.md');

$assert(version_compare($release, '2.2.532', '>='), 'successor_boundary');
$assert(strpos($package, '"version": "' . $release . '"') !== false, 'frontend_package_current_identity');
$assert(strpos($gate, '"version": "\' . $release . \""') !== false || strpos($gate, '\'"version": "\' . $release . \'"\'') !== false, 'frontend_gate_dynamic_identity');
$assert(strpos($runtime, "const RUNTIME_BUILD='" . $release . "'") !== false, 'versioned_runtime_identity');
$assert(strpos($canonical, "const RUNTIME_BUILD='" . $release . "'") !== false, 'canonical_runtime_identity');
$assert($runtime !== '' && $canonical !== '' && hash_equals(hash('sha256', $runtime), hash('sha256', $canonical)), 'canonical_versioned_runtime_parity');
$assert(strpos($responsive, 'fieldflow-offline-runtime-' . $release . '.js') !== false, 'runtime_reference_current');
$assert(strpos($validator, 'frontend_package_identity_ok') !== false && strpos($validator, 'canonical_offline_runtime_identity_ok') !== false && strpos($validator, 'frontend_runtime_certification_continuity_ok') !== false, 'release_manager_prepublication_guards');
$assert(strpos($gate, "FRONTEND_RUNTIME_CERTIFICATION_CONTINUITY_CONTRACT = 'fieldflow-frontend-runtime-certification-continuity-v1'") !== false && strpos($gate, 'checkFrontendRuntimeCertificationContinuityContract') !== false, 'release_gate_contract');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert((bool)preg_match("/SOURCE_RELEASE = '([^']+)'/", $phase, $sourceMatch), 'phase6_source_parse');
$assert((bool)preg_match("/STABLE_RELEASE = '([^']+)'/", $phase, $stableMatch), 'phase6_stable_parse');
$assert((string)($stableMatch[1] ?? '') === $release && version_compare((string)($sourceMatch[1] ?? ''), $release, '<'), 'phase6_continuity');
$assert(strpos($doc, 'fieldflow-frontend-runtime-certification-continuity-v1') !== false && strpos($doc, '`LATEST_SCHEMA_VERSION` remains `2.2.529`.') !== false, 'phase_documentation');

echo "FRONTEND RUNTIME CERTIFICATION CONTINUITY CONTRACT OK\n";
echo "successor=>=2.2.532\n";
echo "frontend_package=current_release\n";
echo "canonical_runtime=byte_identical\n";
echo "submission_build=2.2.485\n";
