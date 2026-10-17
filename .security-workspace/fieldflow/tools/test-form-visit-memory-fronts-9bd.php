<?php
namespace {
    define('ABSPATH', __DIR__ . '/');
    define('ARRAY_A', 'ARRAY_A');
    function sanitize_key($v){ $v=strtolower((string)$v); return preg_replace('/[^a-z0-9_\-]/','',$v) ?: ''; }
    function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
    function sanitize_textarea_field($v){ return trim(strip_tags((string)$v)); }
    function absint($v){ return abs((int)$v); }
    function remove_accents($v){ return (string)$v; }
    function wp_strip_all_tags($v){ return strip_tags((string)$v); }
    function current_time($type){ return $type === 'Y-m-d' ? '2026-09-28' : '2026-09-28 15:00:00'; }
    function wp_date($format,$timestamp=null,$timezone=null){ return date($format,$timestamp ?: time()); }
    function wp_timezone(){ return new \DateTimeZone('Europe/Lisbon'); }

    final class FFTestWpdb {
        public string $prefix = 'wp_';
        public string $last_query = '';
        public function prepare($query, ...$args){
            if (count($args) === 1 && is_array($args[0])) $args = $args[0];
            $index = 0;
            $out = preg_replace_callback('/%[dsf]/', static function($m) use (&$index, $args){
                $value = $args[$index++] ?? '';
                if ($m[0] === '%d') return (string)(int)$value;
                if ($m[0] === '%f') return (string)(float)$value;
                return "'" . addslashes((string)$value) . "'";
            }, (string)$query);
            return $out;
        }
        public function get_var($query){ $this->last_query = (string)$query; return 'report_day'; }
        public function get_row($query, $format=null){ $this->last_query = (string)$query; return null; }
    }
    $GLOBALS['wpdb'] = new FFTestWpdb();
}

namespace RoutesPro\Forms {
    final class Forms {
        public static function table_submissions(): string { return 'wp_routespro_form_submissions'; }
    }
}

namespace {
    require_once dirname(__DIR__) . '/src/Services/SubmissionStatusPolicy.php';
    require_once dirname(__DIR__) . '/src/Forms/DailySubmissionService.php';
    require_once dirname(__DIR__) . '/src/Forms/ProductCardex.php';

    use RoutesPro\Forms\DailySubmissionService;
    use RoutesPro\Forms\ProductCardex;

    $eligible = DailySubmissionService::visit_memory_question_eligible(['key'=>'notes','type'=>'textarea']);
    $photo = DailySubmissionService::visit_memory_question_eligible(['key'=>'photo','type'=>'image_upload']);
    $file = DailySubmissionService::visit_memory_question_eligible(['key'=>'file','type'=>'file_upload']);
    $order = DailySubmissionService::visit_memory_question_eligible(['key'=>'order','type'=>'order_suggestion']);
    $matrix = DailySubmissionService::visit_memory_question_eligible(['key'=>'fronts','type'=>'product_matrix']);
    if (!$eligible || $photo || $file || $order || $matrix) { fwrite(STDERR, "visit memory eligibility failed\n"); exit(1); }

    DailySubmissionService::find_previous_operational(88, 55, '2026-09-28', 12, 34);
    $query = $GLOBALS['wpdb']->last_query;
    if (strpos($query, 'route_location_snapshot') === false
        || strpos($query, 'route_stops') === false
        || strpos($query, 'END)=55') === false
        || strpos($query, 's.project_id=34') === false
        || strpos($query, 's.client_id=12') === false) {
        fwrite(STDERR, "campaign/canonical-store previous visit scope failed\n"); exit(1);
    }
    if (strpos($query, 's.form_id=88') !== false) {
        fwrite(STDERR, "previous visit is still tied to form id\n"); exit(1);
    }
    if (strpos($query, "s.report_day < '2026-09-28'") === false) {
        fwrite(STDERR, "previous visit report day boundary failed\n"); exit(1);
    }

    $first = ProductCardex::normalise_fronts_submission_rows([
        ['category'=>'Gin','ref'=>'ABC','name'=>'Product A','qty'=>'4']
    ], ['type'=>'product_matrix','product_source'=>'manual'], []);
    if (count($first)!==1 || (float)$first[0]['qty'] !== 4.0 || array_key_exists('before',$first[0])) {
        fwrite(STDERR, "first visit fronts persistence failed\n"); exit(1);
    }

    $next = ProductCardex::normalise_fronts_submission_rows([
        ['category'=>'Gin','ref'=>'ABC','name'=>'Product A','before'=>'4','after'=>'7']
    ], ['type'=>'product_matrix','product_source'=>'manual'], []);
    if (count($next)!==1 || (float)$next[0]['before'] !== 4.0 || (float)$next[0]['after'] !== 7.0 || (float)$next[0]['qty'] !== 7.0) {
        fwrite(STDERR, "rolling fronts persistence failed\n"); exit(1);
    }

    $question = [
        'type'=>'product_matrix',
        'product_source'=>'manual',
        'product_rows'=>[['category'=>'Gin','ref'=>'ABC','name'=>'Product A']],
    ];
    $history = [['category'=>'Gin','ref'=>'ABC','name'=>'Product A','before'=>2,'after'=>7,'qty'=>7]];
    $newVisitRows = ProductCardex::question_rows($question, ['_daily_existing'=>false], $history);
    if (count($newVisitRows)!==1 || (float)$newVisitRows[0]['before'] !== 7.0 || (float)$newVisitRows[0]['after'] !== 7.0) {
        fwrite(STDERR, "fronts new visit rotation failed\n"); exit(1);
    }
    $sameDayRows = ProductCardex::question_rows($question, ['_daily_existing'=>true], $history);
    if (count($sameDayRows)!==1 || (float)$sameDayRows[0]['before'] !== 2.0 || (float)$sameDayRows[0]['after'] !== 7.0) {
        fwrite(STDERR, "fronts same day edit preservation failed\n"); exit(1);
    }

    echo "FORM VISIT MEMORY FRONTS 9BD RUNTIME OK\n";
}
