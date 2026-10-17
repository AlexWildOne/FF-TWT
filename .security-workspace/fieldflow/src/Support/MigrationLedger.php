<?php
namespace RoutesPro\Support;

use wpdb;

if (!defined('ABSPATH')) exit;

/**
 * Durable migration history for deterministic upgrades.
 */
final class MigrationLedger {
    public const VERSION = '1.0.0';

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_schema_migrations';
    }

    public static function install(?wpdb $wpdb = null): bool {
        $wpdb = $wpdb ?: $GLOBALS['wpdb'];
        if (!$wpdb instanceof wpdb) return false;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration_key VARCHAR(191) NOT NULL,
            version VARCHAR(32) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            rows_processed BIGINT UNSIGNED NOT NULL DEFAULT 0,
            cursor_value VARCHAR(191) NULL,
            duration_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            error_code VARCHAR(96) NULL,
            error_message VARCHAR(1000) NULL,
            meta_json LONGTEXT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY migration_key_idx (migration_key),
            KEY version_idx (version),
            KEY status_updated_idx (status, updated_at),
            KEY completed_idx (completed_at)
        ) {$charset};";
        dbDelta($sql);
        return self::tableReady(true);
    }

    public static function tableReady(bool $refresh = false): bool {
        static $ready = null;
        if ($refresh) $ready = null;
        if ($ready !== null) return $ready;
        global $wpdb;
        $table = self::table();
        $ready = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        return $ready;
    }

    public static function begin(string $version, array $meta = []): string {
        if (!self::tableReady() && !self::install()) return '';
        global $wpdb;
        $version = substr(sanitize_text_field($version), 0, 32);
        $key = 'schema:' . $version;
        $now = current_time('mysql', true);
        $existingId = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE migration_key=%s LIMIT 1', $key));
        $data = [
            'version'=>$version,
            'status'=>'running',
            'attempts'=>1,
            'rows_processed'=>0,
            'cursor_value'=>null,
            'duration_ms'=>0,
            'error_code'=>null,
            'error_message'=>null,
            'meta_json'=>$meta ? wp_json_encode(self::safeMeta($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'started_at'=>$now,
            'completed_at'=>null,
            'updated_at'=>$now,
        ];
        if ($existingId > 0) {
            unset($data['attempts']);
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . self::table() . " SET version=%s,status='running',attempts=attempts+1,rows_processed=0,cursor_value=NULL,duration_ms=0,error_code=NULL,error_message=NULL,meta_json=%s,started_at=%s,completed_at=NULL,updated_at=%s WHERE id=%d",
                $version,
                $data['meta_json'],
                $now,
                $now,
                $existingId
            ));
        } else {
            $data['migration_key'] = $key;
            $data['created_at'] = $now;
            $wpdb->insert(self::table(), $data);
        }
        return $key;
    }

    public static function complete(string $key, int $durationMs = 0, int $rowsProcessed = 0, array $meta = []): void {
        if ($key === '' || !self::tableReady()) return;
        global $wpdb;
        $now = current_time('mysql', true);
        $data = [
            'status'=>'completed',
            'duration_ms'=>max(0, $durationMs),
            'rows_processed'=>max(0, $rowsProcessed),
            'error_code'=>null,
            'error_message'=>null,
            'completed_at'=>$now,
            'updated_at'=>$now,
        ];
        if ($meta) $data['meta_json'] = wp_json_encode(self::safeMeta($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $wpdb->update(self::table(), $data, ['migration_key'=>$key]);
    }

    public static function fail(string $key, string $code, string $message, int $durationMs = 0, array $meta = []): void {
        if ($key === '' || !self::tableReady()) return;
        global $wpdb;
        $now = current_time('mysql', true);
        $data = [
            'status'=>'failed',
            'duration_ms'=>max(0, $durationMs),
            'error_code'=>substr(sanitize_key($code), 0, 96),
            'error_message'=>substr(wp_strip_all_tags($message), 0, 1000),
            'completed_at'=>$now,
            'updated_at'=>$now,
        ];
        if ($meta) $data['meta_json'] = wp_json_encode(self::safeMeta($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $wpdb->update(self::table(), $data, ['migration_key'=>$key]);
    }

    public static function recent(int $limit = 30): array {
        if (!self::tableReady()) return [];
        global $wpdb;
        $limit = max(1, min(200, $limit));
        return $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    public static function status(): array {
        if (!self::tableReady()) {
            return ['table_ready'=>false,'completed'=>0,'failed'=>0,'running'=>0,'pending'=>0,'last'=>[]];
        }
        global $wpdb;
        $counts = $wpdb->get_results('SELECT status,COUNT(*) total FROM ' . self::table() . ' GROUP BY status', ARRAY_A) ?: [];
        $out = ['table_ready'=>true,'completed'=>0,'failed'=>0,'running'=>0,'pending'=>0,'paused'=>0,'last'=>[]];
        foreach ($counts as $row) {
            $status = sanitize_key((string)($row['status'] ?? ''));
            if (array_key_exists($status, $out)) $out[$status] = (int)($row['total'] ?? 0);
        }
        $out['last'] = self::recent(1)[0] ?? [];
        return $out;
    }

    public static function staleRunning(int $seconds = 1800): array {
        if (!self::tableReady()) return [];
        global $wpdb;
        $before = gmdate('Y-m-d H:i:s', time() - max(300, $seconds));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . " WHERE status='running' AND updated_at<%s ORDER BY id DESC",
            $before
        ), ARRAY_A) ?: [];
    }

    private static function safeMeta(array $meta): array {
        $allowed = [];
        foreach ($meta as $key => $value) {
            $key = sanitize_key((string)$key);
            if ($key === '' || preg_match('/token|secret|password|cookie|authorization/i', $key)) continue;
            if (is_scalar($value) || $value === null) $allowed[$key] = is_string($value) ? substr(wp_strip_all_tags($value), 0, 500) : $value;
        }
        return $allowed;
    }
}
