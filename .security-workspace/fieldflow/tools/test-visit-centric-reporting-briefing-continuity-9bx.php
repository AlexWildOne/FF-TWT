<?php
$root=dirname(__DIR__);
$fail=[];
$sub=(string)file_get_contents($root.'/src/Admin/FormSubmissions.php');
$start=strpos($sub,'public static function get_visit_report_dataset');
$end=$start===false?false:strpos($sub,'private static function', $start);
if($end===false && $start!==false) $end=strpos($sub,'public static function', $start+20);
$method=($start!==false&&$end!==false)?substr($sub,$start,$end-$start):'';
$visitBase=$method!==''&&strpos($method,"FROM ' . \$px . 'routes r")!==false&&strpos($method,"INNER JOIN ' . \$px . 'route_stops rs")!==false;
$terminalPolicy=$method!==''&&strpos($method,"VisitCompletionPolicy::terminalSql('rs')")!==false;
$optionalSubmission=$method!==''&&strpos($method,"LEFT JOIN ' . \$submissions . ' s")!==false;
$submissionBaseLeak=$method!==''&&strpos($method,"FROM ' . FormsModule::table_submissions() . ' s")!==false;
$operativeScope=$method!==''&&strpos($method,'assignments ao')!==false&&strpos($method,'campaign_locations clo')!==false&&strpos($method,'s.user_id=%d')!==false;
$briefingScope=$method!==''&&strpos($method,'briefing_questions_for_submission')!==false&&strpos($method,'BindingResolver::resolve')!==false;
foreach([['visit_stop_base',$visitBase],['terminal_policy',$terminalPolicy],['optional_submission',$optionalSubmission],['no_submission_base_leak',!$submissionBaseLeak],['operative_visit_scope',$operativeScope],['briefing_visit_scope',$briefingScope]] as [$name,$ok])if(!$ok)$fail[]=$name;

$portal=(string)file_get_contents($root.'/src/Front/ClientPortalShortcodeRenderer.php');
$rest=(string)file_get_contents($root.'/src/Rest/EventsController.php');
$export=(string)file_get_contents($root.'/src/Admin/FormSubmissions.php');
if(strpos($portal,"p.set('report_mode','visits')")===false)$fail[]='portal_visit_mode';
if(strpos($rest,"\$reportMode === 'visits'")===false||strpos($rest,'get_visit_report_dataset')===false)$fail[]='rest_visit_mode';
if(strpos($export,"\$reportMode === 'visits' ? self::get_visit_report_dataset")===false)$fail[]='export_visit_mode';

if(!function_exists('sanitize_key')){function sanitize_key($key){$key=strtolower((string)$key);return preg_replace('/[^a-z0-9_\-]/','',$key);}}
if(!defined('ABSPATH')) define('ABSPATH', __DIR__);
require_once $root.'/src/Services/SubmissionStatusPolicy.php';
require_once $root.'/src/Services/VisitCompletionPolicy.php';
$fixtures=[
 ['status'=>'done','reason'=>'','expected'=>true],
 ['status'=>'completed','reason'=>'','expected'=>true],
 ['status'=>'failed','reason'=>'Loja em obras','expected'=>true],
 ['status'=>'failed','reason'=>'','expected'=>false],
 ['status'=>'pending','reason'=>'','expected'=>false],
];
$terminal=0;foreach($fixtures as $f){$actual=\RoutesPro\Services\VisitCompletionPolicy::stopIsTerminal($f['status'],$f['reason']);if($actual!==$f['expected'])$fail[]='fixture_'.$f['status'].'_'.($f['reason']!==''?'reason':'empty');if($actual)$terminal++;}
if($terminal!==3)$fail[]='terminal_cardinality';

$validator=(string)file_get_contents($root.'/src/Support/ReleasePackageValidator.php');
$gate=(string)file_get_contents($root.'/src/Support/ReleaseGate.php');
$parity=strpos($validator,'visit_centric_reporting_briefing_9bx_ok')!==false&&strpos($validator,'checkVisitCentricReportingBriefing9BXContract')!==false&&strpos($gate,'VISIT_CENTRIC_REPORTING_BRIEFING_9BX_CONTRACT')!==false&&strpos($gate,'visit_centric_reporting_briefing_9bx')!==false;
if(!$parity)$fail[]='gate_validator_parity';
if($fail){fwrite(STDERR,"VISIT-CENTRIC REPORTING & BRIEFING CONTINUITY 9BX RUNTIME FAIL | ".implode(',',$fail)."\n");exit(1);}
echo 'VISIT-CENTRIC REPORTING & BRIEFING CONTINUITY 9BX RUNTIME OK | visit_stop_base=1 | terminal_policy=1 | optional_submission=1 | terminal_cardinality=3/5 | operative_visit_scope=1 | briefing_visit_scope=1 | portal_rest_export_parity=1 | gate_validator_parity=1'.PHP_EOL;
