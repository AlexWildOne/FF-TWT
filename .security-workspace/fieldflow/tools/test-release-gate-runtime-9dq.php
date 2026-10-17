<?php
/** 9DQ runtime contract check, including immutable 9AI/9DN/9DO/9DP predecessors. */
if(!defined('ABSPATH')) define('ABSPATH',__DIR__.'/');
if(!defined('ROUTESPRO_PATH')) define('ROUTESPRO_PATH',dirname(__DIR__).'/');
if(!defined('FIELDFLOW_VERSION')) define('FIELDFLOW_VERSION','2.2.651');
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function remove_accents($v){return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v;}
require_once dirname(__DIR__).'/src/Support/ReleaseGate.php';
foreach (['checkUniversalStoreIdentity9DNContract','checkMailIntelligenceDailyReporting9AIContract',
          'checkDailyCloseRecipientReliability9DOContract','checkSafeCardexClonePublication9DPContract',
          'checkFlexibleCardexInsigniaRegistry9DQContract'] as $gate) {
    $result=(new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,$gate))->invoke(null);
    if (($result['status']??'')!=='pass')throw new RuntimeException('9DQ '.$gate.' failed: '.json_encode($result));
    echo "RUNTIME GATE $gate PASS\n";
}
echo "RELEASE GATE RUNTIME 9DQ PASS\n";
