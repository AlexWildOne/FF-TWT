<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Persistence boundary for the FieldFlow mail outbox.
 *
 * The repository deliberately owns SQL only. Mail transport, retry policy and
 * WordPress hooks remain in MailDispatchService so callers have one mail seam.
 */
final class MailOutboxRepository {
    public const TABLE_SUFFIX = 'mail_outbox';

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_' . self::TABLE_SUFFIX;
    }

    public static function exists(): bool {
        global $wpdb;
        $table = self::table();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function insert(array $row): int {
        global $wpdb;
        $table = self::table();
        if (!self::exists()) return 0;

        $now = current_time('mysql', true);
        $defaults = [
            'message_id'=>'', 'idempotency_key'=>null, 'source'=>'system', 'email_type'=>'system',
            'client_id'=>0, 'project_id'=>0, 'route_id'=>0, 'entity_type'=>'', 'entity_id'=>0,
            'to_json'=>'[]', 'cc_json'=>'[]', 'bcc_json'=>'[]', 'subject'=>'', 'body'=>'',
            'headers_json'=>'[]', 'attachments_json'=>'[]', 'status'=>'pending', 'attempt_count'=>0,
            'max_attempts'=>4, 'next_attempt_at'=>$now, 'locked_at'=>null, 'locked_by'=>null,
            'last_dispatch_id'=>null, 'last_error'=>null, 'provider_message_id'=>null,
            'attempt_log_json'=>'[]', 'meta_json'=>'{}', 'created_at'=>$now, 'updated_at'=>$now,
            'sent_at'=>null, 'cancelled_at'=>null,
        ];
        $data = array_merge($defaults, $row);
        $ok = $wpdb->insert($table, $data);
        return $ok === false ? 0 : (int)$wpdb->insert_id;
    }

    public static function get(int $id): array {
        if ($id <= 0 || !self::exists()) return [];
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d LIMIT 1', $id), ARRAY_A);
        return is_array($row) ? $row : [];
    }

    public static function findByIdempotencyKey(string $key): array {
        if ($key === '' || !self::exists()) return [];
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE idempotency_key=%s LIMIT 1', $key), ARRAY_A);
        return is_array($row) ? $row : [];
    }

    public static function update(int $id, array $data): bool {
        if ($id <= 0 || !$data || !self::exists()) return false;
        global $wpdb;
        $data['updated_at'] = current_time('mysql', true);
        return $wpdb->update(self::table(), $data, ['id'=>$id]) !== false;
    }

    public static function appendAttempt(int $id, array $attempt): bool {
        $row = self::get($id);
        if (!$row) return false;
        $log = json_decode((string)($row['attempt_log_json'] ?? '[]'), true);
        if (!is_array($log)) $log = [];
        $log[] = $attempt;
        if (count($log) > 12) $log = array_slice($log, -12);
        return self::update($id, ['attempt_log_json'=>wp_json_encode($log)]);
    }

    public static function recoverStaleLocks(int $ttlSeconds): int {
        if (!self::exists()) return 0;
        global $wpdb;
        $ttlSeconds = max(60, $ttlSeconds);
        $cutoff = gmdate('Y-m-d H:i:s', time() - $ttlSeconds);
        $now = current_time('mysql', true);
        $sql = $wpdb->prepare(
            "UPDATE " . self::table() . " SET status='uncertain', locked_at=NULL, locked_by=NULL, next_attempt_at=NULL, updated_at=%s, last_error=CASE WHEN last_error IS NULL OR last_error='' THEN 'Resultado de transporte indeterminado (lock expirado). Confirmar no fornecedor antes de repetir.' ELSE last_error END WHERE status='sending' AND locked_at IS NOT NULL AND locked_at<%s",
            $now, $cutoff
        );
        $updated = $wpdb->query($sql);
        return $updated === false ? 0 : (int)$updated;
    }

    public static function dueIds(int $limit = 10): array {
        if (!self::exists()) return [];
        global $wpdb;
        $limit = max(1, min(50, $limit));
        $now = current_time('mysql', true);
        $sql = $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE status IN ('pending','retry') AND (next_attempt_at IS NULL OR next_attempt_at<=%s) AND locked_at IS NULL ORDER BY COALESCE(next_attempt_at,created_at) ASC,id ASC LIMIT %d",
            $now, $limit
        );
        return array_map('intval', (array)$wpdb->get_col($sql));
    }

    public static function claim(int $id, string $token): bool {
        if ($id <= 0 || $token === '' || !self::exists()) return false;
        global $wpdb;
        $now = current_time('mysql', true);
        $sql = $wpdb->prepare(
            "UPDATE " . self::table() . " SET status='sending',locked_at=%s,locked_by=%s,updated_at=%s WHERE id=%d AND status IN ('pending','retry') AND locked_at IS NULL AND (next_attempt_at IS NULL OR next_attempt_at<=%s)",
            $now, $token, $now, $id, $now
        );
        return (int)$wpdb->query($sql) === 1;
    }

    public static function nextDueTimestamp(): int {
        if (!self::exists()) return 0;
        global $wpdb;
        $value = $wpdb->get_var("SELECT MIN(COALESCE(next_attempt_at,created_at)) FROM " . self::table() . " WHERE status IN ('pending','retry')");
        if (!$value) return 0;
        $ts = strtotime((string)$value . ' UTC');
        return $ts ? (int)$ts : 0;
    }

    public static function summary(): array {
        $base = ['pending'=>0,'sending'=>0,'retry'=>0,'sent'=>0,'failed'=>0,'uncertain'=>0,'cancelled'=>0,'total'=>0,'oldest_pending'=>''];
        if (!self::exists()) return $base;
        global $wpdb;
        $rows = $wpdb->get_results("SELECT status,COUNT(*) AS total FROM " . self::table() . " GROUP BY status", ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $status = sanitize_key((string)($row['status'] ?? ''));
            $count = (int)($row['total'] ?? 0);
            if (array_key_exists($status, $base)) $base[$status] = $count;
            $base['total'] += $count;
        }
        $base['oldest_pending'] = (string)($wpdb->get_var("SELECT MIN(created_at) FROM " . self::table() . " WHERE status IN ('pending','retry','sending')") ?: '');
        return $base;
    }

    public static function list(array $filters = [], int $limit = 100): array {
        if (!self::exists()) return [];
        global $wpdb;
        $where = ['1=1'];
        $args = [];
        $status = sanitize_key((string)($filters['status'] ?? ''));
        if ($status !== '') { $where[] = 'status=%s'; $args[] = $status; }
        $source = sanitize_key((string)($filters['source'] ?? ''));
        if ($source !== '') { $where[] = 'source=%s'; $args[] = $source; }
        $clientId = absint($filters['client_id'] ?? 0);
        if ($clientId > 0) { $where[] = 'client_id=%d'; $args[] = $clientId; }
        $projectId = absint($filters['project_id'] ?? 0);
        if ($projectId > 0) { $where[] = 'project_id=%d'; $args[] = $projectId; }
        $limit = max(1, min(250, $limit));
        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit;
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function terminalAttachmentRows(int $olderThanSeconds, int $limit = 50): array {
        if (!self::exists()) return [];
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(DAY_IN_SECONDS, $olderThanSeconds));
        $limit = max(1, min(200, $limit));
        $sql = $wpdb->prepare(
            "SELECT id,attachments_json FROM " . self::table() . " WHERE status IN ('sent','failed','cancelled') AND attachments_json IS NOT NULL AND attachments_json NOT IN ('','[]') AND updated_at<%s ORDER BY id ASC LIMIT %d",
            $cutoff, $limit
        );
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function purgeTerminalRows(int $olderThanSeconds, int $limit = 200): int {
        if (!self::exists()) return 0;
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(30 * DAY_IN_SECONDS, $olderThanSeconds));
        $limit = max(1, min(500, $limit));
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE status IN ('sent','cancelled') AND (attachments_json IS NULL OR attachments_json IN ('','[]')) AND updated_at<%s ORDER BY id ASC LIMIT %d",
            $cutoff, $limit
        )) ?: [];
        $ids = array_values(array_filter(array_map('absint', $ids)));
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return (int)$wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE id IN ({$placeholders})", ...$ids));
    }

}
