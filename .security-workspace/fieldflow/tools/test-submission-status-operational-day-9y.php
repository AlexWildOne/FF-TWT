<?php
define('ABSPATH', __DIR__);
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v)); } }
if (!function_exists('wp_timezone')) { function wp_timezone(){ return new DateTimeZone('Europe/Lisbon'); } }
if (!function_exists('wp_date')) { function wp_date($f,$ts,$tz=null){ $d=new DateTime('@'.$ts); if($tz)$d->setTimezone($tz); return $d->format($f); } }
require_once dirname(__DIR__) . '/src/Services/SubmissionStatusPolicy.php';
require_once dirname(__DIR__) . '/src/Services/OperationalReportDayPolicy.php';
use RoutesPro\Services\SubmissionStatusPolicy;
use RoutesPro\Services\OperationalReportDayPolicy;
$fail=[];
$assert=function($ok,$name)use(&$fail){ if(!$ok)$fail[]=$name; };
$assert(SubmissionStatusPolicy::isOperational('submitted'),'submitted_operational');
$assert(SubmissionStatusPolicy::isOperational('reviewed'),'reviewed_operational');
$assert(SubmissionStatusPolicy::isOperational('approved'),'approved_operational');
$assert(SubmissionStatusPolicy::isOperational(''),'legacy_empty_operational');
$assert(!SubmissionStatusPolicy::isOperational('draft'),'draft_not_operational');
$assert(!SubmissionStatusPolicy::isOperational('rejected'),'rejected_not_operational');
$assert(SubmissionStatusPolicy::isEditableExisting('draft'),'draft_editable');
$assert(SubmissionStatusPolicy::isEditableExisting('rejected'),'rejected_editable');
$assert(!SubmissionStatusPolicy::isEditableExisting('void'),'void_inactive');
$assert(OperationalReportDayPolicy::resolve('2026-09-20','2026-09-19','2026-09-21 01:15:00')==='2026-09-20','report_day_priority');
$assert(OperationalReportDayPolicy::resolve('','2026-09-19','2026-09-21 01:15:00')==='2026-09-19','route_day_fallback');
$assert(OperationalReportDayPolicy::resolve('','','2026-09-21 01:15:00')==='2026-09-21','submitted_fallback');
$assert(strpos(OperationalReportDayPolicy::sqlExpression('s','r'),'s.report_day')!==false && strpos(OperationalReportDayPolicy::sqlExpression('s','r'),'r.date')!==false && strpos(OperationalReportDayPolicy::sqlExpression('s','r'),'DATE(s.submitted_at)')!==false,'sql_priority');
if($fail){fwrite(STDERR,'FAIL: '.implode(',',$fail)."\n");exit(1);} echo "SUBMISSION STATUS OPERATIONAL DAY 9Y TEST OK\n";
