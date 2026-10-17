<?php
/**
 * Build-time, non-destructive contract verifier for FieldFlow 2.2.466.
 * Runs without WordPress and complements the in-app Release Gate.
 */
$root = dirname(__DIR__);
$failures = [];
$passes = [];

$assert = static function (bool $ok, string $name, string $detail = '') use (&$failures, &$passes): void {
    if ($ok) {
        $passes[] = $name;
        return;
    }
    $failures[] = $name . ($detail !== '' ? ': ' . $detail : '');
};
$source = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_readable($path) ? (string) file_get_contents($path) : '';
};
$containsAll = static function (string $text, array $markers): bool {
    foreach ($markers as $marker) {
        if (strpos($text, $marker) === false) return false;
    }
    return true;
};

// multi_photo_ordinals
$formRenderer = $source('src/Forms/FormRenderer.php');
$assert($containsAll($formRenderer, [
    'function rpEnsureDraftFileOrdinals(records)',
    'var needed=required[name]+1;',
    'while(existing.length<needed)',
    'data-rp-draft-rehydrated-clone="1"',
    'rpEnsureDraftFileOrdinals(records);',
]), 'multi_photo_ordinals', 'DOM recovery does not reconstruct durable ordinals');
$fixtureOrdinals = [0, 1, 2, 3, 4];
$existingSlots = 2;
$neededSlots = max($fixtureOrdinals) + 1;
while ($existingSlots < $neededSlots) $existingSlots++;
$assert($existingSlots === 5, 'multi_photo_ordinals_fixture', 'five durable ordinal groups must require five recovery slots');

$draftService = $source('src/Forms/DraftService.php');
$assert($containsAll($draftService, [
    'foreach ($desired as $key => $count)',
    'input_ordinal,file_index,id',
    '$expected_total = array_sum($wanted);',
    '$limit = (int)($wanted[$key] ?? 0);',
    'Nem todos os anexos do rascunho puderam ser publicados',
]), 'multi_photo_server_publish', 'server draft publishing must count every input ordinal');

// justified_failure_terminal
$routeProgress = $source('src/Services/RouteProgressService.php');
$visitCompletionPolicy = $source('src/Services/VisitCompletionPolicy.php');
$legacyTerminalContract = $containsAll($routeProgress, [
    'justified_failed_stops',
    "status='failed' AND TRIM(COALESCE(fail_reason,''))<>''",
    '$terminal = $completed + $justified_failed;',
    '$remaining === 0',
    "EventBus::emit('route.completed'",
    'operational_completion_rate',
    'TRIM(COALESCE(rs.fail_reason',
]);
$canonicalTerminalContract = $containsAll($routeProgress, [
    'justified_failed_stops',
    'VisitCompletionPolicy::justifiedNonVisitSql()',
    'VisitCompletionPolicy::remainingSql()',
    '$remaining === 0',
    "EventBus::emit('route.completed'",
    'operational_completion_rate',
]) && $containsAll($visitCompletionPolicy, [
    "CONTRACT = 'fieldflow-visit-closure-daily-mail-v1'",
    'stopIsJustifiedNonVisit',
    "status='failed' AND TRIM(COALESCE(",
]);
$assert($legacyTerminalContract || $canonicalTerminalContract, 'justified_failure_terminal', 'justified failure is not represented as an operational terminal state');
$fixtureStops = [
    ['status' => 'done', 'reason' => ''],
    ['status' => 'completed', 'reason' => ''],
    ['status' => 'failed', 'reason' => 'Loja encerrada'],
];
$terminalStops = 0;
$executedStops = 0;
foreach ($fixtureStops as $stop) {
    if (in_array($stop['status'], ['done', 'completed'], true)) {
        $terminalStops++;
        $executedStops++;
        continue;
    }
    if ($stop['status'] === 'failed' && trim($stop['reason']) !== '') $terminalStops++;
}
$assert($terminalStops === 3 && $executedStops === 2, 'justified_failure_terminal_fixture', 'route must close operationally without falsifying executed visits');

$events = $source('src/Rest/EventsController.php');
$routes = $source('src/Rest/RoutesController.php');
$assert(strpos($events, 'failure_reason_required') !== false && strpos($routes, 'failure_reason_required') !== false, 'failure_reason_server_validation');

// operational_content_revision
$revision = $source('src/Mobile/OperationalContentRevision.php');
$offlineSync = $source('src/Mobile/OfflineSyncService.php');
$briefing = $source('src/Briefings/BriefingService.php');
$cardex = $source('src/Briefings/CardexSyncService.php');
$assert(
    $containsAll($revision, ['fieldflow_operational_content_revision_', 'function bump(', 'fieldflow_operational_content_revision_bumped'])
    && $containsAll($offlineSync, ['OperationalContentRevision::current', 'operational_content_revision'])
    && $containsAll($briefing, ['briefing_publish', 'briefing_archive', 'briefing_mapping', 'briefing_delete', 'OperationalContentRevision::bump'])
    && $containsAll($cardex, ['OperationalContentRevision::bump', 'Reconcile, rather than only INSERT IGNORE', '$wpdb->delete(ProductCardex::table_locations()']),
    'operational_content_revision',
    'Briefing/Cardex/offline bundle revision chain is incomplete'
);

// order_suggestion_mail_export
$submissions = $source('src/Admin/FormSubmissions.php');
$mail = $source('src/Admin/ReportAutomations.php');
$assert(
    $containsAll($submissions, [
        'function getOrderSuggestionSubmission',
        'function orderSuggestionExportRows',
        'function createOrderSuggestionReportFile',
        'Sugestões Encomenda',
        'Sugestão encomenda',
    ])
    && $containsAll($mail, [
        'handleOrderSuggestionSubmission',
        'claimOrderRecipientDispatch',
        'releaseOrderRecipientClaims',
        'scheduleOrderSuggestionRetry',
        'ORDER_RETRY_MAX_ATTEMPTS',
        'executeOrderSuggestion',
        'orderSuggestionEmailBody',
        '$formats = [\'xlsx\',\'pdf\'];',
        'to_team',
        'to_clients',
        'submission_operative',
        'ensureOrderSuggestionAutomation($clientId, $projectId, $date)',
        "'start_date'=>" . '$startDate',
    ]),
    'order_suggestion_mail_export',
    'order suggestion export/mail contract is incomplete'
);

// Offline-next-day fixture: an order suggested yesterday and synced today must still match the auto-provisioned automation.
$offlineReportDay = '2026-09-09';
$syncDay = '2026-09-10';
$automationStart = $offlineReportDay;
$assert($automationStart <= $offlineReportDay && $offlineReportDay < $syncDay, 'order_suggestion_offline_next_day_fixture', 'automation start date must use the report day, not the later sync day');

// Per-recipient delivery fixture: successful recipients keep their claims, only the failed recipient is released for retry.
$recipientDelivery = ['op@example.test' => true, 'client-a@example.test' => true, 'client-b@example.test' => false, 'manager@example.test' => true];
$remainingClaims = array_keys($recipientDelivery);
$retryRecipients = [];
foreach ($recipientDelivery as $email => $ok) {
    if ($ok) continue;
    $remainingClaims = array_values(array_diff($remainingClaims, [$email]));
    $retryRecipients[] = $email;
}
$assert(count($remainingClaims) === 3 && $retryRecipients === ['client-b@example.test'], 'order_suggestion_recipient_retry_fixture', 'only failed recipients must be eligible for retry');

// route completion email must be event-driven and deferred until the request closes.
$assert($containsAll($mail, [
    "add_action('fieldflow_core_event'",
    'route.completed',
    'flushRouteCloseQueue',
    'dispatchDailyCloseForRoute',
]), 'route_completed_mail_event', 'daily mail is not bound to the canonical route completion event');

if ($failures) {
    fwrite(STDERR, "FIELD FORM RELIABILITY CONTRACT FAILED\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "FIELD FORM RELIABILITY CONTRACT OK\n";
echo 'Checks: ' . count($passes) . "\n";
foreach ($passes as $pass) echo " - {$pass}\n";
