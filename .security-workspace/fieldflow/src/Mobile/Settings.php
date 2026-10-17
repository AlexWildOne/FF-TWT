<?php
namespace RoutesPro\Mobile;

if (!defined('ABSPATH')) exit;

/**
 * Central source of truth for the native Android/iOS bridge.
 * Values are intentionally stored server-side so application behaviour can be
 * changed without publishing a new binary, while compile-time capabilities
 * remain in Flutter/Android/iOS.
 */
class Settings {
    private static $cache = null;

    const OPTION = 'fieldflow_mobile_control_center';
    const BLOCKED_DEVICES_OPTION = 'fieldflow_mobile_blocked_devices';

    public static function defaults(): array {
        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $legacy_min = (string) get_option('fieldflow_mobile_minimum_app_version', '1.0.0');
        $legacy_latest = (string) get_option('fieldflow_mobile_latest_app_version', '1.0.0');
        $legacy_maintenance = (bool) get_option('fieldflow_mobile_maintenance_mode', false);

        return [
            'general' => [
                'enabled' => 1,
                'app_name' => 'FieldFlow',
                'display_name' => 'FieldFlow Mobile',
                'environment' => 'production',
                'support_email' => (string) get_option('admin_email', ''),
                'support_url' => '',
                'privacy_url' => '',
                'terms_url' => '',
            ],
            'platforms' => [
                'android' => [
                    'enabled' => 1,
                    'minimum_version' => $legacy_min,
                    'latest_version' => $legacy_latest,
                    'build_number' => '1',
                    'store_url' => '',
                    'force_update' => 0,
                    'package_id' => 'com.thewildtheory.fieldflow',
                    'release_notes' => '',
                ],
                'ios' => [
                    'enabled' => 1,
                    'minimum_version' => '1.0.0',
                    'latest_version' => '1.0.0',
                    'build_number' => '1',
                    'store_url' => '',
                    'force_update' => 0,
                    'package_id' => 'com.thewildtheory.fieldflow',
                    'release_notes' => '',
                ],
            ],
            'maintenance' => [
                'enabled' => $legacy_maintenance ? 1 : 0,
                'android' => 1,
                'ios' => 1,
                'web' => 1,
                'title' => 'Estamos em manutenção',
                'message' => 'Estamos a realizar melhorias no FieldFlow. O acesso será restabelecido assim que os trabalhos estiverem concluídos.',
                'starts_at' => '',
                'expected_at' => '',
                'auto_disable' => 1,
                'retry_after_seconds' => 300,
            ],
            'bridge' => [
                'enabled' => 1,
                'operational_enabled' => 1,
                'client_enabled' => 1,
                'billing_enabled' => 1,
                'subscriptions_enabled' => 1,
                'javascript_enabled' => 1,
                'auto_renew' => 1,
                'ticket_ttl_seconds' => 90,
                'web_session_hours' => 168,
                'renew_window_minutes' => 720,
                'heartbeat_seconds' => 300,
                'context_sync_seconds' => 60,
                'allow_downloads' => 1,
                'allow_external_links' => 1,
                'open_external_in_system_browser' => 1,
                'allow_file_uploads' => 1,
                'allow_multiple_uploads' => 1,
                'allow_camera' => 1,
                'allow_gallery' => 1,
                'allow_location' => 1,
                'network_status_banner' => 1,
                'native_image_viewer' => 1,
                'enable_zoom' => 0,
                'show_portal_header' => 1,
                'show_logout' => 1,
                'allowed_domains' => $host,
            ],
            'security' => [
                'access_ttl_minutes' => 60,
                'refresh_ttl_days' => 30,
                'login_attempts' => 10,
                'login_window_minutes' => 15,
            ],
            'features' => [
                'routes' => 1,
                'reporting' => 1,
                'media_upload' => 1,
                'briefings' => 1,
                'implementations' => 1,
                'stocks' => 1,
                'expenses' => 1,
                'analytics' => 1,
                'commercial_base' => 1,
                'campaign_pdvs' => 1,
                'team' => 1,
                'messages' => 1,
                'academy' => 1,
                'growth_hub' => 1,
            ],
            // 9AZ: visibility is controlled per mobile portal. The legacy
            // features map remains as the server/API union for older app builds.
            'feature_visibility' => [
                'operational' => [
                    'routes' => 1,
                    'reporting' => 1,
                    'media_upload' => 1,
                    'briefings' => 1,
                    'implementations' => 1,
                    'stocks' => 1,
                    'expenses' => 1,
                    'analytics' => 1,
                    'commercial_base' => 1,
                    'campaign_pdvs' => 1,
                    'team' => 1,
                    'messages' => 1,
                    'academy' => 1,
                    'growth_hub' => 1,
                ],
                'client' => [
                    'routes' => 1,
                    'reporting' => 1,
                    'media_upload' => 1,
                    'briefings' => 1,
                    'implementations' => 1,
                    'stocks' => 1,
                    'expenses' => 1,
                    'analytics' => 1,
                    'commercial_base' => 1,
                    'campaign_pdvs' => 1,
                    'team' => 1,
                    'messages' => 1,
                    'academy' => 1,
                    'growth_hub' => 1,
                ],
            ],
        ];
    }

    public static function get(): array {
        if (is_array(self::$cache)) return self::$cache;
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        $defaults = self::defaults();

        // 9AZ migration: existing installations only have the legacy global
        // feature map. Clone its exact state into both portal matrices so an
        // upgrade never makes a previously hidden tab visible by accident.
        if (!isset($saved['feature_visibility']) || !is_array($saved['feature_visibility'])) {
            $legacy = self::merge((array)$defaults['features'], is_array($saved['features'] ?? null) ? (array)$saved['features'] : []);
            $saved['feature_visibility'] = [
                'operational' => $legacy,
                'client' => $legacy,
            ];
        }

        self::$cache = self::sanitize(self::merge($defaults, $saved));
        return self::$cache;
    }

    public static function save(array $input): array {
        $merged = self::merge(self::get(), $input);
        $clean = self::sanitize($merged);
        update_option(self::OPTION, $clean, false);
        self::$cache = $clean;

        // Keep the legacy options in sync for older app builds and integrations.
        update_option('fieldflow_mobile_minimum_app_version', (string)$clean['platforms']['android']['minimum_version'], false);
        update_option('fieldflow_mobile_latest_app_version', (string)$clean['platforms']['android']['latest_version'], false);
        update_option('fieldflow_mobile_maintenance_mode', !empty($clean['maintenance']['enabled']), false);
        return $clean;
    }

    public static function sanitize(array $value): array {
        $d = self::defaults();
        $out = self::merge($d, $value);

        $out['general']['enabled'] = self::flag($out['general']['enabled'] ?? 0);
        $out['general']['app_name'] = sanitize_text_field((string)($out['general']['app_name'] ?? ''));
        $out['general']['display_name'] = sanitize_text_field((string)($out['general']['display_name'] ?? ''));
        $out['general']['environment'] = in_array(($out['general']['environment'] ?? ''), ['production','staging','development'], true) ? $out['general']['environment'] : 'production';
        $out['general']['support_email'] = sanitize_email((string)($out['general']['support_email'] ?? ''));
        foreach (['support_url','privacy_url','terms_url'] as $key) {
            $out['general'][$key] = esc_url_raw((string)($out['general'][$key] ?? ''));
        }

        foreach (['android','ios'] as $platform) {
            $p = $out['platforms'][$platform] ?? [];
            $out['platforms'][$platform] = [
                'enabled' => self::flag($p['enabled'] ?? 0),
                'minimum_version' => self::version($p['minimum_version'] ?? '1.0.0'),
                'latest_version' => self::version($p['latest_version'] ?? '1.0.0'),
                'build_number' => preg_replace('/[^0-9]/', '', (string)($p['build_number'] ?? '1')) ?: '1',
                'store_url' => esc_url_raw((string)($p['store_url'] ?? '')),
                'force_update' => self::flag($p['force_update'] ?? 0),
                'package_id' => substr(preg_replace('/[^A-Za-z0-9._-]/', '', (string)($p['package_id'] ?? '')) ?: '', 0, 190),
                'release_notes' => sanitize_textarea_field((string)($p['release_notes'] ?? '')),
            ];
        }

        foreach (['enabled','android','ios','web','auto_disable'] as $key) $out['maintenance'][$key] = self::flag($out['maintenance'][$key] ?? 0);
        $out['maintenance']['title'] = sanitize_text_field((string)($out['maintenance']['title'] ?? ''));
        $out['maintenance']['message'] = sanitize_textarea_field((string)($out['maintenance']['message'] ?? ''));
        $out['maintenance']['starts_at'] = self::datetime_local($out['maintenance']['starts_at'] ?? '');
        $out['maintenance']['expected_at'] = self::datetime_local($out['maintenance']['expected_at'] ?? '');
        $out['maintenance']['retry_after_seconds'] = max(30, min(86400, absint($out['maintenance']['retry_after_seconds'] ?? 300)));

        foreach (['enabled','operational_enabled','client_enabled','billing_enabled','subscriptions_enabled','javascript_enabled','auto_renew','allow_downloads','allow_external_links','open_external_in_system_browser','allow_file_uploads','allow_multiple_uploads','allow_camera','allow_gallery','allow_location','network_status_banner','native_image_viewer','enable_zoom','show_portal_header','show_logout'] as $key) {
            $out['bridge'][$key] = self::flag($out['bridge'][$key] ?? 0);
        }
        $out['bridge']['ticket_ttl_seconds'] = max(30, min(600, absint($out['bridge']['ticket_ttl_seconds'] ?? 90)));
        $out['bridge']['web_session_hours'] = max(1, min(720, absint($out['bridge']['web_session_hours'] ?? 168)));
        $out['bridge']['renew_window_minutes'] = max(5, min(10080, absint($out['bridge']['renew_window_minutes'] ?? 720)));
        $out['bridge']['heartbeat_seconds'] = max(60, min(3600, absint($out['bridge']['heartbeat_seconds'] ?? 300)));
        $out['bridge']['context_sync_seconds'] = max(30, min(3600, absint($out['bridge']['context_sync_seconds'] ?? 60)));
        $domains = preg_split('/[\r\n,]+/', (string)($out['bridge']['allowed_domains'] ?? '')) ?: [];
        $domains = array_values(array_unique(array_filter(array_map(function($domain) {
            $domain = strtolower(trim((string)$domain));
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = preg_replace('#/.*$#', '', $domain);
            return preg_match('/^[a-z0-9.-]+$/', $domain) ? $domain : '';
        }, $domains))));
        $out['bridge']['allowed_domains'] = implode("\n", $domains);

        $out['security']['access_ttl_minutes'] = max(5, min(1440, absint($out['security']['access_ttl_minutes'] ?? 60)));
        $out['security']['refresh_ttl_days'] = max(1, min(180, absint($out['security']['refresh_ttl_days'] ?? 30)));
        $out['security']['login_attempts'] = max(3, min(50, absint($out['security']['login_attempts'] ?? 10)));
        $out['security']['login_window_minutes'] = max(1, min(120, absint($out['security']['login_window_minutes'] ?? 15)));

        $portalFeatureKeys = [
            'operational' => ['routes','reporting','media_upload','briefings','implementations','stocks','expenses','analytics','commercial_base','messages','academy'],
            'client' => ['routes','reporting','briefings','implementations','stocks','analytics','commercial_base','campaign_pdvs','team','growth_hub'],
        ];
        foreach (['operational','client'] as $target) {
            if (!isset($out['feature_visibility'][$target]) || !is_array($out['feature_visibility'][$target])) {
                $out['feature_visibility'][$target] = [];
            }
            foreach (array_keys($d['features']) as $key) {
                $fallback = $out['features'][$key] ?? 0;
                $out['feature_visibility'][$target][$key] = in_array($key, $portalFeatureKeys[$target], true)
                    ? self::flag($out['feature_visibility'][$target][$key] ?? $fallback)
                    : 0;
            }
        }

        // Legacy global feature flags remain the union of both portals. This
        // keeps API guards and older mobile builds backwards compatible while
        // navigation visibility is now target-specific.
        foreach (array_keys($d['features']) as $key) {
            $out['features'][$key] = (!empty($out['feature_visibility']['operational'][$key]) || !empty($out['feature_visibility']['client'][$key])) ? 1 : 0;
        }
        return $out;
    }

    public static function platform(string $value): string {
        $value = strtolower(sanitize_key($value));
        if (in_array($value, ['iphone','ipad','apple'], true)) return 'ios';
        if (in_array($value, ['android','ios','web'], true)) return $value;
        return '';
    }

    public static function platform_enabled(string $platform): bool {
        $platform = self::platform($platform);
        if ($platform === '' || $platform === 'web') return !empty(self::get()['general']['enabled']);
        $s = self::get();
        return !empty($s['general']['enabled']) && !empty($s['platforms'][$platform]['enabled']);
    }

    public static function platform_config(string $platform): array {
        $platform = self::platform($platform);
        if (!in_array($platform, ['android','ios'], true)) $platform = 'android';
        $s = self::get();
        return (array)$s['platforms'][$platform];
    }

    public static function maintenance_enabled(string $platform = ''): bool {
        $s = self::get();
        $maintenance = (array)$s['maintenance'];
        if (empty($maintenance['enabled'])) return false;

        $now = time();
        $starts_at = self::datetime_timestamp((string)($maintenance['starts_at'] ?? ''));
        if ($starts_at > 0 && $starts_at > $now) return false;

        $expected_at = self::datetime_timestamp((string)($maintenance['expected_at'] ?? ''));
        if (!empty($maintenance['auto_disable']) && $expected_at > 0 && $expected_at <= $now) return false;

        $platform = self::platform($platform);
        if ($platform === '') return true; // Backwards-compatible global check.
        return !empty($maintenance[$platform] ?? 0);
    }

    public static function maintenance_status(string $platform = ''): array {
        $s = self::get();
        $maintenance = (array)$s['maintenance'];
        $starts_at = self::datetime_timestamp((string)($maintenance['starts_at'] ?? ''));
        $expected_at = self::datetime_timestamp((string)($maintenance['expected_at'] ?? ''));
        return [
            'enabled' => self::maintenance_enabled($platform),
            'configured' => !empty($maintenance['enabled']),
            'scheduled' => !empty($maintenance['enabled']) && $starts_at > time(),
            'expired' => !empty($maintenance['enabled']) && !empty($maintenance['auto_disable']) && $expected_at > 0 && $expected_at <= time(),
            'starts_at' => (string)($maintenance['starts_at'] ?? ''),
            'expected_at' => (string)($maintenance['expected_at'] ?? ''),
            'auto_disable' => !empty($maintenance['auto_disable']),
            'retry_after_seconds' => (int)($maintenance['retry_after_seconds'] ?? 300),
        ];
    }

    public static function bridge_enabled(): bool {
        $s = self::get();
        return !empty($s['general']['enabled']) && !empty($s['bridge']['enabled']);
    }

    public static function target_enabled(string $target): bool {
        $s = self::get();
        if ($target === 'operational') return !empty($s['bridge']['operational_enabled']);
        if ($target === 'client') return !empty($s['bridge']['client_enabled']);
        if ($target === 'billing') return !empty($s['bridge']['billing_enabled']);
        if ($target === 'subscriptions') return !empty($s['bridge']['subscriptions_enabled']);
        return false;
    }

    public static function feature(string $key): bool {
        $s = self::get();
        return !empty($s['features'][sanitize_key($key)]);
    }

    /**
     * Portal-specific mobile visibility introduced by 9AZ.
     * Unknown targets deliberately fall back to the legacy union.
     */
    public static function feature_for_target(string $key, string $target): bool {
        $key = sanitize_key($key);
        $target = sanitize_key($target);
        $s = self::get();
        if (in_array($target, ['operational','client'], true)) {
            return !empty($s['feature_visibility'][$target][$key]);
        }
        return !empty($s['features'][$key]);
    }

    public static function features_for_target(string $target): array {
        $target = sanitize_key($target);
        $s = self::get();
        $source = in_array($target, ['operational','client'], true)
            ? (array)($s['feature_visibility'][$target] ?? [])
            : (array)($s['features'] ?? []);
        return array_map('boolval', $source);
    }

    public static function access_ttl(): int {
        return (int)self::get()['security']['access_ttl_minutes'] * MINUTE_IN_SECONDS;
    }

    public static function refresh_ttl(): int {
        return (int)self::get()['security']['refresh_ttl_days'] * DAY_IN_SECONDS;
    }

    public static function login_attempts(): int {
        return (int)self::get()['security']['login_attempts'];
    }

    public static function login_window(): int {
        return (int)self::get()['security']['login_window_minutes'] * MINUTE_IN_SECONDS;
    }

    public static function ticket_ttl(): int {
        return (int)self::get()['bridge']['ticket_ttl_seconds'];
    }

    public static function web_ttl(): int {
        return (int)self::get()['bridge']['web_session_hours'] * HOUR_IN_SECONDS;
    }

    public static function context_sync_seconds(): int {
        return (int)self::get()['bridge']['context_sync_seconds'];
    }

    public static function heartbeat_seconds(): int {
        return (int)self::get()['bridge']['heartbeat_seconds'];
    }

    public static function renew_window(): int {
        return (int)self::get()['bridge']['renew_window_minutes'] * MINUTE_IN_SECONDS;
    }

    public static function bridge_flag(string $key, bool $default = false): bool {
        $bridge = (array)(self::get()['bridge'] ?? []);
        return array_key_exists($key, $bridge) ? !empty($bridge[$key]) : $default;
    }

    public static function blocked_devices(): array {
        $items = get_option(self::BLOCKED_DEVICES_OPTION, []);
        return is_array($items) ? $items : [];
    }

    public static function is_device_blocked(string $device_id): bool {
        $device_id = self::device_id($device_id);
        if ($device_id === '') return false;
        return isset(self::blocked_devices()[$device_id]);
    }

    public static function block_device(string $device_id, int $by_user = 0): bool {
        $device_id = self::device_id($device_id);
        if ($device_id === '') return false;
        $items = self::blocked_devices();
        $items[$device_id] = [
            'blocked_at' => current_time('mysql', true),
            'blocked_by' => $by_user,
        ];
        return update_option(self::BLOCKED_DEVICES_OPTION, $items, false);
    }

    public static function unblock_device(string $device_id): bool {
        $device_id = self::device_id($device_id);
        $items = self::blocked_devices();
        if (!isset($items[$device_id])) return true;
        unset($items[$device_id]);
        return update_option(self::BLOCKED_DEVICES_OPTION, $items, false);
    }

    public static function version_status(string $platform, string $app_version): array {
        $config = self::platform_config($platform);
        $app_version = self::version($app_version ?: '0.0.0');
        $minimum = (string)$config['minimum_version'];
        $latest = (string)$config['latest_version'];
        $available = version_compare($app_version, $latest, '<');
        $required = version_compare($app_version, $minimum, '<') || (!empty($config['force_update']) && $available);
        return [
            'current_version' => $app_version,
            'minimum_version' => $minimum,
            'latest_version' => $latest,
            'update_required' => $required,
            'update_available' => $available,
        ];
    }

    public static function allowed_domains(): array {
        $raw = (string)(self::get()['bridge']['allowed_domains'] ?? '');
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw) ?: [])));
    }

    private static function merge(array $base, array $extra): array {
        foreach ($extra as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    private static function flag($value): int {
        return empty($value) ? 0 : 1;
    }

    private static function version($value): string {
        $value = trim((string)$value);
        return preg_match('/^\d+(?:\.\d+){0,3}(?:[-+][A-Za-z0-9.-]+)?$/', $value) ? $value : '1.0.0';
    }

    private static function datetime_local($value): string {
        $value = trim((string)$value);
        if ($value === '') return '';
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) ? $value : '';
    }

    private static function datetime_timestamp(string $value): int {
        if ($value === '') return 0;
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
        return $date instanceof \DateTimeImmutable ? $date->getTimestamp() : 0;
    }

    private static function device_id($value): string {
        return substr(preg_replace('/[^A-Za-z0-9._:-]/', '', (string)$value) ?: '', 0, 191);
    }
}
