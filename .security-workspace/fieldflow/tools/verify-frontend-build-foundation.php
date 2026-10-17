<?php
/** Build-time verifier for the current FieldFlow Frontend Build & TypeScript Foundation. */
$root = dirname(__DIR__);
$checks = [];
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $condition, string $name) use (&$checks): void {
    $checks[$name] = $condition ? 'PASS' : 'FAIL';
};
$containsAll = static function(string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};

$fieldflow = $read('fieldflow.php');
$version = '';
if (preg_match("/^define\\('FIELDFLOW_VERSION',\\s*'(\\d+\\.\\d+\\.\\d+)'\\);/m", $fieldflow, $match)) $version = $match[1];
$previous = '';
if ($version !== '') {
    $parts = explode('.', $version);
    if ((int)$parts[2] > 0) $previous = $parts[0] . '.' . $parts[1] . '.' . ((int)$parts[2] - 1);
}
$readme = $read('readme.txt');
$package = $read('frontend/package.json');
$tsconfig = $read('frontend/tsconfig.json');
$build = $read('frontend/tools/build.mjs');
$types = $read('frontend/src/types.ts');
$bootstrap = $read('frontend/src/bootstrap.ts');
$generated = $read('frontend/src/version.generated.ts');
$tests = $read('frontend/tests/frontend-build.test.mjs');
$assetRelative = 'assets/fieldflow-frontend-core-' . $version . '.js';
$asset = $read($assetRelative);
$responsive = $read('src/Front/ResponsiveAssets.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$gate = $read('src/Support/ReleaseGate.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$doc = $read('PHASE-7ZR-FRONTEND-BUILD-TYPESCRIPT-FOUNDATION.md');

$assert($version !== '' && $containsAll($fieldflow, ['Version: ' . $version, "FIELDFLOW_VERSION', '" . $version . "'"]) && strpos($readme, 'Stable tag: ' . $version) !== false, 'release_version_alignment');
$source = $stable = '';
if (preg_match("/^\\s*public const SOURCE_RELEASE\\s*=\\s*'([^']+)'/m", $phase, $match)) $source = $match[1];
if (preg_match("/^\\s*public const STABLE_RELEASE\\s*=\\s*'([^']+)'/m", $phase, $match)) $stable = $match[1];
$assert($previous !== '' && $source === $previous && $stable === $version, 'phase6_release_continuity');
$gateVersion = '';
if (preg_match("/^\\s*public const VERSION\\s*=\\s*'([^']+)'/m", $gate, $match)) $gateVersion = $match[1];
$assert(version_compare($gateVersion, '1.79.0', '>=') && $containsAll($gate, ['checkFrontendBuildFoundationContract', 'fieldflow-frontend-build-foundation-v1']), 'release_gate_contract');
$packageData = json_decode($package, true);
$lockData = json_decode($read('frontend/package-lock.json'), true);
$assert(is_array($packageData) && ($packageData['name'] ?? '') === 'fieldflow-frontend' && ($packageData['version'] ?? '') === $version && ($lockData['version'] ?? '') === $version && ($lockData['packages']['']['version'] ?? '') === $version && $containsAll($package, ['"build": "node tools/build.mjs"', '"typecheck": "tsc -p tsconfig.json --noEmit"', '"test": "node --test tests/*.test.mjs"']), 'structured_frontend_package');
$assert($containsAll($tsconfig, ['"strict": true', '"module": "none"', '"noEmitOnError": true']), 'strict_typescript_config');
$assert($containsAll($build, ['FIELDFLOW_VERSION', 'version.generated.ts', 'fieldflow-frontend-core-${version}.js', 'fieldflow-frontend-build-foundation-v1']), 'version_driven_build');
$assert($version !== '' && $containsAll($generated, ["FIELD_FLOW_FRONTEND_BUILD_VERSION = '" . $version . "'"]), 'generated_version_identity');
$assert($containsAll($types, ['interface FieldFlowFrontendApi', 'interface FieldFlowFrontendSnapshot', 'interface FieldFlowOfflineApi']), 'typed_runtime_contracts');
$assert($containsAll($bootstrap, ['fieldFlowFrontendBootstrap', '__buildVersion', 'frontend:ready']), 'typed_bootstrap_source');
$assert($containsAll($tests, ['compiled asset identity matches the plugin release', 'compiled TypeScript bootstrap exposes a stable browser API and is idempotent']), 'behavioural_build_tests');
$assert($version !== '' && $containsAll($asset, ['FieldFlow ' . $version, 'fieldflow-frontend-build-foundation-v1', "FIELD_FLOW_FRONTEND_BUILD_VERSION = '" . $version . "'", "source: 'typescript'", 'fieldflow:frontend:ready']), 'typescript_bundle_identity');
$assert($version !== '' && $containsAll($responsive, ["FRONTEND_CORE_SCRIPT_HANDLE = 'fieldflow-frontend-core'", basename($assetRelative), "'context' => 'front'", "'context' => 'admin'"]), 'wordpress_asset_integration');
$assert($containsAll($validator, ['frontend_build_identity_ok', 'frontend_build_reference_ok', 'O bundle frontend TypeScript não declara a mesma identidade da release.']), 'release_manager_frontend_identity_gate');
$assert($containsAll($doc, ['fieldflow-frontend-build-foundation-v1', 'TypeScript', 'Release Manager', 'No database migration']), 'release_documentation_contract');

$failed = array_keys(array_filter($checks, static fn($status) => $status !== 'PASS'));
foreach ($checks as $name => $status) echo $status . ' ' . $name . PHP_EOL;
echo $failed ? ('FAILED ' . implode(',', $failed) . PHP_EOL) : ('FRONTEND BUILD TYPESCRIPT FOUNDATION CONTRACT OK' . PHP_EOL);
exit($failed ? 1 : 0);
