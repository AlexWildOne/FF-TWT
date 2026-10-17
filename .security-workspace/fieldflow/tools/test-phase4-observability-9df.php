<?php
// PHASE 4 OBSERVABILITY 9DF PASS
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');
require_once dirname(__DIR__) . '/src/Performance/Phase4Baseline.php';
use RoutesPro\Performance\Phase4Baseline;
$assert = static function(bool $ok, string $reason): void {
    if (!$ok) { fwrite(STDERR, 'PHASE4 9DF FAIL: ' . $reason . "\n"); exit(1); }
};
$gate = ['status'=>'warning','samples'=>24,'window_hours'=>24,'categories'=>[
    'mobile'=>['samples'=>12,'p95_duration_ms'=>2530,'status'=>'warning'],
    'web'=>['samples'=>12,'p95_duration_ms'=>1200,'status'=>'pass']
]];
$api = ['table_ready'=>true,'requests'=>36,'estimated_cost_eur'=>8.25,'monthly_budget_eur'=>10,'month_cost_eur'=>9];
$providers = [
    ['provider'=>'google_routes','estimated_cost_eur'=>5.5],
    ['provider'=>'azure_maps','estimated_cost_eur'=>2.25],
    ['provider'=>'other','estimated_cost_eur'=>0.5]
];
$google = ['health'=>'warning','drift'=>['premium flag active'],'premium_requests'=>3,'blocked_events'=>8];
$obs = ['critical_active'=>1,'slowest'=>[['route'=>'/fieldflow/v1/reports','max_duration_ms'=>3800]]];
$out = Phase4Baseline::summarize($gate,$api,$providers,$google,$obs);
$assert($out['contract']===Phase4Baseline::CONTRACT, 'contract');
$assert($out['read_only']===true && $out['cost_window_days']===30,'read only window');
$assert($out['categories']['mobile']['p95_ms']===2530,'measured p95');
$assert($out['by_provider_eur']['google']===5.5 && $out['by_provider_eur']['azure']===2.25,'provider breakdown');
$assert($out['budget_warning']===true && $out['google_drift_count']===1,'risk signals');
$assert($out['critical_active']===1 && $out['google_blocked_events']===8,'observability counts');
$missing = Phase4Baseline::summarize(['status'=>'collecting'],['table_ready'=>false],[],[],[]);
$assert($missing['total_estimated_eur']===null && $missing['api_ready']===false,'missing metrics never look like zero cost');
$assert($missing['samples']===0 && $missing['categories']['mobile']['samples']===0,'empty metrics collecting');
$assert(!$missing['budget_warning'],'no fictitious overspend');
$source = (string)file_get_contents(dirname(__DIR__) . '/src/Performance/Phase4Baseline.php');
$assert(!preg_match('/\b(wp_remote_get|wp_remote_post|wp_insert_post|\$wpdb->(insert|update|delete|query))\s*\(/',$source), 'read-only service only');
$admin = (string)file_get_contents(dirname(__DIR__) . '/src/Admin/SecurityObservability.php');
$assert(str_contains($admin,'Phase4Baseline::collect($gate)'), 'integrated admin baseline');
$assert(str_contains($admin,'Custos são') || str_contains($admin,'Custos são <strong>estimativas'), 'clear estimates');
echo "PHASE 4 OBSERVABILITY 9DF PASS | real aggregates, unknown-data semantics, estimate clarity, no billable calls\n";
