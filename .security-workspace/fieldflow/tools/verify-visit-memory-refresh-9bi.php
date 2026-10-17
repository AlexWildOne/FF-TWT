<?php
$root=dirname(__DIR__);
$requirements=[
    'src/Forms/Forms.php'=>['$previous_modal_revision','data-rp-previous-visit-revision'],
    'src/Forms/FormRenderer.php'=>['fieldflow-visit-memory-refresh-v1','rpClearPreviousVisitModal','memoryRevision','routesproPreviousVisitCleanup'],
    'src/Forms/DailySubmissionService.php'=>['Resolve ownership through submission_id instead.','WHERE submission_id=%d ORDER BY id DESC LIMIT 1'],
    'src/Shortcodes.php'=>['routesproClearPreviousVisitModal','embeddedFormVisitKey'],
    'src/Support/ReleaseGate.php'=>['VISIT_MEMORY_REFRESH_9BI_CONTRACT','checkVisitMemoryRefresh9BIContract'],
    'src/Support/ReleasePackageValidator.php'=>['visit_memory_refresh_9bi_ok'],
    'src/Admin/ReleaseManager.php'=>["packageResult['visit_memory_refresh_9bi_ok']"],
];
$missing=[];
foreach($requirements as $file=>$markers){$raw=(string)file_get_contents($root.'/'.$file);foreach($markers as $marker)if(strpos($raw,$marker)===false)$missing[]=$file.':'.$marker;}
if($missing){fwrite(STDERR,implode(PHP_EOL,$missing).PHP_EOL);exit(1);}
echo "VISIT MEMORY REFRESH 9BI CONTRACT OK\n";
