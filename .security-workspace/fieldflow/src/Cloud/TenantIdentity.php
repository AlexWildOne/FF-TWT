<?php
namespace RoutesPro\Cloud;

if (!defined('ABSPATH')) exit;

final class TenantIdentity {
    private const TENANT_OPTION = 'fieldflow_cloud_tenant_id';
    private const INSTALLATION_OPTION = 'fieldflow_cloud_installation_id';

    public static function tenantId(): string {
        return self::persistentId(self::TENANT_OPTION, 'ff_t_');
    }

    public static function installationId(): string {
        return self::persistentId(self::INSTALLATION_OPTION, 'ff_i_');
    }

    public static function snapshot(): array {
        $host = strtolower((string)(wp_parse_url(home_url('/'), PHP_URL_HOST) ?: ''));
        return [
            'tenant_id' => self::tenantId(),
            'installation_id' => self::installationId(),
            'site_host' => substr($host, 0, 191),
            'plugin_version' => defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '',
        ];
    }

    private static function persistentId(string $option, string $prefix): string {
        $current = strtolower(trim((string)get_option($option, '')));
        if (preg_match('/^' . preg_quote($prefix, '/') . '[a-f0-9]{24}$/', $current)) return $current;

        try {
            $suffix = bin2hex(random_bytes(12));
        } catch (\Throwable $error) {
            $suffix = substr(hash('sha256', wp_generate_uuid4() . '|' . home_url('/') . '|' . microtime(true)), 0, 24);
        }
        $generated = $prefix . $suffix;
        if (!add_option($option, $generated, '', 'no')) {
            $stored = strtolower(trim((string)get_option($option, '')));
            if (preg_match('/^' . preg_quote($prefix, '/') . '[a-f0-9]{24}$/', $stored)) return $stored;
            update_option($option, $generated, false);
        }
        return $generated;
    }
}
