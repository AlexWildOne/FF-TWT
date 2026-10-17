<?php
/** 9DL: read-only invariants. Does not access a live WordPress database. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v) { return trim(strtolower(preg_replace('/[^a-z0-9_-]/i', '_', (string)$v)), '_'); } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($v) { return strip_tags((string)$v); } }
if (!function_exists('remove_accents')) { function remove_accents($v) { return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v; } }
if (!function_exists('absint')) { function absint($v) { return abs((int)$v); } }
require_once dirname(__DIR__).'/src/Stocks/StockService.php';
require_once dirname(__DIR__).'/src/Briefings/BriefingService.php';
function ensure9DL($condition, string $reason): void { if (!$condition) throw new RuntimeException('9DL: '.$reason); }
use RoutesPro\Stocks\StockService as Stock;
use RoutesPro\Briefings\BriefingService as Brief;
ensure9DL(Stock::brandFamily('Sonae')==='continente','Sonae family');
ensure9DL(Stock::brandFamily('SONAE MC')==='continente','Sonae MC family');
ensure9DL(Stock::brandFamily('CNT MATOSINHOS')==='continente','CNT family');
ensure9DL(Stock::brandFamily('CONTINENTE MODELO MAIA')==='continente','Modelo family');
ensure9DL(Stock::brandFamily('Makro Matosinhos')==='makro','Makro distinct');
ensure9DL(!Stock::locationMatchesInsignia(['name'=>'Makro Matosinhos','tags'=>'Sonae'], 'Sonae'),'never label Makro as Sonae from ambiguous tag');
ensure9DL(!Stock::locationMatchesInsignia(['name'=>'Makro Matosinhos'], 'Pingo Doce'),'never label Makro as PD');
ensure9DL(Stock::locationMatchesInsignia(['name'=>'CONTINENTE MODELO MAIA'], 'Sonae'),'legitimate Modelo match');
ensure9DL(Stock::locationMatchesInsignia(['name'=>'CNT MATOSINHOS'], 'Sonae'),'legitimate CNT match');
ensure9DL(!Stock::locationMatchesInsignia(['name'=>'PINGO DOCE PORTO'], 'Sonae'),'cross retailer guard');
ensure9DL(Stock::locationMatchesInsignia(['name'=>'PINGO DOCE PORTO'], 'Pingo Doce'),'PD match preserved');
ensure9DL(Brief::storeVisibility9DK(42,[42=>true],[42=>true,99=>true],true)['scope']==='assigned','route stop');
ensure9DL(Brief::storeVisibility9DK(99,[42=>true],[42=>true,99=>true],true)['scope']==='briefing_only','briefing does not become route');
ensure9DL(Brief::storeVisibility9DK(0,[42=>true],[],true)['scope']==='unmapped_relevant','unmapped distinct');
$root=dirname(__DIR__);
$files=[
 'src/Briefings/BriefingService.php' => ["if (\$scope !== 'assigned')", 'excluded_store_items', "if (!\$relevant) continue;"],
 'src/Stocks/StockService.php' => ['locationMatchesInsignia(', 'cross_insignia_rows', 'repairBatchScope(', 'PDV incompatível'],
 'src/Front/BriefingsShortcodeRenderer.php' => ['ffb-mobile-records', 'ffb-mobile-meta', 'ffb-responsive-records', "source_row", 'ffb-scope-banner'],
 'src/Front/StocksShortcodeRenderer.php' => ['selectedInsignia', 'rebuildStoreOptions(', 'filterFeedback.textContent', 'data-filter-feedback'],
];
foreach ($files as $rel=>$markers){$src=file_get_contents($root.'/'.$rel);ensure9DL($src!==false,'missing '.$rel);foreach($markers as $marker) ensure9DL(strpos($src,$marker)!==false,"missing $marker in $rel");}
echo "BRIEFING STOCK MOBILE 9DL PASS\n";
