<?php
// PHASE 4 ADMIN HOTSPOTS MANIFEST 9DH PASS
if (!defined('ABSPATH')) define('ABSPATH','/tmp/');
require_once dirname(__DIR__) . '/src/Performance/Phase4AdminHotspots.php';
use RoutesPro\Performance\Phase4AdminHotspots;
$assert=static function(bool $yes,string $label): void {if(!$yes){fwrite(STDERR,'9DH FAIL: '.$label."\n"); exit(1);}};
$rows=[];
for($i=0;$i<20;$i++)$rows[]=['operation_key'=>'ADMIN routespro-license-tenants','duration_ms'=>6000+$i,'query_count'=>7400];
for($i=0;$i<18;$i++)$rows[]=['operation_key'=>'ADMIN routespro-security-observability','duration_ms'=>250,'query_count'=>24];
$rows[]=['operation_key'=>'ADMIN rare-case','duration_ms'=>19000,'query_count'=>100];
$report=Phase4AdminHotspots::analyze($rows,24,true);
$assert($report['contract']===Phase4AdminHotspots::CONTRACT && $report['read_only'],'9DH read-only');
$assert($report['sample_count']===39 && $report['operation_count']===3,'measured grouping');
$assert($report['rows'][0]['status']==='critical' && $report['rows'][0]['p95_queries']===7400,'p95 hotspots real');
$assert($report['rows'][2]['status']==='collecting','rare outlier not a false conclusion');
$assert(Phase4AdminHotspots::analyze([],24,false)['sample_count']===0,'no invented data');
$src=(string)file_get_contents(dirname(__DIR__).'/src/Performance/Phase4AdminHotspots.php');
$assert(strpos($src,'LIMIT')!==false && strpos($src,'SHOW TABLES LIKE')!==false,'bounded indexed read');
$assert(!preg_match('/\b(wp_remote_get|wp_remote_post|wp_insert_post|update_option|set_transient|\$wpdb->(insert|update|delete|query))\s*\(/',$src),'no writes or remote HTTP');
$admin=(string)file_get_contents(dirname(__DIR__).'/src/Admin/SecurityObservability.php');
$assert(strpos($admin,'Phase4AdminHotspots::collect(')!==false,'wired only to performance tab');
$assert(strpos($admin,'overviewSnapshotsRequired($tab)')!==false,'overview-only snapshot optimization');
echo 'PHASE 4 ADMIN HOTSPOTS MANIFEST 9DH PASS | measured P95, bounded, no paid calls, lazy overview\n';
