<?php
/** Build-time verifier for Certification Asset Successor Continuity. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$failures = [];
$assert = static function(bool $ok, string $label) use (&$failures): void { if (!$ok) $failures[] = $label; };
$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$migrations = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9J-CERTIFICATION-ASSET-SUCCESSOR-CONTINUITY.md');
$version = '';
if (preg_match("/FIELDFLOW_VERSION['\"]?\\s*,\\s*['\"]([^'\"]+)/", $plugin, $m)) $version = (string)$m[1];
$parts = array_map('intval', explode('.', $version));
$source = count($parts) === 3 && $parts[2] > 0 ? $parts[0] . '.' . $parts[1] . '.' . ($parts[2] - 1) : '';
$frontend = $read('assets/fieldflow-frontend-core-' . $version . '.js');
$react = $read('assets/fieldflow-react-front-' . $version . '.js');
$assert($version !== '' && strpos($readme, 'Stable tag: ' . $version) !== false, 'release_identity');
$assert($source !== '' && strpos($phase, "SOURCE_RELEASE = '" . $source . "'") !== false && strpos($phase, "STABLE_RELEASE = '" . $version . "'") !== false, 'phase6_n_plus_one');
$assert(strpos($gate, "ASSET_SUCCESSOR_CONTRACT = 'fieldflow-certification-asset-successor-continuity-v1'") !== false, 'release_gate_contract');
$assert(strpos($gate, "'assets/fieldflow-frontend-core-' . \$release . '.js'") !== false, 'frontend_gate_current_asset');
$assert(strpos($gate, "'assets/fieldflow-react-front-' . \$release . '.js'") !== false, 'react_gate_current_asset');
$assert($frontend !== '' && strpos($frontend, 'FieldFlow ' . $version) !== false && strpos($frontend, 'fieldflow-frontend-build-foundation-v1') !== false, 'frontend_asset_identity');
$assert($react !== '' && strpos($react, 'FieldFlow ' . $version) !== false && strpos($react, 'fieldflow-react-client-wave1-v1') !== false, 'react_asset_identity');
$assert(strpos($responsive, 'fieldflow-frontend-core-' . $version . '.js') !== false && strpos($responsive, 'fieldflow-react-front-' . $version . '.js') !== false, 'responsive_current_assets');
$assert(strpos($validator, "version_compare((string)(\$result['version'] ?? ''), '2.2.525', '>=')") !== false, 'outbox_successor_validation');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.525'") !== false, 'schema_unchanged');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert(strpos($manager, 'Asset Successor Continuity') !== false, 'release_manager_visibility');
$assert(strpos($doc, 'fieldflow-certification-asset-successor-continuity-v1') !== false && strpos($doc, 'No database schema change.') !== false, 'phase_documentation');
if ($failures) {
    fwrite(STDERR, "CERTIFICATION ASSET SUCCESSOR CONTINUITY CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "CERTIFICATION ASSET SUCCESSOR CONTINUITY CONTRACT OK\n";
echo "release={$version}\n";
echo "contract=fieldflow-certification-asset-successor-continuity-v1\n";
echo "frontend_gate=current_release_asset\n";
echo "react_gate=current_release_asset\n";
echo "outbox_successor=>=2.2.525\n";
echo "schema_change=no\n";
echo "submission_build=2.2.485\n";
