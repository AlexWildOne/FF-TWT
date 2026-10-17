<?php
namespace {
    if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
    if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
    if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
    if (!function_exists('sanitize_key')) { function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; } }
    if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return trim(strip_tags((string)$v)); } }
    if (!function_exists('current_time')) { function current_time($type){ return $type === 'mysql' ? '2026-09-18 19:30:00' : '2026-09-18'; } }
    if (!function_exists('wp_json_encode')) { function wp_json_encode($v,$flags=0){ return json_encode($v,$flags); } }
    if (!function_exists('is_wp_error')) { function is_wp_error($v){ return $v instanceof WP_Error; } }
    if (!class_exists('WP_Error')) {
        class WP_Error {
            private $code,$message,$data;
            public function __construct($code='',$message='',$data=null){$this->code=$code;$this->message=$message;$this->data=$data;}
            public function get_error_code(){return $this->code;}
            public function get_error_message(){return $this->message;}
            public function get_error_data(){return $this->data;}
        }
    }
    class FieldFlowFakeWpdb {
        public string $prefix='wp_'; public string $last_error=''; public int $insert_id=0;
        public array $queries=[]; public array $inserts=[]; public array $updates=[]; public array $deletes=[];
        public ?array $lockedRow=null; public array $oldAnswers=[]; public bool $failInsert=false;
        public function query($sql){$this->queries[]=$sql; return true;}
        public function prepare($query,...$args){foreach($args as $arg){$rep=is_numeric($arg)?(string)(int)$arg:"'".addslashes((string)$arg)."'";$query=preg_replace('/%[dsf]/',$rep,$query,1);}return$query;}
        public function get_row($sql,$mode=null){return $this->lockedRow;}
        public function get_results($sql,$mode=null){return $this->oldAnswers;}
        public function insert($table,$data,$formats=[]){
            if($this->failInsert){$this->last_error='Duplicate entry';return false;}
            $this->inserts[]=['table'=>$table,'data'=>$data,'formats'=>$formats];
            if(str_ends_with($table,'routespro_form_submissions')) $this->insert_id=101;
            return 1;
        }
        public function update($table,$data,$where,$formats=[],$whereFormats=[]){$this->updates[]=['table'=>$table,'data'=>$data,'where'=>$where];return 1;}
        public function delete($table,$where,$formats=[]){$this->deletes[]=['table'=>$table,'where'=>$where];return 1;}
    }
}

namespace RoutesPro\Forms {
    final class Forms {
        public static function table_submissions(): string { return 'wp_routespro_form_submissions'; }
        public static function table_answers(): string { return 'wp_routespro_form_submission_answers'; }
        public static function decode_schema(string $json): array { $v=json_decode($json,true); return is_array($v)?$v:['questions'=>[]]; }
    }
    final class DailySubmissionService {
        public static function normalise_report_day($v): string { return trim((string)$v) ?: '2026-09-18'; }
        public static function build_meta(array $base,string $day,$existing,int $user,string $saved): array { $base['daily_report']=['day'=>$day,'editor_user_id'=>$user,'original_submission_id'=>(int)($existing['id']??0)]; return $base; }
        public static function can_edit(array $row,int $user): bool { return true; }
        public static function edit_context_conflict(array $row,array $context){ return null; }
        public static function original_context(array $row): array { return ['route_id'=>(int)($row['route_id']??0)]; }
        public static function is_duplicate_database_error(string $error): bool { return stripos($error,'duplicate')!==false; }
    }
    final class ContextQuestions { public static function regular_for_context(array $c): array{return['ctx'];} public static function inject_into_schema(array $s,array $q): array{$s['trace'][]='context';return$s;} }
    final class BriefingFormBridge { public static function expand_blocks(array $s,array $c): array{$s['trace'][]='briefing';return$s;} }
    final class EvidenceFlow { public static function expand_schema(array $s): array{$s['trace'][]='evidence';return$s;} }
    final class ConditionalFlow { public static function normalise_schema(array $s): array{$s['trace'][]='conditional';return$s;} }
    final class ProductCardex { public static function synchronise_order_questions_with_fronts(array $s,array $c): array{$s['trace'][]='cardex';return$s;} }
}

namespace {
    $root=dirname(__DIR__);
    require_once $root.'/src/Forms/SubmissionApplicationService.php';
    $fail=[];
    $assert=static function($ok,$name)use(&$fail){echo($ok?'PASS ':'FAIL ').$name."\n";if(!$ok)$fail[]=$name;};
    $read=static fn($r)=>is_readable($root.'/'.$r)?(string)file_get_contents($root.'/'.$r):'';
    $contains=static function($c,$m){foreach($m as$x)if(strpos($c,$x)===false)return false;return true;};

    $service='\\RoutesPro\\Forms\\SubmissionApplicationService';
    $assert($service::CONTRACT==='fieldflow-submission-application-service-v1' && $service::VERSION===1,'application_service_contract');
    $schema=$service::buildSchema(['schema_json'=>json_encode(['questions'=>[['key'=>'q1']]])],['form_id'=>1]);
    $assert(($schema['trace']??[])===['context','briefing','evidence','conditional','cardex'],'canonical_schema_order');

    $GLOBALS['wpdb']=new FieldFlowFakeWpdb();
    $web=$service::persist([
        'form_id'=>7,'binding_id'=>8,'context'=>['client_id'=>1,'project_id'=>2,'route_id'=>3,'route_stop_id'=>4,'location_id'=>5],
        'user_id'=>9,'owner_user_id'=>9,'report_day'=>'2026-09-18','saved_at'=>'2026-09-18 19:30:00','base_meta'=>['source'=>'web'],
        'daily_existing'=>null,'is_update'=>false,'answers'=>['photos'=>['type'=>'image_upload','label'=>'Fotos','value'=>['https://example.test/a.jpg']]],
        'channel'=>'web','commit_after_persist'=>true,
    ]);
    $webDb=$GLOBALS['wpdb'];
    $webAnswer=$webDb->inserts[1]['data']??[];
    $assert(!is_wp_error($web) && !empty($web['committed']) && empty($web['transaction_open']),'web_durable_commit');
    $assert($webDb->queries===['START TRANSACTION','COMMIT'],'web_transaction_boundary');
    $assert(($webAnswer['value_text']??null)===($webAnswer['value_json']??null) && strpos((string)($webAnswer['value_json']??''),'https:\\/\\/')!==false,'web_storage_semantics_preserved');

    $GLOBALS['wpdb']=new FieldFlowFakeWpdb();
    $mobile=$service::persist([
        'form_id'=>7,'binding_id'=>8,'context'=>['client_id'=>1,'project_id'=>2,'route_id'=>3,'route_stop_id'=>4,'location_id'=>5],
        'user_id'=>9,'owner_user_id'=>9,'report_day'=>'2026-09-18','saved_at'=>'2026-09-18 19:30:00','base_meta'=>['source'=>'mobile'],
        'daily_existing'=>null,'is_update'=>false,'answers'=>['photos'=>['type'=>'image_upload','label'=>'Fotos','value'=>['https://example.test/a.jpg']]],
        'channel'=>'mobile','commit_after_persist'=>false,
    ]);
    $mobileDb=$GLOBALS['wpdb'];
    $mobileAnswer=$mobileDb->inserts[1]['data']??[];
    $assert(!is_wp_error($mobile) && empty($mobile['committed']) && !empty($mobile['transaction_open']),'mobile_legacy_transaction_preserved');
    $assert($mobileDb->queries===['START TRANSACTION'],'mobile_legacy_no_early_commit');
    $assert(array_key_exists('value_text',$mobileAnswer) && $mobileAnswer['value_text']===null && strpos((string)($mobileAnswer['value_json']??''),'https://')!==false,'mobile_storage_semantics_preserved');

    $db=new FieldFlowFakeWpdb();
    $db->lockedRow=['id'=>42,'submitted_at'=>'2026-09-18 10:00:00','route_id'=>3,'route_stop_id'=>4,'location_id'=>5];
    $db->oldAnswers=[['question_key'=>'old','value_text'=>'x']];
    $GLOBALS['wpdb']=$db;
    $updated=$service::persist([
        'form_id'=>7,'binding_id'=>8,'context'=>['client_id'=>1,'project_id'=>2,'route_id'=>3,'route_stop_id'=>4,'location_id'=>5],
        'user_id'=>9,'owner_user_id'=>9,'report_day'=>'2026-09-18','saved_at'=>'2026-09-18 19:30:00','base_meta'=>['source'=>'web'],
        'daily_existing'=>['id'=>42],'is_update'=>true,'answers'=>['q'=>['type'=>'text','label'=>'Q','value'=>'novo']],
        'channel'=>'web','commit_after_persist'=>true,
    ]);
    $assert(!is_wp_error($updated) && (int)($updated['submission_id']??0)===42 && (string)($updated['original_submitted_at']??'')==='2026-09-18 10:00:00','update_identity_preserved');
    $assert(!empty($updated['audit_before']['submission']) && count($db->deletes)===1,'update_audit_and_answer_replace');

    $state=['submission_id'=>42,'form_id'=>7,'binding_id'=>8,'context'=>['client_id'=>1,'project_id'=>2,'route_id'=>3,'route_stop_id'=>4,'location_id'=>5],'user_id'=>9,'owner_user_id'=>9,'saved_at'=>'2026-09-18 19:30:00','original_submitted_at'=>'2026-09-18 10:00:00','report_day'=>'2026-09-18','is_update'=>true,'meta_json'=>'{}','answers'=>['q'=>['type'=>'text','value'=>'x','label'=>'Q']],'audit_before'=>['submission'=>['id'=>42]],'source'=>'mobile','history_mode'=>'inline','has_cardex_override'=>true];
    $payload=$service::buildSubmissionPayload($state); $job=$service::buildPipelineJob($state);
    $assert(($payload['submission_id']??0)===42 && ($payload['submitted_at']??'')==='2026-09-18 10:00:00','canonical_submission_payload');
    $assert(($job['source']??'')==='mobile' && ($job['history_mode']??'')==='inline' && !empty($job['has_cardex_override']) && ($job['submission_row']['submitted_at']??'')==='2026-09-18 19:30:00','canonical_pipeline_envelope');

    $forms=$read('src/Forms/Forms.php'); $mobileSrc=$read('src/Mobile/SubmissionService.php'); $loader=$read('src/Support/Loader.php');
    $assert($contains($forms,['SubmissionApplicationService::buildSchema($form, $context_for_questions)','SubmissionApplicationService::buildSchema($form, $submit_context)','SubmissionApplicationService::persist([','SubmissionApplicationService::buildPipelineJob']),'web_application_service_integration');
    $assert($contains($mobileSrc,['use RoutesPro\\Forms\\SubmissionApplicationService','SubmissionApplicationService::persist([','commit_after_persist',"'channel' => 'mobile'",'SubmissionApplicationService::buildPipelineJob']),'mobile_application_service_integration');
    $assert(strpos($forms,'$wpdb->insert(self::table_submissions()')===false && strpos($mobileSrc,'$wpdb->insert(Forms::table_submissions()')===false,'channel_sql_duplication_removed');
    $assert(strpos($loader,'src/Forms/SubmissionApplicationService.php')!==false,'loader_registration');

    if($fail){fwrite(STDERR,"SUBMISSION APPLICATION SERVICE CONTRACT FAILED\n".implode("\n",$fail)."\n");exit(1);} 
    echo "SUBMISSION APPLICATION SERVICE CONTRACT OK\n";
    echo "contract=fieldflow-submission-application-service-v1\n";
    echo "web_mobile_durable_persistence=shared\n";
    echo "mobile_legacy_transaction_semantics=preserved\n";
    echo "secondary_side_effects=outside_service\n";
}
