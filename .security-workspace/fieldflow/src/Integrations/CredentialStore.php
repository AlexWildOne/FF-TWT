<?php
namespace RoutesPro\Integrations;

if (!defined('ABSPATH')) exit;

/**
 * Centraliza a cifragem dos segredos guardados nas opcoes WordPress.
 *
 * Os nomes das chaves permanecem iguais para preservar compatibilidade. A
 * camada de configuracao entrega sempre texto simples ao runtime, enquanto a
 * base de dados recebe apenas valores protegidos pelo SecretVault.
 */
class CredentialStore {
    public const MIGRATION_OPTION = 'fieldflow_credential_vault_state';
    public const VERSION = '1.0';

    private static bool $migrationRunning = false;

    public static function registry(): array {
        return [
            'routespro_settings' => [
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
            ],
            'routespro_license' => ['key'],
            'routespro_integrations' => [
                'api_token',
                'powerbi_token',
                'gcloud_auth_header',
                'gcloud_service_account_json',
                'azure_auth_header',
            ],
            'fieldflow_pwa_settings' => ['push_private_key'],
        ];
    }

    public static function decode(array $stored, array $secretKeys, ?array &$unreadable = null): array {
        $unreadable = [];
        foreach ($secretKeys as $key) {
            $key = (string)$key;
            $value = (string)($stored[$key] ?? '');
            if ($value === '' || !SecretVault::isEncrypted($value)) continue;
            $plain = SecretVault::decrypt($value);
            if ($plain === '') {
                $unreadable[$key] = true;
                $stored[$key] = '';
                continue;
            }
            $stored[$key] = $plain;
        }
        return $stored;
    }

    /**
     * @return array{values:array,failed:array,encrypted:int,reused:int}
     */
    public static function encode(array $plain, array $secretKeys, array $existing = []): array {
        $stored = $plain;
        $failed = [];
        $encrypted = 0;
        $reused = 0;

        foreach ($secretKeys as $key) {
            $key = (string)$key;
            if (!array_key_exists($key, $plain)) {
                if (array_key_exists($key, $existing)) $stored[$key] = $existing[$key];
                continue;
            }

            $plainValue = (string)$plain[$key];
            if ($plainValue === '') {
                $stored[$key] = '';
                continue;
            }

            $existingValue = (string)($existing[$key] ?? '');
            if ($existingValue !== '' && SecretVault::isEncrypted($existingValue)) {
                $existingPlain = SecretVault::decrypt($existingValue);
                if ($existingPlain !== '' && hash_equals($existingPlain, $plainValue) && !SecretVault::needsMigration($existingValue)) {
                    $stored[$key] = $existingValue;
                    $reused++;
                    continue;
                }
            }

            $cipher = SecretVault::encrypt($plainValue);
            if ($cipher === '') {
                $failed[$key] = true;
                if (array_key_exists($key, $existing)) $stored[$key] = $existing[$key];
                else unset($stored[$key]);
                continue;
            }
            $stored[$key] = $cipher;
            $encrypted++;
        }

        return [
            'values' => $stored,
            'failed' => array_keys($failed),
            'encrypted' => $encrypted,
            'reused' => $reused,
        ];
    }

    public static function maybeMigrateKnownOptions(): void {
        if (self::$migrationRunning) return;
        $savedState = get_option(self::MIGRATION_OPTION, []);
        if (is_array($savedState)
            && (string)($savedState['version'] ?? '') === self::VERSION
            && (string)($savedState['status'] ?? '') === 'ready'
            && empty($savedState['legacy_fields'])
            && empty($savedState['unreadable_fields'])) {
            return;
        }
        self::$migrationRunning = true;
        try {
            $summary = self::migrateKnownOptions();
            update_option(self::MIGRATION_OPTION, $summary, false);
            if (!empty($summary['migrated_fields']) && class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info(
                    'Credenciais legadas migradas para o cofre central.',
                    ['context_key' => 'credential_vault'],
                    ['migrated_fields' => (int)$summary['migrated_fields'], 'version' => self::VERSION]
                );
            }
        } finally {
            self::$migrationRunning = false;
        }
    }

    public static function migrateKnownOptions(): array {
        $summary = [
            'version' => self::VERSION,
            'status' => SecretVault::canEncrypt() ? 'ready' : 'blocked',
            'migrated_fields' => 0,
            'configured_fields' => 0,
            'encrypted_fields' => 0,
            'legacy_fields' => 0,
            'unreadable_fields' => 0,
            'checked_at_gmt' => gmdate('c'),
        ];

        foreach (self::registry() as $option => $secretKeys) {
            $raw = get_option($option, []);
            if (!is_array($raw)) continue;
            $plain = self::decode($raw, $secretKeys, $unreadable);
            $needsWrite = false;

            foreach ($secretKeys as $key) {
                $value = (string)($raw[$key] ?? '');
                if ($value === '') continue;
                $summary['configured_fields']++;
                if (SecretVault::isEncrypted($value) && !SecretVault::needsMigration($value)) {
                    $summary['encrypted_fields']++;
                } else {
                    $summary['legacy_fields']++;
                    $needsWrite = true;
                }
            }
            $summary['unreadable_fields'] += count($unreadable);

            if (!$needsWrite || !SecretVault::canEncrypt() || !empty($unreadable)) continue;
            $encoded = self::encode($plain, $secretKeys, $raw);
            if (!empty($encoded['failed'])) continue;
            if ($encoded['values'] !== $raw) {
                update_option($option, $encoded['values'], false);
                $summary['migrated_fields'] += (int)$encoded['encrypted'];
            }
        }

        $after = self::status(false);
        $summary['encrypted_fields'] = (int)$after['encrypted_fields'];
        $summary['legacy_fields'] = (int)$after['legacy_fields'];
        $summary['unreadable_fields'] = (int)$after['unreadable_fields'];
        if ($summary['status'] === 'ready' && ($summary['legacy_fields'] > 0 || $summary['unreadable_fields'] > 0)) {
            $summary['status'] = 'warning';
        }
        return $summary;
    }

    public static function status(bool $includeSavedState = true): array {
        $status = [
            'version' => self::VERSION,
            'can_encrypt' => SecretVault::canEncrypt(),
            'configured_fields' => 0,
            'encrypted_fields' => 0,
            'legacy_fields' => 0,
            'unreadable_fields' => 0,
        ];
        foreach (self::registry() as $option => $secretKeys) {
            $raw = get_option($option, []);
            if (!is_array($raw)) continue;
            foreach ($secretKeys as $key) {
                $value = (string)($raw[$key] ?? '');
                if ($value === '') continue;
                $status['configured_fields']++;
                if (!SecretVault::isEncrypted($value)) {
                    $status['legacy_fields']++;
                    continue;
                }
                if (SecretVault::decrypt($value) === '') {
                    $status['unreadable_fields']++;
                } elseif (SecretVault::needsMigration($value)) {
                    $status['legacy_fields']++;
                } else {
                    $status['encrypted_fields']++;
                }
            }
        }
        if ($includeSavedState) {
            $saved = get_option(self::MIGRATION_OPTION, []);
            $status['last_migration'] = is_array($saved) ? $saved : [];
        }
        return $status;
    }
}
