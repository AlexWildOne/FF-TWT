<?php
/** Standalone Phase 9D8 release gate; no paid services or WordPress runtime. */
define('ABSPATH','/tmp/');
$plugin=(string)file_get_contents(dirname(__DIR__).'/fieldflow.php');
if (!preg_match("/define\('FIELDFLOW_VERSION', '([^']+)'\)/", $plugin, $versionMatch)) exit(1);
define('FIELDFLOW_VERSION', $versionMatch[1]);
define('ROUTESPRO_PATH',dirname(__DIR__).'/');
function wp_strip_all_tags($value){return strip_tags((string)$value);}
require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
$method=new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkOperativeInlineDockAuthority9D8Contract');
$result=$method->invoke(null);
if(($result['status']??'')!=='pass'||!empty($result['meta']['missing'])){
    fwrite(STDERR,'9D8 gate failed: '.json_encode($result,JSON_UNESCAPED_UNICODE)."\n");exit(1);
}
foreach(['added_ai_calls','schema_change','governance_change'] as $key) if(($result['meta'][$key]??true)!==false){fwrite(STDERR,"9D8 gate scope regression $key\n");exit(1);}
echo "OPERATIVE DOCK AUTHORITY GATE 9D8 PHP PASS\n";
