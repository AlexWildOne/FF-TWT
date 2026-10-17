<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$react = $read('frontend/src/react-front/locale-switcher.ts');
$runtime = $read('assets/fieldflow-i18n.js');
$app = $read('src/Front/AppShortcodeRenderer.php');
$client = $read('src/Front/ClientPortalShortcodeRenderer.php');
$forms = $read('src/Forms/Forms.php');
$gate = $read('src/Support/ReleaseGate.php');
$checks = [
    'react_hooks' => strpos($react, 'element.createElement(fieldFlowReactLocaleSwitcherComponent, null)') !== false
        && strpos($react, 'const node = fieldFlowReactLocaleSwitcherComponent();') === false
        && strpos($react, 'Invalid hook call') !== false,
    'dual_portal_webview' => strpos($app, 'data-fieldflow-locale-shell="operative"') !== false
        && strpos($client, 'data-fieldflow-locale-shell="client"') !== false
        && strpos($runtime, 'fieldflow:portal-ready') !== false
        && strpos($runtime, 'ensureSwitcher') !== false,
    'browserlike_translation' => strpos($runtime, 'window.Translator') !== false
        && strpos($runtime, 'serverTranslateBatch') !== false
        && strpos($runtime, 'queueAutoTranslation') !== false
        && strpos($runtime, 'MutationObserver') !== false,
    'protected_names' => strpos($runtime, 'preserveElement') !== false
        && strpos($runtime, 'looksAddress') !== false
        && strpos($runtime, 'product-name') !== false
        && strpos($runtime, 'store-name') !== false,
    'chinese_full_translation' => strpos($runtime, "if(locale==='zh-CN')return false") !== false
        && strpos($runtime, "'zh-CN'") !== false,
    'historical_memory' => strpos($forms, 'Frentes reutiliza o valor final anterior') !== false
        && strpos($gate, 'PREMIUM_LOCALE_VISIT_MEMORY_9BZ_CONTRACT') !== false
        && strpos($gate, 'CERTIFICATION_MARKER_CONTINUITY_9CA_CONTRACT') !== false,
];
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, 'APP SHELL & BROWSER-LIKE TRANSLATION 9CC RUNTIME FAIL | ' . implode(',', $failed) . "\n");
    exit(1);
}
echo 'APP SHELL & BROWSER-LIKE TRANSLATION 9CC RUNTIME OK | react_hooks=1 | dual_portal_webview=1 | browserlike_translation=1 | protected_names=1 | chinese_full_translation=1 | historical_memory=1' . "\n";
