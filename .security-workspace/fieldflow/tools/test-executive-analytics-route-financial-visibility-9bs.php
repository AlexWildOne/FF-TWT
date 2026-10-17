<?php
define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return trim(strip_tags((string)$v)); } }
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v)); } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
if (!function_exists('wp_timezone')) { function wp_timezone(){ return new DateTimeZone('Europe/Lisbon'); } }
if (!function_exists('wp_date')) { function wp_date($format,$timestamp=null,$tz=null){ $dt=new DateTime('@'.($timestamp?:time())); $dt->setTimezone($tz?:new DateTimeZone('UTC')); return $dt->format($format); } }
require_once dirname(__DIR__).'/src/Analytics/MonthlyExecutiveAnalyticsService.php';
require_once dirname(__DIR__).'/src/Analytics/AnalyticsAIService.php';
use RoutesPro\Analytics\MonthlyExecutiveAnalyticsService;
use RoutesPro\Analytics\AnalyticsAIService;
$assert=function($ok,$label){if(!$ok){fwrite(STDERR,"FAIL {$label}\n");exit(1);}echo "PASS {$label}\n";};
$stats=[
 'from'=>'2026-09-01','to'=>'2026-09-30','total_routes'=>10,'completed_routes'=>9,'total_stops'=>100,'done_stops'=>94,'done_rate'=>94,
 'previous_summary'=>['total_routes'=>9,'completed_routes'=>8,'done_stops'=>88,'done_rate'=>88],
 'by_location'=>[
   ['location_id'=>1,'location_name'=>'Loja A','city'=>'Porto','stops'=>4,'stops_done'=>4,'done_rate'=>100],
   ['location_id'=>2,'location_name'=>'Loja B','city'=>'Gaia','stops'=>4,'stops_done'=>2,'done_rate'=>50],
 ],
 'team_performance'=>['leader'=>['name'=>'Operativo A','performance_index'=>92,'execution_rate'=>96]],
 'form_analytics'=>['metrics'=>[['title'=>'Disponibilidade','value'=>88,'unit'=>'%','note'=>'Disponível em loja']]],
 'canonical_operational_report'=>['kpis'=>[
   'routes_total'=>10,'routes_completed'=>9,'visits_planned'=>100,'visits_completed'=>94,'visits_justified'=>3,'visits_remaining'=>3,
   'execution_rate'=>94,'operational_closure_rate'=>97,'mobility_distance_km'=>1240,'mobility_total_cost_eur'=>310,'mobility_toll_cost_eur'=>42,
   'order_suggestions'=>12,'order_boxes'=>84,'fronts_gain'=>15,'fronts_growth_percent'=>7.5,'evidence_files'=>65,
 ]],
];
$out=MonthlyExecutiveAnalyticsService::build($stats,['from'=>'2026-09-01','to'=>'2026-09-30']);
$assert(($out['contract']??'')==='fieldflow-monthly-executive-analytics-v1','monthly_contract');
$assert(abs((float)($out['primary_kpis']['execution']['value']??0)-94.0)<0.01,'execution_kpi');
$assert(abs((float)($out['primary_kpis']['coverage']['value']??0)-100.0)<0.01,'pdv_coverage');
$assert(abs((float)($out['secondary_kpis']['mobility_total_cost_eur']??0)-310.0)<0.01,'mobility_cost');
$assert(abs((float)($out['secondary_kpis']['mobility_cost_per_visit_eur']??0)-3.30)<0.01,'cost_per_visit');
$assert((int)($out['secondary_kpis']['order_suggestions']??0)===12,'order_suggestions');
$assert(!empty($out['risk_locations']) && ($out['risk_locations'][0]['location_name']??'')==='Loja B','risk_locations');
$assert(abs((float)($out['trends']['execution']['delta']??0)-6.0)<0.01,'previous_period_delta');
$explainClient=AnalyticsAIService::explainability('client',$stats,['from'=>'2026-09-01','to'=>'2026-09-30']);
$explainMerch=AnalyticsAIService::explainability('merchandiser',$stats,[]);
$assert(($explainClient['contract']??'')==='fieldflow-explainable-analytics-v1' && !empty($explainClient['does_not_do']),'client_explainability');
$assert(strpos((string)($explainMerch['summary']??''),'tuas rotas')!==false,'merchandiser_explainability');
echo "EXECUTIVE ANALYTICS ROUTE FINANCIAL VISIBILITY 9BS RUNTIME OK monthly_contract execution_kpi pdv_coverage mobility_cost cost_per_visit order_suggestions risk_locations previous_period_delta client_explainability merchandiser_explainability\n";
