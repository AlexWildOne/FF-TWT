<?php
/** 9DO runtime gate, including protected 9DN store identity. */
if (!defined('ABSPATH')) define('ABSPATH',__DIR__.'/');
if (!defined('ROUTESPRO_PATH')) define('ROUTESPRO_PATH',dirname(__DIR__).'/');
if (!defined('FIELDFLOW_VERSION')) define('FIELDFLOW_VERSION','2.2.649');
function wp_strip_all_tags($v) { return strip_tags((string)$v); }
function remove_accents($v) { return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v; }
require_once dirname(__DIR__).'/src/Support/ReleaseGate.php';
use RoutesPro\Support\ReleaseGate;
foreach (['checkUniversalStoreIdentity9DNContract','checkDailyCloseRecipientReliability9DOContract'] as $name) {
    $method=new ReflectionMethod(ReleaseGate::class,$name);
    $result=$method->invoke(null);
    if (($result['status']??'')!=='pass') throw new RuntimeException('Blocked '.$name.': '.json_encode($result));
    echo 'RUNTIME GATE '.$name." PASS\n";
}
echo "RELEASE GATE RUNTIME 9DO PASS\n";
