<?php
namespace RoutesPro\Observability;

use RoutesPro\Support\Config;

if (!defined('ABSPATH')) exit;

/**
 * Technical media submission telemetry.
 *
 * Stores counters and sizes only. Filenames, image contents, answers and
 * request payloads are never persisted.
 */
final class MediaTelemetry {
    public const CLEANUP_HOOK = 'fieldflow_media_telemetry_cleanup';
    private static bool $registered = false;
    private static ?bool $tableReady = null;

    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_media_telemetry';
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('init', [self::class, 'schedule']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
    }

    public static function schedule(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + 3 * HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function enabled(): bool {
        return !empty(Config::get('media_telemetry_enabled', 1));
    }

    public static function tableReady(bool $refresh = false): bool {
        if (!$refresh && self::$tableReady !== null) return self::$tableReady;
        global $wpdb;
        $table = self::tableName();
        return self::$tableReady = ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    }

    public static function record(string $status, array $data = []): int {
        if (!self::enabled() || !self::tableReady()) return 0;
        global $wpdb;
        $status = sanitize_key($status);
        if (!in_array($status, ['success','error','queued_offline'], true)) $status = 'error';
        $original = max(0, (int)($data['original_bytes'] ?? 0));
        $optimized = max(0, (int)($data['optimized_bytes'] ?? 0));
        $received = max(0, (int)($data['server_received_bytes'] ?? 0));
        $row = [
            'status' => $status,
            'error_code' => substr(sanitize_key((string)($data['error_code'] ?? '')), 0, 64) ?: null,
            'form_id' => absint($data['form_id'] ?? 0) ?: null,
            'client_id' => absint($data['client_id'] ?? 0) ?: null,
            'project_id' => absint($data['project_id'] ?? 0) ?: null,
            'route_id' => absint($data['route_id'] ?? 0) ?: null,
            'location_id' => absint($data['location_id'] ?? 0) ?: null,
            'user_id' => absint($data['user_id'] ?? get_current_user_id()) ?: null,
            'file_count' => max(0, (int)($data['file_count'] ?? 0)),
            'original_bytes' => $original,
            'optimized_bytes' => $optimized,
            'server_received_bytes' => $received,
            'saved_bytes' => max(0, $original - $optimized),
            'optimized_files' => max(0, (int)($data['optimized_files'] ?? 0)),
            'duplicate_files' => max(0, (int)($data['duplicate_files'] ?? 0)),
            'retry_count' => max(0, (int)($data['retry_count'] ?? 0)),
            'processing_ms' => max(0, (int)($data['processing_ms'] ?? 0)),
            'created_at' => current_time('mysql', true),
        ];
        $ok = $wpdb->insert(self::tableName(), $row, [
            '%s','%s','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%d','%s'
        ]);
        return $ok === false ? 0 : (int)$wpdb->insert_id;
    }

    public static function summary(int $days = 30): array {
        global $wpdb;
        $days = max(1, min(365, $days));
        $base = [
            'table_ready'=>self::tableReady(), 'days'=>$days, 'submissions'=>0, 'successes'=>0, 'failures'=>0,
            'file_count'=>0, 'original_bytes'=>0, 'optimized_bytes'=>0, 'saved_bytes'=>0,
            'optimized_files'=>0, 'duplicate_files'=>0, 'retry_count'=>0, 'avg_processing_ms'=>0,
            'compression_pct'=>0.0, 'failure_rate_pct'=>0.0,
        ];
        if (!$base['table_ready']) return $base;
        $from = gmdate('Y-m-d H:i:s', time() - ($days - 1) * DAY_IN_SECONDS);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) submissions,SUM(status='success') successes,SUM(status='error') failures,COALESCE(SUM(file_count),0) file_count,COALESCE(SUM(original_bytes),0) original_bytes,COALESCE(SUM(optimized_bytes),0) optimized_bytes,COALESCE(SUM(saved_bytes),0) saved_bytes,COALESCE(SUM(optimized_files),0) optimized_files,COALESCE(SUM(duplicate_files),0) duplicate_files,COALESCE(SUM(retry_count),0) retry_count,ROUND(AVG(processing_ms),0) avg_processing_ms FROM " . self::tableName() . " WHERE created_at >= %s",
            $from
        ), ARRAY_A) ?: [];
        foreach (['submissions','successes','failures','file_count','original_bytes','optimized_bytes','saved_bytes','optimized_files','duplicate_files','retry_count','avg_processing_ms'] as $key) $base[$key] = (int)($row[$key] ?? 0);
        $base['compression_pct'] = $base['original_bytes'] > 0 ? round(($base['saved_bytes'] / $base['original_bytes']) * 100, 1) : 0.0;
        $base['failure_rate_pct'] = $base['submissions'] > 0 ? round(($base['failures'] / $base['submissions']) * 100, 1) : 0.0;
        return $base;
    }

    public static function daily(int $days = 14): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $days = max(1, min(90, $days));
        $from = gmdate('Y-m-d 00:00:00', time() - ($days - 1) * DAY_IN_SECONDS);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) usage_day,COUNT(*) submissions,SUM(status='success') successes,SUM(status='error') failures,COALESCE(SUM(file_count),0) file_count,COALESCE(SUM(saved_bytes),0) saved_bytes,COALESCE(SUM(retry_count),0) retry_count,ROUND(AVG(processing_ms),0) avg_processing_ms FROM " . self::tableName() . " WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY usage_day DESC",
            $from
        ), ARRAY_A) ?: [];
    }

    public static function recentFailures(int $limit = 50): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $limit = max(1, min(200, $limit));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT id,error_code,form_id,client_id,project_id,route_id,location_id,file_count,server_received_bytes,retry_count,processing_ms,created_at FROM ' . self::tableName() . " WHERE status='error' ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];
    }

    public static function cleanup(): void {
        global $wpdb;
        if (!self::tableReady()) return;
        $days = max(7, min(3650, (int)Config::get('media_telemetry_retention_days', 90)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::tableName() . ' WHERE created_at < %s', $cutoff));
    }
}
