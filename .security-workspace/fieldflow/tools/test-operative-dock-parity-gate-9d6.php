<?php
/** 9D6 standalone historical ReleaseGate continuity, no live services. */
define('ABSPATH','/tmp/');
$plugin=(string)file_get_contents(dirname(__DIR__).'/fieldflow.php');
if (!preg_match("/define\('FIELDFLOW_VERSION', '([^']+)'\)/", $plugin, $versionMatch)) exit(1);
define('FIELDFLOW_VERSION', $versionMatch[1]);
define('ROUTESPRO_PATH',dirname(__DIR__).'/');
function wp_strip_all_tags($value){return strip_tags((string)$value);}
require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
$method=new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkOperativeDockParity9D6Contract');
$result=$method->invoke(null);
if(($result['status']??'')!=='pass'||!empty($result['meta']['missing'])) {
    fwrite(STDERR,"9D6 release gate failed: ".json_encode($result,JSON_UNESCAPED_UNICODE)."\n");exit(1);
}
foreach(['added_ai_calls','schema_change','governance_change'] as $item) {
    if(($result['meta'][$item]??true)!==false){fwrite(STDERR,"9D6 gate regression: $item\n");exit(1);}
}
echo "OPERATIVE DOCK RELEASE GATE 9D6 PHP PASS | 2.2.630 to 2.2.631\n";
