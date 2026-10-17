<?php
/** Candidate 2.2.654 contract: historical gates remain intact, new 9DT gate passes. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
if (!defined('ROUTESPRO_PATH')) define('ROUTESPRO_PATH',dirname(__DIR__).'/');
if (!defined('FIELDFLOW_VERSION')) define('FIELDFLOW_VERSION','2.2.654');
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function remove_accents($v){return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v;}
require_once dirname(__DIR__).'/src/Support/ReleaseGate.php';
foreach (['checkMailIntelligenceDailyReporting9AIContract','checkUniversalStoreIdentity9DNContract',
    'checkDailyCloseRecipientReliability9DOContract','checkSafeCardexClonePublication9DPContract',
    'checkFlexibleCardexInsigniaRegistry9DQContract','checkCardexFormBindingContinuity9DRContract',
    'checkCardexVisibleBinding9DSContract','checkCardexScopeTruth9DTContract'] as $method) {
    $res=(new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,$method))->invoke(null);
    if (($res['status'] ?? '') !== 'pass') throw new RuntimeException($method.' FAILED: '.json_encode($res));
    echo $method." PASS\n";
}
echo "RELEASE GATE RUNTIME 9DT PASS\n";
