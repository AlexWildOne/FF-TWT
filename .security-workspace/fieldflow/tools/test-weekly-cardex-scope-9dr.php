<?php
/** 9DR weekly sync isolation: no production DB; only synthetic rows, no live sync. */
if (!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
if (!defined('ARRAY_A'))define('ARRAY_A','ARRAY_A');
function absint($x){return abs((int)$x);}
function sanitize_text_field($x){return trim(strip_tags((string)$x));}
function sanitize_key($x){return preg_replace('/[^a-z0-9_\-]+/','',strtolower((string)$x));}
function remove_accents($x){return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$x)?:$x;}
function wp_strip_all_tags($x){return strip_tags((string)$x);}
function wp_json_encode($x,$flags=0){return json_encode($x,$flags);}
function current_time($x){return '2026-10-15 15:00:00';}
final class WeeklyCardexDB9DR {
    public string $prefix='ff_';
    public array $rows=[];
    public int $insert_id=0;
    public string $last_error='';
    public function prepare($sql,...$values){return vsprintf($sql,$values);}
    public function get_results($sql,$type=null){return array_values($this->rows);}
    public function insert($table,$data){$this->insert_id=++$this->insert_id+1000;$data['id']=$this->insert_id;$this->rows[]=$data;return 1;}
}
$wpdb=new WeeklyCardexDB9DR();
require_once dirname(__DIR__).'/src/Stocks/StockService.php';
require_once dirname(__DIR__).'/src/Stores/StoreIdentityAuthority.php';
require_once dirname(__DIR__).'/src/Forms/ProductCardex.php';
require_once dirname(__DIR__).'/src/Briefings/CardexSyncService.php';
use RoutesPro\Briefings\CardexSyncService as S;
$method=new ReflectionMethod(S::class,'findOrCreateCardex');
$cfgSonae=['label'=>'Sonae','insignia'=>'Continente'];
$cfgPD=['label'=>'Pingo Doce','insignia'=>'Pingo Doce'];
$managedSonae='{"managed_by":"briefing_weekly","retailer_key":"sonae"}';
$managedPD='{"managed_by":"briefing_weekly","retailer_key":"pingo_doce"}';
$checks=0;
$check=function($assert,$msg)use(&$checks){$checks++;if(!$assert)throw new RuntimeException("9DR FAIL {$checks}: {$msg}");};
$wpdb->rows=[['id'=>15,'name'=>'Pernod CBD','insignia'=>'Continente Bom Dia','status'=>'active','client_id'=>10,'project_id'=>20,'meta_json'=>$managedSonae]];
$result=$method->invoke(null,10,20,'sonae',$cfgSonae);
$check((int)$result['id']!==15,'weekly Sonae CNT sync must not rewrite CBD');
$wpdb->rows=[['id'=>16,'name'=>'Pernod MDL','insignia'=>'Continente Modelo','status'=>'active','client_id'=>10,'project_id'=>20,'meta_json'=>$managedSonae]];
$result=$method->invoke(null,10,20,'sonae',$cfgSonae);
$check((int)$result['id']!==16,'weekly Sonae CNT sync must not rewrite MDL');
$wpdb->rows=[['id'=>17,'name'=>'Pernod CNT','insignia'=>'Continente','status'=>'active','client_id'=>10,'project_id'=>20,'meta_json'=>$managedSonae]];
$result=$method->invoke(null,10,20,'sonae',$cfgSonae);
$check((int)$result['id']===17,'weekly Sonae CNT sync preserves matching weekly cardex');
$wpdb->rows=[['id'=>18,'name'=>'Manual PD','insignia'=>'Pingo Doce','status'=>'active','client_id'=>10,'project_id'=>20,'meta_json'=>'{}']];
$result=$method->invoke(null,10,20,'pingo_doce',$cfgPD);
$check((int)$result['id']!==18,'weekly Pingo Doce sync must not seize manually curated cardex');
$wpdb->rows=[['id'=>19,'name'=>'Pernod PD','insignia'=>'Pingo Doce','status'=>'active','client_id'=>10,'project_id'=>20,'meta_json'=>$managedPD]];
$result=$method->invoke(null,10,20,'pingo_doce',$cfgPD);
$check((int)$result['id']===19,'matching managed PD cardex may be reused');
$wpdb->rows=[['id'=>20,'name'=>'Cloned CNT','insignia'=>'Continente','status'=>'active','client_id'=>10,'project_id'=>20,'meta_json'=>'{"managed_by":"briefing_weekly","retailer_key":"sonae","clone_source_id":8}']];
$result=$method->invoke(null,10,20,'sonae',$cfgSonae);
$check((int)$result['id']!==20,'published clone never seized');
echo "WEEKLY CARDEX SCOPE 9DR PASS | checks={$checks} | subtype exact | manual protected | clone protected\n";
