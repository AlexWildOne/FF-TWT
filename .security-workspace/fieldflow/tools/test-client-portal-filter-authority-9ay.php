<?php
$root = dirname(__DIR__);
$front=(string)@file_get_contents($root.'/src/Front/ClientPortalShortcodeRenderer.php');
$failed=[];
$assert=static function(bool $ok,string $name) use (&$failed): void { if(!$ok)$failed[]=$name; };
$start=strpos($front,'function setRouteDateLock(date, routeId)');
$end=$start===false?false:strpos($front,'function renderOwnerRoutesSummary',$start);
$block=($start!==false&&$end!==false)?substr($front,$start,$end-$start):'';
$assert($block!=='','filter_context_block');
$assert(strpos($block,'els.from.disabled=false')!==false && strpos($block,'els.to.disabled=false')!==false,'date_inputs_enabled');
$assert(strpos($block,'els.from.value=date')===false && strpos($block,'els.to.value=date')===false,'route_does_not_rewrite_dates');
$assert(strpos($front,'if(state.routeDateLocked) return;')===false,'date_change_not_blocked');
// 9D4: accept both guarded block syntax and one-line listener syntax.
$assert(strpos($front,"els.from.addEventListener('change'")!==false && strpos($front,"els.to.addEventListener('change'")!==false,'date_listeners_present');
$assert(strpos($front,"p.set('from',els.from.value)")!==false && strpos($front,"p.set('to',els.to.value)")!==false,'stats_date_scope');
$assert(strpos($front,'routesInSelectedRange')!==false && strpos($front,'routeAggregate')!==false,'route_totals_period_scope');
$assert(strpos($front,"metricCard('Top owner'")!==false,'stable_summary_kpi');
$assert(strpos($front,'renderTeamPerformance(s);')!==false,'analytics_performance_isolated');
if($failed){fwrite(STDERR,'FAILED '.implode(',',$failed).PHP_EOL);exit(1);}echo "CLIENT PORTAL FILTER AUTHORITY 9AY RUNTIME OK\n";
