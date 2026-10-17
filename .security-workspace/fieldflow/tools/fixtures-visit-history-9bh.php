<?php
namespace {
    define('ABSPATH', __DIR__ . '/');
    define('ARRAY_A', 'ARRAY_A');
    define('ROUTESPRO_PATH', dirname(__DIR__) . '/');
    define('FIELDFLOW_VERSION', '2.2.581');
    function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; }
    function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
    function sanitize_textarea_field($v){ return trim(strip_tags((string)$v)); }
    function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
    function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
    function esc_url($v){return (string)$v;}
    function esc_url_raw($v){return (string)$v;}
    function admin_url($v){return 'https://example.test/wp-admin/'.$v;}
    function wp_nonce_field(...$args){return '';}
    function wp_unslash($v){return $v;}
    function mysql2date($format,$date){return date($format,strtotime($date));}
    function date_i18n($format,$timestamp){return date($format,$timestamp);}
    function absint($v){ return abs((int)$v); }
    function remove_accents($v){ return (string)$v; }
    function wp_strip_all_tags($v){ return strip_tags((string)$v); }
    function wp_json_encode($v,$flags=0){ return json_encode($v,$flags); }
    function current_time($type,$gmt=false){ return $type === 'Y-m-d' ? '2026-10-01' : '2026-10-01 15:00:00'; }
    function wp_date($format,$timestamp=null,$timezone=null){ return date($format,$timestamp ?: strtotime('2026-10-01')); }
    function wp_timezone(){ return new \DateTimeZone('Europe/Lisbon'); }
    function get_current_user_id(){ return 7; }
    function is_user_logged_in(){ return true; }
    function is_wp_error($value){ return $value instanceof \WP_Error; }
    function wp_get_current_user(){ return new class { public string $display_name='Tester'; public function exists(){ return true; } }; }
    class WP_Error { public function __construct(...$args){} }

    // Execute the production queries rather than merely checking SQL substrings.
    final class FFHistory9BHDb {
        public string $prefix='wp_';
        public \PDO $db;
        public bool $hasDay=true;
        public function __construct(){
            $this->db=new \PDO('sqlite::memory:');
            $this->db->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
            $this->db->exec('CREATE TABLE wp_routespro_form_submissions (id INTEGER PRIMARY KEY, form_id INTEGER, location_id INTEGER, client_id INTEGER, project_id INTEGER, report_day TEXT, submitted_at TEXT, status TEXT, record_id INTEGER DEFAULT 0, record_version_id INTEGER DEFAULT 0, route_id INTEGER DEFAULT 0, route_stop_id INTEGER DEFAULT 0, user_id INTEGER DEFAULT 7, meta_json TEXT DEFAULT "")');
            $this->db->exec('CREATE TABLE wp_routespro_routes (id INTEGER PRIMARY KEY, client_id INTEGER DEFAULT 0, project_id INTEGER DEFAULT 0, date TEXT, status TEXT)');
            $this->db->exec("CREATE TABLE wp_routespro_route_stops (id INTEGER PRIMARY KEY, route_id INTEGER, location_id INTEGER, status TEXT DEFAULT 'planned')");
            $this->db->exec("CREATE TABLE wp_routespro_route_location_snapshot (id INTEGER PRIMARY KEY, route_id INTEGER, route_stop_id INTEGER, location_id INTEGER, name TEXT DEFAULT '')");
            $this->db->exec('CREATE TABLE wp_routespro_form_submission_answers (id INTEGER PRIMARY KEY, submission_id INTEGER, question_key TEXT, question_label TEXT, value_text TEXT, value_number REAL, value_json TEXT)');
            $this->db->exec('CREATE TABLE wp_routespro_form_records (id INTEGER PRIMARY KEY, record_key TEXT, current_version_id INTEGER)');
            $this->db->exec('CREATE TABLE wp_routespro_form_record_versions (id INTEGER PRIMARY KEY, submission_id INTEGER, version_no INTEGER, submitted_at TEXT)');
            $this->db->exec('CREATE TABLE wp_routespro_form_record_values (id INTEGER PRIMARY KEY, version_id INTEGER, question_key TEXT, question_label TEXT, value_type TEXT, value_text TEXT, value_number REAL, value_json TEXT, sort_order INTEGER DEFAULT 0)');
        }
        public function prepare($query,...$args){
            if(count($args)===1 && is_array($args[0])) $args=$args[0];
            $i=0;
            return preg_replace_callback('/%[dsf]/',function($m)use(&$i,$args){$v=$args[$i++]??'';return $m[0]==='%s'?$this->db->quote((string)$v):(string)(0+$v);},$query);
        }
        public function get_var($sql){ if(str_starts_with($sql,'SHOW COLUMNS'))return $this->hasDay?'report_day':null; $value=$this->db->query($sql)->fetchColumn();return $value===false?null:$value; }
        public function get_row($sql,$format=null){ $row=$this->db->query($sql)->fetch(\PDO::FETCH_ASSOC);return $row===false?null:$row; }
        public function get_results($sql,$format=null){ return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC); }
        public function submission(int $id,int $form,int $store,string $day,string $at,string $status='submitted',int $client=12,int $project=34){
            $stmt=$this->db->prepare('INSERT INTO wp_routespro_form_submissions (id,form_id,location_id,client_id,project_id,report_day,submitted_at,status) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$id,$form,$store,$client,$project,$day,$at,$status]);
        }
        public function answer(int $submission,string $key,$value){
            $stmt=$this->db->prepare('INSERT INTO wp_routespro_form_submission_answers (submission_id,question_key,question_label,value_text,value_number,value_json) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$submission,$key,$key,is_scalar($value)?(string)$value:'',is_bool($value)?(int)$value:null,is_array($value)?json_encode($value):null]);
        }
    }
    $GLOBALS['wpdb']=new FFHistory9BHDb();
}
namespace RoutesPro\Support {
    final class Permissions { public static function is_manager($id){return true;} public static function can_access_route(...$args){return true;} }
}
