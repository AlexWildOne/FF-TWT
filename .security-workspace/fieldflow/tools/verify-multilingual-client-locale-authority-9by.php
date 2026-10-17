<?php
$root = dirname(__DIR__);
$requirements = [
    'fieldflow.php' => ["FIELDFLOW_VERSION', '2.2.596"],
    'src/Localization/LanguageService.php' => [
        'fieldflow-multilingual-client-locale-authority-v1',
        "public const META_KEY = 'i18n'",
        "'pt-PT'", "'en-GB'", "'es-ES'", "'fr-FR'",
        "self::USER_META . '_client_'",
        'allow_user_switch',
        'fieldflow-i18n.js',
        'fieldflow-i18n.css',
        'ResponsiveAssets::shouldLoadForRequest()',
    ],
    'src/Localization/LanguageController.php' => [
        "register_rest_route('fieldflow/v1', '/language'",
        'persistPreference',
        'fieldflow_i18n_scope_denied',
        'fieldflow_i18n_locale_disabled',
    ],
    'src/Admin/Clients.php' => [
        'fieldflow_default_locale',
        'fieldflow_enabled_locales',
        'fieldflow_allow_locale_switch',
        'LanguageService::sanitiseClientI18n',
    ],
    'src/Architecture/RuntimeRegistry.php' => ['LanguageService::registerHooks'],
    'src/Architecture/RestRouteRegistry.php' => ['LanguageController'],
    'assets/fieldflow-i18n.js' => [
        'fieldflow-multilingual-client-locale-authority-v1',
        'MutationObserver',
        'FieldFlowI18n',
        'fieldflow:locale-changed',
        'data-fieldflow-i18n-ignore',
        'fieldflow:i18n:',
    ],
    'assets/fieldflow-i18n.css' => ['.ff-language-switcher', '.ff-language-trigger', '.ff-language-option'],
    'assets/fieldflow-offline-runtime.js' => ['window.FieldFlowI18n', "const RUNTIME_BUILD='2.2.596'"],
    'src/Cloud/Phase6StableRelease.php' => [
        "public const SOURCE_RELEASE = '2.2.595'",
        "public const STABLE_RELEASE = '2.2.596'",
        "// public const SOURCE_RELEASE = '2.2.594'",
        "// public const STABLE_RELEASE = '2.2.595'",
    ],
    'src/Support/ReleaseGate.php' => [
        'MULTILINGUAL_CLIENT_LOCALE_AUTHORITY_9BY_CONTRACT',
        'checkMultilingualClientLocaleAuthority9BYContract',
    ],
    'src/Support/ReleasePackageValidator.php' => [
        'fieldflow-multilingual-client-locale-authority-v1',
        'multilingual_client_locale_authority_9by_ok',
    ],
    'PHASE-9BY-MULTILINGUAL-CLIENT-LOCALE-AUTHORITY.md' => [
        'fieldflow-multilingual-client-locale-authority-v1',
        'No database schema changes',
        'No FlutterFlow source modification is required',
    ],
];
$missing = [];
foreach ($requirements as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    foreach ($markers as $marker) {
        if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
    }
}
if ($missing) {
    fwrite(STDERR, "MULTILINGUAL CLIENT LOCALE AUTHORITY 9BY CONTRACT FAILED\n" . implode("\n", $missing) . "\n");
    exit(1);
}
echo "MULTILINGUAL CLIENT LOCALE AUTHORITY 9BY CONTRACT OK successor=>=2.2.596\n";
