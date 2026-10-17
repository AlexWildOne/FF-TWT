<?php
$root=dirname(__DIR__);
$fail=[];
$required=[
 'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-client-report-scope-briefing-assignment-v1','function paramsForReports()','A route opened in Rotas must never leak into Relatórios'],
 'src/Admin/FormSubmissions.php'=>['fieldflow-client-report-scope-briefing-assignment-v1','briefing_questions_for_submission','ContextQuestionService::briefing_for_context','briefing_location_assigned_to_owner','assigned_to=%d AND is_active=1'],
 'src/Forms/SubmissionPresentation.php'=>['fieldflow-client-report-scope-briefing-assignment-v1',"'briefing' => ['name'=>'Briefing'"],
 'src/Support/ReleasePackageValidator.php'=>['client_report_scope_briefing_assignment_9bw_ok','fieldflow-client-report-scope-briefing-assignment-v1'],
 'src/Support/ReleaseGate.php'=>['CLIENT_REPORT_SCOPE_BRIEFING_ASSIGNMENT_9BW_CONTRACT','checkClientReportScopeBriefingAssignment9BWContract'],
 'PHASE-9BW-CLIENT-REPORT-HEADER-SCOPE-BRIEFING-ASSIGNMENT.md'=>['fieldflow-client-report-scope-briefing-assignment-v1','No database schema'],
];
foreach($required as $file=>$markers){$path=$root.'/'.$file;$raw=is_readable($path)?(string)file_get_contents($path):'';if($raw===''){$fail[]=$file.':file';continue;}foreach($markers as $m)if(strpos($raw,$m)===false)$fail[]=$file.':'.$m;}
$main=(string)file_get_contents($root.'/fieldflow.php');preg_match("/FIELDFLOW_VERSION'\s*,\s*'([^']+)'/",$main,$m);$v=$m[1]??'0.0.0';if(version_compare($v,'2.2.594','<'))$fail[]='successor';
$phase=(string)file_get_contents($root.'/src/Cloud/Phase6StableRelease.php');foreach(["public const SOURCE_RELEASE = '2.2.593'","public const STABLE_RELEASE = '2.2.594'"] as $x)if(strpos($phase,$x)===false)$fail[]='phase:'.$x;
if($fail){fwrite(STDERR,"CLIENT REPORT HEADER SCOPE & BRIEFING ASSIGNMENT 9BW CONTRACT FAIL\n".implode("\n",$fail)."\n");exit(1);}echo "CLIENT REPORT HEADER SCOPE & BRIEFING ASSIGNMENT 9BW CONTRACT OK successor=>=2.2.594\n";
