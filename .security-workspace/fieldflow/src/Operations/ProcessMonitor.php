<?php
namespace RoutesPro\Operations;

if (!defined('ABSPATH')) exit;

final class ProcessMonitor {
    public const CLEANUP_HOOK = 'fieldflow_process_monitor_cleanup';
    private const STALE_MINUTES = 30;
    private static bool $registered = false;
    private static array $active = [];

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_process_runs';
    }

    public static function circuitsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_process_circuits';
    }

    public static function definitions(): array {
        return [
            'webhook_worker' => self::definition('Fila de webhooks', 'fieldflow_webhook_worker', 'integrações', 0, true, true, 60, ['autoRetry' => true, 'maxAttempts' => 3, 'circuit' => true]),
            'billing_maintenance' => self::definition('Manutenção de faturação', 'fieldflow_billing_maintenance', 'faturação', 0, true, true, HOUR_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3, 'circuit' => true]),
            'observability_hourly' => self::definition('Verificação de observabilidade', 'fieldflow_observability_hourly', 'observabilidade', 0, true, true, HOUR_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3]),
            'observability_cleanup' => self::definition('Limpeza de observabilidade', 'fieldflow_observability_cleanup', 'observabilidade', 0, true, true, DAY_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3]),
            'load_readiness_cleanup' => self::definition('Limpeza de métricas de desempenho', 'fieldflow_load_readiness_cleanup', 'observabilidade', 0, true, true, DAY_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3]),
            'queue_watchdog' => self::definition('Watchdog da fila moderna', 'fieldflow_queue_watchdog', 'filas', 0, true, true, 5 * MINUTE_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3]),
            'queue_cleanup' => self::definition('Limpeza da fila moderna', 'fieldflow_queue_cleanup', 'filas', 0, true, true, DAY_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3]),
            'queue_dispatch' => self::definition('Dispatcher da fila moderna', 'fieldflow_queue_dispatch', 'filas', 0, false, true, 0, ['autoRetry' => true, 'maxAttempts' => 3]),
            'form_draft_cleanup' => self::definition('Limpeza de rascunhos', 'fieldflow_form_draft_cleanup', 'formulários', 0, true, true, DAY_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3]),
            'mobile_session_cleanup' => self::definition('Limpeza de sessões mobile', 'fieldflow_mobile_session_cleanup', 'mobile', 0, true, true, DAY_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3]),
            'mobile_webview_cleanup' => self::definition('Limpeza de sessões WebView', 'fieldflow_mobile_webview_cleanup', 'mobile', 0, true, true, DAY_IN_SECONDS, ['autoRetry' => true, 'maxAttempts' => 3]),
            'report_automation' => self::definition('Relatório automático', 'fieldflow_report_automation_run', 'relatórios', 1, false, false, 0, ['circuit' => true]),
            'briefing_ai' => self::definition('Processamento IA de briefing', 'fieldflow_briefing_ai_process', 'inteligência artificial', 1, false, true, 0, ['circuit' => true]),
            'submission_cardex' => self::definition('Reconciliação de Cardex', 'fieldflow_reconcile_submission_cardex', 'formulários', 1, false, true, 0),
            'submission_history' => self::definition('Reconciliação de histórico', 'fieldflow_reconcile_submission_history', 'formulários', 1, false, true, 0),
            'submission_route' => self::definition('Reconciliação de rota', 'fieldflow_reconcile_route_after_submission', 'rotas', 1, false, true, 0),
            'submission_integrations' => self::definition('Integrações pós-submissão', 'fieldflow_submission_post_commit', 'integrações', 3, false, false, 0, ['circuit' => true]),
            'submission_side_effect_retry' => self::definition('Retry isolado de side effect', 'fieldflow_submission_side_effect_retry', 'integrações', 3, false, true, 0, ['autoRetry' => false, 'maxAttempts' => 4, 'circuit' => true]),
            'submitted_draft_delete' => self::definition('Remoção de rascunho submetido', 'fieldflow_delete_submitted_draft', 'formulários', 2, false, true, 0, ['autoRetry' => true, 'maxAttempts' => 3]),
        ];
    }

    private static function definition(string $label, string $hook, string $type, int $acceptedArgs, bool $manual, bool $retryable, int $expectedInterval, array $recovery = []): array {
        return array_merge(compact('label', 'hook', 'type', 'acceptedArgs', 'manual', 'retryable', 'expectedInterval'), [
            'autoRetry' => false,
            'maxAttempts' => 0,
            'circuit' => false,
            'backoff' => [60, 300, 900],
        ], $recovery);
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;

        foreach (self::definitions() as $key => $definition) {
            $accepted = max(0, (int)$definition['acceptedArgs']);
            add_action((string)$definition['hook'], static function (...$args) use ($key): void {
                ProcessMonitor::begin($key, $args);
            }, 1, $accepted);
            add_action((string)$definition['hook'], static function (...$args) use ($key): void {
                ProcessMonitor::complete($key, $args);
            }, PHP_INT_MAX, $accepted);
        }

        add_action('init', [self::class, 'ensureCleanupSchedule'], 30);
        add_filter('schedule_event', [self::class, 'captureScheduledEvent'], 10, 1);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
        add_action('fieldflow_observability_hourly', [self::class, 'auditOverdue'], 90);
        add_action('fieldflow_observability_hourly', [self::class, 'recoverBlocked'], 95);
        register_shutdown_function([self::class, 'captureShutdown']);
    }

    public static function ensureCleanupSchedule(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
        self::syncScheduledEvents();
    }

    public static function tableReady(): bool {
        global $wpdb;
        $table = self::table();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function circuitsReady(): bool {
        global $wpdb;
        $table = self::circuitsTable();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function captureScheduledEvent($event) {
        if (!is_object($event) || !self::tableReady()) return $event;
        $hook = (string)($event->hook ?? '');
        $timestamp = (int)($event->timestamp ?? 0);
        if ($hook === '' || $timestamp <= 0) return $event;
        foreach (self::definitions() as $key => $definition) {
            if ((string)$definition['hook'] !== $hook) continue;
            self::recordPending($key, (array)($event->args ?? []), $timestamp);
            break;
        }
        return $event;
    }

    private static function recordPending(string $processKey, array $args, int $timestamp): int {
        $definition = self::definitions()[$processKey] ?? null;
        if (!$definition || !self::tableReady()) return 0;
        global $wpdb;
        $safeArgs = self::safeArgs($args);
        $hash = self::instanceHash((string)$definition['hook'], $safeArgs);
        $scheduledAt = gmdate('Y-m-d H:i:s', $timestamp);
        $existing = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE instance_hash=%s AND status='pending' AND scheduled_at BETWEEN %s AND %s ORDER BY id DESC LIMIT 1",
            $hash,
            gmdate('Y-m-d H:i:s', $timestamp - 5),
            gmdate('Y-m-d H:i:s', $timestamp + 5)
        ));
        if ($existing > 0) return $existing;
        $attempt = 1 + (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE instance_hash=%s', $hash));
        $now = current_time('mysql', true);
        $wpdb->insert(self::table(), [
            'run_uuid' => wp_generate_uuid4(),
            'process_key' => $processKey,
            'hook_name' => (string)$definition['hook'],
            'instance_hash' => $hash,
            'process_type' => (string)$definition['type'],
            'reference_id' => self::referenceId($safeArgs),
            'status' => 'pending',
            'attempt' => $attempt,
            'source' => 'cron',
            'scheduled_at' => $scheduledAt,
            'message' => 'Execução agendada pelo WP-Cron.',
            'args_json' => wp_json_encode($safeArgs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int)$wpdb->insert_id;
    }

    private static function syncScheduledEvents(): void {
        if (!self::tableReady() || get_transient('ff_process_schedule_sync')) return;
        set_transient('ff_process_schedule_sync', 1, 5 * MINUTE_IN_SECONDS);
        $byHook = [];
        foreach (self::definitions() as $key => $definition) $byHook[(string)$definition['hook']] = $key;
        $seen = 0;
        $cron = function_exists('_get_cron_array') ? _get_cron_array() : [];
        foreach ((array)$cron as $timestamp => $hooks) {
            foreach ((array)$hooks as $hook => $events) {
                if (!isset($byHook[$hook])) continue;
                foreach ((array)$events as $event) {
                    self::recordPending($byHook[$hook], (array)($event['args'] ?? []), (int)$timestamp);
                    if (++$seen >= 250) return;
                }
            }
        }
    }

    public static function begin(string $processKey, array $args): void {
        if (!self::tableReady()) return;
        $definition = self::definitions()[$processKey] ?? null;
        if (!$definition) return;

        global $wpdb;
        $safeArgs = self::safeArgs($args);
        $hash = self::instanceHash((string)$definition['hook'], $safeArgs);
        $now = current_time('mysql', true);
        $pendingId = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE instance_hash=%s AND status='pending' AND (scheduled_at IS NULL OR scheduled_at<=%s) ORDER BY id DESC LIMIT 1",
            $hash,
            gmdate('Y-m-d H:i:s', time() + (10 * MINUTE_IN_SECONDS))
        ));

        if ($pendingId > 0) {
            $wpdb->update(self::table(), [
                'status' => 'running',
                'started_at' => $now,
                'message' => 'Execução iniciada.',
                'updated_at' => $now,
            ], ['id' => $pendingId]);
            $runId = $pendingId;
        } else {
            $attempt = 1 + (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE instance_hash=%s', $hash));
            $wpdb->insert(self::table(), [
                'run_uuid' => wp_generate_uuid4(),
                'process_key' => $processKey,
                'hook_name' => (string)$definition['hook'],
                'instance_hash' => $hash,
                'process_type' => (string)$definition['type'],
                'reference_id' => self::referenceId($safeArgs),
                'status' => 'running',
                'attempt' => $attempt,
                'source' => wp_doing_cron() ? 'cron' : 'system',
                'scheduled_at' => $now,
                'started_at' => $now,
                'message' => 'Execução iniciada.',
                'args_json' => wp_json_encode($safeArgs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $runId = (int)$wpdb->insert_id;
        }

        if ($runId > 0) {
            self::$active[$processKey][] = ['id' => $runId, 'started' => microtime(true)];
        }
    }

    public static function complete(string $processKey, array $args): void {
        if (empty(self::$active[$processKey])) return;
        $active = array_pop(self::$active[$processKey]);
        if (empty(self::$active[$processKey])) unset(self::$active[$processKey]);

        $status = 'completed';
        $message = 'Execução concluída.';
        $errorCode = null;
        if ($processKey === 'report_automation') {
            [$status, $message, $errorCode] = self::reportAutomationOutcome($args);
        }
        self::finish((int)$active['id'], $status, $message, $errorCode, (float)$active['started']);
    }

    public static function failHook(string $hook, string $message, string $errorCode = 'process_exception', bool $recover = true): void {
        foreach (self::definitions() as $processKey=>$definition) {
            if ((string)$definition['hook'] !== $hook || empty(self::$active[$processKey])) continue;
            $active = array_pop(self::$active[$processKey]);
            if (empty(self::$active[$processKey])) unset(self::$active[$processKey]);
            self::finish(
                (int)$active['id'],
                'failed',
                substr(wp_strip_all_tags($message), 0, 255),
                substr(sanitize_key($errorCode), 0, 96),
                (float)$active['started'],
                $recover
            );
            return;
        }
    }

    private static function reportAutomationOutcome(array $args): array {
        global $wpdb;
        $id = absint($args[0] ?? 0);
        if (!$id) return ['completed', 'Execução concluída.', null];
        $table = $wpdb->prefix . 'routespro_report_schedules';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return ['completed', 'Execução concluída.', null];
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT last_status,last_message FROM {$table} WHERE id=%d", $id), ARRAY_A) ?: [];
        $reportStatus = sanitize_key((string)($row['last_status'] ?? ''));
        $message = sanitize_text_field((string)($row['last_message'] ?? 'Execução concluída.'));
        if (in_array($reportStatus, ['failed', 'partial'], true)) {
            return ['failed', $message ?: 'O relatório terminou com falhas.', 'report_' . $reportStatus];
        }
        return ['completed', $message ?: 'Relatório enviado.', null];
    }

    private static function finish(int $runId, string $status, string $message, ?string $errorCode, float $started, bool $recover = true): void {
        if ($runId <= 0 || !self::tableReady()) return;
        global $wpdb;
        $status = in_array($status, ['completed', 'failed'], true) ? $status : 'failed';
        $now = current_time('mysql', true);
        $wpdb->update(self::table(), [
            'status' => $status,
            'completed_at' => $now,
            'duration_ms' => max(0, (int)round((microtime(true) - $started) * 1000)),
            'message' => substr(wp_strip_all_tags($message), 0, 255),
            'error_code' => $errorCode ? substr(sanitize_key($errorCode), 0, 96) : null,
            'updated_at' => $now,
        ], ['id' => $runId]);
        if ($status === 'completed') self::recordSuccess($runId);
        elseif ($recover) self::handleFailure($runId);
    }

    private static function recordSuccess(int $runId): void {
        $row = self::findRun($runId);
        if (!$row) return;
        global $wpdb;
        if (in_array((string)($row['source'] ?? ''), ['auto_retry', 'retry'], true)) {
            $wpdb->update(self::table(), ['recovery_status' => 'recovered', 'updated_at' => current_time('mysql', true)], ['id' => $runId]);
            if (!empty($row['parent_run_id'])) {
                self::markRecoveryChainRecovered((int)$row['parent_run_id']);
            }
        }
        $label = (string)(self::definitions()[(string)$row['process_key']]['label'] ?? $row['process_key']);
        self::resolveAlert('Falha definitiva: ' . $label);
        self::recordCircuitSuccess((string)$row['process_key']);
    }

    private static function markRecoveryChainRecovered(int $parentId): void {
        global $wpdb;
        $seen = 0;
        while ($parentId > 0 && $seen++ < 10) {
            $next = (int)$wpdb->get_var($wpdb->prepare('SELECT parent_run_id FROM ' . self::table() . ' WHERE id=%d', $parentId));
            $wpdb->update(self::table(), ['recovery_status' => 'recovered', 'next_retry_at' => null, 'terminal_at' => null, 'updated_at' => current_time('mysql', true)], ['id' => $parentId]);
            $parentId = $next;
        }
    }

    private static function handleFailure(int $runId): void {
        $row = self::findRun($runId);
        if (!$row) return;
        $key = (string)$row['process_key'];
        self::recordCircuitFailure($key, (string)($row['error_code'] ?? ''), (string)($row['message'] ?? ''));
        if (!self::isRecoverable($row)) return;
        self::scheduleAutomaticRecovery($row);
    }

    private static function isRecoverable(array $row): bool {
        $definition = self::definitions()[(string)($row['process_key'] ?? '')] ?? null;
        if (!$definition || empty($definition['autoRetry'])) return false;
        return in_array((string)($row['error_code'] ?? ''), ['fatal_error', 'interrupted', 'stale_run', 'stale_pending'], true);
    }

    private static function scheduleAutomaticRecovery(array $failed): array {
        $key = (string)($failed['process_key'] ?? '');
        $definition = self::definitions()[$key] ?? null;
        if (!$definition || empty($definition['autoRetry']) || !self::tableReady()) return ['ok' => false, 'message' => 'Recuperação automática indisponível.'];
        $nextAttempt = max(1, (int)($failed['recovery_attempt'] ?? 0) + 1);
        $maxAttempts = max(0, (int)($definition['maxAttempts'] ?? 0));
        if ($maxAttempts <= 0 || $nextAttempt > $maxAttempts) {
            self::markTerminal((int)$failed['id'], $key, 'Número máximo de recuperações atingido.');
            return ['ok' => false, 'terminal' => true, 'message' => 'A execução passou para a fila de falhas definitivas.'];
        }

        $args = json_decode((string)($failed['args_json'] ?? ''), true);
        $args = is_array($args) ? array_values($args) : [];
        $expected = max(0, (int)$definition['acceptedArgs']);
        if (count($args) !== $expected) {
            self::markTerminal((int)$failed['id'], $key, 'Argumentos insuficientes para recuperação automática.');
            return ['ok' => false, 'terminal' => true, 'message' => 'Argumentos insuficientes para recuperação automática.'];
        }

        $backoff = array_values((array)($definition['backoff'] ?? [60, 300, 900]));
        $delay = max(60, (int)($backoff[min($nextAttempt - 1, count($backoff) - 1)] ?? 900));
        $circuit = self::circuitState($key);
        if (($circuit['state'] ?? 'closed') === 'open' && !empty($circuit['retry_after'])) {
            $delay = max($delay, max(60, strtotime((string)$circuit['retry_after'] . ' UTC') - time()));
        }
        if ((int)($definition['expectedInterval'] ?? 0) > 0) {
            $natural = wp_next_scheduled((string)$definition['hook'], $args);
            if ($natural && (int)$natural <= time() + $delay + (10 * MINUTE_IN_SECONDS)) {
                return self::adoptScheduledRecovery($failed, $args, $nextAttempt, (int)$natural);
            }
        }
        return self::insertRecoveryRun($failed, $args, $nextAttempt, $delay);
    }

    private static function adoptScheduledRecovery(array $failed, array $args, int $attempt, int $timestamp): array {
        global $wpdb;
        $key = (string)$failed['process_key'];
        $pendingId = self::recordPending($key, $args, $timestamp);
        if ($pendingId <= 0) return ['ok' => false, 'message' => 'Não foi possível associar a próxima execução recorrente.'];
        $scheduledAt = gmdate('Y-m-d H:i:s', $timestamp);
        $now = current_time('mysql', true);
        $wpdb->update(self::table(), [
            'source' => 'auto_retry',
            'recovery_attempt' => $attempt,
            'recovery_status' => 'scheduled',
            'parent_run_id' => (int)$failed['id'],
            'message' => 'Recuperação associada à próxima execução recorrente.',
            'meta_json' => wp_json_encode(['recovery_of' => (int)$failed['id'], 'natural_cron' => true]),
            'updated_at' => $now,
        ], ['id' => $pendingId]);
        $wpdb->update(self::table(), ['recovery_status' => 'retry_scheduled', 'next_retry_at' => $scheduledAt, 'updated_at' => $now], ['id' => (int)$failed['id']]);
        return ['ok' => true, 'run_id' => $pendingId, 'message' => 'Recuperação associada à próxima execução recorrente.'];
    }

    private static function insertRecoveryRun(array $failed, array $args, int $attempt, int $delay): array {
        global $wpdb;
        $key = (string)$failed['process_key'];
        $definition = self::definitions()[$key] ?? null;
        if (!$definition) return ['ok' => false, 'message' => 'Definição de processo indisponível.'];
        $safeArgs = self::safeArgs($args);
        $hash = self::instanceHash((string)$definition['hook'], $safeArgs);
        $timestamp = time() + max(60, $delay);
        $scheduledAt = gmdate('Y-m-d H:i:s', $timestamp);
        $existing = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE instance_hash=%s AND source='auto_retry' AND status='pending' ORDER BY id DESC LIMIT 1",
            $hash
        ));
        if ($existing > 0) return ['ok' => false, 'duplicate' => true, 'message' => 'Já existe uma recuperação automática pendente.'];
        $now = current_time('mysql', true);
        $wpdb->insert(self::table(), [
            'run_uuid' => wp_generate_uuid4(),
            'process_key' => $key,
            'hook_name' => (string)$definition['hook'],
            'instance_hash' => $hash,
            'process_type' => (string)$definition['type'],
            'reference_id' => self::referenceId($safeArgs),
            'status' => 'pending',
            'attempt' => 1 + (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE instance_hash=%s', $hash)),
            'source' => 'auto_retry',
            'scheduled_at' => $scheduledAt,
            'recovery_attempt' => $attempt,
            'recovery_status' => 'scheduled',
            'parent_run_id' => (int)$failed['id'],
            'message' => 'Recuperação automática agendada com backoff progressivo.',
            'args_json' => wp_json_encode($safeArgs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_json' => wp_json_encode(['recovery_of' => (int)$failed['id'], 'backoff_seconds' => max(60, $delay)]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newId = (int)$wpdb->insert_id;
        if (!$newId) return ['ok' => false, 'message' => 'Não foi possível registar a recuperação automática.'];
        $wpdb->update(self::table(), ['recovery_status' => 'retry_scheduled', 'next_retry_at' => $scheduledAt, 'updated_at' => $now], ['id' => (int)$failed['id']]);
        if (!wp_schedule_single_event($timestamp, (string)$definition['hook'], $safeArgs)) {
            $wpdb->update(self::table(), ['status' => 'failed', 'recovery_status' => 'terminal', 'terminal_at' => $now, 'completed_at' => $now, 'error_code' => 'schedule_rejected', 'message' => 'O WP-Cron recusou a recuperação automática.', 'updated_at' => $now], ['id' => $newId]);
            self::markTerminal((int)$failed['id'], $key, 'O WP-Cron recusou a recuperação automática.');
            return ['ok' => false, 'terminal' => true, 'message' => 'O WP-Cron recusou a recuperação automática.'];
        }
        return ['ok' => true, 'run_id' => $newId, 'message' => 'Recuperação automática agendada.'];
    }

    private static function markTerminal(int $runId, string $processKey, string $message): void {
        if ($runId <= 0 || !self::tableReady()) return;
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->update(self::table(), ['recovery_status' => 'terminal', 'terminal_at' => $now, 'next_retry_at' => null, 'updated_at' => $now], ['id' => $runId]);
        if (class_exists('\RoutesPro\Observability\AlertService')) {
            $label = (string)(self::definitions()[$processKey]['label'] ?? $processKey);
            \RoutesPro\Observability\AlertService::upsert('process_terminal_failure', 'critical', 'Falha definitiva: ' . $label, $message, ['process_key' => $processKey, 'run_id' => $runId]);
        }
    }

    public static function circuitState(string $processKey): array {
        $definition = self::definitions()[$processKey] ?? null;
        if (!$definition || empty($definition['circuit']) || !self::circuitsReady()) return ['process_key' => $processKey, 'state' => 'closed', 'failure_count' => 0];
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::circuitsTable() . ' WHERE process_key=%s', $processKey), ARRAY_A) ?: [];
        if (!$row) return ['process_key' => $processKey, 'state' => 'closed', 'failure_count' => 0];
        if ((string)$row['state'] === 'open' && !empty($row['retry_after']) && strtotime((string)$row['retry_after'] . ' UTC') <= time()) {
            $wpdb->update(self::circuitsTable(), ['state' => 'half_open', 'updated_at' => current_time('mysql', true)], ['id' => (int)$row['id']]);
            $row['state'] = 'half_open';
        }
        return $row;
    }

    private static function recordCircuitFailure(string $processKey, string $errorCode, string $message): void {
        $definition = self::definitions()[$processKey] ?? null;
        if (!$definition || empty($definition['circuit']) || !self::circuitsReady()) return;
        global $wpdb;
        $current = self::circuitState($processKey);
        $failures = max(0, (int)($current['failure_count'] ?? 0)) + 1;
        $open = $failures >= 3;
        $now = current_time('mysql', true);
        $data = [
            'state' => $open ? 'open' : 'closed',
            'failure_count' => $failures,
            'opened_at' => $open ? $now : null,
            'retry_after' => $open ? gmdate('Y-m-d H:i:s', time() + (15 * MINUTE_IN_SECONDS)) : null,
            'last_failure_at' => $now,
            'last_error_code' => substr(sanitize_key($errorCode), 0, 96),
            'last_message' => substr(wp_strip_all_tags($message), 0, 255),
            'updated_at' => $now,
        ];
        if (!empty($current['id'])) $wpdb->update(self::circuitsTable(), $data, ['id' => (int)$current['id']]);
        else $wpdb->insert(self::circuitsTable(), array_merge(['process_key' => $processKey], $data));
        if ($open && class_exists('\RoutesPro\Observability\AlertService')) {
            \RoutesPro\Observability\AlertService::upsert(
                'process_circuit_open',
                'warning',
                'Circuito de recuperação aberto: ' . (string)$definition['label'],
                'As reexecuções controladas ficam suspensas durante 15 minutos para evitar falhas em cascata.',
                ['process_key' => $processKey, 'failure_count' => $failures]
            );
        }
    }

    private static function recordCircuitSuccess(string $processKey): void {
        $definition = self::definitions()[$processKey] ?? null;
        if (!$definition || empty($definition['circuit']) || !self::circuitsReady()) return;
        global $wpdb;
        $current = self::circuitState($processKey);
        $now = current_time('mysql', true);
        $data = ['state' => 'closed', 'failure_count' => 0, 'retry_after' => null, 'last_success_at' => $now, 'last_error_code' => null, 'last_message' => 'Execução concluída com sucesso.', 'updated_at' => $now];
        if (!empty($current['id'])) $wpdb->update(self::circuitsTable(), $data, ['id' => (int)$current['id']]);
        else $wpdb->insert(self::circuitsTable(), array_merge(['process_key' => $processKey], $data));
        self::resolveAlert('Circuito de recuperação aberto: ' . (string)$definition['label']);
    }

    public static function resetCircuit(string $processKey): bool {
        $definition = self::definitions()[$processKey] ?? null;
        if (!$definition || empty($definition['circuit']) || !self::circuitsReady()) return false;
        global $wpdb;
        $now = current_time('mysql', true);
        $current = self::circuitState($processKey);
        $data = ['state' => 'closed', 'failure_count' => 0, 'opened_at' => null, 'retry_after' => null, 'last_error_code' => null, 'last_message' => 'Circuito reposto manualmente.', 'updated_at' => $now];
        $ok = !empty($current['id']) ? $wpdb->update(self::circuitsTable(), $data, ['id' => (int)$current['id']]) !== false : $wpdb->insert(self::circuitsTable(), array_merge(['process_key' => $processKey], $data)) !== false;
        if ($ok) self::resolveAlert('Circuito de recuperação aberto: ' . (string)$definition['label']);
        return $ok;
    }

    public static function circuits(): array {
        if (!self::circuitsReady()) return [];
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::circuitsTable() . ' ORDER BY state DESC, failure_count DESC, process_key ASC', ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $current = self::circuitState((string)$row['process_key']);
            if ($current) $row = $current;
        }
        unset($row);
        return $rows;
    }

    private static function resolveAlert(string $title): void {
        if (!class_exists('\RoutesPro\Observability\AlertService')) return;
        global $wpdb;
        $table = \RoutesPro\Observability\AlertService::tableName();
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
        $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE status='open' AND title=%s ORDER BY id DESC LIMIT 1", $title));
        if ($id > 0) \RoutesPro\Observability\AlertService::setStatus($id, 'resolved');
    }

    public static function captureShutdown(): void {
        if (!self::$active || !self::tableReady()) return;
        $error = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        $fatal = is_array($error) && in_array((int)($error['type'] ?? 0), $fatalTypes, true);
        $message = $fatal ? ('Erro fatal: ' . self::sanitizeErrorMessage((string)($error['message'] ?? 'erro desconhecido'))) : 'Execução interrompida antes da conclusão.';
        $code = $fatal ? 'fatal_error' : 'interrupted';
        foreach (self::$active as $stack) {
            foreach ($stack as $active) {
                self::finish((int)$active['id'], 'failed', $message, $code, (float)$active['started']);
            }
        }
        self::$active = [];
    }

    public static function queueNow(string $processKey, array $args = [], string $source = 'manual', int $retryOf = 0): array {
        $definitions = self::definitions();
        $definition = $definitions[$processKey] ?? null;
        if (!$definition || !self::tableReady()) return ['ok' => false, 'message' => 'Processo indisponível.'];
        if ($source === 'manual' && empty($definition['manual'])) return ['ok' => false, 'message' => 'Este processo só pode ser repetido a partir de uma execução elegível.'];
        if ($source === 'retry' && empty($definition['retryable'])) return ['ok' => false, 'message' => 'A repetição deste processo não é segura.'];
        if (!empty($definition['circuit'])) {
            $circuit = self::circuitState($processKey);
            if (($circuit['state'] ?? 'closed') === 'open') return ['ok' => false, 'circuit' => true, 'message' => 'O circuito de recuperação está temporariamente aberto. Aguarda o período de proteção ou repõe o circuito após validação.'];
        }

        $expected = max(0, (int)$definition['acceptedArgs']);
        $args = array_values(array_slice(self::safeArgs($args), 0, $expected));
        if (count($args) !== $expected) return ['ok' => false, 'message' => 'Faltam os argumentos necessários para executar o processo.'];

        global $wpdb;
        $hash = self::instanceHash((string)$definition['hook'], $args);
        $lockKey = 'ff_process_manual_' . substr($hash, 0, 32);
        $recent = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE instance_hash=%s AND (status='running' OR (status='pending' AND scheduled_at<=%s AND created_at>=%s)) ORDER BY id DESC LIMIT 1",
            $hash,
            gmdate('Y-m-d H:i:s', time() + (5 * MINUTE_IN_SECONDS)),
            gmdate('Y-m-d H:i:s', time() - (2 * MINUTE_IN_SECONDS))
        ));
        if (get_transient($lockKey) || $recent > 0) {
            return ['ok' => false, 'duplicate' => true, 'message' => 'Já existe um pedido recente para este processo.'];
        }
        set_transient($lockKey, 1, MINUTE_IN_SECONDS);

        $attempt = 1 + (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE instance_hash=%s', $hash));
        $now = current_time('mysql', true);
        $scheduledAt = gmdate('Y-m-d H:i:s', time() + 2);
        $parent = $retryOf > 0 ? self::findRun($retryOf) : [];
        $wpdb->insert(self::table(), [
            'run_uuid' => wp_generate_uuid4(),
            'process_key' => $processKey,
            'hook_name' => (string)$definition['hook'],
            'instance_hash' => $hash,
            'process_type' => (string)$definition['type'],
            'reference_id' => self::referenceId($args),
            'status' => 'pending',
            'attempt' => $attempt,
            'source' => $source === 'retry' ? 'retry' : 'manual',
            'scheduled_at' => $scheduledAt,
            'recovery_attempt' => $source === 'retry' ? max(1, (int)($parent['recovery_attempt'] ?? 0) + 1) : 0,
            'recovery_status' => $source === 'retry' ? 'scheduled' : 'none',
            'parent_run_id' => $retryOf > 0 ? $retryOf : null,
            'message' => $source === 'retry' ? 'Reexecução segura agendada.' : 'Execução manual agendada.',
            'args_json' => wp_json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_json' => $retryOf > 0 ? wp_json_encode(['retry_of' => $retryOf]) : null,
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $runId = (int)$wpdb->insert_id;
        if (!$runId) {
            delete_transient($lockKey);
            return ['ok' => false, 'message' => 'Não foi possível registar o pedido.'];
        }

        $scheduled = wp_schedule_single_event(time() + 2, (string)$definition['hook'], $args);
        if (!$scheduled) {
            $wpdb->update(self::table(), [
                'status' => 'failed',
                'completed_at' => $now,
                'message' => 'O WordPress recusou o agendamento do processo.',
                'error_code' => 'schedule_rejected',
                'updated_at' => $now,
            ], ['id' => $runId]);
            delete_transient($lockKey);
            return ['ok' => false, 'message' => 'O WordPress recusou o agendamento.'];
        }
        return ['ok' => true, 'run_id' => $runId, 'message' => 'Processo agendado para execução imediata.'];
    }

    public static function retry(int $runId): array {
        $row = self::findRun($runId);
        if (!$row || (string)$row['status'] !== 'failed') return ['ok' => false, 'message' => 'Só é possível repetir uma execução falhada.'];
        $definition = self::definitions()[(string)$row['process_key']] ?? null;
        if (!$definition || empty($definition['retryable'])) return ['ok' => false, 'message' => 'Esta execução não é elegível para repetição segura.'];
        $args = json_decode((string)$row['args_json'], true);
        return self::queueNow((string)$row['process_key'], is_array($args) ? $args : [], 'retry', $runId);
    }

    public static function findRun(int $runId): array {
        if ($runId <= 0 || !self::tableReady()) return [];
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $runId), ARRAY_A) ?: [];
    }

    public static function summary(): array {
        $empty = [
            'pending' => 0,
            'running' => 0,
            'completed_24h' => 0,
            'failed_24h' => 0,
            'failed_unrecovered_24h' => 0,
            'recovered_24h' => 0,
            'terminal' => 0,
            'terminal_active' => 0,
            'circuits_open' => 0,
        ];
        if (!self::tableReady()) return $empty;
        global $wpdb;
        $table = self::table();
        $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(status='pending') pending, SUM(status='running') running, SUM(status='completed' AND completed_at>=%s) completed_24h, SUM(status='failed' AND completed_at>=%s) failed_24h, SUM(recovery_status='recovered' AND completed_at>=%s) recovered_24h, SUM(recovery_status='terminal') terminal FROM {$table}",
            $since,
            $since,
            $since
        ), ARRAY_A) ?: [];
        $values = array_map('intval', array_merge($empty, $row));

        // Current operational health must not be permanently degraded by
        // historical failures that were subsequently recovered. Keep the raw
        // historical counters above for audit, but score only unresolved
        // failures and terminal chains for which no later success exists.
        $values['failed_unrecovered_24h'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} f WHERE f.status='failed' AND f.completed_at>=%s AND f.recovery_status<>'recovered' " .
            "AND NOT EXISTS (SELECT 1 FROM {$table} s WHERE s.process_key=f.process_key AND s.status='completed' AND s.completed_at>f.completed_at)",
            $since
        ));
        $values['terminal_active'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT t.process_key) FROM {$table} t WHERE t.recovery_status='terminal' AND COALESCE(t.terminal_at,t.completed_at)>=%s " .
            "AND NOT EXISTS (SELECT 1 FROM {$table} s WHERE s.process_key=t.process_key AND s.status='completed' AND s.completed_at>COALESCE(t.terminal_at,t.completed_at))",
            $since
        ));
        if (self::circuitsReady()) $values['circuits_open'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . self::circuitsTable() . " WHERE state='open'");
        return $values;
    }

    public static function operationalHealth(): array {
        $summary = self::summary();
        $overdue = 0;
        foreach (self::overview() as $process) if (!empty($process['overdue'])) $overdue++;
        $score = 100;
        $score -= min(30, (int)$summary['failed_unrecovered_24h'] * 5);
        $score -= min(25, (int)$summary['terminal_active'] * 10);
        $score -= min(25, (int)$summary['circuits_open'] * 10);
        $score -= min(20, $overdue * 5);
        $score = max(0, $score);
        return ['score' => $score, 'status' => $score >= 90 ? 'healthy' : ($score >= 70 ? 'attention' : 'critical'), 'overdue' => $overdue, 'summary' => $summary];
    }

    public static function terminalFailures(int $limit = 100): array {
        if (!self::tableReady()) return [];
        global $wpdb;
        $limit = max(1, min(250, $limit));
        return $wpdb->get_results("SELECT * FROM " . self::table() . " WHERE recovery_status='terminal' ORDER BY terminal_at DESC,id DESC LIMIT {$limit}", ARRAY_A) ?: [];
    }

    public static function recent(array $filters = [], int $limit = 100): array {
        if (!self::tableReady()) return [];
        global $wpdb;
        $where = ['1=1'];
        $args = [];
        $status = sanitize_key((string)($filters['status'] ?? ''));
        $type = sanitize_text_field((string)($filters['type'] ?? ''));
        $process = sanitize_key((string)($filters['process'] ?? ''));
        if (in_array($status, ['pending', 'running', 'completed', 'failed'], true)) { $where[] = 'status=%s'; $args[] = $status; }
        if ($type !== '') { $where[] = 'process_type=%s'; $args[] = $type; }
        if (isset(self::definitions()[$process])) { $where[] = 'process_key=%s'; $args[] = $process; }
        $limit = max(1, min(250, $limit));
        $sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit;
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function overview(): array {
        $definitions = self::definitions();
        $cron = self::cronSnapshot();
        $latest = [];
        if (self::tableReady()) {
            global $wpdb;
            $rows = $wpdb->get_results('SELECT r.* FROM ' . self::table() . ' r INNER JOIN (SELECT process_key,MAX(id) max_id FROM ' . self::table() . ' GROUP BY process_key) x ON x.max_id=r.id', ARRAY_A) ?: [];
            foreach ($rows as $row) $latest[(string)$row['process_key']] = $row;
        }
        $out = [];
        foreach ($definitions as $key => $definition) {
            $next = $cron[$key]['next'] ?? 0;
            $overdue = self::isOverdue($definition, (int)$next);
            $out[$key] = array_merge($definition, [
                'key' => $key,
                'latest' => $latest[$key] ?? [],
                'next_run' => (int)$next,
                'scheduled_count' => (int)($cron[$key]['count'] ?? 0),
                'overdue' => $overdue,
                'circuit' => self::circuitState($key),
            ]);
        }
        return $out;
    }

    private static function cronSnapshot(): array {
        $byHook = [];
        foreach (self::definitions() as $key => $definition) $byHook[(string)$definition['hook']] = $key;
        $snapshot = [];
        $cron = function_exists('_get_cron_array') ? _get_cron_array() : [];
        foreach ((array)$cron as $timestamp => $hooks) {
            foreach ((array)$hooks as $hook => $events) {
                if (!isset($byHook[$hook])) continue;
                $key = $byHook[$hook];
                $snapshot[$key]['count'] = (int)($snapshot[$key]['count'] ?? 0) + count((array)$events);
                if (empty($snapshot[$key]['next']) || (int)$timestamp < (int)$snapshot[$key]['next']) $snapshot[$key]['next'] = (int)$timestamp;
            }
        }
        return $snapshot;
    }

    private static function isOverdue(array $definition, int $next): bool {
        $interval = (int)($definition['expectedInterval'] ?? 0);
        if ($interval <= 0) return $next > 0 && $next < (time() - 5 * MINUTE_IN_SECONDS);
        if ($next <= 0) return true;
        return $next < (time() - min($interval, HOUR_IN_SECONDS));
    }

    public static function auditOverdue(): void {
        if (!class_exists('\RoutesPro\Observability\AlertService')) return;
        global $wpdb;
        foreach (self::overview() as $process) {
            if ((int)$process['expectedInterval'] <= 0) continue;
            $title = 'Processo em atraso: ' . (string)$process['label'];
            if (!empty($process['overdue'])) {
                \RoutesPro\Observability\AlertService::upsert(
                    'process_overdue',
                    'warning',
                    $title,
                    'O processo recorrente não tem uma próxima execução válida ou ultrapassou a tolerância operacional.',
                    ['process_key' => (string)$process['key'], 'hook' => (string)$process['hook'], 'next_run' => (int)$process['next_run']]
                );
                continue;
            }
            $alertsTable = \RoutesPro\Observability\AlertService::tableName();
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $alertsTable)) !== $alertsTable) continue;
            $alertId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$alertsTable} WHERE status='open' AND title=%s ORDER BY id DESC LIMIT 1", $title));
            if ($alertId > 0) \RoutesPro\Observability\AlertService::setStatus($alertId, 'resolved');
        }
    }

    public static function markStale(): int {
        if (!self::tableReady()) return 0;
        global $wpdb;
        $now = current_time('mysql', true);
        $stale = gmdate('Y-m-d H:i:s', time() - (self::STALE_MINUTES * MINUTE_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,status FROM " . self::table() . " WHERE (status='running' AND started_at<%s) OR (status='pending' AND scheduled_at<%s) ORDER BY id ASC LIMIT 100",
            $stale,
            $stale
        ), ARRAY_A) ?: [];
        $count = 0;
        foreach ($rows as $row) {
            $running = (string)$row['status'] === 'running';
            $updated = $wpdb->update(self::table(), [
                'status' => 'failed',
                'completed_at' => $now,
                'error_code' => $running ? 'stale_run' : 'stale_pending',
                'message' => $running ? 'A execução excedeu o limite operacional.' : 'O pedido não foi iniciado dentro do prazo.',
                'updated_at' => $now,
            ], ['id' => (int)$row['id']]);
            if ($updated !== false) {
                $count++;
                self::handleFailure((int)$row['id']);
            }
        }
        return $count;
    }

    public static function recoverBlocked(): array {
        $stale = self::markStale();
        if (!self::tableReady()) return ['stale' => $stale, 'scheduled' => 0];
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM " . self::table() . " WHERE status='failed' AND recovery_status='none' AND error_code IN ('fatal_error','interrupted','stale_run','stale_pending') ORDER BY id DESC LIMIT 50", ARRAY_A) ?: [];
        $scheduled = 0;
        foreach ($rows as $row) {
            $result = self::scheduleAutomaticRecovery($row);
            if (!empty($result['ok'])) $scheduled++;
        }
        return ['stale' => $stale, 'scheduled' => $scheduled];
    }

    public static function cleanup(): void {
        if (!self::tableReady()) return;
        global $wpdb;
        self::markStale();
        $completedBefore = gmdate('Y-m-d H:i:s', time() - (90 * DAY_IN_SECONDS));
        $failedBefore = gmdate('Y-m-d H:i:s', time() - (180 * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status='completed' AND completed_at<%s", $completedBefore));
        $terminalBefore = gmdate('Y-m-d H:i:s', time() - (365 * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status='failed' AND recovery_status<>'terminal' AND completed_at<%s", $failedBefore));
        $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status='failed' AND recovery_status='terminal' AND terminal_at<%s", $terminalBefore));
        self::auditOverdue();
    }

    public static function diagnosticSnapshot(): array {
        $processes = [];
        foreach (self::overview() as $key => $process) {
            $processes[] = [
                'key' => $key,
                'label' => (string)$process['label'],
                'hook' => (string)$process['hook'],
                'type' => (string)$process['type'],
                'auto_retry' => !empty($process['autoRetry']),
                'max_attempts' => (int)$process['maxAttempts'],
                'next_run_gmt' => !empty($process['next_run']) ? gmdate('c', (int)$process['next_run']) : null,
                'overdue' => !empty($process['overdue']),
                'circuit' => [
                    'state' => (string)($process['circuit']['state'] ?? 'closed'),
                    'failure_count' => (int)($process['circuit']['failure_count'] ?? 0),
                    'retry_after' => $process['circuit']['retry_after'] ?? null,
                ],
            ];
        }
        $clean = static function (array $rows): array {
            foreach ($rows as &$row) {
                unset($row['args_json'], $row['meta_json'], $row['instance_hash']);
            }
            unset($row);
            return $rows;
        };
        return [
            'health' => self::operationalHealth(),
            'processes' => $processes,
            'circuits' => self::circuits(),
            'recent_runs' => $clean(self::recent([], 100)),
            'terminal_failures' => $clean(self::terminalFailures(50)),
        ];
    }

    private static function safeArgs(array $args, int $depth = 0): array {
        $out = [];
        foreach (array_slice($args, 0, 20, true) as $key => $value) {
            $keyText = strtolower((string)$key);
            if (preg_match('/secret|token|password|passwd|nonce|api.?key|authorization/', $keyText)) {
                $out[$key] = '[redacted]';
            } elseif (is_array($value) && $depth < 2) {
                $out[$key] = self::safeArgs($value, $depth + 1);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $out[$key] = $value;
            } elseif (is_string($value)) {
                $out[$key] = substr(sanitize_text_field($value), 0, 100);
            } else {
                $out[$key] = '[unsupported]';
            }
        }
        return $out;
    }

    private static function sanitizeErrorMessage(string $message): string {
        $message = wp_strip_all_tags($message);
        foreach ([defined('ABSPATH') ? ABSPATH : '', defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ''] as $path) {
            if ($path !== '') $message = str_replace(wp_normalize_path($path), '[path]/', wp_normalize_path($message));
        }
        return substr($message, 0, 220);
    }

    private static function instanceHash(string $hook, array $args): string {
        return hash('sha256', $hook . '|' . wp_json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function referenceId(array $args): ?int {
        $first = $args[0] ?? null;
        if (is_int($first) || (is_string($first) && ctype_digit($first))) {
            $id = absint($first);
            return $id > 0 ? $id : null;
        }
        return null;
    }
}
