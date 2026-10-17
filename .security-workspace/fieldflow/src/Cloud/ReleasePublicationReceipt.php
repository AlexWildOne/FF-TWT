<?php
namespace RoutesPro\Cloud;

use RoutesPro\Core\AuditLog;

if (!defined('ABSPATH')) exit;

/**
 * Durable publication receipt for the WordPress Release Manager.
 *
 * The canonical release flow writes the receipt to the local option and audit
 * log. Multisite installations also mirror it to a network option. Reads prefer
 * exact-version durable evidence so a stale persistent object cache cannot make
 * Phase 6 forget a publication that was already completed from WordPress.
 */
final class ReleasePublicationReceipt {
    public const CONTRACT = 'fieldflow-release-manager-publication-receipt-v1';
    public const OPTION = 'fieldflow_release_manager_last_state';
    public const NETWORK_OPTION = 'fieldflow_release_manager_network_last_state';

    public static function persist(array $state): array {
        $receipt = self::sanitize($state, 'option');
        if (!self::isStructurallyValid($receipt)) return [];

        if (function_exists('wp_cache_delete')) wp_cache_delete(self::OPTION, 'options');
        update_option(self::OPTION, self::withoutRuntimeMeta($receipt), false);

        if (is_multisite()) {
            if (function_exists('wp_cache_delete')) wp_cache_delete(self::NETWORK_OPTION, 'site-options');
            update_site_option(self::NETWORK_OPTION, self::withoutRuntimeMeta($receipt));
        }
        return $receipt;
    }

    public static function current(string $expectedVersion = ''): array {
        $expectedVersion = sanitize_text_field($expectedVersion);
        $candidates = [];

        $local = get_option(self::OPTION, []);
        if (is_array($local) && $local) $candidates[] = self::sanitize($local, 'option');

        if (is_multisite()) {
            $network = get_site_option(self::NETWORK_OPTION, []);
            if (is_array($network) && $network) $candidates[] = self::sanitize($network, 'network_option');
        }

        foreach (self::auditCandidates() as $candidate) $candidates[] = $candidate;
        $candidates = array_values(array_filter($candidates, [self::class, 'isStructurallyValid']));

        if ($expectedVersion !== '') {
            $matching = array_values(array_filter($candidates, static function(array $candidate) use ($expectedVersion): bool {
                return isset($candidate['version']) && hash_equals($expectedVersion, (string)$candidate['version']);
            }));
            if ($matching) $candidates = $matching;
        }

        if (!$candidates) return self::emptyReceipt();

        usort($candidates, static function(array $a, array $b): int {
            $sourcePriority = ['audit_log'=>30, 'network_option'=>20, 'option'=>10];
            $aPriority = $sourcePriority[(string)($a['receipt_source'] ?? '')] ?? 0;
            $bPriority = $sourcePriority[(string)($b['receipt_source'] ?? '')] ?? 0;
            $aTime = strtotime((string)($a['published_at'] ?? '')) ?: 0;
            $bTime = strtotime((string)($b['published_at'] ?? '')) ?: 0;
            if ($aTime !== $bTime) return $bTime <=> $aTime;
            return $bPriority <=> $aPriority;
        });

        $receipt = $candidates[0];
        if (($receipt['receipt_source'] ?? '') !== 'option') self::healLocalOption($receipt);
        return $receipt;
    }

    private static function auditCandidates(): array {
        if (!class_exists(AuditLog::class) || !AuditLog::exists()) return [];
        $rows = AuditLog::recent(100, [
            'entity_type'=>'fieldflow_release',
            'action_name'=>'release.published',
        ]);
        $out = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string)($row['after_json'] ?? ''), true);
            if (!is_array($decoded)) continue;
            $candidate = self::sanitize($decoded, 'audit_log');
            if (empty($candidate['published_at'])) {
                $candidate['published_at'] = sanitize_text_field((string)($row['created_at'] ?? ''));
            }
            if (empty($candidate['published_by'])) {
                $candidate['published_by'] = absint($row['actor_user_id'] ?? 0);
            }
            if (self::isStructurallyValid($candidate)) $out[] = $candidate;
        }
        return $out;
    }

    private static function sanitize(array $state, string $source): array {
        $version = sanitize_text_field((string)($state['version'] ?? ''));
        $channel = sanitize_key((string)($state['channel'] ?? ''));
        $sha = strtolower(sanitize_text_field((string)($state['sha256'] ?? $state['package_sha256'] ?? '')));
        return [
            'version'=>$version,
            'channel'=>$channel,
            'sha256'=>$sha,
            'bytes'=>max(0, (int)($state['bytes'] ?? $state['package_size'] ?? 0)),
            'release_id'=>sanitize_text_field((string)($state['release_id'] ?? '')),
            'published_at'=>sanitize_text_field((string)($state['published_at'] ?? '')),
            'published_by'=>absint($state['published_by'] ?? 0),
            'publisher_service'=>sanitize_text_field((string)($state['publisher_service'] ?? '')),
            'receipt_source'=>$source,
            'contract'=>self::CONTRACT,
        ];
    }

    private static function isStructurallyValid(array $receipt): bool {
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', (string)($receipt['version'] ?? '')) === 1
            && in_array((string)($receipt['channel'] ?? ''), ['stable','beta'], true)
            && preg_match('/^[a-f0-9]{64}$/', (string)($receipt['sha256'] ?? '')) === 1
            && trim((string)($receipt['published_at'] ?? '')) !== '';
    }

    private static function healLocalOption(array $receipt): void {
        $plain = self::withoutRuntimeMeta($receipt);
        if (function_exists('wp_cache_delete')) wp_cache_delete(self::OPTION, 'options');
        update_option(self::OPTION, $plain, false);
        if (is_multisite()) {
            if (function_exists('wp_cache_delete')) wp_cache_delete(self::NETWORK_OPTION, 'site-options');
            update_site_option(self::NETWORK_OPTION, $plain);
        }
    }

    private static function withoutRuntimeMeta(array $receipt): array {
        unset($receipt['receipt_source'], $receipt['contract']);
        return $receipt;
    }

    private static function emptyReceipt(): array {
        return [
            'version'=>'', 'channel'=>'', 'sha256'=>'', 'bytes'=>0, 'release_id'=>'',
            'published_at'=>'', 'published_by'=>0, 'publisher_service'=>'',
            'receipt_source'=>'none', 'contract'=>self::CONTRACT,
        ];
    }
}
