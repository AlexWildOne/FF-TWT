<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ["Version: 2.2.601", "define('FIELDFLOW_VERSION', '2.2.601')"],
    'frontend/src/react-front/locale-switcher.ts' => ['fieldflow-adaptive-locale-dock-v1',"window.addEventListener('scroll'","document.addEventListener('scroll'","switcherClasses.push('is-docked')","switcherClasses.push('is-scrolling')",'setDocked(false)','setOpen(true)'],
    'assets/fieldflow-i18n.css' => ['Adaptive Locale Dock','.ff-language-switcher.is-docked:not(.is-open):not(.is-scrolling)','.ff-language-switcher.is-scrolling:not(.is-open)','safe-area-inset-right','prefers-reduced-motion:reduce'],
    'assets/fieldflow-i18n.js' => ['fieldflow-adaptive-locale-dock-v1','wireLegacyAdaptiveDock',"wrap.classList.add('is-docked','is-scrolling')",'adaptiveDockContract'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.600'","public const STABLE_RELEASE = '2.2.601'"],
    'src/Support/ReleaseGate.php' => ['ADAPTIVE_LOCALE_DOCK_9CD_CONTRACT','checkAdaptiveLocaleDock9CDContract'],
    'src/Support/ReleasePackageValidator.php' => ['adaptive_locale_dock_9cd_ok','Adaptive Locale Dock Continuity 9CD'],
    'src/Admin/ReleaseManager.php' => ["packageResult['adaptive_locale_dock_9cd_ok']",'AUTO-HIDE PREMIUM OK'],
    'PHASE-9CD-ADAPTIVE-LOCALE-DOCK-CONTINUITY.md' => ['fieldflow-adaptive-locale-dock-v1','No database schema changes','No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
$react = $root . '/assets/fieldflow-react-front-2.2.601.js';
if (is_readable($react)) {
    $raw = (string)file_get_contents($react);
    foreach (['FieldFlow 2.2.601','fieldflow-adaptive-locale-dock-v1',"switcherClasses.push('is-docked')","switcherClasses.push('is-scrolling')"] as $marker) {
        if (strpos($raw, $marker) === false) $errors[] = 'compiled:' . $marker;
    }
} else {
    $errors[] = 'assets/fieldflow-react-front-2.2.601.js:file';
}
if ($errors) {
    fwrite(STDERR, "ADAPTIVE LOCALE DOCK 9CD CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "ADAPTIVE LOCALE DOCK 9CD CONTRACT OK successor=>=2.2.601\n";
