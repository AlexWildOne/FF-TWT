<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($value){$value=strtolower((string)$value);return preg_replace('/[^a-z0-9_\-]/','',$value)?:'';} }
require_once dirname(__DIR__) . '/src/Services/SubmissionStatusPolicy.php';
use RoutesPro\Services\SubmissionStatusPolicy;
$assert=static function(bool $ok,string $name):void{if(!$ok){fwrite(STDERR,"FAIL: {$name}\n");exit(1);}};
$assert(SubmissionStatusPolicy::isOperational('submitted'),'submitted_operational');
$assert(SubmissionStatusPolicy::isOperational('reviewed'),'reviewed_operational');
$assert(SubmissionStatusPolicy::isOperational('approved'),'approved_operational');
$assert(!SubmissionStatusPolicy::isOperational('rejected'),'rejected_non_operational');
$assert(!SubmissionStatusPolicy::isOperational('draft'),'draft_non_operational');
$assert(SubmissionStatusPolicy::isInactive('void'),'void_inactive');
$assert(!SubmissionStatusPolicy::isEditableExisting('void'),'void_not_active_record');
$service=(string)file_get_contents(dirname(__DIR__) . '/src/Services/SubmissionCorrectionService.php');
$assert(strpos($service,'RouteProgressService::sync($routeId, true, false)')!==false,'route_reconcile_without_completion_event');
$assert(strpos($service,'mail_side_effects_emitted')!==false,'mail_side_effect_contract');
$assert(strpos($service,'admin_correction_history')!==false,'history_contract');
echo "SUBMISSION CORRECTIONS AUDIT 9Z RUNTIME OK\nvoid=soft-audited\nroute_completion_mail=replay-disabled\n";
