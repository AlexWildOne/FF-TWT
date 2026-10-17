<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
};

$plugin = $read('fieldflow.php');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$policy = $read('src/Services/VisitCompletionPolicy.php');
$statusPolicy = $read('src/Services/SubmissionStatusPolicy.php');
$routeProgress = $read('src/Services/RouteProgressService.php');
$formCompletion = $read('src/Services/FormVisitCompletionService.php');
$automations = $read('src/Admin/ReportAutomations.php');
$notify = $read('src/Services/Notify.php');
$events = $read('src/Rest/EventsController.php');
$routes = $read('src/Rest/RoutesController.php');
$manual = $read('src/Services/VisitManualCompletionService.php');
$doc = $read('PHASE-9W-VISIT-CLOSURE-DAILY-COMPLETION-MAIL.md');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$loader = $read('src/Support/Loader.php');
$migrations = $read('src/Support/Migrations.php');
$pipeline = $read('src/Forms/SubmissionPipeline.php');

$assert((bool)preg_match("/FIELDFLOW_VERSION'\s*,\s*'([^']+)'/", $plugin, $m), 'release_identity_missing');
$release = (string)$m[1];
$assert(version_compare($release, '2.2.539', '>='), 'release_identity_too_old');
$assert(strpos($policy, "CONTRACT = 'fieldflow-visit-closure-daily-mail-v1'") !== false, 'policy_contract');
$assert(strpos($statusPolicy, "['submitted','reviewed','approved']") !== false && strpos($policy, 'SubmissionStatusPolicy::isOperational') !== false, 'submission_finality');
$assert(strpos($routeProgress, "SubmissionStatusPolicy::operationalSql('fs')") !== false, 'reconciliation_finality');
$assert(strpos($routeProgress, 'VisitCompletionPolicy::remainingSql()') !== false, 'route_terminal_policy');
$assert(strpos($formCompletion, 'VisitCompletionPolicy::submissionClosesVisit') !== false, 'form_completion_finality');
$assert(strpos($formCompletion, 'ReportAutomations::queueDailyCloseForRoute') !== false, 'form_completion_daily_close');
$assert(strpos($automations, "DAILY_CLOSE_RETRY_HOOK = 'fieldflow_daily_close_dispatch_retry'") !== false, 'daily_close_retry_hook');
$assert(strpos($automations, "'recipient_mode'=>'client_and_extra'") !== false, 'consolidated_extra_recipient');
$assert(strpos($automations, "['claim'=>'extra','mode'=>'extra']") !== false, 'immediate_extra_recipient');
$assert(strpos($automations, 'scheduleDailyCloseRetry') !== false && strpos($automations, 'legacy_route_notification') !== false, 'daily_close_reliability');
$assert(strpos($notify, "'route_notification|completed|' . \$route_id") !== false, 'legacy_idempotency');
$assert(substr_count($events, 'ReportAutomations::queueDailyCloseForRoute') >= 2, 'event_paths_post_commit');
$assert(strpos($routes, 'ReportAutomations::queueDailyCloseForRoute') !== false, 'route_patch_post_commit');
$assert(strpos($manual, 'ReportAutomations::queueDailyCloseForRoute') !== false, 'manual_completion_post_commit');
$assert(strpos($loader, 'src/Services/VisitCompletionPolicy.php') !== false, 'loader_policy');
$assert(strpos($doc, '`SOURCE_RELEASE` is `2.2.538` and `STABLE_RELEASE` is `2.2.539`') !== false, 'phase_documentation');
$assert(strpos($gate, "VISIT_CLOSURE_DAILY_MAIL_9W_CONTRACT = 'fieldflow-visit-closure-daily-mail-v1'") !== false, 'release_gate_contract');
$assert(strpos($validator, "'visit_closure_daily_mail_9w_ok'=>false") !== false, 'package_validator_contract');
$assert(strpos($manager, 'Visit Closure &amp; Daily Completion Mail 9W') !== false && strpos($manager, "packageResult['visit_closure_daily_mail_9w_ok']") !== false, 'release_manager_kpi');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema_identity');
$assert(strpos($pipeline, "public const BUILD = '2.2.485'") !== false, 'submission_identity');
$assert((bool)preg_match("/SOURCE_RELEASE\s*=\s*'([^']+)'/", $phase6, $sourceMatch), 'phase6_source');
$assert((bool)preg_match("/STABLE_RELEASE\s*=\s*'([^']+)'/", $phase6, $stableMatch), 'phase6_stable');
$assert((string)$stableMatch[1] === $release, 'phase6_runtime_match');

echo "VISIT CLOSURE DAILY COMPLETION MAIL 9W CONTRACT OK\n";
echo "release={$release}\n";
echo "successor=>=2.2.539\n";
echo "schema=2.2.529\n";
echo "submission_build=2.2.485\n";
