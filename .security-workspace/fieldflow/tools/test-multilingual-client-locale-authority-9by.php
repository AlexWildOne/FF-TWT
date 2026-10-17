<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$service = $read('src/Localization/LanguageService.php');
$controller = $read('src/Localization/LanguageController.php');
$clientAdmin = $read('src/Admin/Clients.php');
$runtime = $read('assets/fieldflow-i18n.js');
$offline = $read('assets/fieldflow-offline-runtime.js');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');

$checks = [
    'client_meta_authority' => strpos($service, "public const META_KEY = 'i18n'") !== false
        && strpos($clientAdmin, 'LanguageService::sanitiseClientI18n') !== false,
    'user_preference_scope' => strpos($service, "self::USER_META . '_client_'") !== false
        && strpos($controller, 'persistPreference') !== false,
    'front_selector' => strpos($runtime, 'ff-language-switcher') !== false
        && strpos($runtime, 'enabledLocales') !== false
        && strpos($runtime, 'allowUserSwitch') !== false,
    'dynamic_runtime_translation' => strpos($runtime, 'MutationObserver') !== false
        && strpos($runtime, 'translateTree') !== false
        && strpos($runtime, 'fieldflow:locale-changed') !== false,
    'native_webview_no_flutterflow' => strpos($service, 'ResponsiveAssets::shouldLoadForRequest()') !== false
        && strpos($service, 'WebViewBridge') !== false
        && strpos($offline, 'window.FieldFlowI18n') !== false,
    'data_translation_guard' => strpos($runtime, 'data-fieldflow-i18n-ignore') !== false
        && strpos($runtime, "['script','style','code','pre','textarea','noscript']") !== false,
    'gate_validator_parity' => strpos($gate, 'checkMultilingualClientLocaleAuthority9BYContract') !== false
        && strpos($validator, 'multilingual_client_locale_authority_9by_ok') !== false
        && strpos($phase6, "public const SOURCE_RELEASE = '2.2.595'") !== false
        && strpos($phase6, "public const STABLE_RELEASE = '2.2.596'") !== false,
];
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "MULTILINGUAL CLIENT LOCALE AUTHORITY 9BY RUNTIME FAILED | " . implode(',', $failed) . "\n");
    exit(1);
}
echo "MULTILINGUAL CLIENT LOCALE AUTHORITY 9BY RUNTIME OK | client_meta_authority=1 | user_preference_scope=1 | front_selector=1 | dynamic_runtime_translation=1 | native_webview_no_flutterflow=1 | data_translation_guard=1 | gate_validator_parity=1\n";
