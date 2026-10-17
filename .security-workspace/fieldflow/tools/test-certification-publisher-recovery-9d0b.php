<?php
/** 9D0B: verify the live certification check and three-part identity. */
define('ABSPATH', '/tmp/');
define('FIELDFLOW_VERSION', '2.2.626');
define('ROUTESPRO_PATH', dirname(__DIR__) . '/');
function wp_strip_all_tags($v) { return strip_tags((string)$v); }
require_once ROUTESPRO_PATH . 'src/Support/ReleaseGate.php';
$gate = new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkFrontsSafeMultimodal9CSContract');
$result = $gate->invoke(null);
$phase = (string) file_get_contents(ROUTESPRO_PATH . 'src/Cloud/Phase6StableRelease.php');
$publisher = (string) file_get_contents(ROUTESPRO_PATH . 'azure-release-publisher-6b5/release-publisher-function/src/index.js');
function check9d0b($ok,$msg){if(!$ok){fwrite(STDERR,'9D0B FAIL: '.$msg."\n");exit(1);}}
check9d0b(($result['status']??'')==='pass','9CS gate');
check9d0b(empty($result['meta']['missing']),'9CS missing requirements');
check9d0b(strpos($phase,"public const SOURCE_RELEASE = '2.2.625';")!==false,'installed predecessor');
check9d0b(strpos($phase,"public const STABLE_RELEASE = '2.2.626';")!==false,'candidate identity');
check9d0b(strpos($publisher,'function validateVersion(value)')!==false,'remote publisher version validator');
echo 'RELEASE RECOVERY 9D0B PHP PASS; 9CS contract OK; publisher version=2.2.626' . "\n";
