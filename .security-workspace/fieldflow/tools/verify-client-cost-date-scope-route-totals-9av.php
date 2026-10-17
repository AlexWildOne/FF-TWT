<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string { $p=$root.'/'.$relative; return is_readable($p)?(string)file_get_contents($p):''; };
$failed=[];
$assert=static function(bool $ok,string $name) use (&$failed): void { if(!$ok)$failed[]=$name; };
$front=$read('src/Front/ClientPortalShortcodeRenderer.php');
$stats=$read('src/Rest/StatsController.php');
$phase6=$read('src/Cloud/Phase6StableRelease.php');
$gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php');
$doc=$read('PHASE-9AV-CLIENT-COST-DATE-SCOPE-ROUTE-TOTALS.md');
foreach(['selectedFilterRange','routeInRange','routeAggregate','aggregateMoney','rp-route-list-totals',"p.set('date_from',els.from.value)","p.set('date_to',els.to.value)"] as $m)$assert(strpos($front,$m)!==false,'front:'.$m);
$assert(strpos($stats,"get_param('date_from')")!==false && strpos($stats,"get_param('date_to')")!==false,'stats_aliases');
$assert(strpos($phase6,"SOURCE_RELEASE = '2.2.565'")!==false && strpos($phase6,"STABLE_RELEASE = '2.2.566'")!==false,'phase6_successor');
$assert(strpos($gate,"CLIENT_COST_DATE_SCOPE_ROUTE_TOTALS_9AV_CONTRACT = 'fieldflow-client-cost-date-scope-route-totals-v1'")!==false,'gate_contract');
$assert(strpos($gate,'checkClientCostDateScopeRouteTotals9AVContract')!==false,'gate_check');
$assert(strpos($validator,'client_cost_date_scope_route_totals_9av_ok')!==false,'validator_check');
$assert(strpos($doc,'fieldflow-client-cost-date-scope-route-totals-v1')!==false,'doc_contract');
if($failed){fwrite(STDERR,'FAILED '.implode(',',$failed).PHP_EOL);exit(1);}echo "CLIENT COST DATE SCOPE ROUTE TOTALS 9AV CONTRACT OK\n";
