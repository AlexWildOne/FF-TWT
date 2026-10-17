<?php
namespace RoutesPro\Operations\Queue;

if (!defined('ABSPATH')) exit;

final class QueueManager {
    public const DISPATCH_HOOK = 'fieldflow_queue_dispatch';
    public const WATCHDOG_HOOK = 'fieldflow_queue_watchdog';
    public const CLEANUP_HOOK = 'fieldflow_queue_cleanup';
    public const ACTION_SCHEDULER_GROUP = 'fieldflow';
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_filter('cron_schedules', [self::class, 'cronSchedules']);
        add_action('init', [self::class, 'ensureSchedules'], 35);
        add_action(self::DISPATCH_HOOK, [QueueWorker::class, 'run']);
        add_action(self::WATCHDOG_HOOK, [QueueWorker::class, 'run']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
        add_action('fieldflow_queue_health_probe', [self::class, 'completeProbe'], 10, 1);

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('fieldflow queue status', static function (): void {
                \WP_CLI::line((string)wp_json_encode(QueueManager::status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            });
            \WP_CLI::add_command('fieldflow queue run', static function (array $args, array $assoc): void {
                $limit = isset($assoc['limit']) ? max(1, min(50, (int)$assoc['limit'])) : 10;
                \WP_CLI::line((string)wp_json_encode(QueueWorker::run($limit), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            });
        }
    }

    public static function cronSchedules(array $schedules): array {
        if (!isset($schedules['fieldflow_five_minutes'])) {
            $schedules['fieldflow_five_minutes'] = ['interval'=>5 * MINUTE_IN_SECONDS,'display'=>'FieldFlow a cada cinco minutos'];
        }
        return $schedules;
    }

    public static function ensureSchedules(): void {
        if (!wp_next_scheduled(self::WATCHDOG_HOOK)) {
            wp_schedule_event(time() + 90, 'fieldflow_five_minutes', self::WATCHDOG_HOOK);
        }
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + (3 * HOUR_IN_SECONDS), 'daily', self::CLEANUP_HOOK);
        }
        $next = QueueRepository::nextPendingTimestamp();
        if ($next > 0) self::signalDispatcher(max(1, $next - time()));
    }

    public static function schedule(string $hook, array $args = [], int $delay = 1, array $options = []): bool {
        $delay = max(1, $delay);
        $timestamp = time() + $delay;
        if (!QueueSettings::isAllowed($hook)) return self::legacySchedule($hook, $args, $timestamp);

        $settings = QueueSettings::get();
        $forceDurable = !empty($options['force_durable']);
        if ((string)$settings['mode'] === 'observe' && !empty($settings['diagnostics'])) {
            QueueRepository::observe($hook, $args, $timestamp, (string)($options['source'] ?? 'submission_pipeline'));
        }
        if ((!$forceDurable && !QueueSettings::isPilotOwner($hook, $settings)) || !QueueRepository::tableReady()) {
            return self::legacySchedule($hook, $args, $timestamp);
        }

        $definition = QueueSettings::allowedHooks()[$hook];
        $enqueueOptions = [
            'available_at'=>$timestamp,
            'queue'=>(string)($options['queue'] ?? $definition['queue']),
            'max_attempts'=>(int)($options['max_attempts'] ?? $definition['max_attempts']),
            'priority'=>(int)($options['priority'] ?? 0),
            'source'=>(string)($options['source'] ?? 'submission_pipeline'),
            'driver'=>QueueSettings::effectiveDriver($settings),
            'meta'=>is_array($options['meta'] ?? null) ? $options['meta'] : [],
        ];
        if (!empty($options['idempotency_key'])) $enqueueOptions['idempotency_key'] = (string)$options['idempotency_key'];
        $result = QueueRepository::enqueue($hook, $args, $enqueueOptions);
        if (!empty($result['ok'])) {
            self::signalDispatcher($delay);
            return true;
        }

        self::recordFallback($hook, (string)($result['reason'] ?? 'queue_enqueue_failed'));
        return self::legacySchedule($hook, $args, $timestamp);
    }

    public static function signalDispatcher(int $delay = 1): bool {
        $delay = max(1, $delay);
        $timestamp = time() + $delay;
        $driver = QueueSettings::effectiveDriver();
        if ($driver === 'action_scheduler') {
            try {
                $next = 0;
                if (function_exists('as_next_scheduled_action')) {
                    $candidate = as_next_scheduled_action(self::DISPATCH_HOOK, [], self::ACTION_SCHEDULER_GROUP);
                    $next = is_wp_error($candidate) ? 0 : (int)$candidate;
                } elseif (function_exists('as_has_scheduled_action')) {
                    $next = as_has_scheduled_action(self::DISPATCH_HOOK, [], self::ACTION_SCHEDULER_GROUP) ? $timestamp : 0;
                }
                if ($next <= 0 || $next > $timestamp + 30) {
                    $actionId = as_schedule_single_action($timestamp, self::DISPATCH_HOOK, [], self::ACTION_SCHEDULER_GROUP, true);
                    if ($actionId) return true;
                } else {
                    return true;
                }
            } catch (\Throwable $error) {
                self::recordFallback(self::DISPATCH_HOOK, 'action_scheduler_unavailable');
            }
        }
        $next = wp_next_scheduled(self::DISPATCH_HOOK);
        if ($next && (int)$next <= $timestamp + 30) return true;
        $scheduled = wp_schedule_single_event($timestamp, self::DISPATCH_HOOK);
        return $scheduled !== false && !is_wp_error($scheduled);
    }

    public static function runProbe(string $source = 'backoffice'): array {
        if (!QueueRepository::tableReady()) return ['ok'=>false,'message'=>'A tabela da fila moderna ainda não está disponível.'];
        $token = wp_generate_uuid4();
        delete_option(QueueSettings::LAST_PROBE_OPTION);
        $result = QueueRepository::enqueue('fieldflow_queue_health_probe', [$token], [
            'available_at'=>time(),
            'queue'=>'system',
            'max_attempts'=>1,
            'priority'=>100,
            'source'=>sanitize_key($source),
            'driver'=>'direct_probe',
            'idempotency_key'=>'probe:' . $token,
            'meta'=>['read_only'=>true],
        ]);
        if (empty($result['ok'])) return ['ok'=>false,'message'=>'Não foi possível criar o job de teste.'];
        $worker = QueueWorker::run(1, true);
        $probe = get_option(QueueSettings::LAST_PROBE_OPTION, []);
        $ok = is_array($probe) && hash_equals((string)($probe['token'] ?? ''), $token);
        return [
            'ok'=>$ok,
            'message'=>$ok ? 'Teste da fila concluído com sucesso.' : 'O job foi criado, mas não terminou nesta execução.',
            'job_id'=>(int)$result['job_id'],
            'worker'=>$worker,
            'executed_at'=>current_time('mysql', true),
        ];
    }

    public static function completeProbe(string $token): void {
        update_option(QueueSettings::LAST_PROBE_OPTION, [
            'token'=>sanitize_text_field($token),
            'completed_at'=>current_time('mysql', true),
            'driver'=>QueueSettings::effectiveDriver(),
            'version'=>QueueSettings::VERSION,
        ], false);
    }

    public static function status(): array {
        $settings = QueueSettings::get();
        $stats = QueueRepository::stats();
        $heartbeat = get_option(QueueWorker::HEARTBEAT_OPTION, []);
        $lastHeartbeat = is_array($heartbeat) ? (string)($heartbeat['completed_at'] ?? '') : '';
        $heartbeatTs = $lastHeartbeat !== '' ? strtotime($lastHeartbeat . ' UTC') : 0;
        $stalled = (int)$stats['pending'] > 0 && (int)$stats['oldest_pending_seconds'] > 900;
        $status = !QueueRepository::tableReady() ? 'critical' : ($stalled || (int)$stats['dead'] > 0 ? 'attention' : 'healthy');
        return [
            'version'=>QueueSettings::VERSION,
            'mode'=>(string)$settings['mode'],
            'selected_driver'=>(string)$settings['driver'],
            'effective_driver'=>QueueSettings::effectiveDriver($settings),
            'fallback'=>'wp_cron',
            'action_scheduler_available'=>QueueSettings::actionSchedulerAvailable(),
            'table_ready'=>QueueRepository::tableReady(),
            'pilot_hooks'=>array_values($settings['pilot_hooks']),
            'pilot_hook_count'=>count($settings['pilot_hooks']),
            'status'=>$status,
            'stats'=>$stats,
            'last_heartbeat'=>$lastHeartbeat,
            'heartbeat_recent'=>$heartbeatTs > 0 && $heartbeatTs >= time() - (15 * MINUTE_IN_SECONDS),
            'legacy_fallback'=>true,
            'last_rollback'=>get_option(QueueSettings::ROLLBACK_OPTION, []),
            'last_probe'=>get_option(QueueSettings::LAST_PROBE_OPTION, []),
        ];
    }

    public static function diagnosticSnapshot(): array {
        $settings = QueueSettings::get();
        return ['status'=>self::status(),'recent_jobs'=>!empty($settings['diagnostics']) ? QueueRepository::recent(100) : []];
    }

    public static function cleanup(): void {
        $settings = QueueSettings::get();
        QueueRepository::releaseStaleLocks((int)$settings['lock_ttl_seconds']);
        QueueRepository::cleanup((int)$settings['retention_days']);
    }

    private static function legacySchedule(string $hook, array $args, int $timestamp): bool {
        if (wp_next_scheduled($hook, $args)) return true;
        $scheduled = wp_schedule_single_event($timestamp, $hook, $args);
        return $scheduled !== false && !is_wp_error($scheduled);
    }

    private static function recordFallback(string $hook, string $reason): void {
        if (!class_exists('\\RoutesPro\\Support\\Logger')) return;
        \RoutesPro\Support\Logger::warning('A fila moderna regressou ao WP-Cron.', ['context_key'=>'queue_modernization'], [
            'hook'=>sanitize_key($hook),
            'reason'=>sanitize_key($reason),
        ]);
    }
}
