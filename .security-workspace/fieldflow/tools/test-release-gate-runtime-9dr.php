<?php
/** 9DR runtime Gate, protecting 9AI, 9DN, 9DO, 9DP and 9DQ predecessors. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
if (!defined('ROUTESPRO_PATH')) define('ROUTESPRO_PATH', dirname(__DIR__).'/');
if (!defined('FIELDFLOW_VERSION')) define('FIELDFLOW_VERSION', '2.2.652');
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function remove_accents($v){return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v;}
require_once dirname(__DIR__).'/src/Support/ReleaseGate.php';
foreach ([
    'checkMailIntelligenceDailyReporting9AIContract',
    'checkUniversalStoreIdentity9DNContract',
    'checkDailyCloseRecipientReliability9DOContract',
    'checkSafeCardexClonePublication9DPContract',
    'checkFlexibleCardexInsigniaRegistry9DQContract',
    'checkCardexFormBindingContinuity9DRContract',
] as $name) {
    $result=(new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,$name))->invoke(null);
    if (($result['status']??'')!=='pass') throw new RuntimeException('9DR '.$name.': '.json_encode($result));
    echo 'RUNTIME GATE '.$name." PASS\n";
}
echo "RELEASE GATE RUNTIME 9DR PASS\n";
