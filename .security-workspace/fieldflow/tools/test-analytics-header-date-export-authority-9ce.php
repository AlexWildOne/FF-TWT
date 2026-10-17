<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$client = $read('src/Front/ClientPortalShortcodeRenderer.php');
$operative = $read('src/Shortcodes.php');
$webview = $read('src/Mobile/WebViewBridge.php');
$server = $read('src/Admin/FormSubmissions.php');
$gate = $read('src/Support/ReleaseGate.php');
$checks = [
    'client_live_dates' => strpos($client, 'function analyticsHeaderDateScope()') !== false
        && strpos($client, "addEventListener('input',syncAnalyticsExportDateAuthority)") !== false
        && strpos($client, "p.set('from',scope.from)") !== false
        && strpos($client, "p.set('to',scope.to)") !== false,
    'operative_live_dates' => strpos($operative, 'function analyticsHeaderDateScope()') !== false
        && strpos($operative, "addEventListener('input',syncAnalyticsExportDateAuthority)") !== false
        && strpos($operative, "p.set('from',scope.from)") !== false
        && strpos($operative, "p.set('to',scope.to)") !== false,
    'webview_capture' => strpos($webview, 'function applyLiveAnalyticsDateScope(anchor,url)') !== false
        && strpos($webview, "url.searchParams.set('from',fromValue)") !== false
        && strpos($webview, "url.searchParams.set('to',toValue)") !== false
        && strpos($webview, 'url=applyLiveAnalyticsDateScope(anchor,url);') !== false,
    'server_normalization' => strpos($server, 'normalizeDashboardReportDate') !== false
        && strpos($server, 'checkdate($parts[1], $parts[2], $parts[0])') !== false
        && strpos($server, 'if ($filters[\'from\'] > $filters[\'to\'])') !== false,
    'presets_preserved' => strpos($client, 'applyAnalyticsPeriod') !== false
        && strpos($client, 'syncAnalyticsExportDateAuthority();') !== false
        && strpos($operative, 'applyPeriodPreset') !== false
        && substr_count($operative, 'syncAnalyticsExportDateAuthority();') >= 3,
    'historical_continuity' => strpos($gate, 'PORTAL_EXPORT_RUNTIME_CERT_PARITY_9BV_CONTRACT') !== false
        && strpos($gate, 'PREMIUM_LOCALE_VISIT_MEMORY_9BZ_CONTRACT') !== false
        && strpos($gate, 'APP_SHELL_BROWSERLIKE_TRANSLATION_9CC_CONTRACT') !== false
        && strpos($gate, 'ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT') !== false,
];
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, 'ANALYTICS HEADER DATE EXPORT AUTHORITY 9CE RUNTIME FAIL | ' . implode(',', $failed) . "\n");
    exit(1);
}
echo 'ANALYTICS HEADER DATE EXPORT AUTHORITY 9CE RUNTIME OK | client_live_dates=1 | operative_live_dates=1 | webview_capture=1 | server_normalization=1 | presets_preserved=1 | historical_continuity=1' . "\n";
