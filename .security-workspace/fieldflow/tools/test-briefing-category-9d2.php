<?php
/** 9D2 isolated integration: no WordPress database, AI calls or real data mutation. */
namespace {
    define('ABSPATH','/tmp/wp/');
    define('ARRAY_A', 'ARRAY_A');
    $options=[];
    function sanitize_text_field($v) { return trim((string)$v); }
    function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_\-]+/', '', (string)$v)); }
    function wp_strip_all_tags($v) { return strip_tags((string)$v); }
    function absint($v) { return abs((int)$v); }
    function wp_json_encode($v,...$args) { return json_encode($v,...$args); }
    function get_option($k,$default=[]) { global $options; return $options[$k] ?? $default; }
    function update_option($k,$v,$autoload=false) { global $options; $old=$options[$k]??null; $options[$k]=$v; return $old!==$v; }
    function get_current_user_id() { return 9; }
    function current_time($format) { return '2026-10-10 21:00:00'; }
    function ff9d2_assert($cond,$label) { if (!$cond) {fwrite(STDERR,"9D2 FAIL: $label\n"); exit(1);} }
    class DbStub {
        public $prefix='wp_'; public $insert_id=0; public $datasets=[]; public $inserted=[]; public $updated=[];
        public function prepare($sql,...$args) { foreach ($args as $a) { $r=is_string($a) ? "'".addslashes($a)."'" : (string)$a; $sql=preg_replace('/%[dsf]/',$r,$sql,1); } return $sql; }
        public function get_var($sql) {if (strpos($sql,'SHOW TABLES')!==false) {preg_match("/'([^']+)'/",$sql,$m);return $m[1]??'';}return null;}
        public function get_col($sql) { if (strpos($sql,'briefing_store_items')!==false) return [88]; return []; }
        public function get_row($sql,$output=null) {
            if (strpos($sql,'briefing_sections')!==false) {
                if (strpos($sql,'id=11')!==false) return $this->datasets['oldSection'];
                if (strpos($sql,'id=22')!==false) return $this->datasets['newSection'];
                return null;
            }
            if (strpos($sql,'briefings')!==false) return $this->datasets['oldBriefing'];
            return null;
        }
        public function get_results($sql,$output=null) {
            if (strpos($sql,'briefing_sections')!==false) return strpos($sql,'briefing_id=2')!==false ? [$this->datasets['newSection']] : [$this->datasets['oldSection']];
            if (strpos($sql,'context_questions')!==false) return strpos($sql,'briefing_id=2')!==false ? [] : [$this->datasets['oldQuestion']];
            return [];
        }
        public function insert($table,$row) {$this->inserted[]=$row; $this->insert_id=400+count($this->inserted);return 1;}
        public function update($table,$row,$where){$this->updated[]=[$row,$where];return 1;}
    }
    $wpdb=new DbStub();
}
namespace RoutesPro\Catalog {
    class ProductCatalogService {
        public static $items=[];
        public static function clientIndex(int $clientId): array {return self::$items[$clientId]??[];}
        public static function normalizeText(string $v): string {$v=strtolower(trim($v));return trim(preg_replace('/\s+/',' ',preg_replace('/[^a-z0-9 ]+/',' ',$v)));}
        public static function normalizeReference(string $v): string {return strtoupper(preg_replace('/[^A-Z0-9]+/','',strtoupper($v)));}
        public static function normalizeEan(string $v): string {return ltrim(preg_replace('/\D+/','',$v),'0');}
        public static function capacityKey($value): string {return trim((string)$value);}
        public static function capacityFromName(string $name): string {return preg_match('/(\d+)cl/i',$name,$m)?$m[1]:'';}
    }
}
namespace RoutesPro\Forms {
    class ContextQuestions { public static function table():string {return 'wp_context_questions';} }
    class ProductCardex { public static function table_items(): string{return 'wp_cardex_items';} public static function table_cardex(): string{return 'wp_cardex';}}
}
namespace RoutesPro\Briefings {
    class BriefingService {
        public static function get(int $id): ?array {return $id===2?['id'=>2,'client_id'=>10,'project_id'=>100,'valid_from'=>'2026-10-12','valid_to'=>'2026-10-18','version_no'=>1]:null;}
        public static function table(string $name): string {return 'wp_'.$name;}
        public static function questionScopeAllowsUnmapped(string $scope): bool{return false;}
    }
}
namespace {
    require dirname(__DIR__).'/src/Briefings/BriefingCategoryMappingService.php';
    use RoutesPro\Briefings\BriefingCategoryMappingService as M;
    use RoutesPro\Catalog\ProductCatalogService as C;
    C::$items=[10=>[101=>['active'=>1,'name'=>'Jameson Original 70cl','reference'=>'JSO70','ean'=>'5601234567890','capacity_cl'=>'70','category'=>'Whiskey','aliases'=>[]],102=>['active'=>1,'name'=>'Jameson Black Barrel 70cl','reference'=>'JBB70','ean'=>'5601234567891','capacity_cl'=>'70','category'=>'Whiskey Premium','aliases'=>[]]],20=>[201=>['active'=>1,'name'=>'Vodka Absolut 70cl','reference'=>'ABS70','ean'=>'5601234567892','capacity_cl'=>'70','category'=>'Vodka','aliases'=>[]]]];
    ff9d2_assert(M::CONTRACT==='fieldflow-briefing-category-continuity-9d2-v1','contract');
    ff9d2_assert(M::productMatch(10,'Verificar Jameson Original 70cl')['id']===101,'exact product');
    ff9d2_assert(M::productMatch(10,'Verificar Jameson Black Barrel 70cl')['id']===102,'variant separation');
    ff9d2_assert(M::productMatch(10,'Verificar Jameson Original 1L')['id']===0,'different capacity never inherit');
    ff9d2_assert(M::resolve(10,100,'Verificar Jameson Original 70cl')['category']==='Whiskey','automatic');
    ff9d2_assert(M::saveManual(10,100,'Verificar Jameson Original 70cl','sonae','','Whiskey Premium'),'manual decision saved');
    ff9d2_assert(M::resolve(10,100,'Verificar Jameson Original 70cl','sonae')['source']==='manual','manual wins');
    ff9d2_assert(M::resolve(10,101,'Verificar Jameson Original 70cl','sonae')['source']==='catalog','project isolation');
    ff9d2_assert(M::resolve(20,100,'Verificar Jameson Original 70cl','sonae')['category']==='','client isolation');
    ff9d2_assert(!M::saveManual(10,100,'Verificar Jameson Original 70cl','sonae','','Gin'),'invalid category rejected');
    $secOld=['id'=>11,'briefing_id'=>1,'retailer_key'=>'sonae','section_type'=>'promotions','title'=>'Promoções','content_json'=>json_encode(['items'=>[['name'=>'Jameson Original 70cl'],['name'=>'Jameson Black Barrel 70cl']]])];
    $secNew=['id'=>22,'briefing_id'=>2,'retailer_key'=>'sonae','section_type'=>'promotions','title'=>'Promoções','content_json'=>json_encode(['items'=>[['name'=>'Jameson Original 70cl'],['name'=>'Jameson Black Barrel 70cl'],['name'=>'Novo produto 1L']]])];
    $wpdb->datasets['oldSection']=$secOld;
    $wpdb->datasets['newSection']=$secNew;
    $wpdb->datasets['oldBriefing']=['id'=>1];
    $wpdb->datasets['oldQuestion']=['id'=>301,'briefing_id'=>1,'client_id'=>10,'project_id'=>100,'location_id'=>88,'is_active'=>1,'source_type'=>'briefing','briefing_section_id'=>11,'question_key'=>'brf_old','question_label'=>'Verificar Jameson Original 70cl','visibility_rules_json'=>json_encode(['target_scope'=>'section']), 'question_type'=>'radio'];
    $stats=M::inheritVerifiedQuestions(2);
    ff9d2_assert($stats['restored']===1 && count($wpdb->inserted)===1,'same SKU persists after unrelated product added');
    ff9d2_assert(strpos($wpdb->inserted[0]['visibility_rules_json'],'Whiskey Premium')!==false,'manual category persisted');
    $wpdb->inserted=[];
    $wpdb->datasets['newSection']['content_json']=json_encode(['items'=>[['name'=>'Jameson Black Barrel 70cl'],['name'=>'Outro produto 1L']]]);
    $stats=M::inheritVerifiedQuestions(2);
    ff9d2_assert($stats['restored']===0 && $stats['review']===1,'changed SKU blocks inheritance');
    echo "BRIEFING CATEGORY CONTINUITY 9D2 PHP PASS | manual tenant/project isolation | variant | 1L | inheritance and product replacement\n";
}
