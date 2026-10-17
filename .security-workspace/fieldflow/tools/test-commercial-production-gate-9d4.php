<?php
/** 9D4 release gate isolated test, no network or WordPress writes. */
define('ABSPATH','/tmp/');
define('FIELDFLOW_VERSION','2.2.629');
define('ROUTESPRO_PATH',dirname(__DIR__).'/');
function wp_strip_all_tags($value) { return strip_tags((string)$value); }
require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
$gate=new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkCommercialProductionHardening9D4Contract');
$result=$gate->invoke(null);
if(($result['status']??'')!=='pass'||!empty($result['meta']['missing'])){
 fwrite(STDERR, '9D4 gate failure: '.json_encode($result,JSON_UNESCAPED_UNICODE)."\n");exit(1);
}
foreach(['added_ai_calls','schema_change','governance_change'] as $invariant){
 if(($result['meta'][$invariant]??true)!==false){fwrite(STDERR,'9D4 illegal contract change: '.$invariant."\n");exit(1);}
}
echo "COMMERCIAL PRODUCTION RELEASE GATE 9D4 PHP PASS | no new AI | no schema | no governance changes\n";
