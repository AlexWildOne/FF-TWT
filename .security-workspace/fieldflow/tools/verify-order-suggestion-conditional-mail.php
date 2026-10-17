<?php
/** Build-time verifier for FieldFlow 2.2.474 Order Suggestion Conditional Mail Guard. */
$root = dirname(__DIR__);
$checks = [];
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $condition, string $name) use (&$checks): void {
    $checks[$name] = $condition ? 'PASS' : 'FAIL';
};
$containsAll = static function(string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};
$fieldflow = $read('fieldflow.php');
$readme = $read('readme.txt');
$flow = $read('src/Forms/ConditionalFlow.php');
$forms = $read('src/Forms/Forms.php');
$mobile = $read('src/Mobile/SubmissionService.php');
$subs = $read('src/Admin/FormSubmissions.php');
$daily = $read('src/Forms/DailySubmissionService.php');
$mail = $read('src/Admin/ReportAutomations.php');
$gate = $read('src/Support/ReleaseGate.php');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$responsive = $read('src/Front/ResponsiveAssets.php');

$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert(strpos($responsive, 'fieldflow-offline-runtime-2.2.530.js') !== false, 'offline_runtime_cache_version');
$assert($containsAll($flow, ['submission_meta', 'hidden_question_keys', 'order_suggestion_keys', 'active_order_suggestion_keys']), 'conditional_snapshot_contract');
$assert($containsAll($forms, ["base_meta['conditional_flow']", 'ConditionalFlow::submission_meta($schema, $conditional_visibility)']), 'web_snapshot_persistence');
$assert($containsAll($mobile, ["base_meta['conditional_flow']", 'ConditionalFlow::submission_meta($schema, $conditional_visibility)']), 'mobile_snapshot_persistence');
$assert($containsAll($subs, ['activeOrderSuggestionKeys', 'active_order_suggestion_keys', 'orderSuggestionMailEligibility', 'if (empty($mailEligibility[\'eligible\'])) return [];']), 'order_submission_guard');
$assert($containsAll($daily, ['conditional_flow_snapshot', "base_meta['conditional_flow'] = \$conditional_flow_snapshot"]), 'edit_snapshot_replacement');
$assert($containsAll($subs, ['ConditionalFlow::payload($schema, $values)', "sanitize_key((string)(\$question['type'] ?? '')) !== 'order_suggestion'"]), 'legacy_visibility_fallback');
$assert(strpos($mail, 'FormSubmissions::getOrderSuggestionSubmission($submissionId)') !== false, 'mail_uses_guarded_submission');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'fieldflow-order-suggestion-conditional-mail-v1', 'checkOrderSuggestionConditionalMailContract']), 'release_gate_contract');

$failed = array_keys(array_filter($checks, static fn($status) => $status !== 'PASS'));
foreach ($checks as $name => $status) echo $status . ' ' . $name . PHP_EOL;
echo $failed ? ('FAILED ' . implode(',', $failed) . PHP_EOL) : ('PASS fieldflow-order-suggestion-conditional-mail-v1' . PHP_EOL);
exit($failed ? 1 : 0);
