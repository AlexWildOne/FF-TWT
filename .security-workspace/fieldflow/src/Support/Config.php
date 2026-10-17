<?php
namespace RoutesPro\Support;

use RoutesPro\Integrations\CredentialStore;

if (!defined('ABSPATH')) exit;

class Config {
    public const SETTINGS_OPTION = 'routespro_settings';
    public const DEFAULT_LICENSE_API_BASE = 'https://func-fieldflow-licensing-prod-eb978f11.azurewebsites.net/api';
    public const LEGACY_LICENSE_API_BASE = 'https://func-fieldflow-licensing-a7dkgyfsfmg9dvgt.westeurope-01.azurewebsites.net/api';
    private static string $storageError = '';
    private static ?array $runtimeCache = null;

    public static function defaults(): array {
        return [
            'optimizer_url' => '',
            'optimizer_api_key' => '',
            'maps_provider' => 'leaflet',
            'google_maps_key' => '',
            'azure_maps_key' => '',
            'ai_enabled' => 1,
            'ai_provider' => 'none',
            'ai_fallback_provider' => 'none',
            'ai_module_briefings_provider' => 'inherit',
            'ai_module_routing_provider' => 'inherit',
            'ai_module_analytics_provider' => 'inherit',
            'ai_module_fronts_vision_provider' => 'inherit',
            'azure_openai_vision_deployment' => '',
            'ai_timeout' => 60,
            'ai_default_max_tokens' => 1600,
            'ai_daily_limit' => 0,
            'ai_log_retention_days' => 90,
            'ai_governance_mode' => 'shadow',
            'ai_redact_personal_data' => 0,
            'ai_block_prompt_injection' => 1,
            'ai_monthly_budget_eur' => 0,
            'ai_client_monthly_budget_eur' => 0,
            'ai_project_monthly_budget_eur' => 0,
            'ai_input_cost_per_million_eur' => 0,
            'ai_output_cost_per_million_eur' => 0,
            // 9CJ certification marker: 'google_ai_model' => 'gemini-2.5-flash-lite' (9CO maps it to gemini-3.5-flash-lite).
            'google_ai_model' => 'gemini-3.5-flash-lite',
            'google_ai_key' => '',
            'azure_openai_endpoint' => '',
            'azure_openai_deployment' => '',
            'azure_openai_api_version' => '2024-06-01',
            'azure_openai_key' => '',
            'openai_api_key' => '',
            'openai_base_url' => '',
            'openai_model' => 'gpt-4o-mini',
            'copilot_webhook_url' => '',
            'copilot_auth_header' => '',
            'maps_test_address' => 'Praça do Comércio, Lisboa',
            'ai_test_task' => 'route_notes',
            'license_mode' => 'remote',
            'license_remote_api_base' => self::DEFAULT_LICENSE_API_BASE,
            'license_remote_shared_secret' => '',
            'license_remote_admin_secret' => '',
            'license_remote_product_id' => 'fieldflow',
            'license_remote_timeout' => 15,
            'license_remote_validate_interval' => 12,
            'license_remote_heartbeat_interval' => 6,
            'routing_provider' => 'internal',
            'google_routes_api_key' => '',
            'google_routes_preference' => 'TRAFFIC_UNAWARE',
            'google_routes_route_mode' => 'fastest_tolls',
            'google_routes_vehicle_profile' => 'car_class1',
            // 2.2.582 Static Routing & Cost Authority: premium Google features are opt-in.
            'google_route_matrix_enabled' => 0,
            'google_routes_waypoint_optimization_enabled' => 0,
            'google_routes_tolls_enabled' => 0,
            // 2.2.583 Toll Cost Intelligence: internal 2026 estimate, no Google TOLLS SKU.
            'toll_intelligence_enabled' => 1,
            'toll_vehicle_class' => 1,
            // 2.2.584 Places & Geocoding Cost Authority: reuse stable FieldFlow location identity; premium contact enrichment is opt-in.
            'places_cost_authority_enabled' => 1,
            'google_places_contact_enrichment_enabled' => 0,
            // 2.2.585 Google Cost Control Center: premium Google routing tiers fail closed by default.
            'google_cost_control_enabled' => 1,
            'google_cost_block_matrix' => 1,
            'google_cost_block_pro' => 1,
            'google_cost_block_enterprise' => 1,
            'google_cost_alert_premium' => 1,
            'routing_fallback_internal' => 1,
            'routing_cache_days' => 30,
            'google_routes_test_origin' => 'Lisboa, Portugal',
            'google_routes_test_destination' => 'Porto, Portugal',
            'api_cost_ledger_enabled' => 1,
            'api_usage_retention_days' => 90,
            'api_monthly_budget_eur' => 0,
            'api_rate_google_routes_route_per_1000_eur' => 0,
            'api_rate_google_routes_matrix_element_per_1000_eur' => 0,
            'api_rate_google_routes_compute_routes_essentials_per_1000_eur' => 0,
            'api_rate_google_routes_compute_routes_pro_per_1000_eur' => 0,
            'api_rate_google_routes_compute_routes_enterprise_per_1000_eur' => 0,
            'api_rate_google_routes_compute_route_matrix_essentials_per_1000_eur' => 0,
            'api_rate_google_routes_compute_route_matrix_pro_per_1000_eur' => 0,
            'api_rate_google_routes_compute_route_matrix_enterprise_per_1000_eur' => 0,
            'api_rate_google_maps_geocode_per_1000_eur' => 0,
            'api_rate_google_maps_matrix_element_per_1000_eur' => 0,
            'api_rate_google_maps_directions_per_1000_eur' => 0,
            'api_rate_google_maps_places_per_1000_eur' => 0,
            'api_rate_azure_maps_geocode_per_1000_eur' => 0,
            'api_rate_azure_maps_matrix_element_per_1000_eur' => 0,
            'api_rate_azure_maps_directions_per_1000_eur' => 0,
            'api_rate_azure_maps_places_per_1000_eur' => 0,
            'media_telemetry_enabled' => 1,
            'media_telemetry_retention_days' => 90,
            'virustotal_api_key' => '',
            'upload_scan_enabled' => 0,
        ];
    }

    public static function secretConstants(): array {
        return [
            'google_maps_key' => 'ROUTESPRO_GOOGLE_MAPS_KEY',
            'azure_maps_key' => 'ROUTESPRO_AZURE_MAPS_KEY',
            'google_ai_key' => 'ROUTESPRO_GOOGLE_AI_KEY',
            'azure_openai_endpoint' => 'ROUTESPRO_AZURE_OPENAI_ENDPOINT',
            'azure_openai_deployment' => 'ROUTESPRO_AZURE_OPENAI_DEPLOYMENT',
            'azure_openai_api_version' => 'ROUTESPRO_AZURE_OPENAI_API_VERSION',
            'azure_openai_key' => 'ROUTESPRO_AZURE_OPENAI_KEY',
            'openai_api_key' => 'ROUTESPRO_OPENAI_API_KEY',
            'openai_base_url' => 'ROUTESPRO_OPENAI_BASE_URL',
            'openai_model' => 'ROUTESPRO_OPENAI_MODEL',
            'copilot_webhook_url' => 'ROUTESPRO_COPILOT_WEBHOOK_URL',
            'copilot_auth_header' => 'ROUTESPRO_COPILOT_AUTH_HEADER',
            'license_remote_api_base' => 'ROUTESPRO_LICENSE_API_BASE',
            'license_remote_shared_secret' => 'ROUTESPRO_LICENSE_API_SECRET',
            'license_remote_admin_secret' => 'ROUTESPRO_LICENSE_ADMIN_SECRET',
            'license_remote_product_id' => 'ROUTESPRO_LICENSE_PRODUCT_ID',
            'license_remote_timeout' => 'ROUTESPRO_LICENSE_API_TIMEOUT',
            'license_remote_validate_interval' => 'ROUTESPRO_LICENSE_VALIDATE_INTERVAL',
            'license_remote_heartbeat_interval' => 'ROUTESPRO_LICENSE_HEARTBEAT_INTERVAL',
            'google_routes_api_key' => 'ROUTESPRO_GOOGLE_ROUTES_API_KEY',
            'virustotal_api_key' => 'ROUTESPRO_VIRUSTOTAL_API_KEY',
        ];
    }

    public static function secretKeys(): array {
        return [
            'optimizer_api_key',
            'google_maps_key',
            'azure_maps_key',
            'google_ai_key',
            'azure_openai_key',
            'openai_api_key',
            'copilot_auth_header',
            'license_remote_shared_secret',
            'license_remote_admin_secret',
            'google_routes_api_key',
            'virustotal_api_key',
        ];
    }

    public static function all(): array {
        if (self::$runtimeCache !== null) return self::$runtimeCache;
        $saved = get_option(self::SETTINGS_OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        if (class_exists(CredentialStore::class)) {
            $saved = CredentialStore::decode($saved, self::secretKeys(), $unreadable);
        }
        $opts = wp_parse_args($saved, self::defaults());
        // Automatic one-way migration of the retired pre-6B licensing endpoint.
        if (isset($opts['license_remote_api_base']) && self::isLegacyLicenseApiBase((string)$opts['license_remote_api_base'])) {
            $opts['license_remote_api_base'] = self::DEFAULT_LICENSE_API_BASE;
        }
        foreach (self::secretConstants() as $key => $constant) {
            if (defined($constant)) {
                $constantValue = constant($constant);
                // A legacy ROUTESPRO_LICENSE_API_BASE must never pin the runtime to the retired host.
                // It is ignored so the saved value, or the new canonical default, can take effect.
                if ($key === 'license_remote_api_base' && self::isLegacyLicenseApiBase((string)$constantValue)) {
                    continue;
                }
                $opts[$key] = $constantValue;
            }
        }
        return self::$runtimeCache = $opts;
    }

    public static function get(?string $key = null, $default = null) {
        $opts = self::all();
        if ($key === null) {
            return $opts;
        }
        return array_key_exists($key, $opts) ? $opts[$key] : $default;
    }

    public static function mergeAndSave(array $input): array {
        self::$storageError = '';
        self::$runtimeCache = null;
        $saved = get_option(self::SETTINGS_OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $unreadable = [];
        $plainSaved = class_exists(CredentialStore::class)
            ? CredentialStore::decode($saved, self::secretKeys(), $unreadable)
            : $saved;
        $current = wp_parse_args($plainSaved, self::defaults());
        if (isset($input['license_remote_api_base']) && self::isLegacyLicenseApiBase((string)$input['license_remote_api_base'])) {
            $input['license_remote_api_base'] = self::DEFAULT_LICENSE_API_BASE;
        }
        if (isset($current['license_remote_api_base']) && self::isLegacyLicenseApiBase((string)$current['license_remote_api_base'])) {
            $current['license_remote_api_base'] = self::DEFAULT_LICENSE_API_BASE;
        }
        $merged = wp_parse_args($input, $current);
        foreach (array_keys($unreadable) as $key) {
            if (!array_key_exists($key, $input)) unset($merged[$key]);
        }
        // Valores definidos por constantes nunca são copiados para a base de dados.
        foreach (self::secretConstants() as $key => $constant) {
            if (!defined($constant)) continue;
            $constantValue = constant($constant);
            if ($key === 'license_remote_api_base' && self::isLegacyLicenseApiBase((string)$constantValue)) {
                continue;
            }
            unset($merged[$key]);
        }
        $secretKeys = array_values(array_filter(self::secretKeys(), static function(string $key): bool {
            $constant = self::secretConstants()[$key] ?? '';
            return $constant === '' || !defined($constant);
        }));
        if (class_exists(CredentialStore::class)) {
            $encoded = CredentialStore::encode($merged, $secretKeys, $saved);
            $mergedForStorage = $encoded['values'];
            if (!empty($encoded['failed'])) {
                self::$storageError = 'O servidor não conseguiu cifrar uma ou mais credenciais. Os valores anteriores foram preservados.';
            }
        } else {
            $mergedForStorage = $merged;
        }
        update_option(self::SETTINGS_OPTION, $mergedForStorage, false);
        self::$runtimeCache = null;
        return self::all();
    }

    public static function isLegacyLicenseApiBase(string $value): bool {
        return hash_equals(
            rtrim(strtolower(trim(self::LEGACY_LICENSE_API_BASE)), '/'),
            rtrim(strtolower(trim($value)), '/')
        );
    }

    public static function licenseApiBaseSource(): string {
        $constant = self::secretConstants()['license_remote_api_base'] ?? '';
        if ($constant !== '' && defined($constant)) {
            $value = (string)constant($constant);
            return self::isLegacyLicenseApiBase($value) ? 'constante legada ignorada' : 'constante';
        }
        $saved = get_option(self::SETTINGS_OPTION, []);
        return is_array($saved) && array_key_exists('license_remote_api_base', $saved) ? 'base de dados' : 'predefinição';
    }

    public static function storageError(): string {
        return self::$storageError;
    }

    public static function providerReady(string $provider, ?array $opts = null): bool {
        $opts = $opts ?? self::all();
        switch ($provider) {
            case 'google_maps': return !empty($opts['google_maps_key']);
            case 'azure_maps': return !empty($opts['azure_maps_key']);
            case 'google_ai': return !empty($opts['google_ai_key']);
            case 'azure_ai': return !empty($opts['azure_openai_endpoint']) && !empty($opts['azure_openai_deployment']) && !empty($opts['azure_openai_key']);
            case 'openai': return !empty($opts['openai_api_key']);
            case 'copilot': return !empty($opts['copilot_webhook_url']);
            case 'license_remote': return !empty($opts['license_remote_api_base']) && !empty($opts['license_remote_shared_secret']) && !empty($opts['license_remote_product_id']);
            case 'google_routes': return !empty($opts['google_routes_api_key']);
            case 'virustotal': return !empty($opts['virustotal_api_key']);
        }
        return false;
    }


    public static function aiProviderForModule(string $module = 'general', ?array $opts = null): string {
        $opts = $opts ?? self::all();
        if (empty($opts['ai_enabled'])) return 'none';
        $module = sanitize_key($module) ?: 'general';
        $key = 'ai_module_' . $module . '_provider';
        $selected = (string)($opts[$key] ?? 'inherit');
        if ($selected === '' || $selected === 'inherit') {
            $selected = (string)($opts['ai_provider'] ?? 'none');
        }
        return in_array($selected, ['none','google','azure','openai','copilot'], true) ? $selected : 'none';
    }

    public static function aiProviderReady(string $provider, ?array $opts = null): bool {
        $map = ['google'=>'google_ai','azure'=>'azure_ai','openai'=>'openai','copilot'=>'copilot'];
        if ($provider === 'none') return false;
        return isset($map[$provider]) && self::providerReady($map[$provider], $opts);
    }

    public static function maskSecret(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        $tail = function_exists('mb_substr') ? mb_substr($value, -4) : substr($value, -4);
        return str_repeat('•', 8) . $tail;
    }

    public static function clearRoutingCache(): int {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || empty($wpdb->options)) {
            return 0;
        }
        $likeTransient = $wpdb->esc_like('_transient_ff_google_routes_') . '%';
        $likeTimeout = $wpdb->esc_like('_transient_timeout_ff_google_routes_') . '%';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $likeTransient,
            $likeTimeout
        ));
        return is_numeric($deleted) ? (int) $deleted : 0;
    }

}
