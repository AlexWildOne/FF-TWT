<?php
/** Standalone 9D9 phase 6 gate, no WordPress or paid services. */
define('ABSPATH','/tmp/');
$main=(string)file_get_contents(dirname(__DIR__).'/fieldflow.php');
if (!preg_match("/define\('FIELDFLOW_VERSION', '([^']+)'\)/", $main, $mm)) exit(1);
define('FIELDFLOW_VERSION', $mm[1]);
define('ROUTESPRO_PATH',dirname(__DIR__).'/');
function wp_strip_all_tags($v){return strip_tags((string)$v);}
require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
$method=new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkOperativeClientDockParity9D9Contract');
$result=$method->invoke(null);
if(($result['status']??'')!=='pass'||!empty($result['meta']['missing'])){
 fwrite(STDERR,'9D9 gate FAIL: '.json_encode($result,JSON_UNESCAPED_UNICODE)."\n");exit(1);
}
foreach(['added_ai_calls','schema_change','governance_change'] as $key){
 if(($result['meta'][$key]??true)!==false){fwrite(STDERR,"9D9 gate safety $key FAIL\n");exit(1);}
}
echo "OPERATIVE CLIENT DOCK PARITY GATE 9D9 PASS\n";
