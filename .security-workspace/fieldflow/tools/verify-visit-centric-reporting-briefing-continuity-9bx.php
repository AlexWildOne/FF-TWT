<?php
$root=dirname(__DIR__);
$fail=[];
$required=[
 'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1',"p.set('report_mode','visits')",'visitas fechadas'],
 'src/Rest/EventsController.php'=>["\$reportMode === 'visits'",'get_visit_report_dataset'],
 'src/Admin/FormSubmissions.php'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1','get_visit_report_dataset','VisitCompletionPolicy::terminalSql(\'rs\')','INNER JOIN \' . $px . \'route_stops rs','LEFT JOIN \' . $submissions . \' s','routespro_campaign_locations','briefing_questions_for_submission','BindingResolver::resolve','justified_non_visit'],
 'src/Forms/SubmissionPresentation.php'=>['Fecho operacional','Justificação','Fecho da visita','Relatório ID'],
 'src/Support/ReleasePackageValidator.php'=>['visit_centric_reporting_briefing_9bx_ok','fieldflow-visit-centric-reporting-briefing-continuity-v1'],
 'src/Support/ReleaseGate.php'=>['VISIT_CENTRIC_REPORTING_BRIEFING_9BX_CONTRACT','checkVisitCentricReportingBriefing9BXContract'],
 'PHASE-9BX-VISIT-CENTRIC-REPORTING-BRIEFING-CONTINUITY.md'=>['fieldflow-visit-centric-reporting-briefing-continuity-v1','No database schema changes'],
];
foreach($required as $file=>$markers){$path=$root.'/'.$file;$raw=is_readable($path)?(string)file_get_contents($path):'';if($raw===''){$fail[]=$file.':file';continue;}foreach($markers as $m)if(strpos($raw,$m)===false)$fail[]=$file.':'.$m;}
$main=(string)file_get_contents($root.'/fieldflow.php');preg_match("/FIELDFLOW_VERSION'\s*,\s*'([^']+)'/",$main,$m);$v=$m[1]??'0.0.0';if(version_compare($v,'2.2.595','<'))$fail[]='successor';
$phase=(string)file_get_contents($root.'/src/Cloud/Phase6StableRelease.php');foreach(["public const SOURCE_RELEASE = '2.2.594'","public const STABLE_RELEASE = '2.2.595'"] as $x)if(strpos($phase,$x)===false)$fail[]='phase:'.$x;
if($fail){fwrite(STDERR,"VISIT-CENTRIC REPORTING & BRIEFING CONTINUITY 9BX CONTRACT FAIL\n".implode("\n",$fail)."\n");exit(1);}echo "VISIT-CENTRIC REPORTING & BRIEFING CONTINUITY 9BX CONTRACT OK successor=>=2.2.595\n";
