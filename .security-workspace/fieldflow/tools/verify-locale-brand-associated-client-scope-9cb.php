<?php
$root = dirname(__DIR__);
$requirements = [
    'fieldflow.php' => ['FIELDFLOW_VERSION'],
    'src/Localization/LanguageService.php' => ['SCOPE_CONTINUITY_CONTRACT', 'fieldflow-locale-brand-scope-continuity-v1', 'scopeClientIds'],
    'src/Localization/LanguageController.php' => ["register_rest_route('fieldflow/v1', '/language'", 'Permissions::is_allowed_client'],
    'assets/fieldflow-i18n.js' => ['fieldflow-locale-brand-scope-continuity-v1', 'setClientContext', "document.addEventListener('routespro:scope-change'", 'fieldflow:i18n-context-changed', 'applyLegacyBrand'],
    'assets/fieldflow-i18n.css' => ['.ff-language-trigger:hover', 'var(--ff-lang-primary)', 'var(--ff-lang-accent)', '.ff-language-code-badge'],
    'frontend/src/react-front/locale-switcher.ts' => ['fieldflow-locale-brand-scope-continuity-v1', 'fieldFlowLocaleBrandStyle', "document.addEventListener('routespro:scope-change'", "window.addEventListener('fieldflow:i18n-context-changed'", 'MutationObserver'],
    'frontend/src/react-front/bootstrap.ts' => ["window.addEventListener('fieldflow:i18n-context-changed'", 'fieldFlowReactLocaleSwitcherMount'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.598'", "public const STABLE_RELEASE = '2.2.599'", "// public const SOURCE_RELEASE = '2.2.597'", "// public const STABLE_RELEASE = '2.2.598'"],
    'src/Support/ReleaseGate.php' => ['LOCALE_BRAND_ASSOCIATED_CLIENT_SCOPE_9CB_CONTRACT', 'checkLocaleBrandAssociatedClientScope9CBContract'],
    'src/Support/ReleasePackageValidator.php' => ['locale_brand_associated_client_scope_9cb_ok', 'Locale Brand & Associated Client Scope Continuity 9CB'],
    'src/Admin/ReleaseManager.php' => ["packageResult['locale_brand_associated_client_scope_9cb_ok']"],
    'PHASE-9CB-LOCALE-BRAND-ASSOCIATED-CLIENT-SCOPE-CONTINUITY.md' => ['fieldflow-locale-brand-scope-continuity-v1', '`SOURCE_RELEASE` is `2.2.598` and `STABLE_RELEASE` is `2.2.599`', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
$missing = [];
foreach ($requirements as $relative => $markers) {
    $raw = is_readable($root.'/'.$relative) ? (string)file_get_contents($root.'/'.$relative) : '';
    if ($raw === '') { $missing[] = $relative.':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative.':'.$marker;
}
$plugin = is_readable($root.'/fieldflow.php') ? (string)file_get_contents($root.'/fieldflow.php') : '';
if (!preg_match("/FIELDFLOW_VERSION',\\s*'([^']+)'/", $plugin, $m) || version_compare((string)$m[1], '2.2.599', '<')) $missing[] = 'fieldflow.php:successor-version';
$react = $root.'/assets/fieldflow-react-front-2.2.599.js';
$reactRaw = is_readable($react) ? (string)file_get_contents($react) : '';
foreach (['FieldFlow 2.2.599','fieldflow-locale-brand-scope-continuity-v1','fieldFlowLocaleBrandStyle','routespro:scope-change'] as $marker) if (strpos($reactRaw,$marker) === false) $missing[] = 'assets/fieldflow-react-front-2.2.599.js:'.$marker;
if ($missing) {
    fwrite(STDERR, "LOCALE BRAND & ASSOCIATED CLIENT SCOPE CONTINUITY 9CB CONTRACT FAILED\n".implode("\n",$missing)."\n");
    exit(1);
}
echo "LOCALE BRAND & ASSOCIATED CLIENT SCOPE CONTINUITY 9CB CONTRACT OK successor=>=2.2.599\n";
