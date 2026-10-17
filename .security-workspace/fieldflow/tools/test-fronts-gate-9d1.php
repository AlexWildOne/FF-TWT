<?php
/** 9D1: actual release gate rather than a source-only text assertion. */
define('ABSPATH','/tmp/');
define('FIELDFLOW_VERSION','2.2.626');
define('ROUTESPRO_PATH',dirname(__DIR__) . '/');
function wp_strip_all_tags($v) {return strip_tags((string)$v);}
require_once ROUTESPRO_PATH . 'src/Support/ReleaseGate.php';
$gate = new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class, 'checkFrontsVisionIntelligence9D1Contract');
$result = $gate->invoke(null);
if (($result['status'] ?? '') !== 'pass' || !empty($result['meta']['missing'])) {
    fwrite(STDERR,'9D1 release gate failed: '.json_encode($result,JSON_UNESCAPED_UNICODE)."\n");exit(1);
}
if (empty($result['meta']['no_extra_ai_calls']) || !array_key_exists('history_auto_fill',$result['meta'])
    || $result['meta']['history_auto_fill'] !== false) {fwrite(STDERR,"9D1 unsafe release metadata\n");exit(1);}
echo "FRONTS RELEASE GATE 9D1 PHP OK | strict scope | no extra AI | manual history\n";
