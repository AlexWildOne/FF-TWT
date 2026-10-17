<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ["Version: 2.2.602", "define('FIELDFLOW_VERSION', '2.2.602')"],
    'src/Front/ClientPortalShortcodeRenderer.php' => ['fieldflow-analytics-header-date-export-authority-v1','function analyticsHeaderDateScope()','function syncAnalyticsExportDateAuthority()',"addEventListener('input',syncAnalyticsExportDateAuthority)","addEventListener('pointerdown',syncAnalyticsExportDateAuthority","p.set('from',scope.from)","p.set('to',scope.to)"],
    'src/Shortcodes.php' => ['fieldflow-analytics-header-date-export-authority-v1','function analyticsHeaderDateScope()','function syncAnalyticsExportDateAuthority()',"addEventListener('input',syncAnalyticsExportDateAuthority)","addEventListener('pointerdown',syncAnalyticsExportDateAuthority","p.set('from',scope.from)","p.set('to',scope.to)"],
    'src/Mobile/WebViewBridge.php' => ['fieldflow-webview-dashboard-export-date-authority-v1','function applyLiveAnalyticsDateScope(anchor,url)',"url.searchParams.set('from',fromValue)","url.searchParams.set('to',toValue)"],
    'src/Admin/FormSubmissions.php' => ['fieldflow-analytics-header-date-export-authority-v1','normalizeDashboardReportDate','checkdate($parts[1], $parts[2], $parts[0])','if ($filters[\'from\'] > $filters[\'to\'])'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.601'","public const STABLE_RELEASE = '2.2.602'"],
    'src/Support/ReleaseGate.php' => ['ANALYTICS_HEADER_DATE_EXPORT_AUTHORITY_9CE_CONTRACT','checkAnalyticsHeaderDateExportAuthority9CEContract'],
    'src/Support/ReleasePackageValidator.php' => ['analytics_header_date_export_authority_9ce_ok','Analytics Header Date Export Authority 9CE'],
    'src/Admin/ReleaseManager.php' => ["packageResult['analytics_header_date_export_authority_9ce_ok']",'DATAS EXPORT OK'],
    'PHASE-9CE-ANALYTICS-HEADER-DATE-EXPORT-AUTHORITY.md' => ['fieldflow-analytics-header-date-export-authority-v1','Header dates override stale export URLs','Preset buttons remain supported','No database schema changes','No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
if ($errors) {
    fwrite(STDERR, "ANALYTICS HEADER DATE EXPORT AUTHORITY 9CE CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "ANALYTICS HEADER DATE EXPORT AUTHORITY 9CE CONTRACT OK successor=>=2.2.602\n";
