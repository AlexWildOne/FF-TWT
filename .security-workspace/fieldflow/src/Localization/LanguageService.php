<?php
namespace RoutesPro\Localization;

use RoutesPro\Front\ResponsiveAssets;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow multilingual runtime authority.
 *
 * Locale settings live inside the existing client meta_json column, so this
 * capability does not require DDL and remains safe for runtime-only upgrades.
 */
final class LanguageService {
    public const CONTRACT = 'fieldflow-multilingual-client-locale-authority-v1';
    public const PREMIUM_UI_CONTRACT = 'fieldflow-react-locale-switcher-v1';
    public const COVERAGE_CONTRACT = 'fieldflow-i18n-operational-coverage-v2';
    public const SCOPE_CONTINUITY_CONTRACT = 'fieldflow-locale-brand-scope-continuity-v1';
    public const AUTO_TRANSLATION_CONTRACT = 'fieldflow-browserlike-auto-translation-v1';
    public const APP_SHELL_CONTINUITY_CONTRACT = 'fieldflow-app-shell-locale-runtime-v1';
    public const META_KEY = 'i18n';
    public const USER_META = 'fieldflow_locale';
    private const SCRIPT_HANDLE = 'fieldflow-i18n-runtime';
    private const STYLE_HANDLE = 'fieldflow-i18n-runtime';

    public static function registerHooks(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueueFront'], 80);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdmin'], 80);
    }

    public static function supportedLocales(): array {
        return [
            'pt-PT' => ['label'=>'Português', 'flag'=>'🇵🇹', 'html_lang'=>'pt-PT'],
            'en-GB' => ['label'=>'English', 'flag'=>'🇬🇧', 'html_lang'=>'en-GB'],
            'es-ES' => ['label'=>'Español', 'flag'=>'🇪🇸', 'html_lang'=>'es-ES'],
            'fr-FR' => ['label'=>'Français', 'flag'=>'🇫🇷', 'html_lang'=>'fr-FR'],
            'zh-CN' => ['label'=>'简体中文', 'flag'=>'🇨🇳', 'html_lang'=>'zh-CN'],
        ];
    }

    public static function normaliseLocale(string $locale): string {
        $locale = trim(str_replace('_', '-', $locale));
        $aliases = [
            'pt'=>'pt-PT', 'pt-pt'=>'pt-PT',
            'en'=>'en-GB', 'en-gb'=>'en-GB', 'en-us'=>'en-GB',
            'es'=>'es-ES', 'es-es'=>'es-ES',
            'fr'=>'fr-FR', 'fr-fr'=>'fr-FR',
            'zh'=>'zh-CN', 'zh-cn'=>'zh-CN', 'zh-hans'=>'zh-CN', 'zh-hans-cn'=>'zh-CN',
        ];
        $key = strtolower($locale);
        if (isset($aliases[$key])) return $aliases[$key];
        foreach (array_keys(self::supportedLocales()) as $supported) {
            if (strcasecmp($supported, $locale) === 0) return $supported;
        }
        return '';
    }

    public static function clientConfig(int $clientId): array {
        $supported = array_keys(self::supportedLocales());
        $config = [
            'default_locale'=>'pt-PT',
            'enabled_locales'=>$supported,
            'allow_user_switch'=>true,
            'overrides'=>[],
        ];
        if ($clientId <= 0) return $config;

        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            'SELECT meta_json FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d LIMIT 1',
            $clientId
        ));
        $meta = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        if (!is_array($meta)) $meta = [];
        $saved = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        $default = self::normaliseLocale((string)($saved['default_locale'] ?? 'pt-PT')) ?: 'pt-PT';
        $enabled = [];
        foreach ((array)($saved['enabled_locales'] ?? $supported) as $locale) {
            $normal = self::normaliseLocale((string)$locale);
            if ($normal !== '') $enabled[$normal] = $normal;
        }
        if (!$enabled) foreach ($supported as $locale) $enabled[$locale] = $locale;
        if (!isset($enabled[$default])) $enabled[$default] = $default;

        $config['default_locale'] = $default;
        $config['enabled_locales'] = array_values($enabled);
        $config['allow_user_switch'] = !array_key_exists('allow_user_switch', $saved) || !empty($saved['allow_user_switch']);
        $config['overrides'] = is_array($saved['overrides'] ?? null) ? $saved['overrides'] : [];
        return $config;
    }

    public static function sanitiseClientI18n(array $input): array {
        $supported = array_keys(self::supportedLocales());
        $default = self::normaliseLocale((string)($input['default_locale'] ?? 'pt-PT')) ?: 'pt-PT';
        $enabled = [];
        foreach ((array)($input['enabled_locales'] ?? []) as $locale) {
            $normal = self::normaliseLocale((string)$locale);
            if ($normal !== '') $enabled[$normal] = $normal;
        }
        if (!$enabled) $enabled[$default] = $default;
        if (!isset($enabled[$default])) $enabled[$default] = $default;
        $ordered = [];
        foreach ($supported as $locale) if (isset($enabled[$locale])) $ordered[] = $locale;
        return [
            'default_locale'=>$default,
            'enabled_locales'=>$ordered,
            'allow_user_switch'=>!empty($input['allow_user_switch']),
            'overrides'=>is_array($input['overrides'] ?? null) ? $input['overrides'] : [],
        ];
    }

    public static function resolveClientId(): int {
        $candidates = [
            $_REQUEST['client_id'] ?? 0,
            $_REQUEST['routespro_client_id'] ?? 0,
        ];
        foreach ($candidates as $candidate) {
            $clientId = absint($candidate);
            if ($clientId > 0 && (Permissions::is_manager() || Permissions::is_allowed_client($clientId))) return $clientId;
        }
        if (is_user_logged_in()) {
            [$clientId] = Permissions::resolve_front_context(0, 0);
            return absint($clientId);
        }
        return 0;
    }

    public static function resolveLocale(int $clientId = 0, ?int $userId = null): string {
        $config = self::clientConfig($clientId);
        $uid = $userId ?: get_current_user_id();
        if ($clientId > 0 && empty($config['allow_user_switch']) && !Permissions::is_manager($uid)) {
            return (string)$config['default_locale'];
        }
        $enabled = array_fill_keys((array)$config['enabled_locales'], true);
        $requested = isset($_REQUEST['ff_lang']) ? self::normaliseLocale(sanitize_text_field((string)$_REQUEST['ff_lang'])) : '';
        if ($requested !== '' && isset($enabled[$requested])) return $requested;

        if ($uid > 0) {
            if ($clientId > 0) {
                $clientPreference = self::normaliseLocale((string)get_user_meta($uid, self::USER_META . '_client_' . $clientId, true));
                if ($clientPreference !== '' && isset($enabled[$clientPreference])) return $clientPreference;
            }
            $globalPreference = self::normaliseLocale((string)get_user_meta($uid, self::USER_META, true));
            if ($globalPreference !== '' && isset($enabled[$globalPreference])) return $globalPreference;
        }
        return (string)$config['default_locale'];
    }

    public static function persistPreference(int $userId, string $locale, int $clientId = 0): bool {
        $locale = self::normaliseLocale($locale);
        if ($userId <= 0 || $locale === '') return false;
        $config = self::clientConfig($clientId);
        if (!in_array($locale, (array)$config['enabled_locales'], true)) return false;
        if ($clientId > 0) update_user_meta($userId, self::USER_META . '_client_' . $clientId, $locale);
        update_user_meta($userId, self::USER_META, $locale);
        return true;
    }

    public static function runtimeConfig(string $context = 'front', int $clientId = 0): array {
        $clientId = $context === 'front' ? ($clientId ?: self::resolveClientId()) : 0;
        $client = self::clientConfig($clientId);
        $enabledCodes = $context === 'admin' ? array_keys(self::supportedLocales()) : (array)$client['enabled_locales'];
        $supported = self::supportedLocales();
        $enabled = [];
        foreach ($enabledCodes as $code) {
            if (!isset($supported[$code])) continue;
            $enabled[] = ['code'=>$code] + $supported[$code];
        }
        $locale = self::resolveLocale($clientId);
        if ($context === 'admin') {
            $adminPreference = self::normaliseLocale((string)get_user_meta(get_current_user_id(), self::USER_META, true));
            if ($adminPreference !== '') $locale = $adminPreference;
        }
        $overrides = [];
        if ($context === 'front' && is_array($client['overrides'][$locale] ?? null)) $overrides = $client['overrides'][$locale];

        $scopeClientIds = [];
        if ($context === 'front' && is_user_logged_in()) {
            $scope = Permissions::get_scope();
            $scopeClientIds = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
            if (Permissions::is_manager() && $clientId > 0 && !in_array($clientId, $scopeClientIds, true)) $scopeClientIds[] = $clientId;
            sort($scopeClientIds);
        }

        return [
            'contract'=>self::CONTRACT,
            'premiumUiContract'=>self::PREMIUM_UI_CONTRACT,
            'coverageContract'=>self::COVERAGE_CONTRACT,
            'scopeContract'=>self::SCOPE_CONTINUITY_CONTRACT,
            'autoTranslationContract'=>self::AUTO_TRANSLATION_CONTRACT,
            'appShellContract'=>self::APP_SHELL_CONTINUITY_CONTRACT,
            'version'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            'context'=>$context,
            'clientId'=>$clientId,
            'scopeClientIds'=>$scopeClientIds,
            'locale'=>$locale,
            'defaultLocale'=>(string)$client['default_locale'],
            'enabledLocales'=>$enabled,
            'allowUserSwitch'=>$context === 'admin' ? true : !empty($client['allow_user_switch']),
            'preferenceEndpoint'=>rest_url('fieldflow/v1/language'),
            'translationEndpoint'=>rest_url('fieldflow/v1/language/translate'),
            'translationProviderAvailable'=>class_exists('\RoutesPro\Localization\TranslationService') ? TranslationService::providerAvailable() : false,
            'translateProtectedNames'=>$locale === 'zh-CN',
            'nonce'=>wp_create_nonce('wp_rest'),
            'native'=>class_exists('\\RoutesPro\\Mobile\\WebViewBridge') && \RoutesPro\Mobile\WebViewBridge::is_native_context(),
            'overrides'=>$overrides,
        ];
    }

    public static function enqueueFront(): void {
        if (!class_exists(ResponsiveAssets::class) || !ResponsiveAssets::shouldLoadForRequest()) return;
        self::enqueueAssets(self::runtimeConfig('front'));
    }

    public static function enqueueAdmin(string $hookSuffix = ''): void {
        if (!is_admin()) return;
        $page = isset($_GET['page']) ? sanitize_key((string)$_GET['page']) : '';
        if ($page === '' || (strpos($page, 'routespro') !== 0 && strpos($page, 'fieldflow') !== 0)) return;
        self::enqueueAssets(self::runtimeConfig('admin'));
    }

    private static function enqueueAssets(array $config): void {
        wp_enqueue_style(self::STYLE_HANDLE, ROUTESPRO_URL . 'assets/fieldflow-i18n.css', [], ROUTESPRO_VERSION);
        wp_enqueue_script(self::SCRIPT_HANDLE, ROUTESPRO_URL . 'assets/fieldflow-i18n.js', [], ROUTESPRO_VERSION, true);
        wp_localize_script(self::SCRIPT_HANDLE, 'FieldFlowI18nConfig', $config);
    }
}
