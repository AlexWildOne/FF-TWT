<?php
/**
 * Build-time verifier for FieldFlow 2.2.473 Evidence ZIP System Browser Handoff.
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

$bridge = $source('src/Mobile/WebViewBridge.php');
$shortcodes = $source('src/Shortcodes.php');
$portal = $source('src/Front/ClientPortalShortcodeRenderer.php');
$gate = $source('src/Support/ReleaseGate.php');
$fieldflow = $source('fieldflow.php');
$readme = $source('readme.txt');
$phase = $source('src/Cloud/Phase6StableRelease.php');
$responsive = $source('src/Front/ResponsiveAssets.php');

$assert($containsAll($bridge, [
    'function preparedEvidenceDownload(meta)',
    "source:'evidence_zip_prepared'",
    "transport:'external_system_browser'",
    'nativeExternalOpen(payload,3600)',
    "fieldflow.evidence.download.requested",
    "fieldflow.evidence.download.failed",
    "if(meta.evidenceExport)return preparedEvidenceDownload(meta)",
]), 'system_browser_handoff_markers');

$start = strpos($bridge, 'function preparedEvidenceDownload(meta)');
$end = $start === false ? false : strpos($bridge, 'window.FieldFlowNativeBridge=Object.assign', $start);
$segment = ($start !== false && $end !== false) ? substr($bridge, $start, $end - $start) : '';
$assert($segment !== '', 'prepared_evidence_segment_found');
if ($segment !== '') {
    $assert(strpos($segment, 'nativeDownload(meta)') === false, 'generic_native_downloader_bypassed');
    $assert(strpos($segment, "fieldflow.download.requested") === false, 'generic_download_event_bypassed');
}

$assert($containsAll($shortcodes, [
    "downloadPrepared==='function'",
    'ZIP aberto para download',
    "data-fieldflow-download-fallback",
]), 'operational_analytics_handoff');
$assert($containsAll($portal, [
    "downloadPrepared==='function'",
    'ZIP aberto para download',
    "data-fieldflow-download-fallback",
]), 'client_portal_handoff');
$assert($containsAll($gate, [
    "VERSION = '1.79.0'",
    'checkEvidenceZipSystemHandoffContract',
    'fieldflow-evidence-zip-system-handoff-v1',
]), 'release_gate_contract');
$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert(strpos($responsive, 'fieldflow-offline-runtime-2.2.530.js') !== false, 'offline_runtime_cache_version');

if ($failures) {
    fwrite(STDERR, "EVIDENCE ZIP SYSTEM HANDOFF CONTRACT FAILED\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}
echo "EVIDENCE ZIP SYSTEM HANDOFF CONTRACT OK\n";
echo 'Checks: ' . count($passes) . "\n";
foreach ($passes as $pass) echo " - {$pass}\n";
