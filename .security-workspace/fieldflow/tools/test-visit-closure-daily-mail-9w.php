<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) {
    function sanitize_key($value) {
        $value = strtolower((string)$value);
        return preg_replace('/[^a-z0-9_\-]/', '', $value) ?: '';
    }
}
require_once dirname(__DIR__) . '/src/Services/SubmissionStatusPolicy.php';
require_once dirname(__DIR__) . '/src/Services/VisitCompletionPolicy.php';

use RoutesPro\Services\VisitCompletionPolicy;

$assert = static function(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
};

$assert(VisitCompletionPolicy::submissionClosesVisit('submitted'), 'submitted_closes');
$assert(VisitCompletionPolicy::submissionClosesVisit('reviewed'), 'reviewed_closes');
$assert(VisitCompletionPolicy::submissionClosesVisit('approved'), 'approved_closes');
$assert(VisitCompletionPolicy::submissionClosesVisit(''), 'historical_null_closes');
$assert(!VisitCompletionPolicy::submissionClosesVisit('draft'), 'draft_does_not_close');
$assert(!VisitCompletionPolicy::submissionClosesVisit('rejected'), 'rejected_does_not_close');
$assert(VisitCompletionPolicy::stopIsExecuted('done'), 'done_executed');
$assert(VisitCompletionPolicy::stopIsExecuted('completed'), 'completed_executed');
$assert(VisitCompletionPolicy::stopIsJustifiedNonVisit('failed', 'Loja encerrada'), 'failed_justified_terminal');
$assert(!VisitCompletionPolicy::stopIsJustifiedNonVisit('failed', '  '), 'failed_without_reason_not_terminal');
$assert(VisitCompletionPolicy::stopIsTerminal('failed', 'Sem acesso'), 'justified_nonvisit_terminal');
$assert(!VisitCompletionPolicy::stopIsTerminal('pending', ''), 'pending_not_terminal');
$assert(strpos(VisitCompletionPolicy::remainingSql('rs'), "rs.status='failed'") !== false, 'remaining_sql_alias');

echo "VISIT CLOSURE DAILY COMPLETION MAIL 9W RUNTIME OK\n";
echo "submission_finality=enforced\n";
echo "justified_nonvisit=terminal_not_executed\n";
echo "daily_close=terminal_route_only\n";
