<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string) file_get_contents($path) : '';
};
$fail = static function (string $key): void { fwrite(STDERR, "FAIL {$key}\n"); exit(1); };
$assert = static function (bool $condition, string $key) use ($fail): void { if (!$condition) $fail($key); echo "PASS {$key}\n"; };
$previousPatch = static function(string $version): string {
    if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $m)) return '';
    $patch = max(0, (int)$m[3] - 1);
    return $m[1] . '.' . $m[2] . '.' . $patch;
};

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$bridge = $read('src/Mobile/WebViewBridge.php');
$migrations = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-9T-CERTIFICATION-GATE-CONTINUITY-HOTFIX.md');

$release = '';
if (preg_match("/define\\('FIELDFLOW_VERSION',\\s*'([^']+)'\\)/", $plugin, $m)) $release = (string)$m[1];
$source = '';
$stable = '';
$phaseVersion = '';
$gateVersion = '';
if (preg_match("/SOURCE_RELEASE\\s*=\\s*'([^']+)'/", $phase6, $m)) $source = (string)$m[1];
if (preg_match("/STABLE_RELEASE\\s*=\\s*'([^']+)'/", $phase6, $m)) $stable = (string)$m[1];
if (preg_match("/VERSION\\s*=\\s*'([^']+)'/", $phase6, $m)) $phaseVersion = (string)$m[1];
if (preg_match("/public const VERSION\\s*=\\s*'([^']+)'/", $gate, $m)) $gateVersion = (string)$m[1];

$assert($release !== '' && version_compare($release, '2.2.536', '>=') && strpos($plugin, 'Version: ' . $release) !== false && strpos($readme, 'Stable tag: ' . $release) !== false, 'release_identity');
$assert($stable === $release && $source === $previousPatch($release) && version_compare($phaseVersion, '1.0.93', '>='), 'phase6_continuity');
$assert(version_compare($gateVersion, '1.85.0', '>=') && strpos($gate, "CERTIFICATION_GATE_CONTINUITY_9T_CONTRACT = 'fieldflow-certification-gate-continuity-9t-v1'") !== false && strpos($gate, 'checkCertificationGateContinuity9TContract') !== false, 'release_gate_contract');
$assert(strpos($gate, "version_compare(\$nativeShellVersion, '1.9', '<')") !== false && strpos($gate, "'native_shell_min'=>'1.9'") !== false, 'native_shell_successor_semantics');
$assert(strpos($gate, "'Field Session &amp; Form Continuity'") !== false, 'field_session_manager_marker');
$assert(strpos($validator, "'certification_gate_continuity_9t_ok'=>false") !== false && strpos($validator, "version_compare((string)(\$result['version'] ?? ''), '2.2.536', '>=')") !== false, 'package_validator_successor_contract');
$assert(strpos($manager, 'Certification Gate Continuity 9T') !== false && strpos($manager, "packageResult['certification_gate_continuity_9t_ok']") !== false, 'release_manager_kpi');

$native = '';
if (preg_match("/NATIVE_SHELL_VERSION\s*=\s*'([^']+)'/", $bridge, $m)) $native = (string)$m[1];
$assert($native !== '' && version_compare($native, '1.9', '>='), 'native_shell_runtime_minimum');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema_frozen');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_frozen');
$assert(strpos($doc, 'fieldflow-certification-gate-continuity-9t-v1') !== false && strpos($doc, '`SOURCE_RELEASE` is `2.2.535` and `STABLE_RELEASE` is `2.2.536`') !== false, 'phase_documentation');
$assert(version_compare($release, '2.2.536', '>='), 'successor_semantics');

echo "CERTIFICATION GATE CONTINUITY 9T CONTRACT OK\n";
echo "release={$release}\n";
echo "contract=fieldflow-certification-gate-continuity-9t-v1\n";
echo "native_shell_min=1.9\n";
echo "successor=>=2.2.536\n";
echo "schema=2.2.529\n";
echo "submission_build=2.2.485\n";
