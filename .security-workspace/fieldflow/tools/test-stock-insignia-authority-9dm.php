<?php
/** 9DM: no WordPress connection, no writes, independent matcher and review tests. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
function wp_strip_all_tags($v) { return strip_tags((string)$v); }
function remove_accents($v) { return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v; }
function apply_filters($name,$v) { return $v; }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function current_time($format) { return '2026-10-14 00:00:00'; }
function get_current_user_id() { return 1; }
function ensure9DM(bool $result,string $name): void { if (!$result) throw new RuntimeException('9DM FAILED: '.$name); }
require_once dirname(__DIR__).'/src/Stocks/StockService.php';
require_once dirname(__DIR__).'/src/Stocks/StockStoreMatcher.php';
use RoutesPro\Stocks\StockService as S;
use RoutesPro\Stocks\StockStoreMatcher as M;

foreach (['Sonae'=>'continente','SONAE MC'=>'continente','CNT MATOSINHOS'=>'continente',
          'MDL MAIA'=>'continente','CBD LISBOA'=>'continente',
          'PD PORTO'=>'pingodoce','Pingo Doce Porto'=>'pingodoce',
          'Arcol'=>'arcol','ARCOL MATOSINHOS'=>'arcol',
          'M. Cunha'=>'mcunha','M Cunha Braga'=>'mcunha','Mcunha'=>'mcunha',
          'MAKRO MATOSINHOS'=>'makro','RECHEIO PORTO'=>'recheio'] as $name=>$wanted) {
    ensure9DM(S::brandFamily($name)===$wanted,'brand family '.$name);
}

$cases = [
 ['CNT Matosinhos','Sonae',true],['MDL Maia','Sonae',true],['CBD Gaia','Sonae',true],
 ['Continente Modelo Gaia','Sonae',true],['Continente Bom Dia Porto','Sonae',true],
 ['Pingo Doce Porto','Sonae',false],['Makro Matosinhos','Sonae',false],
 ['Arcol Porto','Sonae',false],['Mcunha Porto','Sonae',false],
 ['Sonae Matosinhos','Sonae',false],['Centro Maia','Sonae',false],
 ['PD Matosinhos','Pingo Doce',true],['Pingo Doce Porto','Pingo Doce',true],
 ['CNT Matosinhos','Pingo Doce',false],['Arcol Porto','Pingo Doce',false],
 ['Arcol Porto','Arcol',true],['ARCOL PORTO','M Cunha',false],
 ['M. Cunha Porto','M Cunha',true],['Mcunha Braga','Mcunha',true],
 ['Arcol Braga','Mcunha',false],['M. Cunha Porto','Arcol',false],
 ['Arcol Continente Maia','Arcol',false],['PD CNT Matosinhos','Sonae',false],
 ['','Sonae',false],['PD Porto','insignia desconhecida',false],
];
foreach ($cases as [$name,$batch,$expected]) {
    ensure9DM(S::locationMatchesInsignia(['name'=>$name],$batch)===$expected, 'location authority '.$name.' / '.$batch);
}
ensure9DM(S::locationMatchesInsignia(['name'=>'Centro 125','tags'=>'CNT'],'Sonae'), 'verified CNT tag on generic PDV');
ensure9DM(S::locationMatchesInsignia(['name'=>'Centro 125','meta_json'=>'{"insignia":"MDL"}'],'Sonae'), 'verified MDL metadata');
ensure9DM(S::locationMatchesInsignia(['name'=>'Centro 125','meta_json'=>'{"retailer_key":"Arcol"}'],'Arcol'), 'verified Arcol metadata');
ensure9DM(!S::locationMatchesInsignia(['name'=>'Centro 125','tags'=>'Sonae'],'Sonae'), 'generic group is not store evidence');
ensure9DM(!S::locationMatchesInsignia(['name'=>'Centro 125','meta_json'=>'{"project":"Sonae"}'],'Sonae'), 'untrusted project metadata');
ensure9DM(!S::locationMatchesInsignia(['name'=>'Centro 125','tags'=>'CNT, PD'],'Sonae'), 'conflicting retailer tags');
ensure9DM(!S::locationMatchesInsignia(['name'=>'Centro 125','tags'=>'Arcol','meta_json'=>'{"brand":"Mcunha"}'],'Arcol'), 'conflicting signals');
ensure9DM(!S::locationMatchesInsignia(['name'=>'Makro Matosinhos','tags'=>'CNT'],'Sonae'), 'name authority over tags');
ensure9DM(!S::sourceMatchesInsignia('M Cunha Braga','Sonae'), 'source shop contradicts Sonae file');
ensure9DM(!S::sourceMatchesInsignia('Makro Matosinhos','Sonae'), 'source Makro blocked');
ensure9DM(S::sourceMatchesInsignia('Arcol Braga','Arcol'), 'Arcol source accepted');
ensure9DM(S::sourceMatchesInsignia('Loja 123','Sonae'), 'generic source requires a verified target');

ensure9DM(S::sonaeSubtype('MDL MAIA')==='mdl', 'MDL subtype');
ensure9DM(S::sonaeSubtype('CONTINENTE BOM DIA')==='cbd', 'CBD subtype');
ensure9DM(S::sonaeSubtype('CNT GAIA')==='cnt', 'CNT subtype');
ensure9DM(S::sourceMatchesLocation('MDL Maia',['name'=>'CONTINENTE MODELO MAIA'],'Sonae'),'MDL source target subtype agreement');
ensure9DM(!S::sourceMatchesLocation('MDL Maia',['name'=>'CNT Maia'],'Sonae'),'MDL source never maps CNT');
ensure9DM(!S::sourceMatchesLocation('CBD Gaia',['name'=>'MDL Gaia'],'Sonae'),'CBD source never maps MDL');
ensure9DM(S::sourceMatchesLocation('CBD Gaia',['name'=>'CBD Gaia'],'Sonae'),'CBD valid');
ensure9DM(S::locationMatchesInsignia(['name'=>'MDL Maia'],'Continente Modelo'),'Modelo file only MDL');
ensure9DM(!S::locationMatchesInsignia(['name'=>'CNT Maia'],'Continente Modelo'),'Modelo file excludes CNT');
ensure9DM(!S::locationMatchesInsignia(['name'=>'CBD Gaia'],'Continente Modelo'),'Modelo file excludes CBD');
$twoSubtypes = [['id'=>101,'name'=>'CNT Gaia','external_ref'=>'700','city'=>'Gaia'],
    ['id'=>102,'name'=>'MDL Gaia','external_ref'=>'700','city'=>'Gaia']];
ensure9DM(M::rankCandidates(['store_name'=>'MDL Gaia','store_external_ref'=>'700','locality'=>'Gaia'],$twoSubtypes,'Sonae')[0]['location_id']===102,'exact ref cannot cross CNT/MDL');

$locations = [
 ['id'=>1,'name'=>'Makro Matosinhos','tags'=>'Sonae','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>2,'name'=>'CNT Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>3,'name'=>'Arcol Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>4,'name'=>'M Cunha Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>5,'name'=>'Loja Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
];
$source=['store_name'=>'Matosinhos','store_external_ref'=>'123','locality'=>'Matosinhos'];
$ranked=M::rankCandidates($source,$locations,'Sonae');
ensure9DM(count($ranked)===1 && $ranked[0]['location_id']===2, 'same exact code across retailers cannot override identity');
$ranked=M::rankCandidates($source,$locations,'Arcol');
ensure9DM(count($ranked)===1 && $ranked[0]['location_id']===3, 'Arcol independent ranking');
$ranked=M::rankCandidates($source,$locations,'Mcunha');
ensure9DM(count($ranked)===1 && $ranked[0]['location_id']===4, 'M Cunha independent ranking');
$ranked=M::rankCandidates(['store_name'=>'Makro Matosinhos','store_external_ref'=>'123','locality'=>'Matosinhos'],$locations,'Sonae');
ensure9DM(!$ranked, 'cross retailer source never suggested');

class FF9DMDB {
    public $prefix = 'wp_';
    public $users = 'wp_users';
    public $writes = 0;
    public $scopeRows = [];
    public $locations = [];
    public function prepare($sql,...$args) {return $sql;}
    public function get_row($sql,$mode) {
        if (strpos($sql,'FROM wp_routespro_stock_batches') !== false) return ['id'=>17,'client_id'=>1,'project_id'=>1,'insignia'=>'Sonae'];
        if (strpos($sql,'FROM wp_routespro_locations') !== false) return ['id'=>1,'name'=>'Makro Matosinhos','tags'=>'Sonae','meta_json'=>''];
        return null;
    }
    public function get_results($sql,$mode) {
        if (strpos($sql,'FROM wp_routespro_stock_items i LEFT JOIN wp_routespro_locations') !== false) return $this->scopeRows;
        if (strpos($sql,'FROM wp_routespro_locations l LEFT JOIN wp_routespro_campaign_locations') !== false) return $this->locations;
        if (strpos($sql,'WHERE batch_id=%d AND location_id=0') !== false) return [];
        return [];
    }
    public function replace($table,$values) { $this->writes++; return 1; }
    public function query($sql) { $this->writes++; return 1; }
}
$wpdb = new FF9DMDB();
$wpdb->locations = $locations;
$index=S::locationIndex(1,1,'Sonae');
ensure9DM(array_keys($index['rows'])===[2], 'location index contains only verified Sonae stores');
ensure9DM(S::locationIndex(1,1,'unknown')['rows']===[], 'unknown batch fails closed');
$wpdb->scopeRows = [['source_store_key'=>'s1','store_external_ref'=>'123','store_name'=>'Matosinhos','locality'=>'Matosinhos','location_id'=>1,'linked_location_name'=>'Makro Matosinhos','linked_location_tags'=>'Sonae','linked_location_meta_json'=>'','row_count'=>1,'product_count'=>1]];
$review=S::repairBatchScope(17);
ensure9DM(($review['removed']??-1)===0 && $review['review_count']===1, 'read only audit returns legacy conflict');
ensure9DM($wpdb->writes===0, 'audit must never write/delete old records');
$unmatched=S::unmatchedStores(17);
ensure9DM(count($unmatched)===1 && ($unmatched[0]['review_reason']??'')==='pdv_brand_unverified', 'legacy conflict shown in manual mapping panel');
ensure9DM($wpdb->writes===0, 'review panel cannot mutate old records');
$rejected=false;
try { S::saveMapping(1,1,'Sonae','123','Matosinhos',1); } catch (RuntimeException $e) {$rejected=true;}
ensure9DM($rejected && $wpdb->writes===0, 'manual cross-insignia mapping rejected without write');
$rejected=false;
try { S::saveMapping(1,1,'Sonae','123','Makro Matosinhos',2); } catch (RuntimeException $e) {$rejected=true;}
ensure9DM($rejected && $wpdb->writes===0, 'source conflict rejected even if target is legitimate');

echo "STOCK INSIGNIA AUTHORITY 9DM PASS\n";
