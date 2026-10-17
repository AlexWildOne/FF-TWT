<?php
/**
 * Build-time, non-destructive verifier for FieldFlow 2.2.466 manual mail recovery.
 * Runs without WordPress and complements the in-app Release Gate.
 */
$root = dirname(__DIR__);
$failures = [];
$passes = [];
$source = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_readable($path) ? (string) file_get_contents($path) : '';
};
$assert = static function (bool $ok, string $name, string $detail = '') use (&$failures, &$passes): void {
    if ($ok) { $passes[] = $name; return; }
    $failures[] = $name . ($detail !== '' ? ': ' . $detail : '');
};
$containsAll = static function (string $text, array $markers): bool {
    foreach ($markers as $marker) if (strpos($text, $marker) === false) return false;
    return true;
};

$mail = $source('src/Admin/ReportAutomations.php');
$emails = $source('src/Admin/Emails.php');
$routeProgress = $source('src/Services/RouteProgressService.php');
$submissions = $source('src/Admin/FormSubmissions.php');

$assert($containsAll($mail, [
    'handleManualSend', 'renderManualCenter', 'manualScheduleFor', 'manual_dispatch',
    "'anchor_date'=>" . '$date', "'report_user_id'=>" . '$userId', 'manual_route_close',
    'RouteProgressService::sync($itemId, true, false)',
]), 'manual_route_replay', 'historical daily-close replay or guarded route reconciliation is incomplete');

$assert($containsAll($mail, [
    'getOrderSuggestionSubmission($itemId)', 'manual_order_suggestion', 'order_suggestion_manual',
    'ensureOrderSuggestionAutomation($clientId, $projectId, $date)', "'_resolved_recipients'",
]), 'manual_order_replay', 'historical order-suggestion replay is incomplete');

$assert($containsAll($mail, [
    'manualRecipientSet', "'to_team'] = 1", "'to_clients'] = 1", "'extra_emails'] = ''",
    '(string)($r[\'type\'] ?? \'\') === \'client\'', 'submission_operative',
]), 'manual_recipient_scope', 'manual client/team recipient isolation is incomplete');

$assert($containsAll($mail, [
    'test_team', 'test_client', 'data-manual-test-email-hidden', 'manual_route_close_test',
    'manual_order_suggestion_test', 'if (!empty($context[\'manual_dispatch\'])) return;',
]), 'manual_test_isolation', 'test-only recipient isolation or schedule-state protection is incomplete');

$assert($containsAll($routeProgress, [
    'bool $emit_completion_event = true', "EventBus::emit('route.status_changed'",
    '$next_status === \'completed\' && $emit_completion_event', 'completion_event_emitted',
]), 'manual_route_reconciliation_fixture', 'manual route repair must preserve status integration while suppressing the automatic completion mail event');

$assert($containsAll($emails, ["'manual'", 'Envio manual', 'renderManualCenter']), 'manual_center_navigation');
$assert($containsAll($submissions, ['getOrderSuggestionSubmission', 'createOrderSuggestionReportFile']), 'manual_order_source_truth');

// Fixture: choosing both audiences should keep one copy per email and prefer the team profile when duplicated.
$team = [['email'=>'same@example.test','type'=>'team'],['email'=>'op@example.test','type'=>'team']];
$client = [['email'=>'same@example.test','type'=>'client'],['email'=>'client@example.test','type'=>'client']];
$out = [];
foreach (array_merge($team, $client) as $recipient) {
    $email = strtolower($recipient['email']);
    if (isset($out[$email]) && ($out[$email]['type'] ?? '') === 'team') continue;
    $out[$email] = $recipient;
}
$assert(count($out) === 3 && ($out['same@example.test']['type'] ?? '') === 'team', 'manual_recipient_dedupe_fixture');

if ($failures) {
    fwrite(STDERR, "MAIL CENTER HISTORICAL RECOVERY CONTRACT FAILED\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "MAIL CENTER HISTORICAL RECOVERY CONTRACT OK\n";
echo 'Checks: ' . count($passes) . "\n";
foreach ($passes as $pass) echo " - {$pass}\n";
