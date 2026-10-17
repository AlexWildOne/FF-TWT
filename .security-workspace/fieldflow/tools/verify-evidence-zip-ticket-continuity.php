<?php
/**
 * Build-time verifier for FieldFlow 2.2.473 Evidence ZIP Ticket Continuity.
 * Runs without WordPress and verifies the capability-ticket transport contract.
 */
$root = dirname(__DIR__);
$failures = [];
$passes = [];
$assert = static function (bool $ok, string $name, string $detail = '') use (&$failures, &$passes): void {
    if ($ok) { $passes[] = $name; return; }
    $failures[] = $name . ($detail !== '' ? ': ' . $detail : '');
};
$source = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$containsAll = static function (string $text, array $markers): bool {
    foreach ($markers as $marker) if (strpos($text, $marker) === false) return false;
    return true;
};

$stats = $source('src/Rest/StatsController.php');
$registry = $source('src/Architecture/RuntimeRegistry.php');
$bridge = $source('src/Mobile/WebViewBridge.php');
$gate = $source('src/Support/ReleaseGate.php');
$fieldflow = $source('fieldflow.php');
$readme = $source('readme.txt');
$phase = $source('src/Cloud/Phase6StableRelease.php');
$responsive = $source('src/Front/ResponsiveAssets.php');

$assert($containsAll($stats, [
    'analytics_prepared_evidence_ticket_key',
    'ff_evidence_ready_ticket_',
    "bin2hex(random_bytes(32))",
    "'fieldflow_evidence_zip_download' => '1'",
    "'ticket' => \$ticket",
]), 'opaque_capability_ticket');
$assert($containsAll($stats, [
    'handlePreparedEvidencePublicDownload',
    "['GET', 'HEAD']",
    "if (\$method === 'HEAD') exit;",
    'evidence-zip-v4',
    'capability-ticket',
]), 'head_and_get_idempotent_transport');
$assert($containsAll($stats, [
    'fieldflow_evidence_group_token_ttl',
    '4 * HOUR_IN_SECONDS',
    'fieldflow_evidence_prepared_ttl',
]), 'evidence_token_ttl_continuity');
$assert($containsAll($registry, [
    "add_action('template_redirect'",
    'handlePreparedEvidencePublicDownload',
]), 'public_ticket_runtime_hook');
$assert($containsAll($bridge, [
    'nativeDownload(meta)',
    'browserDownload(meta)',
    "throw new Error('HTTP '+response.status)",
]), 'mobile_native_browser_retry_path_present');

$start = strpos($stats, 'public static function handlePreparedEvidencePublicDownload');
$end = $start === false ? false : strpos($stats, 'public static function handlePreparedEvidenceDownload', $start);
$segment = ($start !== false && $end !== false) ? substr($stats, $start, $end - $start) : '';
$assert($segment !== '', 'public_transport_segment_found');
if ($segment !== '') {
    $assert(strpos($segment, 'is_user_logged_in()') === false, 'public_ticket_no_cookie_session_dependency');
    $assert(strpos($segment, 'wp_verify_nonce') === false, 'public_ticket_no_wp_nonce_dependency');
    $assert(strpos($segment, "if (\$method === 'HEAD') exit;") !== false, 'head_probe_never_consumes_file');
    $assert(strpos($segment, "@unlink(\$path);\n        if (\$sent") === false, 'stream_does_not_delete_after_first_get');
    $assert(strpos($segment, "set_transient(\$transient_key, \$payload, \$remaining)") !== false, 'ticket_remains_reusable_during_ttl');
}

$assert($containsAll($gate, [
    "VERSION = '1.79.0'",
    'checkEvidenceZipTicketContinuityContract',
    'fieldflow-evidence-zip-ticket-continuity-v1',
]), 'release_gate_contract');
$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert(strpos($responsive, 'fieldflow-offline-runtime-2.2.530.js') !== false, 'offline_runtime_cache_version');

if (!defined('ABSPATH')) define('ABSPATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
require_once $root . '/src/Rest/StatsController.php';
try {
    $ref = new ReflectionClass('RoutesPro\\Rest\\StatsController');
    $key = $ref->getMethod('analytics_prepared_evidence_ticket_key');
    $key->setAccessible(true);
    $ticket = str_repeat('a', 64);
    $derived = (string)$key->invoke(null, $ticket);
    $assert(strpos($derived, 'ff_evidence_ready_ticket_') === 0 && strlen($derived) === strlen('ff_evidence_ready_ticket_') + 40, 'ticket_key_derivation_executes');
    $assert($derived === (string)$key->invoke(null, strtoupper($ticket)), 'ticket_key_normalizes_case');
} catch (Throwable $e) {
    $failures[] = 'ticket_key_runtime: ' . $e->getMessage();
}

if ($failures) {
    fwrite(STDERR, "EVIDENCE ZIP TICKET CONTINUITY CONTRACT FAILED\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}
echo "EVIDENCE ZIP TICKET CONTINUITY CONTRACT OK\n";
echo 'Checks: ' . count($passes) . "\n";
foreach ($passes as $pass) echo " - {$pass}\n";
