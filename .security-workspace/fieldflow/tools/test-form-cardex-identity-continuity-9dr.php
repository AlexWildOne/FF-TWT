<?php
/** 9DR: executable synthetic coverage of form Cardex binding; no real WordPress DB or users. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
function absint($x){return abs((int)$x);}
function sanitize_key($x){return preg_replace('/[^a-z0-9_\-]+/','',strtolower((string)$x));}
function sanitize_text_field($x){return trim(strip_tags((string)$x));}
function wp_strip_all_tags($x){return strip_tags((string)$x);}
function remove_accents($x){return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$x) ?: (string)$x;}
function wp_json_encode($x,$opts=0){return json_encode($x,$opts);}
function current_time($x){return '2026-10-15 15:00:00';}
function do_action(...$unused){}
function get_option($k,$def=[]){return $def;}
function update_option(...$unused){return true;}
function get_current_user_id(){return 4;}
final class FormCardexDb9DR {
    public string $prefix='ff_';
    public int $insert_id=200;
    public array $locations=[];
    public array $cardex=[];
    public array $links=[];
    public function __construct(){
        foreach ([
            101=>'Continente Bom Dia Gaia',102=>'Continente Modelo Gaia',103=>'Pingo Doce Gaia',
            104=>'Pingo Doce Lisboa',105=>'Makro Gaia',106=>'Garcias Lisboa',107=>'Continente Gaia',
        ] as $id=>$name) $this->locations[$id]=['id'=>$id,'name'=>$name,'tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20];
        foreach ([
            11=>['Sonae','9DR Sonae',1,'{}'],12=>['CBD','9DR CBD',1,'{}'],
            13=>['MDL','9DR Modelo',1,'{}'],14=>['pingo_doce','9DR Pingo legacy',1,'{}'],
            15=>['PD','9DR PD',1,'{}'],16=>['Garcias','9DR Garcias',1,'{"clone_source_id":14,"clone_scope":"manual","clone_publication_approved":true}'],
            17=>['Makro','9DR stale Makro',1,'{}'],18=>['Pingo Doce','9DR draft',0,'{"clone_scope":"manual"}'],
            19=>['CNT','9DR CNT',1,'{}'],
        ] as $id=>[$insignia,$name,$active,$meta]) {
            $this->cardex[$id]=['id'=>$id,'insignia'=>$insignia,'name'=>$name,'client_id'=>10,'project_id'=>20,
                'status'=>$active?'active':'draft','meta_json'=>$meta];
        }
        $this->links=[
            ['cardex_id'=>11,'location_id'=>101,'id'=>1],
            ['cardex_id'=>12,'location_id'=>101,'id'=>2],
            ['cardex_id'=>13,'location_id'=>102,'id'=>3],
            ['cardex_id'=>17,'location_id'=>103,'id'=>4],
            ['cardex_id'=>14,'location_id'=>103,'id'=>5],
            ['cardex_id'=>15,'location_id'=>104,'id'=>6],
            ['cardex_id'=>18,'location_id'=>104,'id'=>7],
            ['cardex_id'=>16,'location_id'=>106,'id'=>8],
            ['cardex_id'=>19,'location_id'=>107,'id'=>9],
        ];
    }
    public function prepare($sql,...$params){ return vsprintf((string)$sql,$params); }
    public function get_row($sql,$output=null) {
        if(preg_match('/FROM ff_routespro_locations WHERE id=(\d+)/',$sql,$m))return $this->locations[(int)$m[1]]??null;
        if(preg_match('/FROM ff_routespro_product_cardex WHERE id=(\d+)/',$sql,$m))return $this->cardex[(int)$m[1]]??null;
        return null;
    }
    public function get_results($sql,$output=null){
        if(str_contains($sql,'FROM ff_routespro_product_cardex_locations cl') && str_contains($sql,'JOIN')) {
            preg_match('/cl.location_id=(\d+)/',$sql,$m); $location=(int)($m[1]??0); $rows=[];
            foreach ($this->links as $link) {
                if ($link['location_id']!==$location)continue;
                $c=$this->cardex[$link['cardex_id']]??null;
                if(!$c || $c['status']!=='active')continue;
                $rows[]=array_merge($c,['cardex_name'=>$c['name'],'link_id'=>$link['id']]);
            }
            usort($rows,fn($a,$b)=>(int)$b['link_id']<=>(int)$a['link_id']);
            return $rows;
        }
        if(str_contains($sql,'FROM ff_routespro_product_cardex') && str_contains($sql,'ORDER BY name'))return array_values($this->cardex);
        return [];
    }
    public function get_col($sql){return [];}
    public function get_var($sql){
        if(str_contains($sql,'FROM ff_routespro_product_cardex_locations')){
            preg_match('/cardex_id=(\d+)/',$sql,$c);
            preg_match('/location_id=(\d+)/',$sql,$l);
            foreach($this->links as $row){
                if($row['cardex_id']===(int)($c[1]??0)&&(!$l || $row['location_id']===(int)$l[1]))return 1;
            }
        }
        return null;
    }
}
$wpdb=new FormCardexDb9DR();
require_once dirname(__DIR__).'/src/Stocks/StockService.php';
require_once dirname(__DIR__).'/src/Stores/StoreIdentityAuthority.php';
require_once dirname(__DIR__).'/src/Forms/ProductCardex.php';
require_once dirname(__DIR__).'/src/Admin/CardexCloneService.php';
use RoutesPro\Forms\ProductCardex as C;
(new ReflectionProperty(C::class,'schema_checked'))->setValue(true);
$n=0;
$check=function($condition,$label) use (&$n){$n++;if(!$condition)throw new RuntimeException("9DR FAIL [{$n}]: {$label}");};
$check(C::resolve_cardex_id_for_location(101,10,20)===12,'CBD exact cardex outranks generic Sonae');
$check(C::resolve_cardex_id_for_location(102,10,20)===13,'MDL resolves legacy alias');
$check(C::resolve_cardex_id_for_location(103,10,20)===14,'PD legacy alias resolves despite stale Makro link');
$check(C::resolve_cardex_id_for_location(104,10,20)===15,'PD draft never shadows active link');
$check(C::resolve_cardex_id_for_location(106,10,20)===16,'9DQ dynamically registered retailer stays eligible');
$check(C::resolve_cardex_id_for_location(105,10,20)===0,'unlinked Makro cannot borrow Pingo Doce Cardex');
$check(C::resolve_cardex_id_for_location(107,10,20)===19,'CNT stays separate from CBD and MDL');
$fronts=['type'=>'product_matrix','key'=>'frentes','label'=>'Frentes','product_source'=>'cardex_auto','cardex_id'=>0];
$order=['type'=>'order_suggestion','key'=>'encomenda','label'=>'Sugestão de encomenda','product_source'=>'cardex_auto','cardex_id'=>0];
$schema=C::synchronise_order_questions_with_fronts(['questions'=>[$fronts,$order]],['location_id'=>101,'client_id'=>10,'project_id'=>20]);
$check((int)($schema['questions'][1]['_fieldflow_paired_cardex_id']??0)===12,'order suggestion pairs with precisely the CBD fronts Cardex');
$check(C::question_is_available_for_context($schema['questions'][0],['location_id'=>101,'client_id'=>10,'project_id'=>20]),'fronts question remains visible with valid CBD Cardex');
$check(C::question_is_available_for_context($schema['questions'][1],['location_id'=>101,'client_id'=>10,'project_id'=>20]),'paired order cardex valid');
$check(!C::question_is_available_for_context(['type'=>'product_matrix','product_source'=>'cardex_fixed','cardex_id'=>13],['location_id'=>101]),'wrong fixed MDL cardex is not silently substituted for CBD');
$check(!C::question_is_available_for_context(['type'=>'product_matrix','product_source'=>'cardex_fixed','cardex_id'=>18],['location_id'=>104]),'draft cardex remains unavailable');
$check(!C::question_is_available_for_context(['type'=>'product_matrix','product_source'=>'cardex_fixed','cardex_id'=>17],['location_id'=>103]),'cross-retailer fixed cardex remains blocked');
$check(C::question_is_available_for_context(['type'=>'product_matrix','product_source'=>'cardex_fixed','cardex_id'=>14],['location_id'=>103]),'matching explicitly linked legacy PD cardex accepted');
$check(\RoutesPro\Stores\StoreIdentityAuthority::locationMatches('Sonae',$wpdb->locations[101]),'historic Sonae matching contract remains intact');
echo "FORM CARDEX IDENTITY CONTINUITY 9DR PASS | checks={$n} | CNT/CBD/MDL/PD isolated | legacy restored | order paired\n";
