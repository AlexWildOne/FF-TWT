<?php
/** 9D3 Release Gate integration: no Azure network access or WordPress writes. */
define('ABSPATH', '/tmp/');
define('FIELDFLOW_VERSION', '2.2.628');
define('ROUTESPRO_PATH', dirname(__DIR__) . '/');
function wp_strip_all_tags($value) { return strip_tags((string)$value); }
require_once ROUTESPRO_PATH . 'src/Support/ReleaseGate.php';
$gate = new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class, 'checkCatalogCategoryPicker9D3Contract');
$result = $gate->invoke(null);
if (($result['status'] ?? '') !== 'pass' || !empty($result['meta']['missing'])) {
    fwrite(STDERR, '9D3 Gate failed: ' . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}
if (($result['meta']['added_ai_calls'] ?? true) !== false || ($result['meta']['schema_change'] ?? true) !== false) {
    fwrite(STDERR, "9D3 Gate failed: schema/AI regression\n"); exit(1);
}
echo "CATALOG CATEGORY RELEASE GATE 9D3 PHP PASS | no new AI calls | no schema changes\n";
