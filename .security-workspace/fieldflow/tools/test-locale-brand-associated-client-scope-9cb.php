<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root.'/'.$relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$runtime = $read('assets/fieldflow-i18n.js');
$css = $read('assets/fieldflow-i18n.css');
$reactSource = $read('frontend/src/react-front/locale-switcher.ts');
$reactAsset = $read('assets/fieldflow-react-front-2.2.599.js');
$service = $read('src/Localization/LanguageService.php');
$controller = $read('src/Localization/LanguageController.php');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$forms = $read('src/Forms/Forms.php');
$daily = $read('src/Forms/DailySubmissionService.php');
$checks = [
    'brand_hover' => strpos($css,'.ff-language-trigger:hover') !== false
        && strpos($css,'var(--ff-lang-primary)') !== false
        && strpos($css,'var(--ff-lang-accent)') !== false
        && strpos($reactSource,'fieldFlowLocaleBrandStyle') !== false,
    'dynamic_client_scope' => strpos($runtime,'setClientContext') !== false
        && strpos($runtime,"document.addEventListener('routespro:scope-change'") !== false
        && strpos($runtime,'fieldflow:i18n-context-changed') !== false
        && strpos($service,'scopeClientIds') !== false
        && strpos($controller,'Permissions::is_allowed_client') !== false,
    'react_rebind' => strpos($reactSource,"window.addEventListener('fieldflow:i18n-context-changed'") !== false
        && strpos($reactSource,"document.addEventListener('routespro:scope-change'") !== false
        && strpos($reactSource,'if (!canSwitch) return null;') !== false
        && strpos($reactAsset,'fieldflow-locale-brand-scope-continuity-v1') !== false,
    'legacy_brand_fallback' => strpos($runtime,'applyLegacyBrand') !== false
        && strpos($runtime,"'--ff-lang-primary':'--ff-primary'") !== false,
    'historical_certification' => strpos($forms,'Frentes reutiliza o valor final anterior') !== false
        && strpos($daily,'new_visit_autofill_prefill') !== false,
    'successor_continuity' => strpos($phase6,"public const SOURCE_RELEASE = '2.2.598'") !== false
        && strpos($phase6,"public const STABLE_RELEASE = '2.2.599'") !== false
        && strpos($phase6,"// public const SOURCE_RELEASE = '2.2.597'") !== false
        && strpos($phase6,"// public const STABLE_RELEASE = '2.2.598'") !== false,
];
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR,"LOCALE BRAND & ASSOCIATED CLIENT SCOPE CONTINUITY 9CB RUNTIME FAILED | ".implode(',',$failed)."\n");
    exit(1);
}
echo "LOCALE BRAND & ASSOCIATED CLIENT SCOPE CONTINUITY 9CB RUNTIME OK | brand_hover=1 dynamic_client_scope=1 react_rebind=1 legacy_brand_fallback=1 historical_certification=1 successor_continuity=1\n";
