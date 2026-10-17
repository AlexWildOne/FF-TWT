<?php
/** 9DN: real cardex resolver rejects stale cross-insignia link and checks subsequent valid link. */
define('ABSPATH', __DIR__.'/');
define('ARRAY_A','ARRAY_A');
function absint($v){return abs((int)$v);}
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function remove_accents($v){return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v;}
final class FieldFlowCardexDb9DN {
    public string $prefix='ff_';
    public int $calls=0;
    public array $links=[];
    public array $location=['name'=>'CNT Matosinhos','tags'=>'','meta_json'=>'','client_id'=>7,'project_id'=>14];
    public function prepare(string $sql,...$params):string { return vsprintf($sql,$params); }
    public function get_row(string $sql,$format=null):?array { return $this->location; }
    public function get_results(string $sql,$format=null):array {
        $this->calls++;
        if (strpos($sql,'LIMIT 20')===false || strpos($sql,'cardex_name')===false) throw new RuntimeException('Linked cardex candidates must include names and multiple links.');
        return $this->links;
    }
}
$wpdb=new FieldFlowCardexDb9DN();
require_once dirname(__DIR__).'/src/Stocks/StockService.php';
require_once dirname(__DIR__).'/src/Stores/StoreIdentityAuthority.php';
require_once dirname(__DIR__).'/src/Forms/ProductCardex.php';
$prop=new ReflectionProperty(\RoutesPro\Forms\ProductCardex::class,'schema_checked');
$prop->setValue(true);
$wpdb->links=[
    ['id'=>101,'insignia'=>'PD','cardex_name'=>'Pingo Doce','client_id'=>7,'project_id'=>14],
    ['id'=>102,'insignia'=>'Sonae','cardex_name'=>'Sonae','client_id'=>7,'project_id'=>14],
];
if (\RoutesPro\Forms\ProductCardex::resolve_cardex_id_for_location(10,7,14)!==102) throw new RuntimeException('Stale PD must not override valid Sonae link.');
$wpdb->links=[
    ['id'=>101,'insignia'=>'PD','cardex_name'=>'Pingo Doce','client_id'=>7,'project_id'=>14],
    ['id'=>102,'insignia'=>'','cardex_name'=>'Cardex Genérico','client_id'=>7,'project_id'=>14],
    ['id'=>103,'insignia'=>'CBD','cardex_name'=>'Continente Bom Dia','client_id'=>7,'project_id'=>14],
    ['id'=>104,'insignia'=>'CNT','cardex_name'=>'Continente','client_id'=>7,'project_id'=>14],
];
if (\RoutesPro\Forms\ProductCardex::resolve_cardex_id_for_location(10,7,14)!==104) throw new RuntimeException('Must skip unlabelled and CBD link, resolve CNT.');
if ($wpdb->calls!==2) throw new RuntimeException('Cardex resolver did not execute expected two queries.');
echo "CARDEX STORE IDENTITY 9DN PASS\n";
