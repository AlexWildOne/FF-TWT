<?php
/** Runtime validation of 9DL, 9DM and 9DN contracts against the installed PHP code. */
define('ABSPATH', __DIR__.'/');
define('ROUTESPRO_PATH', dirname(__DIR__).'/');
define('FIELDFLOW_VERSION', '2.2.648');
function wp_strip_all_tags($v) { return strip_tags((string)$v); }
function remove_accents($v) { return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v; }
require_once dirname(__DIR__).'/src/Support/ReleaseGate.php';
use RoutesPro\Support\ReleaseGate;
foreach (['checkBriefingStocksMobile9DLContract', 'checkStockInsigniaAuthority9DMContract', 'checkUniversalStoreIdentity9DNContract'] as $method) {
    $ref = new ReflectionMethod(ReleaseGate::class, $method);
    $result = $ref->invoke(null);
    if (($result['status'] ?? '') !== 'pass') {
        throw new RuntimeException('Runtime Release Gate ' . $method . ' blocked: ' . json_encode($result));
    }
    echo 'RUNTIME GATE ' . $method . ' PASS' . PHP_EOL;
}
echo "RELEASE GATE RUNTIME 9DN PASS\n";
