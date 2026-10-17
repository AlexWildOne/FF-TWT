<?php
/**
 * Build-time verifier for FieldFlow 2.2.473 Analytics Mobile Full-Width & Download State.
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

$app = $source('src/Front/AppShortcodeRenderer.php');
$shortcodes = $source('src/Shortcodes.php');
$clientPortal = $source('src/Front/ClientPortalShortcodeRenderer.php');
$bridge = $source('src/Mobile/WebViewBridge.php');
$gate = $source('src/Support/ReleaseGate.php');
$fieldflow = $source('fieldflow.php');
$readme = $source('readme.txt');
$phase = $source('src/Cloud/Phase6StableRelease.php');
$responsive = $source('src/Front/ResponsiveAssets.php');

$assert($containsAll($app, [
    'ff-portal-layout-aligned',
    'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"',
]), 'app_analytics_viewport_bleed');
$assert($containsAll($shortcodes, [
    '2.2.472, mobile Analytics full-width and uninterrupted vertical reading.',
    '.ffad-ask-thread{max-height:none!important;overflow:visible!important',
    '.ffad-chart{width:100%!important;max-width:100%!important;min-height:0!important;overflow-x:auto!important;overflow-y:hidden!important',
    '.ffad-analytics-table{max-height:none!important;overflow:visible!important',
]), 'analytics_vertical_scroll_continuity');
$assert($containsAll($clientPortal, [
    'ff-portal-layout-aligned',
    'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"',
    '#rpcp-analytics-panel{',
    'width:100%!important',
    '.rp-ask-thread{',
    'max-height:none!important;',
    'overflow-y:hidden!important;',
]), 'client_portal_analytics_mobile_continuity');
$assert($containsAll($shortcodes, [
    "downloadPrepared==='function'",
    'await window.FieldFlowNativeBridge.downloadPrepared',
    "ZIP indisponível · tenta novamente",
]), 'prepared_zip_bridge_handoff');
$assert(strpos($shortcodes, "const note=els.note;if(note)note.textContent='Não foi possível exportar as evidências.") === false, 'no_global_route_note_overwrite');
$assert($containsAll($bridge, [
    'downloadPrepared:function',
    "dashboardExport:false,routeExport:false,evidenceExport:",
    'preparedEvidenceDownload(meta)',
    'ZIP enviado para o gestor de downloads.',
]), 'mobile_bridge_evidence_download_state');
$assert($containsAll($gate, [
    "VERSION = '1.79.0'",
    'checkAnalyticsMobileFullWidthDownloadStateContract',
    'fieldflow-analytics-mobile-fullwidth-download-state-v1',
]), 'release_gate_contract');
$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert(strpos($responsive, 'fieldflow-offline-runtime-2.2.530.js') !== false, 'offline_runtime_cache_version');

if ($failures) {
    fwrite(STDERR, "ANALYTICS MOBILE FULLWIDTH DOWNLOAD STATE CONTRACT FAILED\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}
echo "ANALYTICS MOBILE FULLWIDTH DOWNLOAD STATE CONTRACT OK\n";
echo 'Checks: ' . count($passes) . "\n";
foreach ($passes as $pass) echo " - {$pass}\n";
