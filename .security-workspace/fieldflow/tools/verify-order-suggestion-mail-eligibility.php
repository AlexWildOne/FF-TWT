<?php
/** Behavioural verifier for FieldFlow 2.2.530 Order Suggestion Mail Eligibility v2. */
define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string)$key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}
require_once dirname(__DIR__) . '/src/Admin/FormSubmissions.php';

use RoutesPro\Admin\FormSubmissions;

$checks = [];
$assert = static function(bool $condition, string $name) use (&$checks): void {
    $checks[$name] = $condition ? 'PASS' : 'FAIL';
};

$state = FormSubmissions::resolveOrderSuggestionMailEligibility(['order'], ['order'], ['order'], ['order'], true);
$assert(empty($state['eligible']) && ($state['reason'] ?? '') === 'snapshot_inactive', 'hidden_snapshot_blocks_mail');

$state = FormSubmissions::resolveOrderSuggestionMailEligibility(['order'], ['order'], [], [], true);
$assert(empty($state['eligible']) && ($state['reason'] ?? '') === 'canonical_inactive', 'persisted_no_vetoes_stale_active_snapshot');

$state = FormSubmissions::resolveOrderSuggestionMailEligibility(['order_a','order_b'], ['order_a'], [], ['order_b'], true);
$assert(empty($state['eligible']) && ($state['reason'] ?? '') === 'conditional_disagreement_inactive', 'disagreement_fails_closed');

$state = FormSubmissions::resolveOrderSuggestionMailEligibility(['order'], ['order'], [], ['order'], true);
$assert(!empty($state['eligible']) && ($state['active_keys'] ?? []) === ['order'], 'both_sources_active_allows_mail');

$state = FormSubmissions::resolveOrderSuggestionMailEligibility([], [], [], ['order'], false);
$assert(!empty($state['eligible']) && ($state['active_keys'] ?? []) === ['order'], 'canonical_only_legacy_active_allows_mail');

$state = FormSubmissions::resolveOrderSuggestionMailEligibility([], [], [], [], false);
$assert(empty($state['eligible']) && ($state['reason'] ?? '') === 'canonical_inactive', 'canonical_only_legacy_inactive_blocks_mail');

$failed = array_keys(array_filter($checks, static fn($status) => $status !== 'PASS'));
foreach ($checks as $name => $status) echo $status . ' ' . $name . PHP_EOL;
echo $failed ? ('FAILED ' . implode(',', $failed) . PHP_EOL) : ('PASS fieldflow-order-suggestion-mail-eligibility-v2' . PHP_EOL);
exit($failed ? 1 : 0);
