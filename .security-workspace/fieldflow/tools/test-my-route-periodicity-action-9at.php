<?php
$root = dirname(__DIR__);
$service=(string)@file_get_contents($root.'/src/Services/PeriodicityProgressService.php');
$front=(string)@file_get_contents($root.'/src/Shortcodes.php');
$app=(string)@file_get_contents($root.'/src/Front/AppShortcodeRenderer.php');
$planner=(string)@file_get_contents($root.'/src/Front/SelfRoutePlannerShortcodeRenderer.php');
$assert=function(bool $ok,string $msg){if(!$ok){fwrite(STDERR,"FAIL: {$msg}\n");exit(1);}};
$assert(strpos($service,'businessDaysRemaining')!==false,'business-day pressure missing');
$assert(strpos($service,'riskProfile')!==false,'risk profile missing');
$assert(strpos($service,"'critical'")!==false && strpos($service,"'attention'")!==false && strpos($service,"'normal'")!==false,'risk levels missing');
$assert(strpos($front,'Só falta cumprir')!==false,'compact pending filter missing');
$assert(strpos($front,"sessionStorage.setItem(periodicityFilterStorageKey")!==false,'filter persistence missing');
$assert(strpos($front,'data-periodicity-plan')!==false && strpos($front,'fieldflow:open-planner')!==false,'planner action missing');
$assert(strpos($app,"openPanel('discovery')")!==false && strpos($app,'fieldflow:planner-focus-store')!==false,'app planner bridge missing');
$assert(strpos($planner,'applyPlannerIntent')!==false && strpos($planner,'selected.add(intent.location_id)')!==false,'exact-store preselection missing');
$assert(strpos($front,'fieldflow:self-routes-created')!==false,'coverage refresh after planning missing');
echo "MY ROUTE PERIODICITY ACTION 9AT RUNTIME OK\n";
