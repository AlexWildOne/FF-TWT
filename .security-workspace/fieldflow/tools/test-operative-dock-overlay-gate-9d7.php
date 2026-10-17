<?php
/** 9D7 standalone Release Gate check; no WordPress or external services. */
define('ABSPATH','/tmp/');
$plugin=(string)file_get_contents(dirname(__DIR__).'/fieldflow.php');
if (!preg_match("/define\('FIELDFLOW_VERSION', '([^']+)'\)/", $plugin, $versionMatch)) exit(1);
define('FIELDFLOW_VERSION', $versionMatch[1]);
define('ROUTESPRO_PATH',dirname(__DIR__).'/');
function wp_strip_all_tags($value){return strip_tags((string)$value);}
require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
$method=new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkOperativeDockOverlay9D7Contract');
$result=$method->invoke(null);
if(($result['status']??'')!=='pass'||!empty($result['meta']['missing'])){
    fwrite(STDERR,'9D7 release gate failed: '.json_encode($result,JSON_UNESCAPED_UNICODE)."\n");exit(1);
}
foreach(['added_ai_calls','schema_change','governance_change'] as $key){
    if(($result['meta'][$key]??true)!==false){fwrite(STDERR,'9D7 gate scope regression '.$key."\n");exit(1);}
}
echo "OPERATIVE DOCK OVERLAY GATE 9D7 PHP PASS | 2.2.631 to 2.2.632\n";
