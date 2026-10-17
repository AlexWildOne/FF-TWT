<?php
namespace { require_once __DIR__.'/fixtures-visit-history-9bh.php'; }
namespace RoutesPro\Forms {
    final class Forms {
        public static function table_submissions(){return 'wp_routespro_form_submissions';}
        public static function table_answers(){return 'wp_routespro_form_submission_answers';}
    }
}
namespace {
    require_once ROUTESPRO_PATH.'src/Services/SubmissionStatusPolicy.php';
    require_once ROUTESPRO_PATH.'src/Forms/RecordService.php';
    require_once ROUTESPRO_PATH.'src/Forms/DailySubmissionService.php';
    $db=$GLOBALS['wpdb'];
    $db->submission(101,88,55,'2026-09-30','2026-09-30 11:00:00');
    $db->submission(102,88,55,'2026-10-01','2026-10-01 09:00:00');
    $db->submission(103,88,55,'2026-10-01','2026-10-01 10:00:00');
    $db->db->exec('INSERT INTO wp_routespro_form_record_versions VALUES (900,101,1,"2026-09-30 11:00:00"),(901,102,1,"2026-10-01 09:00:00"),(902,102,2,"2026-10-01 10:00:00")');
    $db->db->exec('INSERT INTO wp_routespro_form_record_values (version_id,question_key,question_label,value_type,value_text) VALUES (900,"notes","Notes","textarea","ONE VISIT BEHIND"),(901,"notes","Notes","textarea","OLD EDIT"),(902,"notes","Notes","textarea","LATEST OWN VERSION")');
    $db->db->exec('UPDATE wp_routespro_form_submissions SET record_version_id=900 WHERE id IN (102,103)');
    $schema=['questions'=>[['key'=>'notes','label'=>'Notes','type'=>'textarea']]];
    $assert=static function($ok,$name){if(!$ok)throw new \RuntimeException('FAIL '.$name);echo 'PASS '.$name.PHP_EOL;};
    $value=\RoutesPro\Forms\DailySubmissionService::submission_prefill(102,$schema);
    $assert(($value['notes']??null)==='LATEST OWN VERSION','stale_pointer_uses_latest_version_of_selected_submission');
    $value=\RoutesPro\Forms\DailySubmissionService::submission_prefill(103,$schema);
    $assert($value===[],'foreign_version_cannot_fill_missing_current_visit_answers');
    $db->answer(102,'notes','DURABLE CURRENT ANSWER');
    $value=\RoutesPro\Forms\DailySubmissionService::submission_prefill(102,$schema);
    $assert(($value['notes']??null)==='DURABLE CURRENT ANSWER','durable_answer_keeps_authority_over_projection');
    require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
    $gate=(new \ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkVisitMemoryRefresh9BIContract'))->invoke(null);
    $assert($gate['status']==='pass','installed_certification_gate_accepts_memory_refresh_contract');
    echo 'VISIT MEMORY VERSION OWNERSHIP 9BI RUNTIME OK checks=4'.PHP_EOL;
}
