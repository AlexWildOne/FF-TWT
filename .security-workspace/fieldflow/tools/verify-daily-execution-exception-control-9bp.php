<?php
$root = dirname(__DIR__);
$fail = [];
$req = [
    'src/Services/DailyExecutionExceptionService.php'=>["CONTRACT = 'fieldflow-daily-execution-exception-control-v1'",'classifyRecord','external_api_calls','submission_mutations'],
    'src/Rest/RoutesController.php'=>["'/my-execution'",'DailyExecutionExceptionService::forUser'],
    'src/Shortcodes.php'=>['data-execution-contract="fieldflow-daily-execution-exception-control-v1"','rp-focus-toggle','focusOpenStorageKey','loadDailyExecution','alertas de execução'],
    'src/Admin/Assignments.php'=>['DailyExecutionExceptionService::forUser','Controlo de execução e exceções','Alertas execução'],
    'src/Support/ReleaseGate.php'=>['DAILY_EXECUTION_EXCEPTION_9BP_CONTRACT','checkDailyExecutionException9BPContract'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.586'","public const STABLE_RELEASE = '2.2.587'"],
    'PHASE-9BP-DAILY-EXECUTION-EXCEPTION-CONTROL.md'=>['fieldflow-daily-execution-exception-control-v1','No database schema'],
];
foreach ($req as $rel=>$markers) {
    $raw = is_readable($root.'/'.$rel) ? (string)file_get_contents($root.'/'.$rel) : '';
    foreach ($markers as $marker) if (strpos($raw,$marker) === false) $fail[]=$rel.':'.$marker;
}
if ($fail) { fwrite(STDERR, "DAILY EXECUTION EXCEPTION CONTROL 9BP CONTRACT FAIL\n".implode("\n",$fail)."\n"); exit(1); }
echo "DAILY EXECUTION EXCEPTION CONTROL 9BP CONTRACT OK successor=>=2.2.587\n";
