<?php
define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v)); } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
if (!function_exists('current_time')) { function current_time($type){ return $type==='mysql' ? '2026-09-22 12:00:00' : time(); } }
require_once dirname(__DIR__) . '/src/Reporting/DailyCommercialReportService.php';
require_once dirname(__DIR__) . '/src/Reporting/CanonicalOperationalReportService.php';

use RoutesPro\Reporting\CanonicalOperationalReportService;
use RoutesPro\Reporting\DailyCommercialReportService;

$routes = [
    ['date'=>'2026-09-21','status'=>'completed','operational_closure_rate'=>100,'distance_km'=>40,'mobility_costs_available'=>1,'energy_cost_eur'=>5,'toll_cost_eur'=>2,'total_mobility_cost_eur'=>7,'estimated_litres'=>3.1,'estimated_kwh'=>0],
    ['date'=>'2026-09-21','status'=>'completed','operational_closure_rate'=>100,'distance_km'=>20,'mobility_costs_available'=>1,'energy_cost_eur'=>3,'toll_cost_eur'=>1,'total_mobility_cost_eur'=>4,'estimated_litres'=>1.8,'estimated_kwh'=>0],
    ['date'=>'2026-09-22','status'=>'done','operational_closure_rate'=>100,'distance_km'=>30,'mobility_costs_available'=>1,'energy_cost_eur'=>4,'toll_cost_eur'=>0.5,'total_mobility_cost_eur'=>4.5,'estimated_litres'=>2.2,'estimated_kwh'=>0],
    ['date'=>'2026-09-22','status'=>'in_progress','operational_closure_rate'=>50,'distance_km'=>10,'mobility_costs_available'=>1,'energy_cost_eur'=>1,'toll_cost_eur'=>0.2,'total_mobility_cost_eur'=>1.2,'estimated_litres'=>0.6,'estimated_kwh'=>0],
    ['date'=>'2026-09-22','status'=>'completed','operational_closure_rate'=>100,'distance_km'=>null,'mobility_costs_available'=>0,'energy_cost_eur'=>null,'toll_cost_eur'=>null,'total_mobility_cost_eur'=>null,'estimated_litres'=>null,'estimated_kwh'=>null],
];
$ref = new ReflectionClass(CanonicalOperationalReportService::class);
$m = $ref->getMethod('mobility');
$m->setAccessible(true);
$mobility = $m->invoke(null, ['routes'=>$routes,'stops'=>[]]);
$s = $mobility['summary'];
$d = $mobility['by_day'];
$fail=[];
$ok=function($cond,$label)use(&$fail){ if(!$cond)$fail[]=$label; };
$ok(count($d)===2,'days');
$ok(abs((float)$d[0]['distance_km']-60.0)<0.001,'day1 km');
$ok(abs((float)$d[0]['energy_cost_eur']-8.0)<0.001,'day1 energy');
$ok(abs((float)$d[0]['toll_cost_eur']-3.0)<0.001,'day1 toll');
$ok(abs((float)$d[1]['distance_km']-30.0)<0.001,'day2 km');
$ok(!empty($d[1]['partial']),'day2 partial');
$ok(abs((float)$s['distance_km']-90.0)<0.001,'total km');
$ok(abs((float)$s['energy_cost_eur']-12.0)<0.001,'total energy');
$ok(abs((float)$s['toll_cost_eur']-3.5)<0.001,'total toll');
$ok(abs((float)$s['total_mobility_cost_eur']-15.5)<0.001,'total mobility');
$ok((int)$s['closed_routes']===4,'closed routes');
$ok((int)$s['costed_routes']===3,'costed routes');
$ok((int)$s['incomplete_routes']===1,'incomplete routes');
$ok((int)$s['open_routes_excluded']===1,'open excluded');
$ok(!empty($s['partial']),'period partial');

$report=[
  'kpis'=>['visits_planned'=>3,'visits_completed'=>3,'visits_justified'=>0,'visits_remaining'=>0,'execution_rate'=>100,'operational_closure_rate'=>100,'fronts_gain'=>0,'fronts_before'=>0,'fronts_after'=>0,'order_suggestions'=>0,'order_lines'=>0,'order_boxes'=>0,'extra_spaces'=>0,'evidence_files'=>0,'briefing_questions'=>0,'briefing_answered'=>0,'briefing_target_assignments'=>0,'briefing_answered_assignments'=>0,'briefing_pending_assignments'=>0,'briefing_target_stores'=>0,'briefing_coverage_percent'=>0],
  'routing'=>['routes'=>$routes,'stops'=>[]],
  'mobility'=>$mobility,
  'fronts'=>['rows'=>[]], 'extra_spaces'=>['rows'=>[]], 'orders'=>['rows'=>[]], 'briefing'=>['rows'=>[]], 'evidence'=>['rows'=>[]],
];
$analysis=DailyCommercialReportService::build($report);
$ok(!empty($analysis['capabilities']['mobility']),'mobility capability');
$ok(abs((float)$analysis['facts']['mobility']['distance_km']-90.0)<0.001,'analysis total km');
$clientText=implode(' ',(array)($analysis['client']['paragraphs']??[]));
$merchText=implode(' ',(array)($analysis['merchandiser']['paragraphs']??[]));
$ok(strpos($clientText,'90,0 km')!==false && strpos($clientText,'12,00 €')!==false && strpos($clientText,'3,50 €')!==false,'client narrative mobility');
$ok(strpos($merchText,'90,0 km')!==false && strpos($merchText,'12,00 €')!==false && strpos($merchText,'3,50 €')!==false,'merch narrative mobility');
if($fail){fwrite(STDERR,'9AK RUNTIME FAIL: '.implode(', ',$fail).PHP_EOL);exit(1);}
echo 'DAILY MOBILITY COST REPORTING 9AK RUNTIME OK | days=2 | km=90.0 | energy=12.00 | tolls=3.50 | total=15.50 | partial=1 | open_excluded=1'.PHP_EOL;
