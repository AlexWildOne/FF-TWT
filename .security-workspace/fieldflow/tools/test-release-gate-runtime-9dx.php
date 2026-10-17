<?php
/** 9DX and latest historical release-gate runtime smoke test. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('ROUTESPRO_PATH')) define('ROUTESPRO_PATH', dirname(__DIR__) . '/');
if (!defined('FIELDFLOW_VERSION')) define('FIELDFLOW_VERSION', '2.2.658');
function wp_strip_all_tags($v) { return strip_tags((string) $v); }
function remove_accents($v) { return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $v) ?: (string) $v; }
require_once dirname(__DIR__) . '/src/Support/ReleaseGate.php';
foreach ([
    'checkMailIntelligenceDailyReporting9AIContract',
    'checkUniversalStoreIdentity9DNContract',
    'checkDailyCloseRecipientReliability9DOContract',
    'checkSafeCardexClonePublication9DPContract',
    'checkFlexibleCardexInsigniaRegistry9DQContract',
    'checkCardexFormBindingContinuity9DRContract',
    'checkCardexVisibleBinding9DSContract',
    'checkCardexScopeTruth9DTContract',
    'checkCardexGlobalLinkedScope9DUContract',
    'checkOrderSuggestionIntegrity9DVContract',
    'checkCardexDataConsistency9DWContract',
    'checkBriefingSteps9DXContract',
] as $method) {
    $result = (new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class, $method))->invoke(null);
    if (($result['status'] ?? '') !== 'pass') {
        throw new RuntimeException($method . ' FAILED: ' . json_encode($result));
    }
    echo $method . " PASS\n";
}
echo "RELEASE GATE RUNTIME 9DX PASS\n";
