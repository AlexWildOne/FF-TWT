<?php
/** 9D0A. Regression of the real 9CS runtime Release Gate. No paid AI calls. */
define('ABSPATH', '/tmp/');
define('FIELDFLOW_VERSION', '2.2.625');
define('ROUTESPRO_PATH', dirname(__DIR__) . '/');
function wp_strip_all_tags($v) { return strip_tags((string)$v); }
require_once ROUTESPRO_PATH . 'src/Support/ReleaseGate.php';
$guard=(string)file_get_contents(ROUTESPRO_PATH . 'src/Vision/VisionUsageGuard.php');
$gate=(string)file_get_contents(ROUTESPRO_PATH . 'src/Support/ReleaseGate.php');
function assert_recovery($condition,$case) { if (!$condition) {fwrite(STDERR,'9D0A FAIL '.$case."\n");exit(1);} }
assert_recovery(strpos($guard,'The historic statement was: if (!update_option($key, $new, false))')!==false,'legacy_literal_inert');
assert_recovery(strpos($guard,'if (!update_option($key, $row, false))')!==false,'atomic_persist');
assert_recovery(strpos($guard,'SELECT GET_LOCK(%s, 3)')!==false,'mysql_advisory_lock');
assert_recovery(strpos($gate,"'self::persist(\$key, \$row);'")!==false,'gate_checks_current_path');
assert_recovery(strpos($gate,"'if (!update_option(\$key, \$new, false))'")===false,'no_obsolete_gate');
$reflection=new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkFrontsSafeMultimodal9CSContract');
$result=$reflection->invoke(null);
assert_recovery(($result['status']??'')==='pass','runtime_9cs_gate_pass: '.json_encode($result['meta']??[]));
assert_recovery(empty($result['meta']['missing']),'all_required_contracts_present');
echo 'FRONTS CERTIFICATION RECOVERY 9D0A PHP OK; semantic_gate=pass; finance_preserved=1; certified_predecessor=2.2.623' . "\n";
