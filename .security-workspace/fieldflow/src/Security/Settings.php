<?php
namespace RoutesPro\Security;

if (!defined('ABSPATH')) exit;

class Settings {
    public const OPTION = 'fieldflow_security_observability_settings';
    private static ?array $cache = null;

    public static function defaults(): array {
        return [
            'rate_limits' => [
                'public_per_minute' => 60,
                'authenticated_per_minute' => 180,
                'ai_per_minute' => 20,
                'export_per_minute' => 30,
                'upload_per_minute' => 40,
            ],
            'uploads' => [
                'max_image_mb' => 15,
                'max_document_mb' => 25,
                'allowed_extensions' => 'jpg,jpeg,png,webp,heic,heif,pdf,csv,xlsx,xls,docx,zip',
            ],
            'observability' => [
                'slow_rest_ms' => 1800,
                'critical_rest_ms' => 5000,
                'queue_warning' => 25,
                'queue_critical' => 100,
                'storage_warning_mb' => 10240,
                'retention_days' => 90,
                'security_retention_days' => 180,
            ],
            'alerts' => [
                'admin_email' => get_option('admin_email'),
                'email_enabled' => 0,
                'cooldown_minutes' => 60,
            ],
        ];
    }

    public static function get(): array {
        if (self::$cache !== null) return self::$cache;
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        $legacy_extensions = ['jpg','jpeg','png','webp','pdf','csv','xlsx','xls','docx','zip'];
        $saved_extensions = preg_split('/[\s,;]+/', strtolower((string)($saved['uploads']['allowed_extensions'] ?? ''))) ?: [];
        $saved_extensions = array_values(array_unique(array_filter(array_map('sanitize_key', $saved_extensions))));
        $legacy_compare = $legacy_extensions;
        sort($saved_extensions);
        sort($legacy_compare);
        if ($saved_extensions === $legacy_compare) {
            $saved['uploads']['allowed_extensions'] = implode(',', array_merge($legacy_extensions, ['heic','heif']));
            update_option(self::OPTION, $saved, false);
        }
        self::$cache = self::sanitize(array_replace_recursive(self::defaults(), $saved));
        return self::$cache;
    }

    public static function save(array $input): array {
        $clean = self::sanitize(array_replace_recursive(self::get(), $input));
        update_option(self::OPTION, $clean, false);
        self::$cache = $clean;
        return $clean;
    }

    public static function sanitize(array $value): array {
        $d = self::defaults();
        $out = array_replace_recursive($d, $value);
        foreach ($d['rate_limits'] as $key => $default) {
            $out['rate_limits'][$key] = max(5, min(5000, absint($out['rate_limits'][$key] ?? $default)));
        }
        $out['uploads']['max_image_mb'] = max(1, min(50, absint($out['uploads']['max_image_mb'] ?? 15)));
        $out['uploads']['max_document_mb'] = max(1, min(100, absint($out['uploads']['max_document_mb'] ?? 25)));
        $extensions = preg_split('/[\s,;]+/', strtolower((string)($out['uploads']['allowed_extensions'] ?? ''))) ?: [];
        $extensions = array_values(array_unique(array_filter(array_map(static function($ext){
            $ext = preg_replace('/[^a-z0-9]/', '', (string)$ext);
            return $ext ?: '';
        }, $extensions))));
        $out['uploads']['allowed_extensions'] = implode(',', $extensions);
        $out['observability']['slow_rest_ms'] = max(250, min(60000, absint($out['observability']['slow_rest_ms'] ?? 1800)));
        $out['observability']['critical_rest_ms'] = max($out['observability']['slow_rest_ms'], min(120000, absint($out['observability']['critical_rest_ms'] ?? 5000)));
        $out['observability']['queue_warning'] = max(1, min(100000, absint($out['observability']['queue_warning'] ?? 25)));
        $out['observability']['queue_critical'] = max($out['observability']['queue_warning'], min(1000000, absint($out['observability']['queue_critical'] ?? 100)));
        $out['observability']['storage_warning_mb'] = max(100, min(1000000, absint($out['observability']['storage_warning_mb'] ?? 10240)));
        $out['observability']['retention_days'] = max(7, min(730, absint($out['observability']['retention_days'] ?? 90)));
        $out['observability']['security_retention_days'] = max(30, min(1825, absint($out['observability']['security_retention_days'] ?? 180)));
        $out['alerts']['admin_email'] = sanitize_email((string)($out['alerts']['admin_email'] ?? ''));
        $out['alerts']['email_enabled'] = empty($out['alerts']['email_enabled']) ? 0 : 1;
        $out['alerts']['cooldown_minutes'] = max(15, min(1440, absint($out['alerts']['cooldown_minutes'] ?? 60)));
        return $out;
    }

    public static function allowedExtensions(): array {
        return array_values(array_filter(explode(',', (string)self::get()['uploads']['allowed_extensions'])));
    }
}
