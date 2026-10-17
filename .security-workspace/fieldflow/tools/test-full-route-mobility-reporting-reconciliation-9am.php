<?php
define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v)); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return is_scalar($v)?trim((string)$v):''; } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v,$flags=0){ return json_encode($v,$flags); } }
require_once dirname(__DIR__) . '/src/Support/TollEstimator.php';
require_once dirname(__DIR__) . '/src/Services/MobilityCostService.php';
require_once dirname(__DIR__) . '/src/Reporting/DailyCommercialReportService.php';
require_once dirname(__DIR__) . '/src/Reporting/CanonicalOperationalReportService.php';

use RoutesPro\Services\MobilityCostService;
use RoutesPro\Reporting\CanonicalOperationalReportService;

$fail=[];
$ok=function($cond,$label)use(&$fail){ if(!$cond)$fail[]=$label; };
$profile=[
    'enabled'=>1,'energy_type'=>'diesel','fuel_type'=>'diesel','hybrid_mode'=>'standard','vehicle_label'=>'Teste',
    'fuel_price_eur_per_l'=>1.70,'consumption_l_per_100km'=>6.0,'electricity_price_eur_per_kwh'=>0,
    'consumption_kwh_per_100km'=>0,'electric_share_pct'=>0,'operational_cost_eur_per_km'=>0,
];

// Historical/stale total contains only PDV-to-PDV kilometres.
$projection=MobilityCostService::reportingProjection(array_merge($profile,[
    'costs_available'=>1,'distance_km'=>40.0,'known_distance_km'=>40.0,'energy_cost_eur'=>4.08,
    'toll_cost_eur'=>2.0,'total_mobility_cost_eur'=>6.08,'known_toll_cost_eur'=>2.0,'toll_is_real_api'=>0,
    'access_distance_km'=>10.0,'inter_stop_distance_km'=>40.0,'return_distance_km'=>12.0,
]));
$ok(abs((float)$projection['reporting_distance_km']-62.0)<0.001,'full route sum');
$ok(abs((float)$projection['reporting_access_distance_km']-10.0)<0.001,'access');
$ok(abs((float)$projection['reporting_inter_stop_distance_km']-40.0)<0.001,'inter stops');
$ok(abs((float)$projection['reporting_return_distance_km']-12.0)<0.001,'return');
$ok(!empty($projection['reporting_full_route_reconciled']),'reconciled');
$ok((string)$projection['reporting_quality']==='full_route_component_reconciled','quality');
$ok(abs((float)$projection['reporting_energy_cost_eur']-6.32)<0.011,'energy on full route');
$ok((float)$projection['reporting_toll_cost_eur']>2.0,'toll recalculated on full route');

// Stored legs can recover a full route when meta component totals are absent.
$stored=MobilityCostService::reportingProjection(array_merge($profile,[
    'costs_available'=>0,'distance_km'=>null,'known_distance_km'=>45.0,'known_toll_cost_eur'=>1.5,'toll_is_real_api'=>0,
    'access_distance_km'=>null,'inter_stop_distance_km'=>45.0,'return_distance_km'=>null,
    'stored_access_distance_km'=>8.0,'stored_inter_stop_distance_km'=>45.0,'stored_return_distance_km'=>12.0,
    'stored_route_distance_km'=>65.0,'stored_route_toll_cost_eur'=>3.10,'stored_route_legs_complete'=>1,
]));
$ok(abs((float)$stored['reporting_distance_km']-65.0)<0.001,'stored full route');
$ok((string)$stored['reporting_distance_basis']==='stored_route_legs','stored basis');
$ok(abs((float)$stored['reporting_toll_cost_eur']-3.10)<0.001,'stored full toll');
$ok(abs((float)$stored['reporting_energy_cost_eur']-6.63)<0.011,'stored energy');

// A correct canonical total remains untouched.
$canonical=MobilityCostService::reportingProjection(array_merge($profile,[
    'costs_available'=>1,'distance_km'=>62.0,'known_distance_km'=>62.0,'energy_cost_eur'=>6.32,
    'toll_cost_eur'=>2.70,'total_mobility_cost_eur'=>9.02,'known_toll_cost_eur'=>2.70,'toll_is_real_api'=>0,
    'access_distance_km'=>10.0,'inter_stop_distance_km'=>40.0,'return_distance_km'=>12.0,
]));
$ok(empty($canonical['reporting_full_route_reconciled']),'canonical untouched');
$ok((string)$canonical['reporting_quality']==='canonical_complete','canonical quality');

$routes=[[
    'date'=>'2026-09-22','status'=>'completed','operational_closure_rate'=>100,
    'distance_km'=>$projection['reporting_distance_km'],
    'access_distance_km'=>$projection['reporting_access_distance_km'],
    'inter_stop_distance_km'=>$projection['reporting_inter_stop_distance_km'],
    'return_distance_km'=>$projection['reporting_return_distance_km'],
    'mobility_costs_available'=>1,'mobility_reporting_partial'=>1,'mobility_reporting_recovered'=>1,
    'energy_cost_eur'=>$projection['reporting_energy_cost_eur'],'toll_cost_eur'=>$projection['reporting_toll_cost_eur'],
    'total_mobility_cost_eur'=>$projection['reporting_total_mobility_cost_eur'],'estimated_litres'=>$projection['reporting_estimated_litres'],'estimated_kwh'=>0,
]];
$ref=new ReflectionClass(CanonicalOperationalReportService::class);
$m=$ref->getMethod('mobility');$m->setAccessible(true);
$roll=$m->invoke(null,['routes'=>$routes,'stops'=>[]]);
$s=$roll['summary'];
$ok(abs((float)$s['distance_km']-62.0)<0.001,'rollup total');
$ok(abs((float)$s['access_distance_km']-10.0)<0.001,'rollup access');
$ok(abs((float)$s['inter_stop_distance_km']-40.0)<0.001,'rollup pdvs');
$ok(abs((float)$s['return_distance_km']-12.0)<0.001,'rollup return');

if($fail){fwrite(STDERR,'9AM RUNTIME FAIL: '.implode(', ',$fail).PHP_EOL);exit(1);}
echo 'FULL ROUTE MOBILITY REPORTING RECONCILIATION 9AM RUNTIME OK | access=10 | pdvs=40 | return=12 | total=62 | fuel_full_route=1'.PHP_EOL;
