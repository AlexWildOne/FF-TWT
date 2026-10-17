<?php
namespace RoutesPro\Cloud;

use RoutesPro\Support\RemoteLicenseClient;

if (!defined('ABSPATH')) exit;

/** WordPress-native private updater for FieldFlow Phase 6B.4. */
final class PrivatePluginUpdater {
    public const CACHE_KEY = 'fieldflow_private_update_manifest_v1';
    public const CACHE_TTL = 30 * MINUTE_IN_SECONDS;
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'injectUpdate']);
        add_filter('plugins_api', [self::class, 'pluginInfo'], 20, 3);
        add_filter('upgrader_pre_download', [self::class, 'preDownload'], 20, 4);
        add_action('upgrader_process_complete', [self::class, 'afterUpgrade'], 20, 2);
    }

    public static function isRegistered(): bool { return self::$registered; }

    public static function clearCache(): void { delete_site_transient(self::CACHE_KEY); }

    public static function injectUpdate($transient) {
        if (!is_object($transient)) return $transient;
        $response = self::check(false, false);
        if (empty($response['success']) || empty($response['update_available'])) return $transient;
        $release = PrivateUpdateLifecycle::releaseForWordPress($response);
        if (!$release) return $transient;
        $current = defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '';
        $latest = (string)($release['version'] ?? '');
        if ($current === '' || $latest === '' || !version_compare($latest, $current, '>')) return $transient;
        if (empty($response['compatible'])) return $transient;

        $plugin = self::pluginBasename();
        $item = new \stdClass();
        $item->id = 'fieldflow-private';
        $item->slug = 'fieldflow';
        $item->plugin = $plugin;
        $item->new_version = $latest;
        $item->url = home_url('/');
        $item->package = self::placeholderPackageUrl();
        $item->tested = (string)($release['tested_wp'] ?? '');
        $item->requires_php = (string)($release['requires_php'] ?? '');
        $item->icons = [];
        $item->banners = [];
        if (!isset($transient->response) || !is_array($transient->response)) $transient->response = [];
        $transient->response[$plugin] = $item;
        return $transient;
    }

    public static function pluginInfo($result, string $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args)) return $result;
        $slug = sanitize_key((string)($args->slug ?? ''));
        $currentSlug = sanitize_key(basename(dirname(self::pluginBasename())));
        if (!in_array($slug, ['fieldflow',$currentSlug], true)) return $result;
        $response = self::check(false, false);
        if (empty($response['success'])) return $result;
        $release = PrivateUpdateLifecycle::releaseForWordPress($response);
        if (!$release) return $result;
        $info = new \stdClass();
        $info->name = 'FieldFlow';
        $info->slug = 'fieldflow';
        $info->version = (string)($release['version'] ?? '');
        $info->author = '<a href="https://thewildtheory.com">The Wild Theory</a>';
        $info->homepage = home_url('/');
        $info->requires = (string)($release['requires_wp'] ?? '');
        $info->tested = (string)($release['tested_wp'] ?? '');
        $info->requires_php = (string)($release['requires_php'] ?? '');
        $info->download_link = self::placeholderPackageUrl();
        $info->sections = [
            'description'=>'FieldFlow Pro / TWT Operations Hub, distribuição privada licenciada pela The Wild Theory.',
            'changelog'=>nl2br(esc_html((string)($release['notes'] ?? 'Atualização privada FieldFlow.'))),
        ];
        return $info;
    }

    public static function preDownload($reply, $package, $upgrader, $hookExtra) {
        if ($reply !== false) return $reply;
        if (!self::isOurUpgrade($package, $hookExtra)) return $reply;
        $response = self::check(true, true);
        if (empty($response['success']) || empty($response['update_available']) || empty($response['compatible'])) {
            return new \WP_Error('fieldflow_private_update_unavailable', sanitize_text_field((string)($response['message'] ?? 'Atualização privada indisponível.')));
        }
        $release = PrivateUpdateLifecycle::releaseForWordPress($response);
        $packageUrl = esc_url_raw((string)($response['package_url'] ?? ''));
        if (!$release || !PrivateUpdateLifecycle::trustedPackageUrl($packageUrl)) {
            return new \WP_Error('fieldflow_private_update_manifest_invalid', 'Manifesto ou URL privado de atualização inválido.');
        }
        $verified = PrivateUpdateLifecycle::downloadAndVerifyPackage($packageUrl, $release);
        if (is_wp_error($verified)) return $verified;
        return (string)$verified['file'];
    }

    public static function afterUpgrade($upgrader, array $hookExtra): void {
        if (($hookExtra['type'] ?? '') !== 'plugin') return;
        $plugins = (array)($hookExtra['plugins'] ?? []);
        if (isset($hookExtra['plugin'])) $plugins[] = (string)$hookExtra['plugin'];
        if (!in_array(self::pluginBasename(), $plugins, true)) return;
        self::clearCache();
        delete_site_transient('update_plugins');
    }

    public static function check(bool $force = false, bool $includePackage = false): array {
        if (!$force && !$includePackage) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached)) return $cached;
        }
        $response = PrivateUpdateLifecycle::remoteCheck($includePackage);
        if (!empty($response['success']) && !$includePackage) {
            set_site_transient(self::CACHE_KEY, $response, self::CACHE_TTL);
            do_action('fieldflow_private_update_checked', $response);
        }
        return $response;
    }

    private static function pluginBasename(): string {
        $file = defined('FIELDFLOW_PATH') ? FIELDFLOW_PATH . 'fieldflow.php' : dirname(__DIR__, 2) . '/fieldflow.php';
        return plugin_basename($file);
    }

    private static function placeholderPackageUrl(): string {
        $base = rtrim(RemoteLicenseClient::baseUrl(), '/');
        return $base !== '' ? $base . '/updates/package?fieldflow_private=licensed' : '';
    }

    private static function isOurUpgrade($package, $hookExtra): bool {
        $plugin = (string)($hookExtra['plugin'] ?? '');
        if ($plugin !== '' && !hash_equals(self::pluginBasename(), $plugin)) return false;
        $plugins = (array)($hookExtra['plugins'] ?? []);
        if ($plugin === '' && $plugins && !in_array(self::pluginBasename(), $plugins, true)) return false;
        $url = is_string($package) ? $package : '';
        if ($url === '') return false;
        $host = strtolower((string)(wp_parse_url($url, PHP_URL_HOST) ?: ''));
        $path = (string)(wp_parse_url($url, PHP_URL_PATH) ?: '');
        return hash_equals(RemoteLicenseClient::TRUSTED_DEFAULT_HOST, $host) && strpos($path, '/api/updates/package') !== false;
    }
}
