<?php
$root = dirname(__DIR__);
$front = (string)@file_get_contents($root . '/src/Front/ClientPortalShortcodeRenderer.php');
$phase = (string)@file_get_contents($root . '/src/Cloud/Phase6StableRelease.php');
$doc = (string)@file_get_contents($root . '/PHASE-9AX-CLIENT-PORTAL-RUNTIME-SCOPE-CONTINUITY.md');
$assert = static function($ok,$label){ if(!$ok){ fwrite(STDERR, "FAIL: {$label}\n"); exit(1);} };
$assert(strpos($front, "function renderKpis(stats){ const prev=stats?.previous_summary||{}; const rank=stats?.rankings||{}; const perf=(stats&&stats.team_performance&&typeof stats.team_performance==='object')?stats.team_performance:{}; const leader=(perf.leader&&typeof perf.leader==='object')?perf.leader:null;") !== false, 'renderKpis_local_perf_leader');
$assert(strpos($phase, "public const SOURCE_RELEASE = '2.2.567'") !== false && strpos($phase, "public const STABLE_RELEASE = '2.2.568'") !== false, 'phase6_successor');
$assert(strpos($doc, 'fieldflow-client-portal-runtime-scope-continuity-v1') !== false, 'contract_doc');
echo "CLIENT PORTAL RUNTIME SCOPE 9AX CONTRACT OK\n";
