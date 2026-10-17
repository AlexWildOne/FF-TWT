<?php
/**
 * 9D4 isolated contract test. No live WordPress/SQL or paid service calls.
 * Validates per-user + per-scope transients, combined filters, and DB paging.
 */
namespace {
    define('ABSPATH', '/tmp/wp/');
    define('ARRAY_A', 'ARRAY_A');
    function absint($n) { return abs((int)$n); }
    function sanitize_text_field($x) { return (string)$x; }
    function sanitize_key($x) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$x)); }
    function sanitize_title($x) { return strtolower(preg_replace('/\s+/', '-', trim((string)$x))); }
    function wp_json_encode($data) { return json_encode($data); }
    function get_option($key,$default=1) { return $default; }
    function get_current_user_id() { return (int)($GLOBALS['actor'] ?? 0); }
    function get_transient($key) { return $GLOBALS['ff_transients'][$key] ?? false; }
    function set_transient($key,$value,$ttl) { $GLOBALS['ff_transients'][$key] = $value; return true; }
    class WP_REST_Request {
        private array $params=[];
        public function __construct($method='GET',$route='/') {}
        public function set_param($key,$value): void { $this->params[$key]=$value; }
        public function get_param($key) { return $this->params[$key] ?? null; }
    }
    class WP_REST_Response {
        public function __construct(public array $data, public int $status) {}
    }
    function ff_assert($valid, $message): void { if(!$valid) {fwrite(STDERR,'FAIL 9D4: '.$message."\n");exit(1);} }
    class DatabaseStub9D4 {
        public string $prefix='wp_';
        public array $queries=[];
        public int $countQueries=0;
        public int $listQueries=0;
        public function prepare($sql,...$args) {
            foreach ($args as $arg) {
                $sql = preg_replace('/%[ds]/', is_numeric($arg) ? (string)(int)$arg : "'".addslashes((string)$arg)."'", $sql, 1);
            }
            return $sql;
        }
        public function get_row($sql,$mode=null) {
            $this->countQueries++;
            $this->queries[]=$sql;
            if (strpos($sql, 'COUNT(DISTINCT l.id)') !== false) {
                return ['total_visible'=>1001,'with_coords'=>901,'google_count'=>601,'validated_count'=>801];
            }
            return [];
        }
        public function get_results($sql,$mode=null) {
            $this->listQueries++;
            $this->queries[]=$sql;
            if (strpos($sql, 'SELECT DISTINCT l.*') !== false) {
                preg_match('/LIMIT (\d+) OFFSET (\d+)/',$sql,$matches);
                $limit=(int)($matches[1]??100);
                $offset=(int)($matches[2]??0);
                $owner=(int)($GLOBALS['actor']??0);
                $scope=(int)($GLOBALS['scope_generation']??1);
                $rows=[];
                for($n=0;$n<$limit;$n++) {
                    if($offset+$n>=1001)break;
                    $rows[]=['id'=>$owner*100000+$scope*10000+$offset+$n+1,'name'=>'PDV '.($offset+$n+1),'lat'=>38.5,'lng'=>-8.1,'updated_at'=>'2026-10-11 10:00:00','source'=>'manual','is_validated'=>1];
                }
                return $rows;
            }
            return [['district'=>'Porto','county'=>'Porto','city'=>'Porto','cat_id'=>1,'cat_name'=>'Retalho','cat_slug'=>'retalho','cat_parent_id'=>0,'sub_id'=>0,'parent_category_id'=>0]];
        }
    }
}
namespace RoutesPro\Support {
    class Permissions {
        public static function get_scope(?int $user_id=null): array {
            $uid=$user_id ?? \get_current_user_id();
            return ['is_manager'=>false,'client_ids'=>[$uid==11?10:20], 'project_ids'=>[($uid==11?100:200)+(int)($GLOBALS['scope_generation']??1)],'route_ids'=>[]];
        }
        public static function scope_sql($a='',$b=null,$c=null): array {
            $scope=self::get_scope();
            return [$b.' IN (%d) AND '.$c.' IN (%d)',[$scope['client_ids'][0],$scope['project_ids'][0]]];
        }
    }
}
namespace RoutesPro\Services { class LocationDeduplicator { public static function dedupe_rows($rows){return $rows;} } }
namespace {
    require dirname(__DIR__).'/src/Rest/CommercialController.php';
    $GLOBALS['actor']=11;
    $GLOBALS['scope_generation']=1;
    $GLOBALS['ff_transients']=[];
    $GLOBALS['wpdb']=new DatabaseStub9D4();
    $db=$GLOBALS['wpdb'];
    $controller=new \RoutesPro\Rest\CommercialController();
    $req=new WP_REST_Request();
    $req->set_param('client_id',10);
    $req->set_param('owner_user_id',11);
    $req->set_param('per_page',10);
    $first=$controller->search($req)->data;
    ff_assert($first['total']===1001,'total represents all visible rows');
    ff_assert($first['stats']['google_count']===601 && $first['stats']['validated_count']===801,'untruncated scope statistics');
    ff_assert(count($first['items'])===10,'first page size');
    ff_assert($db->countQueries===1,'first page count query executed');
    $sql=implode(' ', $db->queries);
    ff_assert(strpos($sql,'(clp.client_id=10 OR l.client_id=10)')!==false,'client filter retained with owner');
    ff_assert(strpos($sql,'cl.assigned_to=11')!==false,'owner filter retained with client');
    ff_assert(strpos($sql,'COUNT(DISTINCT l.id)')!==false,'distinct stable database identity');
    $controller->search($req);
    ff_assert($db->countQueries===1,'identical scope receives cache hit');

    $GLOBALS['actor']=22;
    $second=$controller->search($req)->data;
    ff_assert($db->countQueries===2,'different user cannot hit prior tenant cache');
    ff_assert($first['items'][0]['id']!==$second['items'][0]['id'],'user B result isolated');
    $GLOBALS['actor']=11;
    $GLOBALS['scope_generation']=2;
    $controller->search($req);
    ff_assert($db->countQueries===3,'changed effective permission scope forces cache miss');
    $GLOBALS['scope_generation']=1;
    $otherPage=new WP_REST_Request();$otherPage->set_param('page',101);$otherPage->set_param('per_page',10);
    $last=$controller->search($otherPage)->data;
    ff_assert($last['total']===1001 && count($last['items'])===1,'last page 1001 is accessible');
    ff_assert(strpos(implode(' ', $db->queries),'LIMIT 10 OFFSET 1000')!==false,'SQL offset occurs before result encoding');

    $filters=new WP_REST_Request();
    $filters->set_param('location_id',333);
    $filters->set_param('owner_user_id',11);
    $filters->set_param('include_unassigned',1);
    $before=count($db->queries);
    $controller->filters($filters);
    $query=implode(' ',array_slice($db->queries,$before));
    ff_assert(strpos($query,'l.id=333')!==false,'filter faceting respects location_id');
    ff_assert(strpos($query,'cl.assigned_to IS NULL')!==false,'facets respect include_unassigned');
    $before=$db->listQueries;
    $controller->filters($filters);
    ff_assert($db->listQueries===$before,'facets cached by complete parameters');
    $filters->set_param('include_unassigned',0);
    $controller->filters($filters);
    ff_assert($db->listQueries===$before+1,'include_unassigned variation cannot hit old cache');
    echo "COMMERCIAL PRODUCTION ISOLATION 9D4 PHP PASS | user and scope isolated | client+owner | complete SQL totals | page 1001 | faceting\n";
}
