<?php
namespace RoutesPro\Operations\Queue;

if (!defined('ABSPATH')) exit;

final class QueueRepository {
    private static ?bool $ready = null;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_queue_jobs';
    }

    public static function tableReady(bool $refresh = false): bool {
        if ($refresh) self::$ready = null;
        if (self::$ready !== null) return self::$ready;
        global $wpdb;
        $table = self::table();
        self::$ready = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        return self::$ready;
    }

    public static function enqueue(string $hook, array $args, array $options = []): array {
        if (!self::tableReady()) return ['ok'=>false,'reason'=>'table_missing','job_id'=>0];
        $definition = QueueSettings::allowedHooks()[$hook] ?? null;
        if (!$definition) return ['ok'=>false,'reason'=>'hook_not_allowed','job_id'=>0];
        $args = self::normaliseArgs($args);
        if ($args === null) return ['ok'=>false,'reason'=>'invalid_args','job_id'=>0];
        $expected = max(0, (int)($definition['args'] ?? 0));
        if (count($args) !== $expected) return ['ok'=>false,'reason'=>'invalid_arg_count','job_id'=>0];

        global $wpdb;
        $now = current_time('mysql', true);
        $availableTs = max(time(), (int)($options['available_at'] ?? time()));
        $availableAt = gmdate('Y-m-d H:i:s', $availableTs);
        $idempotency = substr((string)($options['idempotency_key'] ?? hash('sha256', $hook . '|' . wp_json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))), 0, 191);
        $activeKey = hash('sha256', $idempotency);
        $queue = sanitize_key((string)($options['queue'] ?? $definition['queue'] ?? 'default')) ?: 'default';
        $maxAttempts = max(1, min(12, absint($options['max_attempts'] ?? $definition['max_attempts'] ?? 3)));
        $priority = max(-100, min(100, (int)($options['priority'] ?? 0)));
        $source = substr(sanitize_key((string)($options['source'] ?? 'fieldflow')), 0, 32) ?: 'fieldflow';
        $driver = substr(sanitize_key((string)($options['driver'] ?? QueueSettings::effectiveDriver())), 0, 32);
        $meta = self::safeMeta(is_array($options['meta'] ?? null) ? $options['meta'] : []);

        $inserted = $wpdb->insert(self::table(), [
            'job_uuid'=>wp_generate_uuid4(),
            'queue_name'=>$queue,
            'hook_name'=>$hook,
            'status'=>'pending',
            'priority'=>$priority,
            'attempts'=>0,
            'max_attempts'=>$maxAttempts,
            'available_at'=>$availableAt,
            'active_key'=>$activeKey,
            'idempotency_key'=>$idempotency,
            'args_json'=>wp_json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_json'=>$meta ? wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'driver'=>$driver,
            'source'=>$source,
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
        if ($inserted !== false) return ['ok'=>true,'duplicate'=>false,'job_id'=>(int)$wpdb->insert_id,'available_at'=>$availableAt];

        $existing = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE active_key=%s AND status IN ('pending','running') ORDER BY id DESC LIMIT 1",
            $activeKey
        ));
        if ($existing > 0) return ['ok'=>true,'duplicate'=>true,'job_id'=>$existing,'available_at'=>$availableAt];
        return ['ok'=>false,'reason'=>'insert_failed','job_id'=>0];
    }

    public static function observe(string $hook, array $args, int $scheduledAt, string $source = 'observation'): void {
        if (!self::tableReady() || !QueueSettings::isAllowed($hook)) return;
        $args = self::normaliseArgs($args);
        if ($args === null) return;
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert(self::table(), [
            'job_uuid'=>wp_generate_uuid4(),
            'queue_name'=>'observation',
            'hook_name'=>$hook,
            'status'=>'observed',
            'priority'=>0,
            'attempts'=>0,
            'max_attempts'=>0,
            'available_at'=>gmdate('Y-m-d H:i:s', max(time(), $scheduledAt)),
            'completed_at'=>$now,
            'active_key'=>null,
            'idempotency_key'=>substr(hash('sha256', $hook . '|' . wp_json_encode($args) . '|' . $scheduledAt), 0, 191),
            'args_json'=>null,
            'meta_json'=>wp_json_encode(['owner'=>'legacy','arg_count'=>count($args)]),
            'driver'=>'wp_cron',
            'source'=>substr(sanitize_key($source), 0, 32),
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
    }

    public static function claimDue(int $limit, string $token, int $lockTtl): array {
        if (!self::tableReady()) return [];
        self::releaseStaleLocks($lockTtl);
        global $wpdb;
        $limit = max(1, min(50, $limit));
        $now = current_time('mysql', true);
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE status='pending' AND available_at<=%s ORDER BY priority DESC,id ASC LIMIT %d",
            $now,
            $limit
        )) ?: [];
        $jobs = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE " . self::table() . " SET status='running',attempts=attempts+1,lock_token=%s,locked_at=%s,started_at=COALESCE(started_at,%s),updated_at=%s WHERE id=%d AND status='pending' AND available_at<=%s",
                $token,
                $now,
                $now,
                $now,
                $id,
                $now
            ));
            if ($updated !== 1) continue;
            $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d AND lock_token=%s', $id, $token), ARRAY_A);
            if ($row) $jobs[] = $row;
        }
        return $jobs;
    }

    public static function complete(int $jobId, string $token, int $durationMs): bool {
        global $wpdb;
        $now = current_time('mysql', true);
        $updated = $wpdb->update(self::table(), [
            'status'=>'completed','completed_at'=>$now,'duration_ms'=>max(0,$durationMs),'active_key'=>null,
            'lock_token'=>null,'locked_at'=>null,'last_error_code'=>null,'last_error_message'=>null,'updated_at'=>$now,
        ], ['id'=>$jobId,'lock_token'=>$token]);
        return $updated === 1;
    }

    public static function fail(array $job, string $token, string $code, string $message): array {
        global $wpdb;
        $attempts = max(1, (int)($job['attempts'] ?? 1));
        $maxAttempts = max(1, (int)($job['max_attempts'] ?? 1));
        $terminal = $attempts >= $maxAttempts;
        $backoff = [60, 300, 900, 1800, 3600];
        $delay = (int)($backoff[min($attempts - 1, count($backoff) - 1)] ?? 3600);
        $now = current_time('mysql', true);
        $data = [
            'status'=>$terminal ? 'dead' : 'pending',
            'available_at'=>$terminal ? (string)$job['available_at'] : gmdate('Y-m-d H:i:s', time() + $delay),
            'completed_at'=>$terminal ? $now : null,
            'active_key'=>$terminal ? null : (string)$job['active_key'],
            'lock_token'=>null,
            'locked_at'=>null,
            'last_error_code'=>substr(sanitize_key($code), 0, 96),
            'last_error_message'=>substr(wp_strip_all_tags($message), 0, 500),
            'updated_at'=>$now,
        ];
        $wpdb->update(self::table(), $data, ['id'=>(int)$job['id'],'lock_token'=>$token]);
        return ['terminal'=>$terminal,'delay'=>$terminal ? 0 : $delay,'attempts'=>$attempts,'max_attempts'=>$maxAttempts];
    }

    public static function releaseStaleLocks(int $ttl): int {
        if (!self::tableReady()) return 0;
        global $wpdb;
        $before = gmdate('Y-m-d H:i:s', time() - max(120, $ttl));
        $now = current_time('mysql', true);
        return (int)$wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status=IF(attempts>=max_attempts,'dead','pending'),active_key=IF(attempts>=max_attempts,NULL,active_key),completed_at=IF(attempts>=max_attempts,%s,NULL),available_at=IF(attempts>=max_attempts,available_at,%s),lock_token=NULL,locked_at=NULL,last_error_code='stale_lock',last_error_message='O bloqueio do worker expirou e o job foi recuperado.',updated_at=%s WHERE status='running' AND locked_at<%s",
            $now,
            $now,
            $now,
            $before
        ));
    }

    public static function requeueDead(int $jobId): bool {
        if (!self::tableReady() || $jobId <= 0) return false;
        global $wpdb;
        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id=%d AND status='dead'", $jobId), ARRAY_A);
        if (!$job) return false;
        $activeKey = hash('sha256', (string)$job['idempotency_key']);
        $duplicate = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::table() . " WHERE active_key=%s AND status IN ('pending','running') LIMIT 1", $activeKey));
        if ($duplicate > 0) return false;
        $now = current_time('mysql', true);
        $updated = $wpdb->update(self::table(), [
            'status'=>'pending','attempts'=>0,'available_at'=>$now,'started_at'=>null,'completed_at'=>null,'duration_ms'=>0,
            'active_key'=>$activeKey,'lock_token'=>null,'locked_at'=>null,'last_error_code'=>null,'last_error_message'=>null,'source'=>'manual_retry','updated_at'=>$now,
        ], ['id'=>$jobId,'status'=>'dead']);
        return $updated === 1;
    }

    public static function fallbackPendingToLegacy(int $limit = 100): array {
        if (!self::tableReady()) return ['migrated'=>0,'failed'=>0];
        global $wpdb;
        $limit = max(1, min(500, $limit));
        $rows = $wpdb->get_results("SELECT * FROM " . self::table() . " WHERE status='pending' ORDER BY priority DESC,id ASC LIMIT {$limit}", ARRAY_A) ?: [];
        $summary = ['migrated'=>0,'failed'=>0];
        foreach ($rows as $job) {
            $hook = sanitize_key((string)($job['hook_name'] ?? ''));
            $args = json_decode((string)($job['args_json'] ?? ''), true);
            if (!QueueSettings::isAllowed($hook) || !is_array($args)) {
                $summary['failed']++;
                continue;
            }
            $timestamp = !empty($job['available_at']) ? strtotime((string)$job['available_at'] . ' UTC') : time();
            $timestamp = max(time() + 1, (int)$timestamp);
            $scheduled = wp_next_scheduled($hook, array_values($args));
            if (!$scheduled) $scheduled = wp_schedule_single_event($timestamp, $hook, array_values($args));
            if (!$scheduled || is_wp_error($scheduled)) {
                $summary['failed']++;
                continue;
            }
            $now = current_time('mysql', true);
            $updated = $wpdb->update(self::table(), [
                'status'=>'fallback','completed_at'=>$now,'active_key'=>null,'lock_token'=>null,'locked_at'=>null,
                'driver'=>'wp_cron','last_error_code'=>null,'last_error_message'=>'Transferido para o fallback WP-Cron.','updated_at'=>$now,
            ], ['id'=>(int)$job['id'],'status'=>'pending']);
            if ($updated === 1) $summary['migrated']++;
            else $summary['failed']++;
        }
        return $summary;
    }

    public static function stats(): array {
        $empty = ['pending'=>0,'running'=>0,'completed_24h'=>0,'failed_24h'=>0,'dead'=>0,'observed_24h'=>0,'oldest_pending_seconds'=>0];
        if (!self::tableReady()) return $empty;
        global $wpdb;
        $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(status='pending') pending,SUM(status='running') running,SUM(status='completed' AND completed_at>=%s) completed_24h,SUM(status='dead' AND completed_at>=%s) failed_24h,SUM(status='dead') dead,SUM(status='observed' AND completed_at>=%s) observed_24h,MIN(IF(status='pending',available_at,NULL)) oldest_pending FROM " . self::table(),
            $since,$since,$since
        ), ARRAY_A) ?: [];
        $stats = array_merge($empty, array_intersect_key($row, $empty));
        foreach (array_keys($empty) as $key) $stats[$key] = (int)($stats[$key] ?? 0);
        $oldest = !empty($row['oldest_pending']) ? strtotime((string)$row['oldest_pending'] . ' UTC') : 0;
        $stats['oldest_pending_seconds'] = $oldest > 0 ? max(0, time() - $oldest) : 0;
        return $stats;
    }

    public static function recent(int $limit = 50): array {
        if (!self::tableReady()) return [];
        global $wpdb;
        $limit = max(1, min(200, $limit));
        return $wpdb->get_results('SELECT id,job_uuid,queue_name,hook_name,status,priority,attempts,max_attempts,available_at,locked_at,started_at,completed_at,duration_ms,last_error_code,last_error_message,driver,source,created_at,updated_at FROM ' . self::table() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    public static function nextPendingTimestamp(): int {
        if (!self::tableReady()) return 0;
        global $wpdb;
        $next = (string)$wpdb->get_var("SELECT MIN(available_at) FROM " . self::table() . " WHERE status='pending'");
        return $next !== '' ? max(0, strtotime($next . ' UTC')) : 0;
    }

    public static function cleanup(int $retentionDays): int {
        if (!self::tableReady()) return 0;
        global $wpdb;
        $completedBefore = gmdate('Y-m-d H:i:s', time() - (max(7, $retentionDays) * DAY_IN_SECONDS));
        $observedBefore = gmdate('Y-m-d H:i:s', time() - (7 * DAY_IN_SECONDS));
        $deadBefore = gmdate('Y-m-d H:i:s', time() - (180 * DAY_IN_SECONDS));
        $total = 0;
        $total += (int)$wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status='observed' AND completed_at<%s", $observedBefore));
        $total += (int)$wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status IN ('completed','fallback') AND completed_at<%s", $completedBefore));
        $total += (int)$wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status='dead' AND completed_at<%s", $deadBefore));
        return $total;
    }

    private static function normaliseArgs(array $args): ?array {
        $out = [];
        foreach (array_values($args) as $value) {
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $out[] = $value;
                continue;
            }
            if (is_string($value) && strlen($value) <= 10000) {
                $out[] = wp_check_invalid_utf8($value, true);
                continue;
            }
            return null;
        }
        return $out;
    }

    private static function safeMeta(array $meta): array {
        $out = [];
        foreach (array_slice($meta, 0, 20, true) as $key=>$value) {
            $key = sanitize_key((string)$key);
            if ($key === '' || preg_match('/secret|token|password|nonce|api.?key|authorization/', $key)) continue;
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) $out[$key] = $value;
            elseif (is_string($value)) $out[$key] = substr(sanitize_text_field($value), 0, 191);
        }
        return $out;
    }
}
