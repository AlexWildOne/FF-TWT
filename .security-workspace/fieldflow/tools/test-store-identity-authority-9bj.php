<?php
namespace {
    define('ABSPATH', __DIR__ . '/');
    define('ARRAY_A', 'ARRAY_A');
    function absint($v){ return abs((int)$v); }
    function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; }
    function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
    function current_time($type,$gmt=false){ return $type === 'Y-m-d' ? '2026-10-01' : '2026-10-01 18:00:00'; }
    function is_user_logged_in(){ return false; }
    final class FFStoreIdentity9BJDb {
        public string $prefix='wp_';
        public function prepare($query,...$args){
            if(count($args)===1 && is_array($args[0])) $args=$args[0];
            $i=0;
            return preg_replace_callback('/%[dsf]/', function($m) use (&$i,$args){
                $v=$args[$i++]??'';
                return $m[0]==='%s' ? "'".str_replace("'","''",(string)$v)."'" : (string)(0+$v);
            }, $query);
        }
        public function get_row($sql,$format=null){
            if(strpos($sql,'route_stops rs')!==false && strpos($sql,'rs.id = 142')!==false){
                return ['route_id'=>10,'location_id'=>87,'client_id'=>12,'project_id'=>34,'route_date'=>'2026-10-01'];
            }
            if(strpos($sql,'routes WHERE id = 10')!==false){
                return ['client_id'=>12,'project_id'=>34,'route_date'=>'2026-10-01'];
            }
            return null;
        }
        public function get_var($sql){ return null; }
    }
    $GLOBALS['wpdb']=new FFStoreIdentity9BJDb();
}
namespace RoutesPro\Support {
    final class Permissions { public static function get_scope($id){ return []; } }
}
namespace {
    require_once dirname(__DIR__).'/src/Forms/BindingResolver.php';
    require_once dirname(__DIR__).'/src/Forms/DailySubmissionService.php';
    use RoutesPro\Forms\BindingResolver;
    use RoutesPro\Forms\DailySubmissionService;

    $checks=0;
    $assert=static function(bool $ok,string $name) use (&$checks){
        if(!$ok) throw new \RuntimeException('FAIL '.$name);
        $checks++; echo 'PASS '.$name.PHP_EOL;
    };

    $ctx=BindingResolver::get_context([
        'stop_id'=>142,'route_id'=>999,'location_id'=>142,'client_id'=>999,'project_id'=>999,'date'=>'2026-09-01'
    ]);
    $assert((int)$ctx['route_id']===10 && (int)$ctx['route_stop_id']===142,'stop_is_route_authority');
    $assert((int)$ctx['location_id']===87,'stop_is_store_authority');
    $assert((int)$ctx['client_id']===12 && (int)$ctx['project_id']===34,'stop_route_is_campaign_authority');
    $assert((string)$ctx['route_date']==='2026-10-01','stop_route_is_day_authority');

    $ctxAlias=BindingResolver::get_context(['route_stop_id'=>142,'location_id'=>999]);
    $assert((int)$ctxAlias['location_id']===87 && (int)$ctxAlias['route_stop_id']===142,'route_stop_alias_is_canonical');

    $submission=['id'=>301,'client_id'=>12,'project_id'=>34,'route_id'=>10,'route_stop_id'=>142,'location_id'=>142,'_canonical_location_id'=>87];
    $same=['client_id'=>12,'project_id'=>34,'route_id'=>10,'route_stop_id'=>142,'location_id'=>87];
    $wrongStore=array_replace($same,['location_id'=>142]);
    $assert(DailySubmissionService::edit_context_conflict($submission,$same)===null,'canonical_store_accepts_legacy_raw_location');
    $conflict=DailySubmissionService::edit_context_conflict($submission,$wrongStore);
    $assert(is_array($conflict) && ($conflict['field']??'')==='location_id','canonical_store_detects_numeric_collision');

    $sql=DailySubmissionService::canonical_location_sql('s');
    $assert(strpos($sql,'route_location_snapshot')!==false && strpos($sql,'route_stops')!==false,'canonical_sql_uses_stop_and_snapshot');
    $assert(strpos($sql,'direct_rs.route_id=s.route_id')!==false && strpos($sql,'legacy_rs.id=s.location_id')!==false,'canonical_sql_prefers_direct_store_before_legacy_stop_fallback');

    echo 'STORE IDENTITY AUTHORITY 9BJ RUNTIME OK checks='.$checks.PHP_EOL;
}
