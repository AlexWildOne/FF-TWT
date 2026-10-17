<?php
namespace RoutesPro\Performance;

use RoutesPro\Core\AuditLog;
use RoutesPro\Operations\ProcessMonitor;
use RoutesPro\Operations\Queue\QueueManager;
use WP_HTTP_Response;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

/**
 * Low-overhead performance telemetry and advisory release gate.
 *
 * This runtime never blocks requests and never changes the queue driver. It
 * samples metadata only, without request bodies, query text, user IDs, IPs,
 * tokens or uploaded content.
 */
final class LoadReadiness {
    public const VERSION = '1.2.0';
    public const OPTION = 'fieldflow_load_readiness';
    public const LAST_PROBE_OPTION = 'fieldflow_load_readiness_last_probe';
    public const CLEANUP_HOOK = 'fieldflow_load_readiness_cleanup';

    private static bool $registered = false;
    private static ?bool $tableReady = null;
    private static float $requestStartedAt = 0.0;
    private static int $requestStartedQueries = 0;
    private static int $requestStartedMemory = 0;
    private static bool $restCaptured = false;
    private static array $restStarts = [];
    private static array $cronStarts = [];

    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_performance_samples';
    }

    public static function defaults(): array {
        return [
            'enabled' => 1,
            'mode' => 'observe',
            'sample_rate' => 25,
            'retention_days' => 30,
            'gate_window_hours' => 24,
            'minimum_samples' => 5,
            'error_rate_warning' => 2,
            'error_rate_critical' => 5,
            'thresholds' => [
                'rest' => ['warning_ms'=>1500,'critical_ms'=>4000,'warning_queries'=>120,'critical_queries'=>250,'memory_mb'=>192],
                'mobile' => ['warning_ms'=>2000,'critical_ms'=>5000,'warning_queries'=>140,'critical_queries'=>280,'memory_mb'=>192],
                'submission' => ['warning_ms'=>3500,'critical_ms'=>9000,'warning_queries'=>220,'critical_queries'=>450,'memory_mb'=>256],
                'export' => ['warning_ms'=>10000,'critical_ms'=>30000,'warning_queries'=>300,'critical_queries'=>700,'memory_mb'=>384],
                'admin' => ['warning_ms'=>2500,'critical_ms'=>6000,'warning_queries'=>180,'critical_queries'=>350,'memory_mb'=>256],
                'web' => ['warning_ms'=>2500,'critical_ms'=>6000,'warning_queries'=>180,'critical_queries'=>350,'memory_mb'=>256],
                'cron' => ['warning_ms'=>60000,'critical_ms'=>180000,'warning_queries'=>500,'critical_queries'=>1200,'memory_mb'=>512],
                'probe' => ['warning_ms'=>1000,'critical_ms'=>3000,'warning_queries'=>50,'critical_queries'=>100,'memory_mb'=>256],
            ],
        ];
    }

    public static function settings(): array {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        return self::merge(self::defaults(), $stored);
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        global $wpdb;
        self::$requestStartedAt = microtime(true);
        self::$requestStartedQueries = (int)($wpdb->num_queries ?? 0);
        self::$requestStartedMemory = memory_get_usage(true);

        add_filter('rest_pre_dispatch', [self::class, 'beforeRest'], 6, 3);
        add_filter('rest_post_dispatch', [self::class, 'afterRest'], 99, 3);
        add_action('shutdown', [self::class, 'captureRequest'], 90);
        add_action('init', [self::class, 'ensureSchedule'], 40);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
        add_action('admin_post_fieldflow_load_readiness_save', [self::class, 'handleSave']);
        add_action('admin_post_fieldflow_load_readiness_probe', [self::class, 'handleProbe']);

        if (class_exists(ProcessMonitor::class)) {
            foreach (ProcessMonitor::definitions() as $key => $definition) {
                $accepted = max(0, (int)($definition['acceptedArgs'] ?? 0));
                add_action((string)$definition['hook'], static function (...$args) use ($key): void {
                    LoadReadiness::beforeCron((string)$key);
                }, 0, $accepted);
                add_action((string)$definition['hook'], static function (...$args) use ($key): void {
                    LoadReadiness::afterCron((string)$key);
                }, PHP_INT_MAX - 1, $accepted);
            }
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('fieldflow performance gate', static function (array $args, array $assoc): void {
                $hours = isset($assoc['hours']) ? max(1, min(720, (int)$assoc['hours'])) : 24;
                \WP_CLI::line((string)wp_json_encode(LoadReadiness::gate($hours), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            });
            \WP_CLI::add_command('fieldflow performance probe', static function (array $args, array $assoc): void {
                $iterations = isset($assoc['iterations']) ? max(1, min(25, (int)$assoc['iterations'])) : 5;
                \WP_CLI::line((string)wp_json_encode(LoadReadiness::runProbe($iterations, 'wp_cli'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            });
        }
    }

    public static function ensureSchedule(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function beforeRest($result, $server, WP_REST_Request $request) {
        if (!self::enabled() || !self::isFieldFlowRoute((string)$request->get_route())) return $result;
        global $wpdb;
        self::$restStarts[spl_object_id($request)] = [
            'time' => microtime(true),
            'queries' => (int)($wpdb->num_queries ?? 0),
            'memory' => memory_get_usage(true),
        ];
        return $result;
    }

    public static function afterRest($response, $server, WP_REST_Request $request) {
        $route = (string)$request->get_route();
        if (!self::enabled() || !self::isFieldFlowRoute($route)) return $response;
        $id = spl_object_id($request);
        global $wpdb;
        $start = self::$restStarts[$id] ?? [
            'time'=>self::$requestStartedAt ?: microtime(true),
            'queries'=>self::$requestStartedQueries,
            'memory'=>self::$requestStartedMemory,
        ];
        unset(self::$restStarts[$id]);
        self::$restCaptured = true;
        $status = 200;
        if (is_wp_error($response)) {
            $data = $response->get_error_data();
            $status = is_array($data) ? absint($data['status'] ?? 500) : 500;
        } elseif ($response instanceof WP_HTTP_Response || (is_object($response) && method_exists($response, 'get_status'))) {
            $status = (int)$response->get_status();
        }
        $channel = strpos($route, '/fieldflow-mobile/') === 0 ? 'mobile' : 'rest';
        $category = self::categoryForRoute($route, $channel);
        self::recordSample([
            'operation_key' => 'REST ' . strtoupper((string)$request->get_method()) . ' ' . self::normaliseRoute($route),
            'channel' => $channel,
            'category' => $category,
            'duration_ms' => self::elapsedMs((float)$start['time']),
            'query_count' => max(0, (int)($wpdb->num_queries ?? 0) - (int)$start['queries']),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'memory_delta_bytes' => max(0, memory_get_usage(true) - (int)$start['memory']),
            'http_status' => $status,
            'outcome' => $status >= 500 ? 'error' : ($status >= 400 ? 'client_error' : 'success'),
        ]);
        return $response;
    }

    public static function beforeCron(string $key): void {
        if (!self::enabled()) return;
        global $wpdb;
        self::$cronStarts[$key] = [
            'time'=>microtime(true),
            'queries'=>(int)($wpdb->num_queries ?? 0),
            'memory'=>memory_get_usage(true),
        ];
    }

    public static function afterCron(string $key): void {
        if (!isset(self::$cronStarts[$key])) return;
        global $wpdb;
        $start = self::$cronStarts[$key];
        unset(self::$cronStarts[$key]);
        self::recordSample([
            'operation_key'=>'CRON ' . sanitize_key($key),
            'channel'=>'cron',
            'category'=>'cron',
            'duration_ms'=>self::elapsedMs((float)$start['time']),
            'query_count'=>max(0, (int)($wpdb->num_queries ?? 0) - (int)$start['queries']),
            'memory_peak_bytes'=>memory_get_peak_usage(true),
            'memory_delta_bytes'=>max(0, memory_get_usage(true) - (int)$start['memory']),
            'http_status'=>0,
            'outcome'=>'success',
        ]);
    }

    public static function captureRequest(): void {
        if (!self::enabled() || self::$restCaptured || (function_exists('wp_doing_cron') && wp_doing_cron())) return;
        $context = self::requestContext();
        if (!$context) return;
        global $wpdb;
        self::recordSample([
            'operation_key'=>$context['operation_key'],
            'channel'=>$context['channel'],
            'category'=>$context['category'],
            'duration_ms'=>self::elapsedMs(self::$requestStartedAt ?: microtime(true)),
            'query_count'=>max(0, (int)($wpdb->num_queries ?? 0) - self::$requestStartedQueries),
            'memory_peak_bytes'=>memory_get_peak_usage(true),
            'memory_delta_bytes'=>max(0, memory_get_usage(true) - self::$requestStartedMemory),
            'http_status'=>function_exists('http_response_code') ? max(0, (int)http_response_code()) : 0,
            'outcome'=>self::requestOutcome(),
        ]);
    }

    public static function recordSample(array $sample, bool $force = false): bool {
        if (!self::enabled() || !self::tableReady()) return false;
        $category = sanitize_key((string)($sample['category'] ?? 'web'));
        $threshold = self::threshold($category);
        $duration = max(0, (int)($sample['duration_ms'] ?? 0));
        $status = max(0, (int)($sample['http_status'] ?? 0));
        $outcome = sanitize_key((string)($sample['outcome'] ?? 'success'));
        if (!in_array($outcome, ['success','client_error','error'], true)) $outcome = 'success';
        $always = $outcome !== 'success' || $status >= 400 || $duration >= (int)$threshold['warning_ms'];
        if (!$force && !$always && wp_rand(1, 100) > (int)self::settings()['sample_rate']) return false;
        global $wpdb;
        $operation = substr(preg_replace('/[^A-Za-z0-9_:\/ .{}-]/', '', (string)($sample['operation_key'] ?? 'fieldflow')) ?: 'fieldflow', 0, 191);
        $channel = substr(sanitize_key((string)($sample['channel'] ?? 'web')), 0, 32);
        $severity = 'normal';
        if ($duration >= (int)$threshold['critical_ms'] || (int)($sample['query_count'] ?? 0) >= (int)$threshold['critical_queries']) $severity = 'critical';
        elseif ($duration >= (int)$threshold['warning_ms'] || (int)($sample['query_count'] ?? 0) >= (int)$threshold['warning_queries']) $severity = 'warning';
        return $wpdb->insert(self::tableName(), [
            'operation_key'=>$operation,
            'channel'=>$channel,
            'category'=>$category,
            'severity'=>$severity,
            'outcome'=>$outcome,
            'http_status'=>$status,
            'duration_ms'=>$duration,
            'query_count'=>max(0, (int)($sample['query_count'] ?? 0)),
            'memory_peak_bytes'=>max(0, (int)($sample['memory_peak_bytes'] ?? 0)),
            'memory_delta_bytes'=>max(0, (int)($sample['memory_delta_bytes'] ?? 0)),
            'sample_rate'=>(int)self::settings()['sample_rate'],
            'sampled_at'=>current_time('mysql', true),
            'created_at'=>current_time('mysql', true),
        ], ['%s','%s','%s','%s','%s','%d','%d','%d','%d','%d','%d','%s','%s']) !== false;
    }

    public static function gate(?int $hours = null): array {
        $settings = self::settings();
        $hours = $hours === null ? (int)$settings['gate_window_hours'] : max(1, min(720, $hours));
        $base = [
            'version'=>self::VERSION,
            'mode'=>'advisory',
            'window_hours'=>$hours,
            'status'=>'collecting',
            'samples'=>0,
            'categories'=>[],
            'queue'=>self::queueStatus(),
            'generated_at'=>current_time('mysql', true),
        ];
        if (!self::tableReady()) {
            $base['status'] = 'blocked';
            $base['reason'] = 'performance_table_missing';
            return $base;
        }
        global $wpdb;
        $since = gmdate('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT category,COUNT(*) samples,AVG(duration_ms) avg_duration_ms,MAX(duration_ms) max_duration_ms,AVG(query_count) avg_queries,MAX(query_count) max_queries,MAX(memory_peak_bytes) peak_memory_bytes,' .
            'SUM(outcome=\'error\' AND (http_status=0 OR http_status>=500)) errors,' .
            'SUM(outcome=\'client_error\' OR (outcome=\'error\' AND http_status>=400 AND http_status<500)) client_errors FROM ' . self::tableName() . ' WHERE sampled_at>=%s GROUP BY category ORDER BY samples DESC',
            $since
        ), ARRAY_A) ?: [];
        $overall = 'pass';
        foreach ($rows as $row) {
            $category = sanitize_key((string)$row['category']);
            $count = (int)$row['samples'];
            $offset = max(0, (int)ceil($count * 0.95) - 1);
            $p95 = (int)$wpdb->get_var($wpdb->prepare(
                'SELECT duration_ms FROM ' . self::tableName() . ' WHERE sampled_at>=%s AND category=%s ORDER BY duration_ms ASC LIMIT 1 OFFSET ' . $offset,
                $since,
                $category
            ));
            $p95Queries = (int)$wpdb->get_var($wpdb->prepare(
                'SELECT query_count FROM ' . self::tableName() . ' WHERE sampled_at>=%s AND category=%s ORDER BY query_count ASC LIMIT 1 OFFSET ' . $offset,
                $since,
                $category
            ));
            $threshold = self::threshold($category);
            $errorRate = $count > 0 ? round(((int)$row['errors'] / $count) * 100, 2) : 0.0;
            $clientErrorRate = $count > 0 ? round(((int)$row['client_errors'] / $count) * 100, 2) : 0.0;
            $status = 'pass';
            $reasons = [];
            if ($count < (int)$settings['minimum_samples']) {
                $status = 'collecting';
                $reasons[] = 'Amostra ainda reduzida';
            }
            $hasErrorSample = $count >= (int)$settings['minimum_samples'];
            // 4xx responses are application/client outcomes, not server
            // reliability failures. Query volume is still monitored, but a
            // single query-count outlier is advisory unless latency, memory or
            // true server-error rate also crosses a critical boundary.
            if ($p95 >= (int)$threshold['critical_ms'] || ($hasErrorSample && $errorRate >= (float)$settings['error_rate_critical'])) {
                $status = 'fail';
                $reasons[] = 'Limite crítico de latência/erro do servidor ultrapassado';
            } elseif (
                $p95 >= (int)$threshold['warning_ms']
                || $p95Queries >= (int)$threshold['warning_queries']
                || (int)$row['max_queries'] >= (int)$threshold['critical_queries']
                || ($hasErrorSample && $errorRate >= (float)$settings['error_rate_warning'])
            ) {
                if ($status !== 'fail') $status = 'warning';
                $reasons[] = 'Sinal de capacidade acima do limiar de aviso';
            }
            if ((int)$row['peak_memory_bytes'] >= ((int)$threshold['memory_mb'] * MB_IN_BYTES)) {
                $status = 'fail';
                $reasons[] = 'Pico de memória acima do limite';
            }
            if ((int)$row['max_duration_ms'] >= (int)$threshold['critical_ms'] && $p95 < (int)$threshold['critical_ms'] && $status !== 'fail') {
                $status = 'warning';
                $reasons[] = 'Outlier de latência observado';
            }
            if ($status === 'fail') $overall = 'fail';
            elseif ($status === 'warning' && $overall !== 'fail') $overall = 'warning';
            elseif ($status === 'collecting' && $overall === 'pass') $overall = 'collecting';
            $base['samples'] += $count;
            $base['categories'][$category] = [
                'status'=>$status,
                'samples'=>$count,
                'avg_duration_ms'=>(int)round((float)$row['avg_duration_ms']),
                'p95_duration_ms'=>$p95,
                'max_duration_ms'=>(int)$row['max_duration_ms'],
                'avg_queries'=>(int)round((float)$row['avg_queries']),
                'p95_queries'=>$p95Queries,
                'max_queries'=>(int)$row['max_queries'],
                'peak_memory_bytes'=>(int)$row['peak_memory_bytes'],
                'errors'=>(int)$row['errors'],
                'error_rate_pct'=>$errorRate,
                'client_errors'=>(int)$row['client_errors'],
                'client_error_rate_pct'=>$clientErrorRate,
                'thresholds'=>$threshold,
                'reasons'=>array_values(array_unique($reasons)),
            ];
        }
        if (!$rows) $overall = 'collecting';
        $queue = $base['queue'];
        if (($queue['status'] ?? '') === 'critical') $overall = 'fail';
        elseif (($queue['status'] ?? '') === 'attention' && !in_array($overall, ['fail'], true)) $overall = 'warning';
        $base['status'] = $overall;
        return $base;
    }

    public static function status(): array {
        $settings = self::settings();
        $gate = self::gate((int)$settings['gate_window_hours']);
        $lastProbe = get_option(self::LAST_PROBE_OPTION, []);
        return [
            'version'=>self::VERSION,
            'enabled'=>!empty($settings['enabled']),
            'mode'=>(string)$settings['mode'],
            'table_ready'=>self::tableReady(),
            'sample_rate'=>(int)$settings['sample_rate'],
            'retention_days'=>(int)$settings['retention_days'],
            'gate_status'=>(string)$gate['status'],
            'samples'=>(int)$gate['samples'],
            'queue'=>$gate['queue'],
            'cron'=>self::cronStatus(),
            'last_probe'=>is_array($lastProbe) ? $lastProbe : [],
        ];
    }

    public static function queueStatus(): array {
        $health = class_exists(ProcessMonitor::class) ? ProcessMonitor::operationalHealth() : ['score'=>0,'status'=>'critical','overdue'=>0,'summary'=>[]];
        $modern = class_exists(QueueManager::class) ? QueueManager::status() : [];
        $modernStatus = (string)($modern['status'] ?? 'critical');
        $score = (int)($health['score'] ?? 0);
        if ($modernStatus === 'critical') $score = min($score, 60);
        elseif ($modernStatus === 'attention') $score = min($score, 80);
        $status = $score >= 90 ? 'healthy' : ($score >= 70 ? 'attention' : 'critical');
        return [
            'driver'=>(string)($modern['effective_driver'] ?? 'wp_cron'),
            'fallback'=>'wp_cron',
            'action_scheduler_available'=>!empty($modern['action_scheduler_available']),
            'action_scheduler_mode'=>(string)($modern['mode'] ?? 'legacy'),
            'modernization'=>$modern,
            'score'=>$score,
            'status'=>$status,
            'overdue'=>(int)($health['overdue'] ?? 0),
            'summary'=>is_array($health['summary'] ?? null) ? $health['summary'] : [],
        ];
    }

    public static function cronStatus(): array {
        $disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $heartbeat = (string)get_option('fieldflow_observability_last_heartbeat', '');
        $heartbeatTs = $heartbeat !== '' ? strtotime($heartbeat . ' UTC') : 0;
        $recent = $heartbeatTs > 0 && $heartbeatTs >= time() - (2 * HOUR_IN_SECONDS);
        $driver = !$disabled ? 'wp_cron' : ($recent ? 'external_cron_detected' : 'external_cron_required');
        return [
            'driver'=>$driver,
            'wp_cron_disabled'=>$disabled,
            'heartbeat_recent'=>$recent,
            'last_heartbeat'=>$heartbeat,
            'recommended_interval_minutes'=>5,
        ];
    }

    public static function databaseFootprint(): array {
        global $wpdb;
        $pattern = $wpdb->esc_like($wpdb->prefix . 'routespro_') . '%';
        $rows = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $pattern), ARRAY_A) ?: [];
        $tables = [];
        $dataBytes = 0;
        $indexBytes = 0;
        foreach ($rows as $row) {
            $name = (string)($row['Name'] ?? '');
            if ($name === '') continue;
            $data = max(0, (int)($row['Data_length'] ?? 0));
            $index = max(0, (int)($row['Index_length'] ?? 0));
            $total = $data + $index;
            $dataBytes += $data;
            $indexBytes += $index;
            $suffix = strpos($name, $wpdb->prefix . 'routespro_') === 0 ? substr($name, strlen($wpdb->prefix . 'routespro_')) : $name;
            $tables[] = [
                'table'=>sanitize_key($suffix),
                'rows_estimate'=>max(0, (int)($row['Rows'] ?? 0)),
                'data_bytes'=>$data,
                'index_bytes'=>$index,
                'total_bytes'=>$total,
            ];
        }
        usort($tables, static fn(array $a, array $b): int => ($b['total_bytes'] <=> $a['total_bytes']));
        return [
            'table_count'=>count($tables),
            'data_bytes'=>$dataBytes,
            'index_bytes'=>$indexBytes,
            'total_bytes'=>$dataBytes + $indexBytes,
            'largest_tables'=>array_slice($tables, 0, 12),
            'read_only'=>true,
        ];
    }

    public static function runProbe(int $iterations = 5, string $source = 'backoffice'): array {
        global $wpdb;
        $iterations = max(1, min(25, $iterations));
        $targets = ['clients','projects','routes','form_submissions','process_runs','queue_jobs'];
        $tables = [];
        foreach ($targets as $suffix) {
            $table = $wpdb->prefix . 'routespro_' . $suffix;
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) $tables[] = $table;
        }
        $start = microtime(true);
        $queryStart = (int)($wpdb->num_queries ?? 0);
        $memoryStart = memory_get_usage(true);
        $checks = 0;
        foreach (range(1, $iterations) as $iteration) {
            foreach ($tables as $table) {
                $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                $checks++;
            }
        }
        $duration = self::elapsedMs($start);
        $queries = max(0, (int)($wpdb->num_queries ?? 0) - $queryStart);
        $databaseProfile = self::databaseFootprint();
        $result = [
            'status'=>$duration < 3000 ? 'pass' : 'warning',
            'source'=>sanitize_key($source),
            'iterations'=>$iterations,
            'tables'=>count($tables),
            'checks'=>$checks,
            'duration_ms'=>$duration,
            'query_count'=>$queries,
            'memory_peak_bytes'=>memory_get_peak_usage(true),
            'memory_delta_bytes'=>max(0, memory_get_usage(true) - $memoryStart),
            'database_profile'=>$databaseProfile,
            'executed_at'=>current_time('mysql', true),
            'read_only'=>true,
        ];
        update_option(self::LAST_PROBE_OPTION, $result, false);
        self::recordSample([
            'operation_key'=>'PROBE database_readiness',
            'channel'=>'probe',
            'category'=>'probe',
            'duration_ms'=>$duration,
            'query_count'=>$queries,
            'memory_peak_bytes'=>memory_get_peak_usage(true),
            'memory_delta_bytes'=>$result['memory_delta_bytes'],
            'http_status'=>0,
            'outcome'=>'success',
        ], true);
        if (class_exists(AuditLog::class)) {
            AuditLog::record('performance.probe_executed', 'system', 0, null, $result, ['source'=>$source]);
        }
        return $result;
    }

    public static function cleanup(): void {
        if (!self::tableReady()) return;
        global $wpdb;
        $days = max(7, min(180, (int)self::settings()['retention_days']));
        $before = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::tableName() . ' WHERE sampled_at<%s', $before));
    }

    public static function handleSave(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.', 'FieldFlow', ['response'=>403]);
        check_admin_referer('fieldflow_load_readiness_save');
        $input = isset($_POST['fieldflow_load_readiness']) && is_array($_POST['fieldflow_load_readiness']) ? wp_unslash($_POST['fieldflow_load_readiness']) : [];
        $current = self::settings();
        $current['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $current['sample_rate'] = max(1, min(100, absint($input['sample_rate'] ?? $current['sample_rate'])));
        $current['retention_days'] = max(7, min(180, absint($input['retention_days'] ?? $current['retention_days'])));
        $current['gate_window_hours'] = max(1, min(720, absint($input['gate_window_hours'] ?? $current['gate_window_hours'])));
        $current['minimum_samples'] = max(1, min(1000, absint($input['minimum_samples'] ?? $current['minimum_samples'])));
        foreach (['rest','mobile','submission','export','admin','web','cron'] as $category) {
            $posted = is_array($input['thresholds'][$category] ?? null) ? $input['thresholds'][$category] : [];
            foreach (['warning_ms','critical_ms','warning_queries','critical_queries','memory_mb'] as $field) {
                if (isset($posted[$field])) $current['thresholds'][$category][$field] = max(1, absint($posted[$field]));
            }
            $current['thresholds'][$category]['critical_ms'] = max((int)$current['thresholds'][$category]['warning_ms'], (int)$current['thresholds'][$category]['critical_ms']);
            $current['thresholds'][$category]['critical_queries'] = max((int)$current['thresholds'][$category]['warning_queries'], (int)$current['thresholds'][$category]['critical_queries']);
        }
        update_option(self::OPTION, $current, false);
        if (class_exists(AuditLog::class)) AuditLog::record('performance.settings_updated', 'system', 0, null, ['sample_rate'=>$current['sample_rate'],'retention_days'=>$current['retention_days'],'gate_window_hours'=>$current['gate_window_hours']], ['source'=>'backoffice']);
        self::redirect('saved');
    }

    public static function handleProbe(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.', 'FieldFlow', ['response'=>403]);
        check_admin_referer('fieldflow_load_readiness_probe');
        $iterations = max(1, min(25, absint($_POST['iterations'] ?? 5)));
        self::runProbe($iterations, 'backoffice');
        self::redirect('probe');
    }

    private static function redirect(string $notice): void {
        wp_safe_redirect(add_query_arg(['page'=>'routespro-security-observability','tab'=>'performance','fieldflow_performance_notice'=>$notice], admin_url('admin.php')));
        exit;
    }

    private static function enabled(): bool {
        return !empty(self::settings()['enabled']);
    }

    private static function tableReady(): bool {
        if (self::$tableReady !== null) return self::$tableReady;
        global $wpdb;
        $table = self::tableName();
        self::$tableReady = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        return self::$tableReady;
    }

    private static function threshold(string $category): array {
        $settings = self::settings();
        return $settings['thresholds'][$category] ?? $settings['thresholds']['web'];
    }

    private static function categoryForRoute(string $route, string $channel): string {
        if (preg_match('#/(submit|submission|sync|complete-visit|report)#i', $route)) return 'submission';
        if (preg_match('#/(export|download|zip|pdf|excel|csv)#i', $route)) return 'export';
        return $channel === 'mobile' ? 'mobile' : 'rest';
    }

    private static function requestContext(): array {
        $page = isset($_REQUEST['page']) ? sanitize_key((string)wp_unslash($_REQUEST['page'])) : '';
        $action = isset($_REQUEST['action']) ? sanitize_key((string)wp_unslash($_REQUEST['action'])) : '';
        $fieldFlowPage = strpos($page, 'routespro') === 0 || strpos($page, 'fieldflow') === 0;
        $fieldFlowAction = strpos($action, 'routespro') === 0 || strpos($action, 'fieldflow') === 0;
        if (is_admin() && ($fieldFlowPage || $fieldFlowAction)) {
            $name = $action !== '' ? $action : $page;
            $category = preg_match('/export|download|zip|pdf|excel|csv/', $name) ? 'export' : (preg_match('/submit|submission|report_visit/', $name) ? 'submission' : 'admin');
            return ['operation_key'=>'ADMIN ' . ($name ?: 'fieldflow'),'channel'=>'admin','category'=>$category];
        }
        if (!is_admin() && class_exists('\RoutesPro\Front\ResponsiveAssets') && \RoutesPro\Front\ResponsiveAssets::shouldLoadForRequest()) {
            return ['operation_key'=>'WEB fieldflow_portal','channel'=>'web','category'=>'web'];
        }
        return [];
    }

    private static function requestOutcome(): string {
        $status = function_exists('http_response_code') ? (int)http_response_code() : 200;
        $error = error_get_last();
        $fatal = $error && in_array((int)($error['type'] ?? 0), [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR], true);
        if ($fatal || $status >= 500) return 'error';
        if ($status >= 400) return 'client_error';
        return 'success';
    }

    private static function normaliseRoute(string $route): string {
        $route = preg_replace('#/[0-9]+(?=/|$)#', '/{id}', $route) ?: $route;
        $route = preg_replace('#/[a-f0-9]{8}-[a-f0-9-]{27,}(?=/|$)#i', '/{uuid}', $route) ?: $route;
        return substr($route, 0, 160);
    }

    private static function isFieldFlowRoute(string $route): bool {
        return (bool)preg_match('#^/(fieldflow|routespro)#i', $route);
    }

    private static function elapsedMs(float $startedAt): int {
        return max(0, (int)round((microtime(true) - $startedAt) * 1000));
    }

    private static function merge(array $defaults, array $stored): array {
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) $defaults[$key] = self::merge($defaults[$key], $value);
            else $defaults[$key] = $value;
        }
        return $defaults;
    }
}
