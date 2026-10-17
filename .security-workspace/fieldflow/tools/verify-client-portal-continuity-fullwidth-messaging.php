<?php
/** Build-time verifier for FieldFlow 2.2.530 Client Portal Continuity + Full Width + Messaging. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$contains = static function(string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};
$failures = [];
$assert = static function(bool $ok, string $label) use (&$failures): void { if (!$ok) $failures[] = $label; };

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$client = $read('src/Front/ClientPortalShortcodeRenderer.php');
$ui = $read('assets/fieldflow-ui-runtime.js');
$css = $read('assets/fieldflow-premium-shell-v2.css');
$emails = $read('src/Admin/Emails.php');
$shortcodes = $read('src/Shortcodes.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9B-CLIENT-PORTAL-CONTINUITY-FULLWIDTH-MESSAGING.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($client, [
    'fieldflow-client-portal-continuity-v1', 'ff-client-continuity', 'function keepClientTabVisible(button)',
    "bar.scrollTo({left:desired,behavior:'auto'})", 'window.requestAnimationFrame(()=>keepClientTabVisible(activeButton))'
]), 'client_tab_continuity');
$assert(strpos($client, 'window.FieldFlowUI?.compactLayout?.(root)') === false, 'client_switch_no_global_compact_scroll');
$assert($contains($ui, [
    'Phase 9B: the Client Portal owns its own panel continuity', "if(shell.classList.contains('rp-client-premium')||document.body?.classList.contains('ff-app-resume-guard-active')) return;",
    'if(!clientTab) window.requestAnimationFrame(()=>compactPortalLayout(shell));', "!shell.classList?.contains('rp-client-premium')"
]), 'client_runtime_no_scroll_clamp');
$assert($contains($css, [
    'Phase 9B, Client Portal Continuity + Full Width', 'fieldflow-client-portal-continuity-v1',
    'overflow-x:auto!important', 'scrollbar-width:none!important', 'width:0!important', 'height:0!important',
    'width:100%;', 'max-width:none;', 'min-width:0;'
]), 'mobile_nav_fullwidth_css');
$assert($contains($emails, [
    'Phase 9B: sender eligibility follows the authenticated portal scope',
    'Permissions::assert_scope_or_error($client_id, $project_id, $logged_user_id)', 'is_wp_error($scope_check)'
]), 'sender_scope_authority');
$fnStart = strpos($emails, 'public static function get_available_senders_for_user');
$fnEnd = $fnStart !== false ? strpos($emails, 'public static function get_message_app_url', $fnStart) : false;
$senderFn = ($fnStart !== false && $fnEnd !== false) ? substr($emails, $fnStart, $fnEnd - $fnStart) : '';
$assert($senderFn !== '' && strpos($senderFn, 'get_associated_user_ids') === false, 'sender_not_operational_roster_bound');
$assert($contains($shortcodes, ['recipientPayload', 'senderRes.ok', 'senderError', 'recipientError', 'failureMessage', 'els.send.disabled=!(hasRecipients&&hasSenders)']), 'messaging_error_surface');
$assert(strpos($shortcodes, 'O utilizador autenticado não está elegível para enviar nesta campanha.') === false, 'false_eligibility_copy_removed');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert($contains($gate, ['checkClientPortalContinuityContract', 'Client Portal Continuity']), 'release_gate_guard');
$assert($contains($validator, ['client_portal_continuity_ok', 'O Client Portal Continuity não está integralmente ligado.']), 'package_validator_guard');
$assert($contains($manager, ['Client Portal Continuity', "packageResult['client_portal_continuity_ok']"]), 'release_manager_visibility');
$assert($contains($doc, [
    'fieldflow-client-portal-continuity-v1', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.',
    '`SubmissionPipeline::BUILD` remains `2.2.485`.', '`SOURCE_RELEASE` is `2.2.515` and `STABLE_RELEASE` is `2.2.516`'
]), 'phase_documentation');

if ($failures) {
    fwrite(STDERR, "CLIENT PORTAL CONTINUITY CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "CLIENT PORTAL CONTINUITY CONTRACT OK\n";
echo "release=2.2.530\n";
echo "contract=fieldflow-client-portal-continuity-v1\n";
echo "client_tab_scroll=tabstrip_only\n";
echo "mobile_nav_scrollbar=hidden\n";
echo "sender_authority=authenticated_scope\n";
echo "submission_build=2.2.485\n";
