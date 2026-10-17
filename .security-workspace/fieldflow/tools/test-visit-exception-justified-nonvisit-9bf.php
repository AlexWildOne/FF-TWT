<?php
// Lightweight semantic checks for the exception date window. No WordPress bootstrap required.
define('ABSPATH', __DIR__ . '/');
function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v)); }
function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
function sanitize_textarea_field($v){ return trim(strip_tags((string)$v)); }
function absint($v){ return abs((int)$v); }
function current_time($type){ return $type === 'Y-m-d' ? '2026-09-29' : '2026-09-29 10:00:00'; }
function get_current_user_id(){ return 7; }
function wp_generate_uuid4(){ return '00000000-0000-4000-8000-000000000001'; }
function get_option($k,$d=[]){ return $d; }
function add_option(){ return true; }
function update_option(){ return true; }
require_once dirname(__DIR__) . '/src/Services/VisitExceptionService.php';

use RoutesPro\Services\VisitExceptionService;
$failed=[];
$assert=static function($ok,$name)use(&$failed){ if(!$ok)$failed[]=$name; };

$active=['status'=>'active','valid_from'=>'2026-09-01','valid_until'=>'2026-09-30'];
$expired=['status'=>'active','valid_from'=>'2026-08-01','valid_until'=>'2026-08-31'];
$clearedPast=['status'=>'cleared','valid_from'=>'2026-08-01','valid_until'=>'2026-08-31','cleared_at'=>'2026-09-05 09:00:00'];
$clearedFuture=['status'=>'cleared','valid_from'=>'2026-10-01','valid_until'=>'2026-10-31','cleared_at'=>'2026-09-29 09:00:00'];
$permanent=['status'=>'active','valid_from'=>'2026-09-10','valid_until'=>''];

$assert(VisitExceptionService::recordAppliesToWindow($active,'2026-09-01','2026-09-30','current'),'current_active');
$assert(!VisitExceptionService::recordAppliesToWindow($expired,'2026-09-01','2026-09-30','current'),'current_expired');
$assert(VisitExceptionService::recordAppliesToWindow($clearedPast,'2026-08-01','2026-08-31','past'),'past_history_retained');
$assert(!VisitExceptionService::recordAppliesToWindow($clearedFuture,'2026-10-01','2026-10-31','future'),'future_cleared_false');
$assert(VisitExceptionService::recordAppliesToWindow($permanent,'2026-10-01','2026-10-31','future'),'permanent_future_true');

if($failed){ fwrite(STDERR,'FAILED '.implode(',',$failed).PHP_EOL); exit(1); }
echo "VISIT EXCEPTION JUSTIFIED NONVISIT 9BF RUNTIME OK\n";
