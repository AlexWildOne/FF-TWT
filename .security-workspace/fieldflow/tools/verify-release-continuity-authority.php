<?php
/** Build-time verifier for the current FieldFlow Release Continuity Authority. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$containsAll = static function(string $content, array $markers): bool {
    foreach ($markers as $marker) if (strpos($content, $marker) === false) return false;
    return true;
};
$ok = true;
$assert = static function(bool $condition, string $label) use (&$ok): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) $ok = false;
};
$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-8C-RELEASE-CONTINUITY-REACT-ANALYTICS.md');


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
$assert($stable === $release && $source === $previous && version_compare($phaseVersion,'1.0.87','>='),'phase6_n_plus_one');
$assert(version_compare($gateVersion,'1.79.0','>=') && $containsAll($gate,['checkReleaseContinuityAuthorityContract','previousPatchRelease','component_gates_source_release_pinning']),'central_release_gate');
// Exercise the installed gate's central N->N+1 authority, instead of counting
// historical marker arrays that are intentionally retained for predecessors.
if (!defined('ABSPATH')) define('ABSPATH',$root.'/');
if (!defined('FIELDFLOW_VERSION')) define('FIELDFLOW_VERSION',$release);
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($value){return strip_tags((string)$value);} }
require_once $root.'/src/Cloud/Phase6StableRelease.php';
require_once $root.'/src/Support/ReleaseGate.php';
$method = new ReflectionMethod(RoutesPro\Support\ReleaseGate::class,'checkReleaseContinuityAuthorityContract');
$continuity = $method->invoke(null);
$assert(($continuity['status'] ?? '') === 'pass','historical_component_source_pinning_removed');
$assert($containsAll($validator, ['release_continuity_ok', 'previousPatchRelease', 'A continuidade N→N+1 da Phase 6']), 'package_validator_n_plus_one');
$assert($containsAll($manager, ['Release Continuity', "packageResult['release_continuity_ok']", 'N→N+1 OK']), 'release_manager_kpi');
$assert($containsAll($doc, ['single continuity authority', 'source release: `2.2.487`', 'stable release: `2.2.488`', 'No database migration']), 'phase_documentation');

if (!$ok) exit(1);
echo 'RELEASE CONTINUITY AUTHORITY CONTRACT OK' . PHP_EOL;
echo 'n_to_n_plus_one=dynamic' . PHP_EOL;
echo 'component_source_release_pinning=removed' . PHP_EOL;
