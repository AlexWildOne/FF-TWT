<?php
/** Import safety: two cardex with the same label and two distinct SKU with the same name. */
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
if(!defined('ARRAY_A'))define('ARRAY_A','ARRAY_A');
function absint($v){return abs((int)$v);}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function remove_accents($v){return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v;}
function current_time($format){return '2026-10-14 20:30:00';}
function wp_json_encode($v){return json_encode($v);}
final class CardexCsvDb9DP {
    public string $prefix='ff_';
    public int $insert_id=0;
    public array $cardex=[12=>['id'=>12,'name'=>'Promo','insignia'=>'Arcol','client_id'=>1,'project_id'=>3,'status'=>'active']];
    public array $items=[];
    public function prepare($sql,...$params){return vsprintf($sql,$params);}
    public function get_var($sql){
        if (str_contains($sql,'FROM ff_routespro_product_cardex_items')) {
            preg_match('/cardex_id=(\d+)/',$sql,$c);
            preg_match('/reference=(.*?) LIMIT 1/',$sql,$r);
            preg_match('/product_name=(.*?) AND /',$sql,$n);
            foreach ($this->items as $row) {
                if ((int)$row['cardex_id']!==(int)($c[1]??0))continue;
                if ($r && $row['reference']===$r[1])return $row['id'];
                if ($n && $row['product_name']===$n[1] && ($row['reference']==='' || $row['reference']===null))return $row['id'];
            }
            return null;
        }
        if (!str_contains($sql,'FROM ff_routespro_product_cardex'))return null;
        preg_match('/name=([^ ]+(?: [^ ]+)*?) AND client_id=(\d+) AND project_id=(\d+)/',$sql,$match);
        if (!$match) return null;
        foreach($this->cardex as $row){
            if($row['name']!==$match[1] || $row['client_id']!==(int)$match[2] || $row['project_id']!==(int)$match[3])continue;
            if(str_contains($sql,'insignia=') && !str_contains($sql,'insignia='.$row['insignia']))continue;
            if(str_contains($sql,"status='active'") && $row['status']!=='active')continue;
            return (int)$row['id'];
        }
        return null;
    }
    public function insert($table,$data){
        if(str_ends_with($table,'_cardex')){
            $this->insert_id=max(array_keys($this->cardex))+1;
            $data['id']=$this->insert_id;
            $this->cardex[$this->insert_id]=$data;
            return 1;
        }
        if(str_ends_with($table,'_items')){
            $this->insert_id=($this->items ? max(array_keys($this->items)) : 0)+1;
            $data['id']=$this->insert_id;
            $this->items[$this->insert_id]=$data;
            return 1;
        }
        return false;
    }
    public function update($table,$data,$where){
        $id=(int)($where['id']??0);
        if(!isset($this->items[$id]))return false;
        $this->items[$id]=array_merge($this->items[$id],$data);
        return 1;
    }
}
$wpdb=new CardexCsvDb9DP();
require_once dirname(__DIR__).'/src/Forms/ProductCardex.php';
require_once dirname(__DIR__).'/src/Admin/ProductCardex.php';
$class=new ReflectionClass('RoutesPro\\Admin\\ProductCardex');
$make=$class->getMethod('find_or_create_cardex');
$import=$class->getMethod('find_or_upsert_item');
$actual=$make->invoke(null,'Promo','Pingo Doce',1,3);
if($actual===12 || ($wpdb->cardex[$actual]['insignia']??'')!=='Pingo Doce')throw new RuntimeException('Brand collision overwrote Arcol.');
if($make->invoke(null,'Promo','Pingo Doce',1,3)!==$actual)throw new RuntimeException('Repeated import duplicated cardex.');
if(($wpdb->cardex[12]['insignia']??'')!=='Arcol')throw new RuntimeException('Origin brand was modified.');
$base=['category'=>'Whisky','reference'=>'A1','item_type'=>'B4','product_name'=>'Jameson 70cl','capacity_cl'=>70,'units_per_case'=>6,'sort_order'=>1,'is_active'=>1];
$a=$import->invoke(null,$actual,$base);
$b=$import->invoke(null,$actual,array_merge($base,['reference'=>'B2']));
if($a===$b || count($wpdb->items)!==2)throw new RuntimeException('Distinct references were merged by product name.');
if($import->invoke(null,$actual,$base)!==$a)throw new RuntimeException('Existing reference not recognised.');
echo "CARDEX CSV INSIGNIA SKU 9DP PASS | brand_scope=closed | reference_identity=stable\n";
