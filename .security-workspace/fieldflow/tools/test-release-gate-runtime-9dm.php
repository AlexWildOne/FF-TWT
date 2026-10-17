<?php
/** 9DM: test installed-runtime gate semantics, not merely archive strings. */
define('ABSPATH', __DIR__.'/');
define('ROUTESPRO_PATH', dirname(__DIR__).'/');
define('FIELDFLOW_VERSION', '2.2.647');
function wp_strip_all_tags($v) { return strip_tags((string)$v); }
require_once dirname(__DIR__).'/src/Support/ReleaseGate.php';
use RoutesPro\Support\ReleaseGate;
foreach (['checkBriefingStocksMobile9DLContract','checkStockInsigniaAuthority9DMContract'] as $method) {
    $ref = new ReflectionMethod(ReleaseGate::class,$method);
    $result = $ref->invoke(null);
    if (($result['status'] ?? '') !== 'pass') {
        throw new RuntimeException('Runtime Release Gate '.$method.' blocked: '.json_encode($result));
    }
    echo 'RUNTIME GATE '.str_replace('check','',$method).' PASS'.PHP_EOL;
}
echo "RELEASE GATE RUNTIME 9DM PASS\n";
