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
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
};

$plugin = $read('fieldflow.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$responsive = $read('src/Front/ResponsiveAssets.php');

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

$runtime = $read('assets/fieldflow-offline-runtime-'.$release.'.js');
$frontend = $read('assets/fieldflow-frontend-core-'.$release.'.js');
$reactFront = $read('assets/fieldflow-react-front-'.$release.'.js');
$reactBackoffice = $read('assets/fieldflow-react-backoffice-'.$release.'.js');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-8N-CERTIFICATION-RUNTIME-IDENTITY-HOTFIX.md');


$assert($release !== '' && version_compare($release,'2.2.530','>=') && $contains($plugin,['Version: '.$release,"define('FIELDFLOW_VERSION', '".$release."')"]),'plugin_identity');
$assert($stable === $release && $source === $previous && version_compare($phaseVersion,'1.0.87','>='),'phase6_continuity');
$assert(version_compare($gateVersion,'1.79.0','>=') && $contains($gate,['checkCertificationRuntimeIdentityContract','fieldflow-certification-runtime-identity-v1']),'release_gate_contract');
$assert($contains($gate, ["RUNTIME_IDENTITY_SUCCESSOR_CONTRACT = 'fieldflow-runtime-identity-successor-v1'", 'currentOfflineRuntimeRelative', 'currentOfflineRuntimeMarker', 'RUNTIME_IDENTITY_2526_VALIDATOR_BRIDGE']), 'release_gate_runtime_successor_contract');
$assert($contains($responsive, ['fieldflow-offline-runtime-'.$release.'.js','fieldflow-frontend-core-'.$release.'.js','fieldflow-react-front-'.$release.'.js','fieldflow-react-backoffice-'.$release.'.js']), 'responsive_release_assets');
$assert(strpos($responsive, 'fieldflow-offline-runtime-2.2.500.js') === false, 'responsive_no_stale_runtime');
$assert($contains($runtime, ["const RUNTIME_BUILD='".$release."'", '__runtimeBuild:RUNTIME_BUILD', 'fieldflow-resumable-media-v1']), 'offline_runtime_identity');
$assert($contains($frontend, ['FieldFlow '.$release, "FIELD_FLOW_FRONTEND_BUILD_VERSION = '".$release."'", 'fieldflow-typed-offline-core-v1']), 'frontend_identity');
$assert($contains($reactFront, ['FieldFlow '.$release, "FIELD_FLOW_FRONTEND_BUILD_VERSION = '".$release."'", 'fieldflow-react-form-completion-v1']), 'react_front_identity');
$assert($contains($reactBackoffice, ['FieldFlow '.$release, "FIELD_FLOW_FRONTEND_BUILD_VERSION = '".$release."'", 'fieldflow-react-backoffice-foundation-v1']), 'react_backoffice_identity');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert($contains($doc, ['fieldflow-certification-runtime-identity-v1','No IndexedDB schema change.','No resumable media protocol change.','`SOURCE_RELEASE` is `2.2.498` and `STABLE_RELEASE` is `2.2.499`']), 'phase_documentation');

echo "CERTIFICATION RUNTIME IDENTITY HOTFIX CONTRACT OK\n";
echo "runtime={$release} source={$source} phase6={$phaseVersion} gate={$gateVersion} successor=dynamic submission_build=2.2.485\n";
