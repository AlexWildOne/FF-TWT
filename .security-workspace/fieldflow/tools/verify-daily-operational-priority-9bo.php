<?php
$root = dirname(__DIR__);
$fail = [];
$req = [
    'src/Services/DailyOperationalPriorityService.php'=>["CONTRACT = 'fieldflow-daily-operational-priority-v1'",'rankSignals','external_api_calls','route_mutations'],
    'src/Rest/RoutesController.php'=>["'/my-priorities'",'DailyOperationalPriorityService::forUser'],
    'src/Shortcodes.php'=>['rp-daily-focus','fieldflow-daily-operational-priority-v1','loadDailyFocus','daily-operational-priority'],
    'src/Admin/Assignments.php'=>["'operations' => 'Operação diária'",'DailyOperationalPriorityService::forUser','0 chamadas externas'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.585'","public const STABLE_RELEASE = '2.2.586'"],
    'PHASE-9BO-DAILY-OPERATIONAL-PRIORITY.md'=>['fieldflow-daily-operational-priority-v1','No database schema'],
];
foreach ($req as $rel=>$markers) {
    $raw = is_readable($root.'/'.$rel) ? (string)file_get_contents($root.'/'.$rel) : '';
    foreach ($markers as $marker) if (strpos($raw,$marker) === false) $fail[]=$rel.':'.$marker;
}
if ($fail) { fwrite(STDERR, "DAILY OPERATIONAL PRIORITY 9BO CONTRACT FAIL\n".implode("\n",$fail)."\n"); exit(1); }
echo "DAILY OPERATIONAL PRIORITY 9BO CONTRACT OK successor=>=2.2.586\n";
