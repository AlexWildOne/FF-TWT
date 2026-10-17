<?php
/**
 * Build-time verifier for FieldFlow 2.2.473 Form Order Suggestion Placement.
 */
$root = dirname(__DIR__);
$checks = [];
$assert = static function (bool $ok, string $name) use (&$checks): void {
    $checks[$name] = $ok;
    if (!$ok) fwrite(STDERR, "FAIL: {$name}\n");
};
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$containsAll = static function (string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};

$fieldflow = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$product = $read('src/Forms/ProductCardex.php');
$renderer = $read('src/Forms/FormRenderer.php');
$builder = $read('assets/routespro-form-builder.js');
$responsive = $read('src/Front/ResponsiveAssets.php');

$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'checkFormOrderSuggestionPlacementContract', 'fieldflow-form-order-suggestion-placement-v1']), 'release_gate_contract');
$assert(strpos($responsive, 'fieldflow-offline-runtime-2.2.530.js') !== false, 'offline_runtime_cache_version');
$assert($containsAll($product, ['ensure_order_questions_follow_paired_fronts', '_fieldflow_runtime_position_repaired', '_fieldflow_paired_fronts_key']), 'runtime_dependency_guard');
$assert($containsAll($renderer, ['2.2.473: order suggestions are dependent fields', '$pairedFrontsKey', '$schemaPositions', '$stepByField']), 'wizard_step_recovery');
$assert($containsAll($builder, ['2.2.473: every BO question in wizard mode belongs to a real step.', 'assignedStep', 'nearest configured']), 'builder_persistent_step_repair');

// Execute the pure dependency-order helper against two fixtures.
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string)$key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}
require_once $root . '/src/Forms/ProductCardex.php';
$method = new ReflectionMethod('RoutesPro\\Forms\\ProductCardex', 'ensure_order_questions_follow_paired_fronts');
$method->setAccessible(true);
$fixtureBroken = [
    ['key'=>'order','type'=>'order_suggestion','_fieldflow_paired_fronts_key'=>'fronts'],
    ['key'=>'intro','type'=>'text'],
    ['key'=>'fronts','type'=>'product_matrix'],
    ['key'=>'photo','type'=>'image_upload'],
];
$fixed = $method->invoke(null, $fixtureBroken);
$keys = array_map(static fn($q) => is_array($q) ? (string)($q['key'] ?? '') : '', $fixed);
$assert($keys === ['intro','fronts','order','photo'], 'fixture_moves_order_after_fronts');
$assert(!empty($fixed[2]['_fieldflow_runtime_position_repaired']), 'fixture_marks_runtime_repair');

$fixtureGood = [
    ['key'=>'fronts','type'=>'product_matrix'],
    ['key'=>'notes','type'=>'textarea'],
    ['key'=>'order','type'=>'order_suggestion','_fieldflow_paired_fronts_key'=>'fronts'],
];
$kept = $method->invoke(null, $fixtureGood);
$keptKeys = array_map(static fn($q) => is_array($q) ? (string)($q['key'] ?? '') : '', $kept);
$assert($keptKeys === ['fronts','notes','order'], 'fixture_preserves_valid_order');

$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "FORM ORDER SUGGESTION PLACEMENT CONTRACT FAILED: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo 'FORM ORDER SUGGESTION PLACEMENT CONTRACT OK (' . count($checks) . " checks)\n";
