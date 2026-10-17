<?php
/** 9D2 local Phase 6 gate execution, no paid AI calls or WordPress writes. */
define('ABSPATH', '/tmp/');
define('FIELDFLOW_VERSION', '2.2.627');
define('ROUTESPRO_PATH', dirname(__DIR__) . '/');
function wp_strip_all_tags($value) {return strip_tags((string)$value);}
require_once ROUTESPRO_PATH . 'src/Support/ReleaseGate.php';
$gate = new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class, 'checkBriefingCategoryContinuity9D2Contract');
$result = $gate->invoke(null);
if (($result['status'] ?? '') !== 'pass' || !empty($result['meta']['missing'])) {
    fwrite(STDERR, "9D2 GATE FAIL: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}
if (($result['meta']['added_ai_calls'] ?? true) !== false || ($result['meta']['schema_change'] ?? true) !== false) {
    fwrite(STDERR, "9D2 GATE FAIL: unsafe runtime claims\n"); exit(1);
}
echo "BRIEFING RELEASE GATE 9D2 PHP PASS | tenant project identity | no AI calls | no schema migration\n";
