<?php
$root = dirname(__DIR__);
$requirements = [
    'fieldflow.php' => ["FIELDFLOW_VERSION', '2.2.597"],
    'src/Localization/LanguageService.php' => ['PREMIUM_UI_CONTRACT', 'fieldflow-react-locale-switcher-v1', 'COVERAGE_CONTRACT', 'fieldflow-i18n-operational-coverage-v2'],
    'frontend/src/react-front/locale-switcher.ts' => ['fieldflow-react-locale-switcher-v1', 'ff-language-globe3d', 'runtime.setLocale', 'typescript-react-wp-element'],
    'frontend/tsconfig.react-front.json' => ['src/react-front/locale-switcher.ts'],
    'assets/fieldflow-i18n.js' => ['fieldflow-i18n-operational-coverage-v2', 'fieldflow:i18n-ready', 'data-fieldflow-i18n-user-content', 'contenteditable'],
    'assets/fieldflow-i18n.css' => ['.ff-language-globe3d', '.ff-language-menu-premium', 'prefers-reduced-motion'],
    'src/Forms/DailySubmissionService.php' => ['visit_memory_autofill_question_eligible', 'new_visit_autofill_prefill'],
    'src/Forms/Forms.php' => ['DailySubmissionService::new_visit_autofill_prefill', 'Histórico da última visita carregado.', 'Fotografias, ficheiros e Sugestão de encomenda nunca são reutilizados'],
    'src/Mobile/FormPayloadService.php' => ['DailySubmissionService::new_visit_autofill_prefill', "'mode' => 'prefill'"],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.596'", "public const STABLE_RELEASE = '2.2.597'", "// public const SOURCE_RELEASE = '2.2.595'", "// public const STABLE_RELEASE = '2.2.596'"],
    'src/Support/ReleaseGate.php' => ['PREMIUM_LOCALE_VISIT_MEMORY_9BZ_CONTRACT', 'checkPremiumLocaleVisitMemory9BZContract'],
    'src/Support/ReleasePackageValidator.php' => ['premium_locale_visit_memory_9bz_ok', 'Premium Locale & Visit Memory Continuity 9BZ'],
    'PHASE-9BZ-PREMIUM-LOCALE-VISIT-MEMORY-CONTINUITY.md' => ['fieldflow-premium-locale-visit-memory-continuity-v1', 'No FlutterFlow source modification is required', 'No database schema changes'],
];
$missing = [];
foreach ($requirements as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $missing[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
}
$react = $root . '/assets/fieldflow-react-front-2.2.597.js';
$reactRaw = is_readable($react) ? (string)file_get_contents($react) : '';
foreach (['fieldflow-react-locale-switcher-v1','typescript-react-wp-element','2.2.597'] as $marker) if (strpos($reactRaw,$marker) === false) $missing[] = 'assets/fieldflow-react-front-2.2.597.js:' . $marker;
$offline = $root . '/assets/fieldflow-offline-runtime-2.2.597.js';
$offlineRaw = is_readable($offline) ? (string)file_get_contents($offline) : '';
if (strpos($offlineRaw, "const RUNTIME_BUILD='2.2.597'") === false) $missing[] = 'offline:2.2.597';
if ($missing) {
    fwrite(STDERR, "PREMIUM LOCALE & VISIT MEMORY CONTINUITY 9BZ CONTRACT FAILED\n" . implode("\n", $missing) . "\n");
    exit(1);
}
echo "PREMIUM LOCALE & VISIT MEMORY CONTINUITY 9BZ CONTRACT OK successor=>=2.2.597\n";
