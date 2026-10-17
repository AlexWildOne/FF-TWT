<?php
$root=dirname(__DIR__);
$fail=[];
$portal=(string)file_get_contents($root.'/src/Front/ClientPortalShortcodeRenderer.php');
$start=strpos($portal,'function paramsForReports()');
$end=$start===false?false:strpos($portal,'function buildAdminPostExportUrl',$start);
$params=($start!==false&&$end!==false)?substr($portal,$start,$end-$start):'';
$headerDateScope=$params!==''&&strpos($params,"p.set('date_from'")!==false&&strpos($params,"p.set('date_to'")!==false&&strpos($params,"p.set('owner_user_id'")!==false;
$selectedRouteLeak=$params!==''&&(strpos($params,'selectedRouteId')!==false||strpos($params,"p.set('route_id'")!==false);
if(!$headerDateScope)$fail[]='header_date_scope';
if($selectedRouteLeak)$fail[]='selected_route_leak';

$sub=(string)file_get_contents($root.'/src/Admin/FormSubmissions.php');
$storeBriefingScope=strpos($sub,'briefing_questions_for_submission')!==false&&strpos($sub,'ContextQuestionService::briefing_for_context')!==false&&strpos($sub,"'location_id' =>")!==false;
$operativeStoreAssignment=strpos($sub,'briefing_location_assigned_to_owner')!==false&&strpos($sub,'routespro_campaign_locations')!==false&&strpos($sub,'assigned_to=%d AND is_active=1')!==false;
if(!$storeBriefingScope)$fail[]='store_briefing_scope';
if(!$operativeStoreAssignment)$fail[]='operative_store_assignment';

$presentation=(string)file_get_contents($root.'/src/Forms/SubmissionPresentation.php');
$briefingExportParity=strpos($presentation,"'briefing' => ['name'=>'Briefing'")!==false&&strpos($presentation,'briefingRows')!==false&&strpos($presentation,'fieldflow-client-report-scope-briefing-assignment-v1')!==false;
if(!$briefingExportParity)$fail[]='briefing_export_parity';

$validator=(string)file_get_contents($root.'/src/Support/ReleasePackageValidator.php');
$gate=(string)file_get_contents($root.'/src/Support/ReleaseGate.php');
$gateValidatorParity=strpos($validator,'client_report_scope_briefing_assignment_9bw_ok')!==false&&strpos($validator,'checkClientReportScopeBriefingAssignment9BWContract')!==false&&strpos($gate,'CLIENT_REPORT_SCOPE_BRIEFING_ASSIGNMENT_9BW_CONTRACT')!==false&&strpos($gate,'client_report_scope_briefing_assignment_9bw_ok')!==false;
if(!$gateValidatorParity)$fail[]='gate_validator_parity';

if($fail){fwrite(STDERR,"CLIENT REPORT HEADER SCOPE & BRIEFING ASSIGNMENT 9BW RUNTIME FAIL | ".implode(',',$fail)."\n");exit(1);}
echo 'CLIENT REPORT HEADER SCOPE & BRIEFING ASSIGNMENT 9BW RUNTIME OK | header_date_scope=1 | selected_route_leak=0 | store_briefing_scope=1 | operative_store_assignment=1 | briefing_export_parity=1 | gate_validator_parity=1'.PHP_EOL;
