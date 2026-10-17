<?php
namespace { require_once __DIR__.'/fixtures-visit-history-9bh.php'; }
namespace RoutesPro\Forms {
    final class BindingResolver { public static function get_context($context){return $context;} }
    final class SubmissionApplicationService { public static function buildSchema($form,$context){return Forms::decode_schema($form['schema_json']);} }
    // Capture the actual Web orchestration's renderer inputs. The field owners
    // themselves are exercised separately by the Mobile/fronts regression test.
    final class FormRenderer {
        public static array $lastPrefill=[];
        public static array $lastContext=[];
        public static function schema_needs_multipart($schema){return true;}
        public static function theme_style_attr($theme){return '';}
        public static function render_questions($schema,$prefill,$context){self::$lastPrefill=$prefill;self::$lastContext=$context;return '<div data-test-fields></div>';}
    }
}
namespace {
    require_once ROUTESPRO_PATH.'src/Services/SubmissionStatusPolicy.php';
    require_once ROUTESPRO_PATH.'src/Services/OperationalReportDayPolicy.php';
    require_once ROUTESPRO_PATH.'src/Forms/DailySubmissionService.php';
    require_once ROUTESPRO_PATH.'src/Forms/RecordService.php';
    require_once ROUTESPRO_PATH.'src/Forms/Forms.php';
    use RoutesPro\Forms\Forms;
    use RoutesPro\Forms\FormRenderer;
    $db=$GLOBALS['wpdb'];
    $schema=['questions'=>[
        ['key'=>'notes','type'=>'textarea','label'=>'notes'],['key'=>'photo','type'=>'image_upload'],
        ['key'=>'file','type'=>'file_upload'],['key'=>'order','type'=>'order_suggestion'],
        ['key'=>'fronts','type'=>'product_matrix'],
    ]];
    $db->db->exec('CREATE TABLE wp_routespro_forms (id INTEGER PRIMARY KEY, status TEXT, title TEXT, schema_json TEXT, theme_json TEXT, settings_json TEXT)');
    $stmt=$db->db->prepare('INSERT INTO wp_routespro_forms VALUES (88,\'active\',\'Test form\',?,\'{}\',\'{}\')');
    $stmt->execute([json_encode($schema)]);
    $db->submission(101,88,55,'2026-09-30','2026-09-30 11:00:00');
    $db->submission(102,87,55,'2026-10-01','2026-10-01 09:00:00');
    $db->answer(101,'notes','ONE VISIT BEHIND');
    $fronts=[['ref'=>'A','name'=>'Product A','before'=>2,'after'=>8,'qty'=>8]];
    foreach(['notes'=>'LATEST VISIT','photo'=>['old-photo'],'file'=>['old-file'],'order'=>[['boxes'=>99]],'fronts'=>$fronts] as $key=>$value)$db->answer(102,$key,$value);
    $db->db->exec("INSERT INTO wp_routespro_form_records VALUES (1,'88:12:34:55',900),(2,'88:12:34:66',901)");
    $db->db->exec("INSERT INTO wp_routespro_form_record_versions VALUES (900,101,1,'2026-09-30 11:00:00'),(901,0,1,'2026-10-01 10:00:00')");
    $db->db->exec("INSERT INTO wp_routespro_form_record_values (version_id,question_key,question_label,value_type,value_text) VALUES (900,'notes','notes','textarea','STALE RECORD'),(901,'notes','notes','textarea','DRAFT RECORD')");
    $checks=0;
    $assert=static function(bool $ok,string $name)use(&$checks){if(!$ok)throw new \RuntimeException('FAIL '.$name);$checks++;echo 'PASS '.$name.PHP_EOL;};
    $context=['client_id'=>12,'project_id'=>34,'location_id'=>55,'date'=>'2026-10-01'];
    $options=['return_url'=>'https://example.test','hide_actions'=>true];
    $html=Forms::render_form_with_context(88,$context,null,$options);
    $assert(str_contains($html,'LATEST VISIT')&&!str_contains($html,'ONE VISIT BEHIND')&&!str_contains($html,'STALE RECORD'),'web_memory_uses_latest_visit');
    $assert(!str_contains($html,'old-photo')&&!str_contains($html,'old-file'),'web_history_modal_has_no_media');
    $assert(FormRenderer::$lastPrefill===['fronts'=>$fronts],'web_new_visit_prefill_only_fronts_no_order_media');
    $assert(str_contains($html,'01/10/2026 09:00'),'web_memory_date_matches_selected_submission');
    preg_match('/data-rp-previous-visit-revision="([^"]+)"/', $html, $firstRevision);
    preg_match('/data-rp-previous-visit-key="([^"]+)"/', $html, $firstKey);
    $db->db->exec("UPDATE wp_routespro_form_submission_answers SET value_text='LATEST EDIT' WHERE submission_id=102 AND question_key='notes'");
    $updatedHtml=Forms::render_form_with_context(88,$context,null,$options);
    preg_match('/data-rp-previous-visit-revision="([^"]+)"/', $updatedHtml, $secondRevision);
    preg_match('/data-rp-previous-visit-key="([^"]+)"/', $updatedHtml, $secondKey);
    $assert(!empty($firstRevision[1])&&!empty($secondRevision[1])&&$firstRevision[1]!==$secondRevision[1],'web_memory_revision_changes_when_same_visit_answer_changes');
    $assert($firstKey[1]===$secondKey[1],'web_visit_key_stays_stable_for_repeated_render');
    $html=Forms::render_form_with_context(88,array_replace($context,['location_id'=>66]),null,$options);
    $assert(FormRenderer::$lastPrefill===[]&&!str_contains($html,'DRAFT RECORD')&&!str_contains($html,'data-rp-previous-visit-modal'),'web_no_visit_does_not_use_generic_record');
    $db->submission(200,88,55,'2026-10-01','2026-10-01 14:30:00');
    $own=['notes'=>'OWN REPORT','photo'=>['own-photo'],'order'=>[['boxes'=>3]],'fronts'=>[['ref'=>'A','name'=>'Product A','before'=>1,'after'=>6,'qty'=>6]]];
    foreach($own as $key=>$value)$db->answer(200,$key,$value);
    $html=Forms::render_form_with_context(88,$context,null,$options);
    $assert(FormRenderer::$lastPrefill===$own,'web_edit_restores_only_own_report_and_own_media_order');
    $assert(FormRenderer::$lastContext['_daily_existing']===true&&!isset(FormRenderer::$lastPrefill['file']),'web_edit_missing_fields_do_not_inherit_previous_files');
    $assert(!str_contains($html,'data-rp-previous-visit-modal'),'web_edit_has_no_previous_visit_popup');
    $db->db->exec('DELETE FROM wp_routespro_form_submission_answers WHERE submission_id=200');
    Forms::render_form_with_context(88,$context,null,$options);
    $assert(FormRenderer::$lastPrefill===[],'empty_existing_report_does_not_inherit_previous_fronts');
    echo 'WEB VISIT HISTORY ISOLATION 9BH RUNTIME OK checks='.$checks.PHP_EOL;
}
