<?php
namespace RoutesPro\Operations\Queue;

if (!defined('ABSPATH')) exit;

final class QueueWorker {
    public const LOCK_OPTION = 'fieldflow_queue_worker_lock';
    public const HEARTBEAT_OPTION = 'fieldflow_queue_worker_heartbeat';

    public static function run(int $requestedLimit = 0, bool $force = false): array {
        $settings = QueueSettings::get();
        $limit = $requestedLimit > 0 ? max(1, min(50, $requestedLimit)) : (int)$settings['batch_size'];
        if ((string)$settings['mode'] !== 'pilot' && !$force) {
            $fallback = QueueRepository::fallbackPendingToLegacy($limit);
            return ['ok'=>true,'legacy_owner'=>true,'locked'=>false,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0,'fallback'=>$fallback];
        }
        $token = wp_generate_uuid4();
        if (!self::acquireLock($token, (int)$settings['lock_ttl_seconds'])) {
            return ['ok'=>false,'locked'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];
        }

        $started = microtime(true);
        $summary = ['ok'=>true,'locked'=>false,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0,'duration_ms'=>0];
        try {
            for ($processed = 0; $processed < $limit; $processed++) {
                if ((microtime(true) - $started) >= (int)$settings['max_runtime_seconds']) break;
                $jobs = QueueRepository::claimDue(1, $token, (int)$settings['lock_ttl_seconds']);
                if (!$jobs) break;
                $job = $jobs[0];
                $summary['claimed']++;
                $jobStarted = microtime(true);
                $hook = sanitize_key((string)($job['hook_name'] ?? ''));
                try {
                    if (!QueueSettings::isAllowed($hook)) throw new \RuntimeException('Hook não autorizado pela política da fila.');
                    $args = json_decode((string)($job['args_json'] ?? ''), true);
                    if (!is_array($args)) throw new \RuntimeException('Argumentos do job inválidos.');
                    if (!has_action($hook)) throw new \RuntimeException('O job não tem um handler registado.');
                    do_action_ref_array($hook, array_values($args));
                    QueueRepository::complete((int)$job['id'], $token, self::elapsedMs($jobStarted));
                    $summary['completed']++;
                } catch (\Throwable $error) {
                    if (class_exists('\\RoutesPro\\Operations\\ProcessMonitor')) {
                        \RoutesPro\Operations\ProcessMonitor::failHook($hook, $error->getMessage(), 'queue_exception', false);
                    }
                    $failed = QueueRepository::fail($job, $token, 'queue_exception', self::sanitiseError($error));
                    if (!empty($failed['terminal'])) {
                        $summary['dead']++;
                        self::alertDeadJob($job, $error);
                    } else {
                        $summary['retried']++;
                    }
                }
            }
        } finally {
            $summary['duration_ms'] = self::elapsedMs($started);
            update_option(self::HEARTBEAT_OPTION, [
                'completed_at'=>current_time('mysql', true),
                'driver'=>QueueSettings::effectiveDriver($settings),
                'claimed'=>(int)$summary['claimed'],
                'completed'=>(int)$summary['completed'],
                'retried'=>(int)$summary['retried'],
                'dead'=>(int)$summary['dead'],
                'duration_ms'=>(int)$summary['duration_ms'],
            ], false);
            self::releaseLock($token);
        }

        $next = QueueRepository::nextPendingTimestamp();
        if ($next > 0) QueueManager::signalDispatcher(max(1, $next - time()));
        return $summary;
    }

    private static function acquireLock(string $token, int $ttl): bool {
        $payload = ['token'=>$token,'started_at'=>time()];
        if (add_option(self::LOCK_OPTION, $payload, '', 'no')) return true;
        $current = get_option(self::LOCK_OPTION, []);
        $started = is_array($current) ? absint($current['started_at'] ?? 0) : 0;
        if ($started > 0 && (time() - $started) > max(120, $ttl)) {
            delete_option(self::LOCK_OPTION);
            return add_option(self::LOCK_OPTION, $payload, '', 'no');
        }
        return false;
    }

    private static function releaseLock(string $token): void {
        $current = get_option(self::LOCK_OPTION, []);
        if (is_array($current) && hash_equals((string)($current['token'] ?? ''), $token)) delete_option(self::LOCK_OPTION);
    }

    private static function alertDeadJob(array $job, \Throwable $error): void {
        if (!class_exists('\\RoutesPro\\Observability\\AlertService')) return;
        \RoutesPro\Observability\AlertService::upsert(
            'queue_dead_job',
            'critical',
            'Job na fila de falhas definitivas',
            'O job ' . sanitize_key((string)($job['hook_name'] ?? 'desconhecido')) . ' atingiu o limite de tentativas.',
            ['queue_job_id'=>(int)($job['id'] ?? 0),'hook'=>sanitize_key((string)($job['hook_name'] ?? ''))]
        );
    }

    private static function sanitiseError(\Throwable $error): string {
        $message = wp_strip_all_tags($error->getMessage());
        foreach ([defined('ABSPATH') ? ABSPATH : '', defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ''] as $path) {
            if ($path !== '') $message = str_replace(wp_normalize_path($path), '[path]/', wp_normalize_path($message));
        }
        return substr($message ?: get_class($error), 0, 500);
    }

    private static function elapsedMs(float $started): int {
        return max(0, (int)round((microtime(true) - $started) * 1000));
    }
}
