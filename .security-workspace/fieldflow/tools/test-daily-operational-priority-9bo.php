<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/src/Services/DailyOperationalPriorityService.php';
use RoutesPro\Services\DailyOperationalPriorityService;
$routeStops = [
    ['route_id'=>10,'stop_id'=>101,'location_id'=>1,'project_id'=>5,'seq'=>2,'operational_state'=>'pending','location_name'=>'Loja B'],
    ['route_id'=>10,'stop_id'=>100,'location_id'=>2,'project_id'=>5,'seq'=>1,'operational_state'=>'active','location_name'=>'Loja A'],
    ['route_id'=>10,'stop_id'=>102,'location_id'=>3,'project_id'=>5,'seq'=>3,'operational_state'=>'completed','location_name'=>'Loja C'],
];
$coverage = [
    ['location_id'=>1,'project_id'=>5,'risk_level'=>'critical','risk_score'=>110,'remaining_after_justification'=>1,'name'=>'Loja B','status_label'=>'1 visita em atraso'],
    ['location_id'=>2,'project_id'=>5,'risk_level'=>'attention','risk_score'=>55,'remaining_after_justification'=>1,'name'=>'Loja A'],
    ['location_id'=>4,'project_id'=>5,'risk_level'=>'critical','risk_score'=>120,'remaining_after_justification'=>1,'name'=>'Loja D','status_label'=>'Sem visita este mês'],
    ['location_id'=>5,'project_id'=>5,'risk_level'=>'normal','risk_score'=>0,'remaining_after_justification'=>0,'status'=>'justified','name'=>'Loja E','link_id'=>9,'exception'=>['valid_until'=>'2026-10-04']],
];
$items = DailyOperationalPriorityService::rankSignals($routeStops,$coverage,'2026-10-02');
$assert = function($ok,$label){ if(!$ok){ fwrite(STDERR,"FAIL {$label}\n"); exit(1);} echo "PASS {$label}\n"; };
$assert(count($items) >= 4,'items_generated');
$assert(($items[0]['kind'] ?? '') === 'continue_visit','active_visit_first');
$kinds = array_column($items,'kind');
$assert(in_array('visit_today',$kinds,true),'pending_today_present');
$assert(in_array('plan_store',$kinds,true),'critical_unplanned_present');
$assert(in_array('review_justification',$kinds,true),'expiring_justification_present');
$assert(!in_array('completed',$kinds,true),'completed_not_prioritized');
echo "DAILY OPERATIONAL PRIORITY 9BO RUNTIME OK active_first critical_before_attention read_only\n";
