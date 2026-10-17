<?php
$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$read = static function(string $relative) use ($root): string {
    $path = $root . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$fail = static function(string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};
$version = '';
$main = $read('fieldflow.php');
if (preg_match("/define\\(\\s*['\"]FIELDFLOW_VERSION['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $main, $m)) $version = $m[1];
if ($version === '' || version_compare($version, '2.2.531', '<')) $fail('release must be successor=>=2.2.531');

$foundation = $read('src/Front/ReactFrontFoundation.php');
$source = $read('frontend/src/react-front/module-controls.ts');
$briefings = $read('src/Front/BriefingsShortcodeRenderer.php');
$stocks = $read('src/Front/StocksShortcodeRenderer.php');
$shopping = $read('src/Front/ShoppingShortcodeRenderer.php');
$messages = $read('src/Front/TeamInboxShortcodeRenderer.php');
$stockService = $read('src/Stocks/StockService.php');
$shoppingService = $read('src/Shopping/ShoppingService.php');
$css = $read('assets/fieldflow-premium-shell-v2.css');
$submission = $read('src/Forms/SubmissionPipeline.php');
$migrations = $read('src/Support/Migrations.php');
$doc = $read('PHASE-9O-OPERATIONAL-MODERNIZATION-WAVE.md');

$checks = [
    strpos($foundation, "OPERATIONAL_MODERNIZATION_CONTRACT = 'fieldflow-operational-modernization-wave-v1'") !== false,
    strpos($source, 'fieldflow-operational-modernization-wave-v1') !== false,
    strpos($source, 'react-event-shell-9o') !== false,
    strpos($source, 'if (!modernized)') !== false,
    strpos($source, 'fieldflow:operational-module-refresh') !== false,
    substr_count($briefings, "operationalModuleMount(\$id, 'briefings'") >= 2,
    substr_count($stocks, "operationalModuleMount(\$id, 'stocks'") >= 2,
    strpos($briefings, 'fieldflow_briefings_v2_') !== false,
    strpos($shopping, 'fieldflow:shopping:draft:v2:') !== false,
    strpos($shopping, 'legacyLocalKey') !== false,
    strpos($shopping, "root.addEventListener('routespro:panel-open'") === false,
    strpos($stocks, "root.addEventListener('routespro:panel-open'") === false,
    strpos($stockService, 'GROUP BY batch_id') !== false,
    strpos($shoppingService, '$projectUserCache') !== false,
    strpos($messages, "module:'messages'") !== false,
    strpos($messages, "['http:','https:','mailto:','tel:']") !== false,
    strpos($css, 'fieldflow-operational-modernization-wave-v1') !== false,
    strpos($css, '#rp-app-stocks') !== false,
    strpos($css, '#rp-app-shopping') !== false,
    strpos($submission, "public const BUILD = '2.2.485'") !== false,
    strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false,
    strpos($doc, '`SOURCE_RELEASE` is `2.2.530` and `STABLE_RELEASE` is `2.2.531`') !== false,
];
if (in_array(false, $checks, true)) $fail('operational modernization contract markers are incomplete');

echo "OPERATIONAL MODERNIZATION WAVE CONTRACT OK | release={$version} | successor=>=2.2.531 | modules=briefings,stocks,shopping,messages | schema=2.2.529 | submission_build=2.2.485\n";
