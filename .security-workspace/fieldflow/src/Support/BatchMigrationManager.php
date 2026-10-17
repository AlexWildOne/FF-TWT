<?php
namespace RoutesPro\Support;

use RoutesPro\Operations\Queue\QueueManager;
use wpdb;

if (!defined('ABSPATH')) exit;

/**
 * Resumable batch framework for schema diagnostics and future heavy migrations.
 * The 2.2.266 built-in task is read-only.
 */
final class BatchMigrationManager {
    public const VERSION = '1.0.0';
    public const HOOK = 'fieldflow_schema_batch_run';
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action(self::HOOK, [self::class, 'handle'], 10, 1);
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_migration_batches';
    }

    public static function install(?wpdb $wpdb = null): bool {
        $wpdb = $wpdb ?: $GLOBALS['wpdb'];
        if (!$wpdb instanceof wpdb) return false;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_uuid CHAR(36) NOT NULL,
            task_key VARCHAR(96) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            cursor_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
            batch_size SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            processed_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            initiated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            last_error_code VARCHAR(96) NULL,
            last_error_message VARCHAR(1000) NULL,
            lock_token CHAR(36) NULL,
            locked_at DATETIME NULL,
            result_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY batch_uuid_idx (batch_uuid),
            KEY task_status_idx (task_key, status),
            KEY status_updated_idx (status, updated_at),
            KEY locked_idx (status, locked_at),
            KEY initiated_idx (initiated_by)
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

    public static function startSchemaAudit(int $actorId = 0, int $batchSize = 8): array {
        return self::start('schema_audit_full', $actorId, $batchSize, count(SchemaManifest::tables()));
    }

    public static function start(string $taskKey, int $actorId = 0, int $batchSize = 10, int $totalRows = 0, array $meta = []): array {
        if (!self::tableReady() && !self::install()) return ['ok'=>false,'reason'=>'table_missing','batch_id'=>0];
        $taskKey = sanitize_key($taskKey);
        $tasks = self::tasks();
        if ($taskKey === '' || !isset($tasks[$taskKey])) return ['ok'=>false,'reason'=>'task_not_allowed','batch_id'=>0];

        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . " WHERE task_key=%s AND status IN ('pending','running','paused') ORDER BY id DESC LIMIT 1",
            $taskKey
        ), ARRAY_A);
        if ($existing) {
            if ((string)$existing['status'] !== 'paused') {
                self::schedule((int)$existing['id'], 1, (int)$existing['cursor_value']);
            }
            return ['ok'=>true,'duplicate'=>true,'batch_id'=>(int)$existing['id'],'status'=>(string)$existing['status']];
        }

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(self::table(), [
            'batch_uuid'=>wp_generate_uuid4(),
            'task_key'=>$taskKey,
            'status'=>'pending',
            'cursor_value'=>0,
            'batch_size'=>max(1, min(500, $batchSize)),
            'processed_rows'=>0,
            'total_rows'=>max(0, $totalRows),
            'attempts'=>0,
            'initiated_by'=>max(0, $actorId),
            'result_json'=>null,
            'meta_json'=>$meta ? wp_json_encode(self::safeMeta($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
        if ($inserted === false) return ['ok'=>false,'reason'=>'insert_failed','batch_id'=>0];
        $batchId = (int)$wpdb->insert_id;
        self::schedule($batchId, 1, 0);
        return ['ok'=>true,'duplicate'=>false,'batch_id'=>$batchId,'status'=>'pending'];
    }

    public static function handle(int $batchId): void {
        if ($batchId <= 0 || !self::tableReady()) return;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d LIMIT 1', $batchId), ARRAY_A);
        if (!$row || in_array((string)$row['status'], ['completed','failed','cancelled'], true)) return;

        $taskKey = sanitize_key((string)$row['task_key']);
        $tasks = self::tasks();
        if (!isset($tasks[$taskKey])) {
            self::fail($batchId, 'task_not_allowed', 'A tarefa em lote deixou de estar autorizada.');
            return;
        }

        $now = current_time('mysql', true);
        $token = wp_generate_uuid4();
        $staleBefore = gmdate('Y-m-d H:i:s', time() - 900);
        $claimed = $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . " SET status='running',attempts=attempts+1,lock_token=%s,locked_at=%s,started_at=COALESCE(started_at,%s),updated_at=%s WHERE id=%d AND (status='pending' OR (status='running' AND locked_at<%s))",
            $token,
            $now,
            $now,
            $now,
            $batchId,
            $staleBefore
        ));
        if ($claimed !== 1) return;
        $row['attempts'] = (int)$row['attempts'] + 1;
        $row['lock_token'] = $token;
        $row['locked_at'] = $now;

        try {
            $handler = $tasks[$taskKey]['handler'];
            $result = call_user_func($handler, $row);
            if (!is_array($result)) throw new \RuntimeException('A tarefa em lote devolveu um resultado inválido.');
            self::applyResult($batchId, $row, $result, $token);
        } catch (\Throwable $error) {
            $meta = json_decode((string)($row['meta_json'] ?? ''), true);
            if (!is_array($meta)) $meta = [];
            $errorCount = max(0, (int)($meta['error_count'] ?? 0)) + 1;
            $meta['error_count'] = $errorCount;
            if ($errorCount >= 3) {
                self::fail($batchId, 'batch_exception', $error->getMessage(), $token, $meta);
            } else {
                $wpdb->update(self::table(), [
                    'status'=>'pending',
                    'last_error_code'=>'batch_exception',
                    'last_error_message'=>substr(wp_strip_all_tags($error->getMessage()), 0, 1000),
                    'lock_token'=>null,
                    'locked_at'=>null,
                    'meta_json'=>wp_json_encode(self::safeMeta($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at'=>current_time('mysql', true),
                ], ['id'=>$batchId,'lock_token'=>$token]);
                self::schedule($batchId, 60 * $errorCount, (int)$row['cursor_value']);
            }
        }
    }

    public static function runNow(int $batchId): array {
        self::handle($batchId);
        return self::find($batchId);
    }

    public static function pause(int $batchId): bool {
        if (!self::tableReady() || $batchId <= 0) return false;
        global $wpdb;
        return $wpdb->update(self::table(), ['status'=>'paused','updated_at'=>current_time('mysql', true)], ['id'=>$batchId]) !== false;
    }

    public static function resume(int $batchId): bool {
        if (!self::tableReady() || $batchId <= 0) return false;
        global $wpdb;
        $current = self::find($batchId);
        if (!$current) return false;
        $updated = $wpdb->update(self::table(), [
            'status'=>'pending',
            'last_error_code'=>null,
            'last_error_message'=>null,
            'lock_token'=>null,
            'locked_at'=>null,
            'completed_at'=>null,
            'meta_json'=>self::clearErrorCount((string)($current['meta_json'] ?? '')),
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$batchId]);
        if ($updated === false) return false;
        $row = self::find($batchId);
        self::schedule($batchId, 1, (int)($row['cursor_value'] ?? 0));
        return true;
    }

    public static function find(int $batchId): array {
        if (!self::tableReady() || $batchId <= 0) return [];
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d LIMIT 1', $batchId), ARRAY_A) ?: [];
    }

    public static function recent(int $limit = 20): array {
        if (!self::tableReady()) return [];
        global $wpdb;
        $limit = max(1, min(100, $limit));
        return $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    public static function status(): array {
        if (!self::tableReady()) return ['table_ready'=>false,'pending'=>0,'running'=>0,'failed'=>0,'completed'=>0,'last'=>[]];
        global $wpdb;
        $rows = $wpdb->get_results('SELECT status,COUNT(*) total FROM ' . self::table() . ' GROUP BY status', ARRAY_A) ?: [];
        $out = ['table_ready'=>true,'pending'=>0,'running'=>0,'paused'=>0,'failed'=>0,'completed'=>0,'last'=>[]];
        foreach ($rows as $row) {
            $status = sanitize_key((string)($row['status'] ?? ''));
            if (array_key_exists($status, $out)) $out[$status] = (int)($row['total'] ?? 0);
        }
        $out['last'] = self::recent(1)[0] ?? [];
        return $out;
    }

    private static function tasks(): array {
        $tasks = [
            'schema_audit_full' => [
                'label'=>'Auditoria integral do schema',
                'read_only'=>true,
                'handler'=>[self::class, 'processSchemaAudit'],
            ],
            'location_deduplication' => [
                'label'=>'Deduplicação progressiva de locais',
                'read_only'=>false,
                'handler'=>[self::class, 'processLocationDeduplication'],
            ],
            'record_backfill' => [
                'label'=>'Backfill progressivo do histórico de formulários',
                'read_only'=>false,
                'handler'=>[self::class, 'processRecordBackfill'],
            ],
            'route_optimization_reconciliation' => [
                'label'=>'Reconciliação de rotas manuais',
                'read_only'=>false,
                'handler'=>[self::class, 'processRouteOptimizationReconciliation'],
            ],
        ];
        $filtered = apply_filters('fieldflow_schema_batch_tasks', $tasks);
        return is_array($filtered) ? $filtered : $tasks;
    }

    private static function processSchemaAudit(array $row): array {
        $suffixes = array_keys(SchemaManifest::tables());
        $cursor = max(0, (int)($row['cursor_value'] ?? 0));
        $batchSize = max(1, min(50, (int)($row['batch_size'] ?? 8)));
        $chunk = array_slice($suffixes, $cursor, $batchSize);
        $partial = SchemaIntegrity::auditSuffixes($chunk, 'batch');
        $next = $cursor + count($chunk);
        return [
            'done'=>$next >= count($suffixes),
            'cursor'=>$next,
            'processed'=>count($chunk),
            'total'=>count($suffixes),
            'partial'=>$partial,
        ];
    }

    private static function processLocationDeduplication(array $row): array {
        if (!class_exists('\RoutesPro\Services\LocationDeduplicator')) {
            return ['done'=>true,'cursor'=>(int)$row['cursor_value'],'processed'=>0,'total'=>0,'data'=>['reason'=>'service_missing']];
        }
        $limit = max(1, min(25, (int)($row['batch_size'] ?? 5)));
        $result = \RoutesPro\Services\LocationDeduplicator::merge_groups_batch($limit);
        if ((int)($result['total_before'] ?? 0) > 0 && (int)($result['processed'] ?? 0) === 0) {
            throw new \RuntimeException('A deduplicação não conseguiu avançar no lote atual.');
        }
        return [
            'done'=>empty($result['has_more']),
            'cursor'=>(int)$row['cursor_value'] + (int)($result['processed'] ?? 0),
            'processed'=>(int)($result['processed'] ?? 0),
            'total'=>(int)($result['total_before'] ?? 0),
            'data'=>$result,
        ];
    }

    private static function processRouteOptimizationReconciliation(array $row): array {
        if (!class_exists('\RoutesPro\Services\RouteOptimizationReconciliationService')) {
            return ['done'=>true,'cursor'=>(int)$row['cursor_value'],'processed'=>0,'total'=>0,'data'=>['reason'=>'service_missing']];
        }
        return \RoutesPro\Services\RouteOptimizationReconciliationService::processBatch($row);
    }

    private static function processRecordBackfill(array $row): array {
        if (!class_exists('\RoutesPro\Forms\RecordService')) {
            return ['done'=>true,'cursor'=>(int)$row['cursor_value'],'processed'=>0,'total'=>0,'data'=>['reason'=>'service_missing']];
        }
        $limit = max(25, min(500, (int)($row['batch_size'] ?? 250)));
        $before = \RoutesPro\Forms\RecordService::count_backfill_candidates();
        if ($before <= 0) {
            return ['done'=>true,'cursor'=>(int)$row['cursor_value'],'processed'=>0,'total'=>(int)$row['processed_rows'],'data'=>['remaining'=>0]];
        }
        $processed = \RoutesPro\Forms\RecordService::backfill_existing_submissions($limit);
        $remaining = \RoutesPro\Forms\RecordService::count_backfill_candidates();
        if ($processed <= 0 && $remaining > 0) {
            throw new \RuntimeException('O backfill encontrou registos pendentes, mas não conseguiu avançar.');
        }
        $cursor = (int)$row['cursor_value'] + $processed;
        return [
            'done'=>$remaining <= 0,
            'cursor'=>$cursor,
            'processed'=>$processed,
            'total'=>max((int)($row['total_rows'] ?? 0), $cursor + $remaining),
            'data'=>['processed'=>$processed,'batch_size'=>$limit,'remaining'=>$remaining,'before'=>$before],
        ];
    }

    private static function applyResult(int $batchId, array $row, array $result, string $token): void {
        global $wpdb;
        if ((string)($row['task_key'] ?? '') !== 'schema_audit_full') {
            self::applyGenericResult($batchId, $row, $result, $token);
            return;
        }
        $stored = json_decode((string)($row['result_json'] ?? ''), true);
        if (!is_array($stored)) {
            $stored = [
                'mode'=>'full',
                'status'=>'healthy',
                'summary'=>'',
                'counts'=>[
                    'checked'=>0,'healthy'=>0,'warning'=>0,'critical'=>0,'missing_tables'=>0,
                    'missing_columns'=>0,'type_mismatches'=>0,'nullability_mismatches'=>0,
                    'missing_indexes'=>0,'index_mismatches'=>0,
                ],
                'tables'=>[],
            ];
        }
        $partial = is_array($result['partial'] ?? null) ? $result['partial'] : [];
        foreach ((array)($partial['tables'] ?? []) as $suffix => $table) $stored['tables'][$suffix] = $table;
        foreach ($stored['counts'] as $key => $value) {
            if (isset($partial['counts'][$key])) $stored['counts'][$key] = (int)$value + (int)$partial['counts'][$key];
        }
        $stored['status'] = (int)$stored['counts']['critical'] > 0 ? 'critical' : ((int)$stored['counts']['warning'] > 0 ? 'warning' : 'healthy');
        $stored['summary'] = $stored['status'] === 'healthy'
            ? 'Schema saudável nas tabelas verificadas.'
            : ($stored['status'] === 'critical'
                ? 'Foram encontradas divergências críticas em ' . (int)$stored['counts']['critical'] . ' tabelas.'
                : 'Foram encontrados avisos de schema em ' . (int)$stored['counts']['warning'] . ' tabelas.');

        $done = !empty($result['done']);
        $now = current_time('mysql', true);
        $data = [
            'status'=>$done ? 'completed' : 'pending',
            'cursor_value'=>max(0, (int)($result['cursor'] ?? 0)),
            'processed_rows'=>(int)$row['processed_rows'] + max(0, (int)($result['processed'] ?? 0)),
            'total_rows'=>max(0, (int)($result['total'] ?? $row['total_rows'])),
            'last_error_code'=>null,
            'last_error_message'=>null,
            'lock_token'=>null,
            'locked_at'=>null,
            'meta_json'=>self::clearErrorCount((string)($row['meta_json'] ?? '')),
            'result_json'=>wp_json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'completed_at'=>$done ? $now : null,
            'updated_at'=>$now,
        ];
        $wpdb->update(self::table(), $data, ['id'=>$batchId,'lock_token'=>$token]);

        if ($done) {
            $stored['generated_at'] = $now;
            $stored['duration_ms'] = self::durationMs((string)($row['started_at'] ?? ''), $now);
            $stored['plugin_version'] = defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '';
            $stored['manifest_version'] = SchemaManifest::VERSION;
            $stored['audit_version'] = SchemaIntegrity::VERSION;
            $stored['batch_id'] = $batchId;
            update_option(SchemaIntegrity::REPORT_OPTION, $stored, false);
            return;
        }
        self::schedule($batchId, 2, (int)$data['cursor_value']);
    }

    private static function applyGenericResult(int $batchId, array $row, array $result, string $token): void {
        global $wpdb;
        $stored = json_decode((string)($row['result_json'] ?? ''), true);
        if (!is_array($stored)) $stored = ['runs'=>[],'processed_total'=>0];
        $processed = max(0, (int)($result['processed'] ?? 0));
        $stored['processed_total'] = (int)($stored['processed_total'] ?? 0) + $processed;
        $stored['runs'][] = [
            'at'=>current_time('mysql', true),
            'processed'=>$processed,
            'data'=>self::safeMeta(is_array($result['data'] ?? null) ? $result['data'] : []),
        ];
        $stored['runs'] = array_slice($stored['runs'], -50);
        $done = !empty($result['done']);
        $now = current_time('mysql', true);
        $total = max((int)($row['total_rows'] ?? 0), (int)($result['total'] ?? 0));
        $cursor = max((int)($row['cursor_value'] ?? 0), (int)($result['cursor'] ?? 0));
        $data = [
            'status'=>$done ? 'completed' : 'pending',
            'cursor_value'=>$cursor,
            'processed_rows'=>(int)$row['processed_rows'] + $processed,
            'total_rows'=>$total,
            'last_error_code'=>null,
            'last_error_message'=>null,
            'lock_token'=>null,
            'locked_at'=>null,
            'meta_json'=>self::clearErrorCount((string)($row['meta_json'] ?? '')),
            'result_json'=>wp_json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'completed_at'=>$done ? $now : null,
            'updated_at'=>$now,
        ];
        $wpdb->update(self::table(), $data, ['id'=>$batchId,'lock_token'=>$token]);
        if (!$done) self::schedule($batchId, 2, $cursor);
    }

    private static function clearErrorCount(string $json): ?string {
        $meta = json_decode($json, true);
        if (!is_array($meta)) return $json !== '' ? $json : null;
        unset($meta['error_count']);
        return $meta ? wp_json_encode(self::safeMeta($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    private static function fail(int $batchId, string $code, string $message, string $token = '', array $meta = []): void {
        global $wpdb;
        $where = ['id'=>$batchId];
        if ($token !== '') $where['lock_token'] = $token;
        $wpdb->update(self::table(), [
            'status'=>'failed',
            'last_error_code'=>substr(sanitize_key($code), 0, 96),
            'last_error_message'=>substr(wp_strip_all_tags($message), 0, 1000),
            'lock_token'=>null,
            'locked_at'=>null,
            'meta_json'=>$meta ? wp_json_encode(self::safeMeta($meta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'completed_at'=>current_time('mysql', true),
            'updated_at'=>current_time('mysql', true),
        ], $where);
    }

    private static function schedule(int $batchId, int $delay, int $cursor): void {
        QueueManager::schedule(self::HOOK, [$batchId], max(1, $delay), [
            'queue'=>'schema',
            'max_attempts'=>3,
            'priority'=>50,
            'source'=>'schema_integrity',
            'idempotency_key'=>'schema_batch:' . $batchId . ':' . $cursor,
            'meta'=>['batch_id'=>$batchId,'cursor'=>$cursor],
        ]);
    }

    private static function durationMs(string $startedAt, string $completedAt): int {
        $start = $startedAt !== '' ? strtotime($startedAt . ' UTC') : 0;
        $end = $completedAt !== '' ? strtotime($completedAt . ' UTC') : 0;
        return $start > 0 && $end >= $start ? ($end - $start) * 1000 : 0;
    }

    private static function safeMeta(array $meta): array {
        $out = [];
        foreach ($meta as $key => $value) {
            $safeKey = is_int($key) ? $key : sanitize_key((string)$key);
            if ($safeKey === '' || (is_string($safeKey) && preg_match('/token|secret|password|cookie|authorization/i', $safeKey))) continue;
            if (is_array($value)) {
                $out[$safeKey] = self::safeMeta($value);
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[$safeKey] = is_string($value) ? substr(wp_strip_all_tags($value), 0, 500) : $value;
            }
        }
        return $out;
    }
}
