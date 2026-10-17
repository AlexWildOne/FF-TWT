<?php
/** 9DP runtime gate with 9DN and 9DO predecessor protection. */
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
if(!defined('ROUTESPRO_PATH'))define('ROUTESPRO_PATH',dirname(__DIR__).'/');
if(!defined('FIELDFLOW_VERSION'))define('FIELDFLOW_VERSION','2.2.650');
function wp_strip_all_tags($v) {return strip_tags((string)$v);}
function remove_accents($v) {return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v;}
require_once dirname(__DIR__).'/src/Support/ReleaseGate.php';
foreach (['checkUniversalStoreIdentity9DNContract','checkMailIntelligenceDailyReporting9AIContract','checkDailyCloseRecipientReliability9DOContract',
    'checkSafeCardexClonePublication9DPContract'] as $name) {
    $check=(new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,$name))->invoke(null);
    if (($check['status']??'')!=='pass')throw new RuntimeException($name.': '.json_encode($check));
    echo 'RUNTIME GATE '.$name." PASS\n";
}
echo "RELEASE GATE RUNTIME 9DP PASS\n";
