<?php
define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v)); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return is_scalar($v)?trim((string)$v):''; } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
if (!function_exists('current_time')) { function current_time($type){ return $type==='mysql' ? '2026-09-22 12:00:00' : time(); } }
require_once dirname(__DIR__) . '/src/Services/MobilityCostService.php';
require_once dirname(__DIR__) . '/src/Reporting/DailyCommercialReportService.php';
require_once dirname(__DIR__) . '/src/Reporting/CanonicalOperationalReportService.php';

use RoutesPro\Services\MobilityCostService;
use RoutesPro\Reporting\CanonicalOperationalReportService;

$calculated = [
    'enabled'=>1,
    'costs_available'=>0,
    'distance_km'=>null,
    'known_distance_km'=>84.5,
    'known_toll_cost_eur'=>3.20,
    'energy_type'=>'diesel',
    'fuel_type'=>'diesel',
    'hybrid_mode'=>'none',
    'vehicle_label'=>'Viatura teste',
    'fuel_price_eur_per_l'=>1.70,
    'consumption_l_per_100km'=>6.0,
    'electricity_price_eur_per_kwh'=>0,
    'consumption_kwh_per_100km'=>0,
    'electric_share_pct'=>0,
    'operational_cost_eur_per_km'=>0,
];
$projection = MobilityCostService::reportingProjection($calculated);
$fail=[];
$ok=function($cond,$label)use(&$fail){ if(!$cond)$fail[]=$label; };
$ok(abs((float)$projection['reporting_distance_km']-84.5)<0.001,'known km');
$ok(!empty($projection['reporting_costs_available']),'report costs');
$ok(!empty($projection['reporting_recovered']),'recovered');
$ok(!empty($projection['reporting_partial']),'partial');
$ok((string)$projection['reporting_quality']==='known_distance_estimate','quality');
$ok(abs((float)$projection['reporting_energy_cost_eur']-8.62)<0.011,'energy');
$ok(abs((float)$projection['reporting_toll_cost_eur']-3.20)<0.001,'toll');
$ok(abs((float)$projection['reporting_total_mobility_cost_eur']-11.82)<0.011,'total');

$routes = [[
    'date'=>'2026-09-22','status'=>'completed','operational_closure_rate'=>100,
    'distance_km'=>$projection['reporting_distance_km'],
    'mobility_costs_available'=>$projection['reporting_costs_available'],
    'mobility_reporting_partial'=>$projection['reporting_partial'],
    'mobility_reporting_recovered'=>$projection['reporting_recovered'],
    'energy_cost_eur'=>$projection['reporting_energy_cost_eur'],
    'toll_cost_eur'=>$projection['reporting_toll_cost_eur'],
    'total_mobility_cost_eur'=>$projection['reporting_total_mobility_cost_eur'],
    'estimated_litres'=>$projection['reporting_estimated_litres'],
    'estimated_kwh'=>$projection['reporting_estimated_kwh'],
]];
$ref = new ReflectionClass(CanonicalOperationalReportService::class);
$m = $ref->getMethod('mobility');
$m->setAccessible(true);
$mobility = $m->invoke(null, ['routes'=>$routes,'stops'=>[]]);
$s=$mobility['summary'];
$ok(abs((float)$s['distance_km']-84.5)<0.001,'summary km');
$ok(abs((float)$s['energy_cost_eur']-8.62)<0.011,'summary energy');
$ok(abs((float)$s['toll_cost_eur']-3.20)<0.001,'summary toll');
$ok((int)$s['recovered_routes']===1,'summary recovered');
$ok(!empty($s['partial']),'summary partial');
if($fail){fwrite(STDERR,'9AL RUNTIME FAIL: '.implode(', ',$fail).PHP_EOL);exit(1);}
echo 'KNOWN MOBILITY REPORTING RECOVERY 9AL RUNTIME OK | km=84.5 | energy=8.62 | tolls=3.20 | recovered=1 | partial=1'.PHP_EOL;
