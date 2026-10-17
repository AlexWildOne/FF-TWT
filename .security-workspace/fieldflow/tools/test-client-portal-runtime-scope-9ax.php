<?php
$root = dirname(__DIR__);
$front = (string)@file_get_contents($root . '/src/Front/ClientPortalShortcodeRenderer.php');
$assert = static function($ok,$label){ if(!$ok){ fwrite(STDERR, "FAIL: {$label}\n"); exit(1);} };
$start = strpos($front, 'function renderKpis(stats)');
$end = $start === false ? false : strpos($front, 'function renderCommercialKpis', $start);
$block = ($start !== false && $end !== false) ? substr($front,$start,$end-$start) : '';
$assert($block !== '', 'renderKpis_block');
$perfPos = strpos($block, 'const perf=');
$leaderPos = strpos($block, 'const leader=');
$firstUse = strpos($block, 'perf?.mode');
$assert($perfPos !== false && $leaderPos !== false && $firstUse !== false, 'bindings_present');
$assert($perfPos < $firstUse && $leaderPos < $firstUse, 'bindings_before_use');
$assert(strpos($front, 'renderKpis(state.stats);') !== false && strpos($front, 'renderRoutesTable(state.stats.by_day||[]);') !== false && strpos($front, 'renderAnalytics(state.stats);') !== false, 'portal_mount_chain');
echo "CLIENT PORTAL RUNTIME SCOPE 9AX RUNTIME OK\n";
