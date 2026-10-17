<?php
/** Isolated source-level regression test, no WordPress, network, or writes. */
$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$read = static function (string $relative) use ($root): string {
    $path = $root . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) throw new RuntimeException('9D5 missing ' . $relative);
    return (string)file_get_contents($path);
};
$check = static function (bool $condition, string $why): void {
    if (!$condition) {fwrite(STDERR, '9D5 failed: ' . $why . PHP_EOL); exit(1);}
};
$php = $read('fieldflow.php');
$check((bool)preg_match("/define\('FIELDFLOW_VERSION', '([0-9.]+)'\)/", $php, $match) && version_compare($match[1], '2.2.630', '>='), 'version identity successor');
$css = $read('assets/fieldflow-visual-consolidation-9d5.css');
$js = $read('assets/fieldflow-visual-consolidation-9d5.js');
$enqueue = $read('src/Front/ResponsiveAssets.php');
$client = $read('src/Front/ClientPortalShortcodeRenderer.php');
$app = $read('src/Front/AppShortcodeRenderer.php');
foreach (['#routespro-client-portal','#routespro-app','ffv-visual-9d5','ffv-dock-hidden','ffv-filters-open','--ffv-dock-height','safe-area-inset-bottom'] as $marker) {
    $check(str_contains($css,$marker),'scoped css ' . $marker);
}
foreach (['aria-controls','aria-expanded','setupFilters(root)','setupDock(root)',"'#rpcp-from'","'#rpcp-to'",'ff-keyboard-open'] as $marker) {
    $check(str_contains($js,$marker), 'progressive JS ' . $marker);
}
$check(!preg_match('/\b(?:fetch|XMLHttpRequest|localStorage|indexedDB)\s*\(/', $js), 'no API/storage in visual layer');
$check(str_contains($enqueue, "FIELDFLOW_VISUAL_CONSOLIDATION_9D5"), 'kill switch');
$check(str_contains($enqueue,'fieldflow-visual-consolidation-9d5.css'), 'scoped style enqueued');
$check(str_contains($enqueue,'fieldflow-visual-consolidation-9d5.js'), 'scoped script enqueued');
$check(str_contains($enqueue,'fieldflow-offline-runtime-'.($match[1]??'2.2.630').'.js'), 'runtime version successor');
$check(str_contains($app,'data-fieldflow-portal="operative"') && str_contains($app,'ffv-visual-9d5'), 'operative scoped root');
$check(str_contains($client,'data-fieldflow-portal="client"') && str_contains($client,'ffv-visual-9d5'), 'client scoped root');
foreach (['rpcp-from','rpcp-to','rpcp-refresh','rpcp-owner','rpcp-location-search','rpcp-export-pdf','rpcp-reports-panel'] as $id) {
    $check(str_contains($client,$id), 'client authority ' . $id);
}
foreach (['lastReportVisitStorageKey', 'data-panel="route"','data-panel="report"'] as $marker) {
    $check(str_contains($app,$marker),'operative continuity ' . $marker);
}
$check(str_contains($read('src/Support/LicenseManager.php'),'get(?string $key = null, $default = null)'), 'nullable PHP 8.4');
$check(str_contains($read('src/Cloud/Phase6StableRelease.php'), "public const SOURCE_RELEASE = '2.2.629';") || str_contains($read('src/Cloud/Phase6StableRelease.php'), "public const SOURCE_RELEASE = '2.2.630';") || str_contains($read('src/Cloud/Phase6StableRelease.php'), "public const SOURCE_RELEASE = '2.2.631';"), 'certified predecessor');
$check(str_contains($read('src/Cloud/Phase6StableRelease.php'), "public const STABLE_RELEASE = '2.2.630';") || str_contains($read('src/Cloud/Phase6StableRelease.php'), "public const STABLE_RELEASE = '2.2.631';") || str_contains($read('src/Cloud/Phase6StableRelease.php'), "public const STABLE_RELEASE = '2.2.632';"), 'candidate successor');
echo "VISUAL EXPERIENCE CONSOLIDATION 9D5 PHP PASS | scoped brand | safe dock | filters unchanged | PHP8.4\n";
