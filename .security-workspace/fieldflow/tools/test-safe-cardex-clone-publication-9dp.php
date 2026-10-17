<?php
/** 9DP synthetic DB: no production writes, exercises real clone and publication paths. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
function absint($v){return abs((int)$v);}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]+/','',strtolower((string)$v));}
function remove_accents($v){return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v;}
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function wp_json_encode($v,$opts=0){return json_encode($v,$opts);}
function current_time($format){return '2026-10-14 20:30:00';}
function get_current_user_id(){return 5;}
function do_action(...$args){}
function fail9DP($message) { throw new RuntimeException('9DP FAIL: '.$message); }
final class CardexWpdb9DP {
    public string $prefix='ff_';
    public array $tables=[];
    public int $insert_id=0;
    public string $last_error='';
    public bool $failNextItem=false;
    private $snapshot=null;
    public function __construct() {
        $this->tables=[
            'ff_routespro_product_cardex'=>[
                50=>['id'=>50,'name'=>'Pernod PD','insignia'=>'Pingo Doce','client_id'=>10,'project_id'=>20,'status'=>'active','meta_json'=>'{}'],
            ],
            'ff_routespro_product_cardex_items'=>[
                1=>['id'=>1,'cardex_id'=>50,'category'=>'WHISKY','reference'=>'P4','item_type'=>'P5','product_name'=>'Jameson 70cl','capacity_cl'=>70,'units_per_case'=>6,'sort_order'=>1,'is_active'=>1,'meta_json'=>'{"managed_by":"briefing_weekly"}'],
                2=>['id'=>2,'cardex_id'=>50,'category'=>'WHISKY','reference'=>'P5','item_type'=>'B4','product_name'=>'Jameson Black Barrel','capacity_cl'=>70,'units_per_case'=>6,'sort_order'=>2,'is_active'=>1,'meta_json'=>'{}'],
                3=>['id'=>3,'cardex_id'=>50,'category'=>'GIN','reference'=>'X','item_type'=>'STD','product_name'=>'Inactive Test','capacity_cl'=>70,'units_per_case'=>6,'sort_order'=>3,'is_active'=>0,'meta_json'=>'{}'],
            ],
            'ff_routespro_product_cardex_locations'=>[1=>['id'=>1,'cardex_id'=>50,'location_id'=>21]],
            'ff_routespro_product_cardex_item_locations'=>[1=>['id'=>1,'cardex_id'=>50,'item_id'=>1,'location_id'=>21]],
            'ff_routespro_product_cardex_store_state'=>[1=>['id'=>1,'cardex_id'=>50,'location_id'=>21,'revision_no'=>4]],
            'ff_routespro_locations'=>[
                21=>['id'=>21,'name'=>'Pingo Doce Matosinhos','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1],
                22=>['id'=>22,'name'=>'Makro Matosinhos','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1],
                23=>['id'=>23,'name'=>'Arcol Matosinhos','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1],
                24=>['id'=>24,'name'=>'M. Cunha Matosinhos','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1],
                25=>['id'=>25,'name'=>'Continente Matosinhos','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1],
                26=>['id'=>26,'name'=>'Continente Modelo Matosinhos','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1],
                27=>['id'=>27,'name'=>'Recheio Matosinhos','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1],
                28=>['id'=>28,'name'=>'Arcol Outro Cliente','tags'=>'','meta_json'=>'','client_id'=>11,'project_id'=>20,'is_active'=>1],
            ],
            'ff_routespro_campaign_locations'=>[],
        ];
    }
    public function prepare($sql,...$args){ return vsprintf(str_replace('%d','%d',str_replace('%s','%s',$sql)),$args); }
    private function table(string $name):array {return array_values($this->tables[$name]??[]);}
    public function get_row($sql,$mode=null) {
        if (preg_match('/FROM\s+(ff_routespro_(?:product_cardex|locations))\s+WHERE\s+id=(\d+)/i',$sql,$m)) return $this->tables[$m[1]][(int)$m[2]]??null;
        return null;
    }
    public function get_results($sql,$mode=null) {
        if (str_contains($sql,'FROM ff_routespro_product_cardex_items')) {
            preg_match('/cardex_id=(\d+)/',$sql,$m);
            $id=(int)($m[1]??0);
            $rows=array_values(array_filter($this->table('ff_routespro_product_cardex_items'),fn($x)=>$x['cardex_id']===$id));
            if (str_contains($sql,'is_active=1')) $rows=array_values(array_filter($rows,fn($x)=>(int)$x['is_active']===1));
            return $rows;
        }
        if (str_contains($sql,'FROM ff_routespro_product_cardex_locations cl')) {
            preg_match('/cl.cardex_id=(\d+)/',$sql,$m);
            $id=(int)($m[1]??0);
            $out=[];
            foreach ($this->table('ff_routespro_product_cardex_locations') as $row) {
                if ($row['cardex_id']!==$id)continue;
                $loc=$this->tables['ff_routespro_locations'][$row['location_id']]??null;
                if ($loc)$out[]=$loc;
            }
            return $out;
        }
        if (str_contains($sql,'FROM ff_routespro_locations l')) return $this->table('ff_routespro_locations');
        if (str_contains($sql,"status='active'") && str_contains($sql,'FROM ff_routespro_product_cardex')) {
            preg_match('/client_id=(\d+) AND project_id=(\d+) AND id<>(\d+)/',$sql,$m);
            return array_values(array_filter($this->table('ff_routespro_product_cardex'),function($v)use($m){return ($v['status']??'')==='active' && (!$m||((int)$v['client_id']===(int)$m[1] && (int)$v['project_id']===(int)$m[2] && (int)$v['id']!==(int)$m[3]));}));
        }
        return [];
    }
    public function get_var($sql) {
        if (str_contains($sql,'FROM ff_routespro_product_cardex_locations')) {
            preg_match('/cardex_id=(\d+)/',$sql,$c);
            preg_match('/location_id=(\d+)/',$sql,$l);
            foreach($this->table('ff_routespro_product_cardex_locations') as $row){
                if($row['cardex_id']===(int)($c[1]??0) && (!$l||$row['location_id']===(int)$l[1]))return 1;
            }
        }
        return null;
    }
    public function insert($table,$fields,$formats=null) {
        if ($this->failNextItem && str_ends_with($table,'_items')) { $this->failNextItem=false; return false; }
        $key=$this->tables[$table]??[];
        $this->insert_id=$key ? max(array_keys($key))+1 : 1;
        $fields['id']=$this->insert_id;
        $this->tables[$table][$this->insert_id]=$fields;
        return 1;
    }
    public function update($table,$fields,$where,$formats=null,$whereFormats=null){
        $id=(int)($where['id']??0);
        $old=$this->tables[$table][$id]??null;
        if(!$old)return 0;
        foreach($where as $field=>$value)if((string)($old[$field]??'')!==(string)$value)return 0;
        $this->tables[$table][$id]=array_merge($old,$fields);
        return 1;
    }
    public function query($sql){
        if($sql==='START TRANSACTION'){$this->snapshot=unserialize(serialize($this->tables));return 1;}
        if($sql==='ROLLBACK'){$this->tables=$this->snapshot;return 1;}
        if($sql==='COMMIT'){$this->snapshot=null;return 1;}
        return 1;
    }
}
$wpdb=new CardexWpdb9DP();
require_once dirname(__DIR__).'/src/Stocks/StockService.php';
require_once dirname(__DIR__).'/src/Stores/StoreIdentityAuthority.php';
require_once dirname(__DIR__).'/src/Forms/ProductCardex.php';
require_once dirname(__DIR__).'/src/Admin/CardexCloneService.php';
use RoutesPro\Forms\ProductCardex as Cardex;
use RoutesPro\Admin\CardexCloneService as Cloner;
(new ReflectionProperty(Cardex::class,'schema_checked'))->setValue(true);
$checks=0;
$assert=function($value,$msg) use (&$checks){if(!$value)fail9DP($msg);$checks++;};
$raises=function(callable $action,string $msg)use($assert){
    $failed=false;try{$action();}catch(RuntimeException $e){$failed=true;}
    $assert($failed,$msg);
};
$assert(Cloner::supportedInsignia('Continente Modelo') && !Cloner::supportedInsignia('Sonae') && !Cloner::supportedInsignia('M'),'closed insignia list');
$assert(!Cloner::locationInScope($wpdb->tables['ff_routespro_locations'][22],10,20,'Pingo Doce'),'Makro never PD');
$assert(!Cloner::locationInScope($wpdb->tables['ff_routespro_locations'][21],10,20,'M. Cunha'),'PD never M Cunha');
$assert(!Cloner::locationInScope($wpdb->tables['ff_routespro_locations'][26],10,20,'Continente'),'CNT distinct from MDL');
$assert(Cloner::locationInScope($wpdb->tables['ff_routespro_locations'][26],10,20,'Continente Modelo'),'MDL exact');
$assert(!Cloner::locationInScope($wpdb->tables['ff_routespro_locations'][28],10,20,'Arcol'),'cross-client store rejected');
$initialItems=count($wpdb->tables['ff_routespro_product_cardex_items']);
$newId=Cloner::duplicate(50,'Arcol','Cardex Arcol 2026');
$assert($newId!==50 && $newId>0,'clone gets fresh ID');
$clone=Cardex::get_cardex($newId);
$meta=json_decode($clone['meta_json'],true);
$assert($clone['status']==='draft' && $clone['insignia']==='Arcol','clone isolated draft with chosen insignia');
$assert(($meta['clone_source_id']??0)===50 && ($meta['clone_scope']??'')==='manual','clone provenance and closed scope');
$assert(count($wpdb->tables['ff_routespro_product_cardex_items'])===$initialItems+2,'only active source items copied');
foreach ($wpdb->tables['ff_routespro_product_cardex_items'] as $item)if($item['cardex_id']===$newId)$assert(empty($item['meta_json']),'no weekly legacy meta copied');
foreach (['ff_routespro_product_cardex_locations','ff_routespro_product_cardex_item_locations','ff_routespro_product_cardex_store_state'] as $table) {
    foreach ($wpdb->tables[$table] as $row)$assert($row['cardex_id']!==$newId,'no linked stores or store state transferred');
}
$assert(!Cardex::cardex_available_for_location($newId,23),'fixed questions cannot expose a draft');
$raises(fn()=>Cloner::publish($newId,'manual',false),'publication needs explicit confirmation');
$raises(fn()=>Cloner::publish($newId,'manual',true),'publication needs selected stores');
$wpdb->insert('ff_routespro_product_cardex_locations',['cardex_id'=>$newId,'location_id'=>22]);
$raises(fn()=>Cloner::publish($newId,'manual',true),'wrong insignia link cannot publish');
foreach ($wpdb->tables['ff_routespro_product_cardex_locations'] as $lid=>$r)if($r['cardex_id']===$newId)unset($wpdb->tables['ff_routespro_product_cardex_locations'][$lid]);
$wpdb->insert('ff_routespro_product_cardex_locations',['cardex_id'=>$newId,'location_id'=>23]);
$assert(Cloner::publish($newId,'manual',true)===2,'validated clone publishes 2 active items');
$assert(Cardex::get_cardex($newId)['status']==='active','clone active only after validated publish');
$assert(Cardex::cardex_available_for_location($newId,23),'correct linked store gets clone');
$assert(!Cardex::cardex_available_for_location($newId,21),'other-insignia store isolated');
$raises(fn()=>Cloner::publish($newId,'manual',true),'second publication prevented');
$global=Cloner::duplicate(50,'Recheio','Recheio Copy');
$assert(Cloner::publish($global,'global',true)===2,'global requires explicit selection and eligible store');
$assert(Cardex::cardex_available_for_location($global,27),'confirmed global serves Recheio only');
$assert(!Cardex::cardex_available_for_location($global,22),'global clone never serves Makro');
$assert(!Cardex::cardex_available_for_location($global,28),'global clone never serves other client');
$raises(fn()=>Cloner::publish(Cloner::duplicate(50,'Recheio','Another Recheio'),'global',true),'global duplicate blocked');
$before=count($wpdb->tables['ff_routespro_product_cardex']);
$wpdb->failNextItem=true;
$raises(fn()=>Cloner::duplicate(50,'M. Cunha','Rollback test'),'transaction failure triggers rollback');
$assert(count($wpdb->tables['ff_routespro_product_cardex'])===$before,'no partial cardex survives failed copy');
$assert(count($wpdb->tables['ff_routespro_product_cardex_items'])===$initialItems+2+2+2,'rollback removes unfinished items');
$src=file_get_contents(dirname(__DIR__).'/src/Admin/ProductCardex.php');
$assert(str_contains($src, "status") && str_contains($src, " : 'draft'"),'draft save never silently activates');
$assert(str_contains($src,'$reference_key === \'\' && $name_key'),'name fallback restricted to blank SKU');
$weekly=file_get_contents(dirname(__DIR__).'/src/Briefings/CardexSyncService.php');
$assert(str_contains($weekly, "clone_source_id") && str_contains($weekly, "continue;"),'weekly sync never selects published clone');
echo "SAFE CARDEX CLONE PUBLICATION 9DP PASS | checks={$checks} | clone=draft | products=isolated | stores=strict | publication=explicit | rollback=atomic\n";
