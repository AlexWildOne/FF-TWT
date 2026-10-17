<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$failed = [];
$assert = static function(bool $ok, string $name) use (&$failed): void { if (!$ok) $failed[] = $name; };

$service = $read('src/Services/VisitExceptionService.php');
$periodicity = $read('src/Services/PeriodicityProgressService.php');
$routes = $read('src/Rest/RoutesController.php');
$shortcodes = $read('src/Shortcodes.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9BF-VISIT-EXCEPTION-JUSTIFIED-NONVISIT.md');

$assert(strpos($service, "CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1'") !== false, 'service:contract');
$assert(strpos($service, "OPTION = 'fieldflow_visit_exceptions_v1'") !== false, 'service:ledger');
$assert(strpos($service, "AuditLog::record('visit_exception.upsert'") !== false, 'service:audit_upsert');
$assert(strpos($service, "AuditLog::record('visit_exception.clear'") !== false, 'service:audit_clear');
$assert(strpos($periodicity, "JUSTIFIED_EXCEPTION_CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1'") !== false, 'periodicity:contract');
$assert(strpos($periodicity, "'justified_stores'") !== false, 'periodicity:summary');
$assert(strpos($periodicity, "'visits_justified'") !== false, 'periodicity:visits_justified');
$assert(strpos($periodicity, "'remaining_after_justification'") !== false, 'periodicity:remaining');
$assert(strpos($periodicity, "rs.fail_reason") !== false, 'periodicity:route_failure');
$assert(strpos($routes, "'/visit-exceptions'") !== false, 'routes:post');
$assert(strpos($routes, 'save_visit_exception') !== false && strpos($routes, 'clear_visit_exception') !== false, 'routes:actions');
$assert(strpos($shortcodes, 'Justificar não visita') !== false, 'ui:modal');
$assert(strpos($shortcodes, '0 justificadas') !== false, 'ui:kpi');
$assert(strpos($shortcodes, 'data-periodicity-justify') !== false, 'ui:action');
$assert(strpos($shortcodes, 'não conta como visita realizada') !== false, 'ui:truthful_execution');
$assert(strpos($phase, "SOURCE_RELEASE = '2.2.575'") !== false && strpos($phase, "STABLE_RELEASE = '2.2.576'") !== false, 'phase6:successor');
$assert(strpos($gate, "VISIT_EXCEPTION_JUSTIFIED_NONVISIT_9BF_CONTRACT = 'fieldflow-visit-exception-justified-nonvisit-v1'") !== false, 'gate:contract');
$assert(strpos($gate, 'checkVisitExceptionJustifiedNonVisit9BFContract') !== false, 'gate:check');
$assert(strpos($validator, 'visit_exception_justified_nonvisit_9bf_ok') !== false, 'validator:flag');
$assert(strpos($manager, "packageResult['visit_exception_justified_nonvisit_9bf_ok']") !== false, 'manager:kpi');
$assert(strpos($doc, 'fieldflow-visit-exception-justified-nonvisit-v1') !== false, 'doc:contract');
$assert(strpos($read('src/Support/Migrations.php'), "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema:unchanged');
$assert(strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false, 'submission:unchanged');

if ($failed) {
    fwrite(STDERR, 'FAILED ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "VISIT EXCEPTION JUSTIFIED NONVISIT 9BF CONTRACT OK\n";
