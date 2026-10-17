<?php
/** 9DA: standalone fail-closed preflight test; no WordPress or cloud access. */
define('ABSPATH', '/tmp/');
function wp_strip_all_tags($text) {return strip_tags((string)$text);}
define('ROUTESPRO_PATH', dirname(__DIR__) . '/');
$main = (string)file_get_contents(ROUTESPRO_PATH . 'fieldflow.php');
if (!preg_match("/define\\('FIELDFLOW_VERSION', '([^']+)'\\)/", $main, $m)) exit(1);
define('FIELDFLOW_VERSION', $m[1]);
require_once ROUTESPRO_PATH . 'src/Support/ReleasePreflight.php';
require_once ROUTESPRO_PATH . 'src/Support/ReleaseGate.php';

$assert = static function (bool $test, string $why): void {
    if (!$test) { fwrite(STDERR, "9DA FAIL: {$why}\n"); exit(1); }
};
$sha = static function(string $text): string { return hash('sha256', $text); };
$base = [
    'version'=>'2.2.634',
    'files'=>[
        'fieldflow.php'=>['sha256'=>$sha('old'),'bytes'=>3],
        'src/Services/Routes.php'=>['sha256'=>$sha('routes'),'bytes'=>6],
        'src/Support/LicenseManager.php'=>['sha256'=>$sha('kept'),'bytes'=>4],
    ],
];
$package = [
    'ok'=>true, 'version'=>'2.2.635', 'sha256'=>$sha('release'), 'bytes'=>19500,
    'manifest_file_metadata'=>[
        'fieldflow.php'=>['sha256'=>$sha('new'),'bytes'=>3],
        'src/Services/Routes.php'=>['sha256'=>$sha('routes'),'bytes'=>6],
        'src/Support/ReleasePreflight.php'=>['sha256'=>$sha('added'),'bytes'=>5],
    ],
];
$preflight = \RoutesPro\Support\ReleasePreflight::evaluate($package, '2.2.634', $base, true);
$assert(!empty($preflight['can_publish']) && !empty($preflight['diff_available']), 'valid exact successor preflight');
$assert($preflight['counts'] === ['added'=>1,'modified'=>1,'removed'=>1,'unchanged'=>1], 'manifest file differences');
$assert((bool)array_filter($preflight['changed'], static function($item){return $item['path']==='fieldflow.php' && $item['sensitive'];}), 'sensitive marker');
$assert(count($preflight['warnings'])>0, 'warn on sensitive/removal');

$wrong = $package; $wrong['version']='2.2.636';
$bad = \RoutesPro\Support\ReleasePreflight::evaluate($wrong, '2.2.634', $base, true);
$assert(empty($bad['can_publish']) && ($bad['diagnostics'][0]['code'] ?? '') === 'SUCCESSION', 'block skipped version');
$wrong = $package; $wrong['sha256']='bad-sha';
$bad = \RoutesPro\Support\ReleasePreflight::evaluate($wrong, '2.2.634', $base, true);
$assert(empty($bad['can_publish']) && count($bad['diagnostics'])>0, 'block nonsha');
$wrong = ['ok'=>false, 'version'=>'2.2.635', 'error'=>'9D9 incomplete'];
$bad = \RoutesPro\Support\ReleasePreflight::evaluate($wrong, '2.2.634', $base, true);
$assert(empty($bad['can_publish']) && empty($bad['diff_available']) && strpos($bad['diagnostics'][0]['detail'], '9D9')!==false, 'fail closed original validator');
$assert(\RoutesPro\Support\ReleasePreflight::CONTRACT === 'fieldflow-release-manager-assisted-preflight-9da-v1', 'contract stable');

$gate = new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class, 'checkReleaseManagerAssistedPreflight9DAContract');
$status = $gate->invoke(null);
$assert(($status['status'] ?? '') === 'pass' && empty($status['meta']['missing']), '9DA runtime gate');
$continuity = new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class, 'checkOperativeClientDockParity9D9Contract');
$status = $continuity->invoke(null);
$assert(($status['status'] ?? '') === 'pass', '9D9 runtime gate preserved');

// Historical validator pin may only survive as inert Phase6 comment.
$source = (string)file_get_contents(ROUTESPRO_PATH . 'src/Cloud/Phase6StableRelease.php');
$parts = explode('.', FIELDFLOW_VERSION);
$previous = count($parts) === 3 ? $parts[0] . '.' . $parts[1] . '.' . ((int)$parts[2] - 1) : '';
$assert($previous !== '' && strpos($source, "public const SOURCE_RELEASE = '" . $previous . "';") !== false, 'active Phase6 predecessor');
$assert(strpos($source, "public const STABLE_RELEASE = '" . FIELDFLOW_VERSION . "';") !== false, 'active Phase6 candidate');
$assert(strpos($source, "// 9D9 predecessor: public const SOURCE_RELEASE = '2.2.633';") !== false, 'historical source marker inert');
$assert(strpos($source, "// 9D9 predecessor: public const STABLE_RELEASE = '2.2.634';") !== false, 'historical stable marker inert');
$validator = (string)file_get_contents(ROUTESPRO_PATH . 'src/Support/ReleasePackageValidator.php');
$assert(strpos($validator, "'src/Cloud/Phase6StableRelease.php' => [\n                    \"public const SOURCE_RELEASE = '2.2.633';\"") === false, '9D9 pin removed from successor validator');

echo "RELEASE MANAGER ASSISTED PREFLIGHT 9DA PASS\n";
