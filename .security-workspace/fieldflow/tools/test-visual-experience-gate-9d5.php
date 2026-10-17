<?php
/** 9D5 gate isolated test. Never contacts Publisher, SQL, WordPress, or AI. */
define('ABSPATH','/tmp/');
// 9D5 is a historical gate, so test it with the installed successor identity.
$main=(string)file_get_contents(dirname(__DIR__).'/fieldflow.php');
if(!preg_match("/define\('FIELDFLOW_VERSION', '([0-9.]+)'\)/",$main,$match)) exit(1);
define('FIELDFLOW_VERSION',$match[1]);
define('ROUTESPRO_PATH',dirname(__DIR__).'/');
function wp_strip_all_tags($value) { return strip_tags((string)$value); }
require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
$gate=new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkVisualExperienceConsolidation9D5Contract');
$result=$gate->invoke(null);
if(($result['status']??'')!=='pass'||!empty($result['meta']['missing'])) {
    fwrite(STDERR,'9D5 gate failure: '.json_encode($result,JSON_UNESCAPED_UNICODE)."\n");exit(1);
}
foreach(['added_ai_calls','schema_change','governance_change'] as $invariant) {
    if(($result['meta'][$invariant]??true)!==false) {
        fwrite(STDERR,'9D5 unexpected '. $invariant."\n");exit(1);
    }
}
echo "VISUAL EXPERIENCE RELEASE GATE 9D5 PHP PASS | no new AI | no schema | no governance changes\n";
