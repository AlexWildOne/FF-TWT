<?php
namespace { require_once __DIR__.'/fixtures-visit-history-9bh.php'; }
namespace RoutesPro\Forms {
    final class Forms {
        public static array $schema=[];
        public static function table_submissions(){return 'wp_routespro_form_submissions';}
        public static function table_answers(){return 'wp_routespro_form_submission_answers';}
        public static function get_form($id){return ['id'=>$id,'status'=>'active','schema_json'=>json_encode(self::$schema)];}
        public static function decode_schema($json){return json_decode($json,true);}
        public static function schema_hash($schema){return hash('sha256',json_encode($schema));}
        public static function definition_version($form){return 'test';}
    }
}
namespace {
    require_once ROUTESPRO_PATH.'src/Services/SubmissionStatusPolicy.php';
    require_once ROUTESPRO_PATH.'src/Services/OperationalReportDayPolicy.php';
    require_once ROUTESPRO_PATH.'src/Forms/DailySubmissionService.php';
    require_once ROUTESPRO_PATH.'src/Forms/RecordService.php';
    require_once ROUTESPRO_PATH.'src/Forms/ProductCardex.php';
    require_once ROUTESPRO_PATH.'src/Forms/FormRenderer.php';
    require_once ROUTESPRO_PATH.'src/Mobile/FormPayloadService.php';
    require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
    use RoutesPro\Forms\DailySubmissionService as Daily;
    use RoutesPro\Forms\RecordService;
    use RoutesPro\Forms\ProductCardex;
    use RoutesPro\Forms\Forms;
    use RoutesPro\Mobile\FormPayloadService;
    $checks=0;
    $assert=static function(bool $ok,string $name)use(&$checks){if(!$ok)throw new \RuntimeException('FAIL '.$name);$checks++;echo 'PASS '.$name.PHP_EOL;};
    $db=$GLOBALS['wpdb'];
    $db->submission(101,88,55,'2026-09-30','2026-09-30 11:00:00');
    $db->submission(102,87,55,'2026-10-01','2026-10-01 09:00:00');
    $db->submission(103,86,55,'2026-10-01','2026-10-01 10:00:00','draft');
    $db->submission(104,87,55,'2026-10-02','2026-10-02 09:00:00');
    $db->submission(105,87,56,'2026-10-01','2026-10-01 13:00:00');
    $db->submission(106,87,55,'2026-10-01','2026-10-01 13:00:00','submitted',12,35);
    $db->submission(107,87,55,'2026-10-01','2026-10-01 13:00:00','submitted',13,34);
    foreach(['void','cancelled','deleted','trash','rejected'] as $index=>$status)$db->submission(120+$index,87,55,'2026-10-01','2026-10-01 14:00:00',$status);
    $assert(Daily::find_previous_operational(88,55,'2026-10-01',12,34)['id']==101,'legacy_strict_boundary_retained');
    $assert(Daily::find_previous_operational(88,55,'2026-10-01',12,34,true)['id']==102,'latest_same_day_across_form_revision_with_store_campaign_client_status_scope');
    $assert(Daily::find_previous_operational(88,55,'2026-10-05',12,34,true)['id']==102,'future_report_does_not_take_future_history');
    $assert(Daily::find_previous_operational(88,55,'2026-10-01',12,34,true,102)['id']==101,'exclude_current_submission_by_id');
    $assert(Daily::find_previous_operational(88,55,'2026-09-29',12,34,true)===null,'no_future_or_generic_fallback_for_historical_date');
    $assert(Daily::find_previous_operational(88,66,'2026-10-01',12,34,true)===null,'no_history_from_other_store');
    $db->submission(130,87,77,'','2026-10-01 12:00:00');
    $assert(Daily::find_previous_operational(88,77,'2026-10-01',12,34,true)['id']==130,'legacy_blank_report_day');
    $db->submission(131,88,78,'','2026-10-01 12:00:00');
    $assert(Daily::find_existing(88,78,'2026-10-01')['id']==131,'same_day_edit_with_blank_legacy_report_day');
    $db->submission(132,88,79,'2026-10-01','2026-10-01 12:00:00','submitted',13,0);
    $assert(Daily::find_previous_operational(88,79,'2026-10-01',12,0,true)===null,'form_only_scope_keeps_client_isolation');
    $db->db->exec("INSERT INTO wp_routespro_routes VALUES (301,'2026-09-27','done'),(302,'2026-10-01','done'),(303,'2026-10-02','pending')");
    $db->submission(140,87,55,'','2026-10-01 14:50:00');
    $db->db->exec('UPDATE wp_routespro_form_submissions SET route_id=301 WHERE id=140');
    $assert(Daily::find_previous_operational(88,55,'2026-10-01',12,34,true)['id']==102,'delayed_legacy_sync_respects_original_route_day');
    $db->submission(141,87,80,'','2026-09-30 12:00:00');
    $db->db->exec('UPDATE wp_routespro_form_submissions SET route_id=302 WHERE id=141');
    $assert(Daily::find_previous_operational(88,80,'2026-09-30',12,34,true)===null,'legacy_route_day_beats_audit_timestamp');
    $db->submission(142,87,81,'','2026-09-30 12:00:00');
    $db->db->exec('UPDATE wp_routespro_form_submissions SET route_id=303 WHERE id=142');
    $assert(Daily::find_previous_operational(88,81,'2026-10-01',12,34,true)===null,'legacy_future_route_is_not_history');
    $dayProperty=new \ReflectionProperty(Daily::class,'has_report_day_column');
    $dayProperty->setValue(null,null);$db->hasDay=false;
    $assert(Daily::find_previous_operational(88,55,'2026-10-01',12,34,true)['id']==102,'legacy_without_report_day_column');
    $dayProperty->setValue(null,null);$db->hasDay=true;

    $schema=['questions'=>[
        ['key'=>'notes','type'=>'textarea','label'=>'notes'],['key'=>'checked','type'=>'checkbox'],
        ['key'=>'photo','type'=>'image_upload'],['key'=>'categorized','type'=>'categorized_image_upload'],
        ['key'=>'file','type'=>'file_upload'],['key'=>'order','type'=>'order_suggestion','product_source'=>'manual'],
        ['key'=>'fronts','type'=>'product_matrix','product_source'=>'manual','product_rows'=>[['category'=>'Gin','ref'=>'A','name'=>'Product A']]],
    ]];
    $fronts=[['category'=>'Gin','ref'=>'A','name'=>'Product A','before'=>2,'after'=>8,'qty'=>2]];
    $input=['notes'=>'LATEST','checked'=>false,'photo'=>['old-photo'],'categorized'=>['old-cat'],'file'=>['old-file'],'order'=>[['boxes'=>99,'notes'=>'old-order']],'fronts'=>$fronts,'obsolete'=>'old-key'];
    $memory=Daily::visit_memory_prefill($input,$schema);
    $assert(array_keys($memory)===['notes','checked','fronts'],'informational_memory_excludes_media_files_orders_obsolete_keys');
    $assert(Daily::new_visit_prefill($input,$schema)===['fronts'=>$fronts],'new_visit_inputs_only_fronts');
    $assert(Daily::new_visit_prefill($input,['questions'=>[]])===[],'empty_schema_has_no_historical_inputs');
    $filtered=RecordService::without_historical_media($input,$schema);
    $assert(!isset($filtered['photo'],$filtered['categorized'],$filtered['file'],$filtered['order']),'legacy_history_filter_excludes_all_media_and_orders');
    $rotated=ProductCardex::question_rows($schema['questions'][6],['_daily_existing'=>false],$fronts);
    $assert((float)$rotated[0]['before']===8.0&&(float)$rotated[0]['after']===8.0,'fronts_use_latest_final_after_not_old_before_or_stale_qty');
    $edited=ProductCardex::question_rows($schema['questions'][6],['_daily_existing'=>true],$fronts);
    $assert((float)$edited[0]['before']===2.0&&(float)$edited[0]['after']===8.0,'editing_preserves_own_fronts_pair');
    $fallback=new \ReflectionMethod(RoutesPro\Forms\FormRenderer::class,'product_rows_from_question');
    $fallbackRows=$fallback->invoke(null,$schema['questions'][6],$fronts,[]);
    $assert($fallbackRows[0]['before']==8&&$fallbackRows[0]['after']==8&&$fallbackRows[0]['qty']==8,'renderer_fallback_uses_final_fronts');

    foreach($input as $key=>$value)$db->answer(102,$key,$value);
    $db->answer(101,'notes','ONE VISIT BEHIND');
    $db->db->exec("INSERT INTO wp_routespro_form_records VALUES (1,'88:12:34:55',900),(2,'88:12:34:66',901)");
    $db->db->exec("INSERT INTO wp_routespro_form_record_versions VALUES (900,101,1,'2026-09-30 11:00:00'),(901,103,1,'2026-10-01 10:00:00')");
    $db->db->exec("INSERT INTO wp_routespro_form_record_values (version_id,question_key,question_label,value_type,value_text) VALUES (900,'notes','notes','textarea','STALE RECORD'),(901,'notes','notes','textarea','DRAFT RECORD')");
    Forms::$schema=$schema;
    $context=['client_id'=>12,'project_id'=>34,'location_id'=>55,'date'=>'2026-10-01'];
    $payload=FormPayloadService::resolved(88,$context,null,7,[],false);
    $questions=array_column($payload['form']['schema']['questions'],null,'key');
    $assert($questions['notes']['resolved']['previous_visit']['value']==='LATEST','actual_mobile_payload_uses_latest_submission_over_stale_record');
    $assert($questions['notes']['resolved']['value']===null,'mobile_normal_answers_are_memory_only');
    $assert($questions['checked']['resolved']['previous_visit']['available']===true,'false_checkbox_is_valid_visit_memory');
    foreach(['photo','categorized','file','order'] as $key){
        $assert($questions[$key]['resolved']['value']===null&&!$questions[$key]['resolved']['previous_visit']['available'],'mobile_excludes_previous_'.$key);
    }
    $assert((float)$questions['fronts']['resolved']['rows'][0]['before']===8.0,'mobile_fronts_rotate_latest_value');
    $noHistory=FormPayloadService::resolved(88,array_replace($context,['location_id'=>66]),null,7,[],false);
    $noHistoryQuestions=array_column($noHistory['form']['schema']['questions'],null,'key');
    $assert($noHistory['prefill']===[]&&!$noHistoryQuestions['notes']['resolved']['previous_visit']['available'],'mobile_no_operational_visit_does_not_use_draft_record');

    $db->submission(200,88,55,'2026-10-01','2026-10-01 14:30:00');
    $own=['notes'=>'OWN REPORT','photo'=>['own-photo'],'order'=>[['boxes'=>3,'notes'=>'own-order']],'fronts'=>[['category'=>'Gin','ref'=>'A','name'=>'Product A','before'=>1,'after'=>6,'qty'=>6]]];
    foreach($own as $key=>$value)$db->answer(200,$key,$value);
    $editPayload=FormPayloadService::resolved(88,$context,null,7,[],false);
    $editQuestions=array_column($editPayload['form']['schema']['questions'],null,'key');
    $assert($editPayload['daily_report']['submission_id']==200&&$editQuestions['notes']['resolved']['value']==='OWN REPORT','mobile_edit_restores_own_report');
    $assert($editQuestions['photo']['resolved']['value']===['own-photo']&&$editQuestions['order']['resolved']['value']===$own['order'],'current_report_media_orders_preserved_on_edit');
    $assert($editQuestions['file']['resolved']['value']===null&&!isset($editPayload['prefill']['file']),'missing_edit_field_does_not_inherit_old_file');
    $assert((float)$editQuestions['fronts']['resolved']['rows'][0]['before']===1.0&&(float)$editQuestions['fronts']['resolved']['rows'][0]['after']===6.0,'mobile_edit_preserves_own_fronts_pair');
    $assert(!$editQuestions['notes']['resolved']['previous_visit']['available'],'edit_does_not_label_own_report_as_previous_visit');
    $gateMethod=new \ReflectionMethod(RoutesPro\Support\ReleaseGate::class,'checkLatestVisitHistoryIsolation9BHContract');
    $gate=$gateMethod->invoke(null);
    $assert($gate['status']==='pass','installed_release_gate_history_isolation_contract');
    echo 'LATEST VISIT HISTORY ISOLATION 9BH RUNTIME OK checks='.$checks.PHP_EOL;
}
