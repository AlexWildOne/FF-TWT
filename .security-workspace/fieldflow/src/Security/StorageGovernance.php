<?php
namespace RoutesPro\Security;

if (!defined('ABSPATH')) exit;

/**
 * Deterministic governance for FieldFlow private temporary storage.
 *
 * Only expired resumable-upload sessions are deleted here. Evidence, published
 * attachments, active drafts and report exports are deliberately out of scope.
 */
final class StorageGovernance {
    public const VERSION = '1.0.0';
    public const CONTRACT = 'fieldflow-private-storage-governance-v1';
    public const CRON_HOOK = 'fieldflow_private_storage_governance_daily';
    private const RESUMABLE_STALE_GRACE = 2 * DAY_IN_SECONDS;
    private const MAX_CLEANUP_DIRS = 250;

    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('init', [self::class, 'ensureSchedule']);
        add_action(self::CRON_HOOK, [self::class, 'cleanupExpiredTemporaryStorage']);
    }

    public static function ensureSchedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + (3 * HOUR_IN_SECONDS), 'daily', self::CRON_HOOK);
        }
    }

    public static function cleanupExpiredTemporaryStorage(): array {
        $result = ['scanned' => 0, 'eligible' => 0, 'deleted' => 0, 'bytes_freed' => 0];
        $base = self::resumableRoot();
        if (!is_dir($base)) return $result;

        $dirs = glob(trailingslashit($base) . '*', GLOB_ONLYDIR) ?: [];
        $now = time();
        foreach ($dirs as $dir) {
            if ($result['scanned'] >= self::MAX_CLEANUP_DIRS) break;
            $result['scanned']++;
            $uploadId = basename($dir);
            if (!preg_match('/^[a-f0-9]{48}$/', $uploadId)) continue;

            $manifest = self::manifest($dir);
            $expires = absint($manifest['expires_at'] ?? 0);
            $mtime = (int)(@filemtime($dir) ?: 0);
            $expired = $expires > 0
                ? $expires <= $now
                : ($mtime > 0 && $mtime <= ($now - self::RESUMABLE_STALE_GRACE));
            if (!$expired) continue;

            $result['eligible']++;
            $bytes = self::directoryBytes($dir, 5000);
            if (self::deleteDirectory($dir)) {
                $result['deleted']++;
                $result['bytes_freed'] += $bytes;
            }
        }

        update_option('fieldflow_private_storage_last_cleanup', [
            'ran_at' => current_time('mysql', true),
            'scanned' => (int)$result['scanned'],
            'eligible' => (int)$result['eligible'],
            'deleted' => (int)$result['deleted'],
            'bytes_freed' => (int)$result['bytes_freed'],
        ], false);
        return $result;
    }

    public static function diagnostics(): array {
        $roots = [
            'resumable' => self::resumableRoot(),
            'mail_outbox' => trailingslashit(WP_CONTENT_DIR) . 'fieldflow-private-mail-outbox',
            'drafts' => self::draftRoot(),
        ];
        $items = [];
        $totalBytes = 0;
        $totalFiles = 0;
        foreach ($roots as $key => $path) {
            $metrics = self::rootMetrics($path);
            $items[$key] = $metrics;
            $totalBytes += (int)$metrics['bytes'];
            $totalFiles += (int)$metrics['files'];
        }
        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'scheduled' => (bool)wp_next_scheduled(self::CRON_HOOK),
            'cleanup_scope' => 'expired_resumable_only',
            'preserves_evidence' => true,
            'preserves_active_drafts' => true,
            'total_bytes' => $totalBytes,
            'total_mb' => round($totalBytes / MB_IN_BYTES, 2),
            'total_files' => $totalFiles,
            'roots' => $items,
            'last_cleanup' => get_option('fieldflow_private_storage_last_cleanup', []),
        ];
    }

    private static function resumableRoot(): string {
        return trailingslashit(WP_CONTENT_DIR) . 'fieldflow-private-resumable';
    }

    private static function draftRoot(): string {
        $uploads = wp_upload_dir();
        return trailingslashit((string)($uploads['basedir'] ?? '')) . 'fieldflow-private-drafts';
    }

    private static function manifest(string $dir): array {
        $path = trailingslashit($dir) . 'manifest.json';
        if (!is_file($path) || !is_readable($path)) return [];
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    private static function rootMetrics(string $root): array {
        if ($root === '' || !is_dir($root)) return ['exists' => false, 'bytes' => 0, 'mb' => 0.0, 'files' => 0, 'directories' => 0];
        $bytes = 0;
        $files = 0;
        $directories = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($directories + $files >= 20000) break;
                if ($item->isDir()) { $directories++; continue; }
                if ($item->isFile()) {
                    $files++;
                    $bytes += max(0, (int)$item->getSize());
                }
            }
        } catch (\Throwable $e) {
            return ['exists' => true, 'bytes' => $bytes, 'mb' => round($bytes / MB_IN_BYTES, 2), 'files' => $files, 'directories' => $directories, 'partial' => true];
        }
        return ['exists' => true, 'bytes' => $bytes, 'mb' => round($bytes / MB_IN_BYTES, 2), 'files' => $files, 'directories' => $directories, 'partial' => false];
    }

    private static function directoryBytes(string $dir, int $limit): int {
        $bytes = 0;
        $seen = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if (++$seen > $limit) break;
                if ($item->isFile()) $bytes += max(0, (int)$item->getSize());
            }
        } catch (\Throwable $e) {
            return $bytes;
        }
        return $bytes;
    }

    private static function deleteDirectory(string $dir): bool {
        $root = wp_normalize_path(trailingslashit(self::resumableRoot()));
        $target = wp_normalize_path(trailingslashit($dir));
        if ($root === '' || strpos($target, $root) !== 0 || $target === $root) return false;
        if (!is_dir($dir)) return true;

        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                if (!self::deleteDirectory($path)) return false;
            } elseif (is_file($path) || is_link($path)) {
                if (!@unlink($path) && file_exists($path)) return false;
            }
        }
        return @rmdir($dir) || !is_dir($dir);
    }
}
