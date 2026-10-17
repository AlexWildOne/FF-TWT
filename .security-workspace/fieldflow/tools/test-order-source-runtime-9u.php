<?php
$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($value){$value=strtolower((string)$value);return preg_replace('/[^a-z0-9_\-]/','',$value) ?: '';} }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($value){return trim(strip_tags((string)$value));} }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($value){return trim(strip_tags((string)$value));} }
if (!function_exists('wp_unslash')) { function wp_unslash($value){return $value;} }
if (!function_exists('absint')) { function absint($value){return abs((int)$value);} }
if (!function_exists('remove_accents')) { function remove_accents($value){$x=@iconv('UTF-8','ASCII//TRANSLIT',(string)$value);return $x===false?(string)$value:$x;} }
require_once $root . '/src/Forms/OrderSuggestionIntegrity.php';
require_once $root . '/src/Forms/ProductCardex.php';
require_once $root . '/src/Forms/Forms.php';

$reflection = new ReflectionClass('RoutesPro\\Forms\\Forms');
$extract = $reflection->getMethod('extract_order_suggestion_from_request');
$extract->setAccessible(true);

$runCase = static function(array $orderRow) use ($extract): array {
    $_POST = [
        'fronts_wrong__cardex_present' => '1',
        'fronts_wrong__cardex_id' => '0',
        'fronts_wrong' => [[ 'ref'=>'SKU-1', 'name'=>'Produto A', 'after'=>'5', 'units_per_case'=>'6' ]],
        'fronts_main__cardex_present' => '1',
        'fronts_main__cardex_id' => '0',
        'fronts_main' => [[ 'ref'=>'SKU-1', 'name'=>'Produto A', 'after'=>'0', 'units_per_case'=>'6' ]],
        'order_q' => [$orderRow],
    ];
    return $extract->invoke(null, 'order_q', [
        '_fieldflow_paired_fronts_key' => 'fronts_main',
        'order_zero_fronts_suggestion' => 1,
    ], []);
};

$auto = $runCase(['ref'=>'SKU-1','name'=>'Produto A','order_qty'=>'','quantity_source'=>'auto']);
if (($auto[0]['order_qty'] ?? null) !== 1.0 || ($auto[0]['quantity_source'] ?? '') !== 'auto') exit("FAIL automatic_zero_front\n");
echo "PASS automatic_zero_front=1\n";

$manual = $runCase(['ref'=>'SKU-1','name'=>'Produto A','order_qty'=>'4','quantity_source'=>'manual']);
if (($manual[0]['order_qty'] ?? null) !== 4.0 || ($manual[0]['quantity_source'] ?? '') !== 'manual') exit("FAIL manual_quantity\n");
echo "PASS manual_quantity=4\n";

$manualClear = $runCase(['ref'=>'SKU-1','name'=>'Produto A','order_qty'=>'','quantity_source'=>'manual','notes'=>'sem encomenda']);
if (($manualClear[0]['order_qty'] ?? 'sentinel') !== '' || ($manualClear[0]['quantity_source'] ?? '') !== 'manual') exit("FAIL manual_blank\n");
echo "PASS manual_blank_not_recalculated\n";

echo "ORDER SOURCE RUNTIME BEHAVIOUR OK\n";
