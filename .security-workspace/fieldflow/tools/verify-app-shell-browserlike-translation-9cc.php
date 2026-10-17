<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ["Version: 2.2.600", "define('FIELDFLOW_VERSION', '2.2.600')"],
    'src/Localization/LanguageService.php' => ['fieldflow-browserlike-auto-translation-v1','fieldflow-app-shell-locale-runtime-v1',"'zh-CN'","translationEndpoint'=>rest_url('fieldflow/v1/language/translate')"],
    'src/Localization/LanguageController.php' => ["register_rest_route('fieldflow/v1', '/language/translate'",'TranslationService::translateBatch','canUseClient'],
    'src/Localization/TranslationService.php' => ['fieldflow-browserlike-auto-translation-v1','translateBatch','store names, postal addresses and product names','Simplified Chinese'],
    'frontend/src/react-front/locale-switcher.ts' => ['element.createElement(fieldFlowReactLocaleSwitcherComponent, null)','Invalid hook call','runtime.ensureSwitcher','runtime.prepareTranslator'],
    'frontend/src/react-front/bootstrap.ts' => ["document.addEventListener('fieldflow:portal-ready'","window.addEventListener('pageshow'",'FieldFlowI18n?.reconcile'],
    'src/Front/AppShortcodeRenderer.php' => ['data-fieldflow-locale-host="1"','data-fieldflow-locale-shell="operative"'],
    'src/Front/ClientPortalShortcodeRenderer.php' => ['data-fieldflow-locale-host="1"','data-fieldflow-locale-shell="client"'],
    'assets/fieldflow-i18n.js' => ['fieldflow-browserlike-auto-translation-v1','fieldflow-app-shell-locale-runtime-v1','window.Translator','serverTranslateBatch','preserveElement',"locale==='zh-CN'",'fieldflow:portal-ready','ensureSwitcher'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.599'","public const STABLE_RELEASE = '2.2.600'"],
    'src/Support/ReleaseGate.php' => ['APP_SHELL_BROWSERLIKE_TRANSLATION_9CC_CONTRACT','checkAppShellBrowserlikeTranslation9CCContract'],
    'src/Support/ReleasePackageValidator.php' => ['app_shell_browserlike_translation_9cc_ok','App Shell & Browser-like Translation Continuity 9CC'],
    'src/Admin/ReleaseManager.php' => ["packageResult['app_shell_browserlike_translation_9cc_ok']",'APP + TRADUÇÃO TOTAL OK'],
    'PHASE-9CC-APP-SHELL-BROWSERLIKE-TRANSLATION-CONTINUITY.md' => ['fieldflow-app-shell-browserlike-translation-v1','No database schema changes','No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
$react = $root . '/assets/fieldflow-react-front-2.2.600.js';
if (is_readable($react)) {
    $raw = (string)file_get_contents($react);
    foreach (['FieldFlow 2.2.600','fieldflow-app-shell-locale-runtime-v1','fieldflow-browserlike-auto-translation-v1','fieldFlowReactLocaleSwitcherComponent','Invalid hook call'] as $marker) {
        if (strpos($raw, $marker) === false) $errors[] = 'compiled:' . $marker;
    }
} else {
    $errors[] = 'assets/fieldflow-react-front-2.2.600.js:file';
}
if ($errors) {
    fwrite(STDERR, "APP SHELL & BROWSER-LIKE TRANSLATION 9CC CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "APP SHELL & BROWSER-LIKE TRANSLATION 9CC CONTRACT OK successor=>=2.2.600\n";
