<?php
// PHASE 4 COST PERFORMANCE DRIVERS 9DG PASS
if (!defined('ABSPATH')) define('ABSPATH','/tmp/');
require_once dirname(__DIR__) . '/src/Performance/Phase4Drivers.php';
use RoutesPro\Performance\Phase4Drivers;
$assert=static function(bool $condition,string $message):void{
    if (!$condition){fwrite(STDERR,'9DG FAIL: '.$message."\n");exit(1);}
};
$ops=[
 ['provider'=>'google_maps','operation'=>'geocode','requests'=>150,'units'=>150,'reference_rate_eur_per_1000'=>0,'estimated_cost_eur'=>0,'cache_hits'=>0],
 ['provider'=>'google_routes','operation'=>'compute_routes_enterprise','requests'=>4,'units'=>4,'reference_rate_eur_per_1000'=>8,'estimated_cost_eur'=>0.032],
 ['provider'=>'google_maps','operation'=>'places','requests'=>34,'units'=>34,'reference_rate_eur_per_1000'=>7,'estimated_cost_eur'=>0.238,'cache_hits'=>0],
 ['provider'=>'azure_maps','operation'=>'directions','requests'=>10,'units'=>10,'reference_rate_eur_per_1000'=>4,'estimated_cost_eur'=>0.04,'errors'=>5],
];
$gate=['categories'=>['mobile'=>['status'=>'warning','samples'=>21,'p95_duration_ms'=>2400],
 'rest'=>['status'=>'warning','samples'=>2,'p95_duration_ms'=>5000]]];
$daily=[];
for($i=0;$i<14;$i++)$daily[]=['usage_day'=>gmdate('Y-m-d',strtotime('2026-10-12 00:00:00 UTC')-$i*86400),'estimated_cost_eur'=>1,'requests'=>2];
$base=['api_ready'=>true];
$out=Phase4Drivers::analyze($gate,$base,$ops,$daily,'2026-10-12');
$assert($out['contract']===Phase4Drivers::CONTRACT && $out['read_only'],'contract readonly');
$assert($out['unpriced_operations']===1 && $out['unpriced_requests']===150,'unpriced Google SKU not free');
$assert($out['recommendations'][0]['priority']==='unknown_cost' && $out['recommendations'][0]['cost_unknown'],'tariff gap first');
$assert($out['recommendations'][1]['priority']==='premium','premium caution');
$assert(count($out['slow_flows'])===1 && $out['slow_flows'][0]['category']==='mobile','only measured slow flows');
$assert($out['trend_comparable'] && $out['last_7_days']['requests']===14,'indicative trend');
$missing=Phase4Drivers::analyze([],['api_ready'=>false],$ops,$daily,'2026-10-12');
$assert($missing['estimated_known_eur']===null && !$missing['trend_comparable'] && !$missing['recommendations'],'missing ledger not zero');
$noTrend=Phase4Drivers::analyze([],['api_ready'=>true],$ops,[], '2026-10-12');
$assert(!$noTrend['trend_comparable'] && $noTrend['trend_change_pct']===null,'no fictitious trend');
$src=(string)file_get_contents(dirname(__DIR__) . '/src/Performance/Phase4Drivers.php');
$assert(!preg_match('/\b(wp_remote_get|wp_remote_post|wp_insert_post|update_option|set_transient|\$wpdb->(insert|update|delete|query))\s*\(/',$src),'read-only service');
$admin=(string)file_get_contents(dirname(__DIR__) . '/src/Admin/SecurityObservability.php');
$assert(str_contains($admin,'Phase4Drivers::collect($gate, $phase4)'),'connected to admin');
echo "PHASE 4 COST PERFORMANCE DRIVERS 9DG PASS | tariff gaps, priorities, P95, trends, no external calls\n";
