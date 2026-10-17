<?php
$root=dirname(__DIR__);
$requirements=[
    'src/Forms/DailySubmissionService.php'=>['include_same_day','exclude_submission_id','visit_memory_prefill','new_visit_prefill'],
    'src/Forms/Forms.php'=>['DailySubmissionService::visit_memory_prefill','DailySubmissionService::new_visit_prefill','$history_source = $previous_submission;','$prefill = $daily_prefill;','$existing_prefill = $daily_prefill;'],
    'src/Mobile/FormPayloadService.php'=>['DailySubmissionService::visit_memory_prefill','DailySubmissionService::new_visit_prefill','$historySource = $previousSubmission;'],
    'src/Forms/RecordService.php'=>["'file_upload', 'order_suggestion'"],
    'src/Shortcodes.php'=>['embeddedFormSequence','requestId!==embeddedFormSequence','renderId!==formRenderSequence||visitKey(selectedVisit())!==nextVisitKey'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.578'","public const STABLE_RELEASE = '2.2.579'","// SOURCE_RELEASE = '2.2.577'","// STABLE_RELEASE = '2.2.578'"],
    'src/Support/ReleaseGate.php'=>['LATEST_VISIT_HISTORY_ISOLATION_9BH_CONTRACT','checkLatestVisitHistoryIsolation9BHContract'],
    'src/Support/ReleasePackageValidator.php'=>['latest_visit_history_isolation_9bh_ok','fieldflow-latest-visit-history-isolation-v1'],
    'src/Admin/ReleaseManager.php'=>["packageResult['latest_visit_history_isolation_9bh_ok']"],
    'PHASE-9BH-LATEST-VISIT-HISTORY-ISOLATION.md'=>['fieldflow-latest-visit-history-isolation-v1'],
    'src/Support/Migrations.php'=>["LATEST_SCHEMA_VERSION = '2.2.529'"],
    'src/Forms/SubmissionPipeline.php'=>["public const BUILD = '2.2.485'"],
    'tools/test-latest-visit-history-isolation-9bh.php'=>['LATEST VISIT HISTORY ISOLATION 9BH RUNTIME OK'],
    'tools/test-quick-report-selection-9bh.mjs'=>['QUICK REPORT SELECTION 9BH RUNTIME OK'],
];
$missing=[];
foreach($requirements as $file=>$markers){$raw=(string)file_get_contents($root.'/'.$file);foreach($markers as $marker)if(strpos($raw,$marker)===false)$missing[]=$file.':'.$marker;}
foreach(['src/Forms/Forms.php','src/Mobile/FormPayloadService.php'] as $file){
    $raw=(string)file_get_contents($root.'/'.$file);
    if(strpos($raw,': $record_prefill;')!==false||strpos($raw,': $recordPrefill;')!==false)$missing[]=$file.':generic_record_fallback';
}
if($missing){fwrite(STDERR,implode(PHP_EOL,$missing).PHP_EOL);exit(1);}
echo "LATEST VISIT HISTORY ISOLATION 9BH CONTRACT OK\n";
