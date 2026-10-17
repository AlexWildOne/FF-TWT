<?php
/** 9DK read-only regression contract: fake test data, never publishes imports. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v) { return trim(strtolower(preg_replace('/[^a-z0-9_-]/i', '_', (string)$v)), '_'); } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($v) { return strip_tags((string)$v); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v) { return trim(strip_tags((string)$v)); } }
if (!function_exists('remove_accents')) { function remove_accents($v) { return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v; } }
if (!function_exists('absint')) { function absint($v) { return abs((int)$v); } }
require_once dirname(__DIR__) . '/src/Import/WorkbookIntelligence.php';
require_once dirname(__DIR__) . '/src/Briefings/OoxmlReader.php';
require_once dirname(__DIR__) . '/src/Briefings/ShareSpaceMatrix.php';
require_once dirname(__DIR__) . '/src/Briefings/PernodWeeklyImporter.php';
require_once dirname(__DIR__) . '/src/Stocks/StockService.php';
require_once dirname(__DIR__) . '/src/Stocks/StockImporter.php';
require_once dirname(__DIR__) . '/src/Forms/ProductCardex.php';
require_once dirname(__DIR__) . '/src/Briefings/BriefingService.php';

function ensure9DK($truth, string $msg): void { if (!$truth) throw new \RuntimeException('9DK failed: '.$msg); }
$imp = new \ReflectionClass(\RoutesPro\Briefings\PernodWeeklyImporter::class);
$narrative = $imp->getMethod('parseNarrativeSheet');
$sections = [];
$marker = array_fill_keys(['B','C','D','E','F','G','H'], 'PINGO DOCE');
$narrative->invokeArgs(null, [[3=>$marker,4=>['B'=>'FOCO','C'=>'Verificar ruturas'],5=>['B'=>'FOCO','C'=>'Verificar ruturas']], &$sections]);
ensure9DK(count($sections)===1, 'merged marker or duplicate focus');
ensure9DK($sections[0]['retailer_key']==='pingo_doce', 'retailer recognition');
$dates=$imp->getMethod('dateBounds')->invoke(null, [['content'=>['items'=>[['valid_from'=>'2026-09-29','valid_to'=>'2026-10-29']]]]], [], 2026,42);
ensure9DK($dates===['from'=>'2026-10-12','to'=>'2026-10-18'], 'ISO week separate from action date');
$scope=\RoutesPro\Briefings\BriefingService::storeVisibility9DK(10,[10=>true],[10=>true,11=>true],true);
ensure9DK($scope['scope']==='assigned','route is authoritative');
$scope=\RoutesPro\Briefings\BriefingService::storeVisibility9DK(11,[10=>true],[10=>true,11=>true],true);
ensure9DK($scope['scope']==='briefing_only','portfolio is briefing-only');
ensure9DK(\RoutesPro\Briefings\BriefingService::storeVisibility9DK(0,[],[],true)['scope']==='unmapped_relevant','unmapped remains visible');
ensure9DK(!\RoutesPro\Forms\ProductCardex::item_type_matches_profile('B4','P4'),'never infer B4 is P4');
ensure9DK(\RoutesPro\Forms\ProductCardex::item_type_matches_profile('P3','P4'),'P tier cumulative behavior preserved');
$header = new \ReflectionMethod(\RoutesPro\Stocks\StockImporter::class,'detectHeaderInSheet');
$testRows = [1=>['A'=>'STORE','B'=>'STORE NAME','C'=>'ARTICLE','D'=>'PRODUCT NAME','E'=>'SOH','F'=>'PACK'],2=>['A'=>'001','B'=>'Loja Norte','C'=>'100','D'=>'Produto','E'=>'10','F'=>'']];
$h=$header->invoke(null,'Stock_Loja_Final',$testRows);
ensure9DK(is_array($h), 'test stock header detected');
ensure9DK(($h['mapping']['stock_total']??'')==='E','SOH is stock');
ensure9DK(!isset($h['mapping']['conversion_factor']),'PACK alone is not conversion multiplier');
echo 'BRIEFING STOCK SCOPE 9DK PASS',"\n";
