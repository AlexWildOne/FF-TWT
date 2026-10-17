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

$plugin = $read('fieldflow.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$css = $read('assets/fieldflow-react-front.css');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8O-RELEASE-GATE-HISTORICAL-MARKER-HARDENING.md');

$version = '';
if (preg_match('/Version:\s*([0-9.]+)/', $plugin, $m)) $version = (string)$m[1];
$sourceRelease = '';
$stableRelease = '';
if (preg_match("/SOURCE_RELEASE = '([^']+)'/", $phase, $m)) $sourceRelease = (string)$m[1];
if (preg_match("/STABLE_RELEASE = '([^']+)'/", $phase, $m)) $stableRelease = (string)$m[1];
$gateVersion = '';
if (preg_match("/public const VERSION = '([^']+)'/", $gate, $m)) $gateVersion = (string)$m[1];
$assert($version !== '' && version_compare($version, '2.2.500', '>=' ) && strpos($plugin, "FIELDFLOW_VERSION', '" . $version . "'") !== false, 'plugin_identity');
$assert($stableRelease === $version && $sourceRelease !== '' && version_compare($sourceRelease, $stableRelease, '<'), 'phase6_continuity');
$assert($gateVersion !== '' && version_compare($gateVersion, '1.56.0', '>=') && $contains($gate, ['fieldflow-release-gate-historical-marker-v1', "'Phase 8J'", "'Phase 8K'"]), 'release_gate_contract');
$assert(strpos($gate, "'Phase 8J, ' . \$release") === false, 'phase8j_not_version_bound');
$assert(strpos($gate, "'Phase 8K, ' . \$release") === false, 'phase8k_not_version_bound');
$assert($contains($css, ['Phase 8J', 'fieldflow-react-operational-modules-v1', 'Phase 8K', 'fieldflow-react-operational-actions-hardening-v1']), 'historical_css_markers_preserved');
$assert($contains($validator, ['release_gate_historical_markers_ok', 'fieldflow-release-gate-historical-marker-v1']), 'package_validator_contract');
$assert($contains($manager, ['Historical Gate Markers', "packageResult['release_gate_historical_markers_ok']"]), 'release_manager_visibility');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert($contains($doc, ['fieldflow-release-gate-historical-marker-v1', '`SOURCE_RELEASE` is `2.2.499` and `STABLE_RELEASE` is `2.2.500`', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.']), 'phase_documentation');

echo "RELEASE GATE HISTORICAL MARKER HARDENING CONTRACT OK\n";
echo "successor=>=2.2.500 runtime=" . $version . " source=" . $sourceRelease . " gate=" . $gateVersion . " submission_build=2.2.485\n";
