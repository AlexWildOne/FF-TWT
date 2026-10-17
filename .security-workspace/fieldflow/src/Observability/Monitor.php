<?php
namespace RoutesPro\Observability;

use RoutesPro\Core\AuditLog;
use RoutesPro\Security\Settings;

if (!defined('ABSPATH')) exit;

class Monitor {
    public const CRON_HOOK = 'fieldflow_observability_hourly';
    public const CLEANUP_HOOK = 'fieldflow_observability_cleanup';
    public const HEALTH_ACTIVE_CRITICAL_HOURS = 6;
    private static bool $registered = false;

    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_observability_events';
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('shutdown', [self::class, 'captureFatal']);
        add_action('http_api_debug', [self::class, 'captureHttp'], 10, 5);
        add_action(self::CRON_HOOK, [self::class, 'hourlyCheck']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
        add_action('init', [self::class, 'schedule']);
    }

    public static function schedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
    }

    public static function recordRest(string $route, string $method, int $status, int $durationMs, int $queryCount = 0, array $context = []): void {
        $settings = Settings::get()['observability'];
        $severity = 'info';
        if ($status >= 500 || $durationMs >= (int)$settings['critical_rest_ms']) $severity = 'critical';
        elseif ($status >= 400 || $durationMs >= (int)$settings['slow_rest_ms']) $severity = 'warning';
        if ($severity === 'info' && $durationMs < (int)$settings['slow_rest_ms']) return;
        self::record('rest_request', $severity, $route . ' ' . strtoupper($method), array_merge([
            'route' => $route,
            'method' => strtoupper($method),
            'status' => $status,
            'duration_ms' => $durationMs,
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'query_count' => $queryCount,
        ], self::sanitizeContext($context)), $durationMs);
    }

    public static function captureFatal(): void {
        $error = error_get_last();
        if (!$error || !in_array((int)$error['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR], true)) return;
        $file = (string)($error['file'] ?? '');
        if (strpos($file, 'fieldflow') === false && strpos($file, 'routes-pro') === false) return;
        self::record('php_fatal', 'critical', 'Erro fatal PHP', [
            'message' => substr((string)($error['message'] ?? ''), 0, 1000),
            'file' => self::relativePath($file),
            'line' => (int)($error['line'] ?? 0),
        ]);
    }

    public static function captureHttp($response, string $context, string $class, array $parsedArgs, string $url): void {
        if ($context !== 'response') return;
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) return;
        $agent=(string)($parsedArgs['user-agent'] ?? '');
        if(!preg_match('/(googleapis|openai|azure|microsoft|copilot|foundry|maps|graph\.microsoft)/i',(string)$host) && stripos($agent,'FieldFlow')===false) return;
        $isError = is_wp_error($response);
        $status = $isError ? 0 : (int)wp_remote_retrieve_response_code($response);
        if (!$isError && $status < 400) return;
        self::record('external_http', $status >= 500 || $status === 0 ? 'critical' : 'warning', 'Falha de integração externa', [
            'host' => sanitize_text_field((string)$host),
            'status' => $status,
            'error_code' => $isError ? $response->get_error_code() : '',
            'method' => sanitize_key((string)($parsedArgs['method'] ?? 'GET')),
        ]);
    }

    public static function record(string $eventType, string $severity, string $message, array $context = [], int $durationMs = 0): int {
        global $wpdb;
        $table = self::tableName();
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return 0;
        $eventType = substr(sanitize_key($eventType), 0, 64);
        $severity = in_array($severity, ['info','warning','critical'], true) ? $severity : 'info';
        $fingerprint = hash('sha256', implode('|', [
            $eventType,
            (string)($context['route'] ?? ''),
            (string)($context['status'] ?? ''),
            (string)($context['host'] ?? ''),
            (string)($context['file'] ?? ''),
            (string)($context['line'] ?? ''),
            substr($message, 0, 200),
        ]));
        $now = current_time('mysql', true);
        $payload = self::sanitizeContext($context);
        $existing = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE fingerprint=%s LIMIT 1", $fingerprint));
        if ($existing) {
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET occurrences=occurrences+1, severity=%s, message=%s, duration_ms=GREATEST(duration_ms,%d), payload_json=%s, last_seen_at=%s, updated_at=%s WHERE id=%d", $severity, substr(wp_strip_all_tags($message),0,255), $durationMs, wp_json_encode($payload), $now, $now, $existing));
            if ($severity === 'critical') AlertService::fromObservation($existing, $eventType, $message, $payload, $severity);
            return $existing;
        }
        $wpdb->insert($table, [
            'event_uuid' => wp_generate_uuid4(),
            'fingerprint' => $fingerprint,
            'event_type' => $eventType,
            'severity' => $severity,
            'status' => 'open',
            'message' => substr(wp_strip_all_tags($message), 0, 255),
            'user_id' => get_current_user_id() ?: null,
            'client_id' => absint($context['client_id'] ?? 0) ?: null,
            'project_id' => absint($context['project_id'] ?? 0) ?: null,
            'device_id' => substr(sanitize_text_field((string)($context['device_id'] ?? ($_SERVER['HTTP_X_FIELDFLOW_DEVICE_ID'] ?? ''))),0,191) ?: null,
            'route' => substr(sanitize_text_field((string)($context['route'] ?? '')),0,191) ?: null,
            'http_status' => absint($context['status'] ?? 0) ?: null,
            'duration_ms' => max(0, $durationMs),
            'memory_bytes' => memory_get_peak_usage(true),
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'occurrences' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int)$wpdb->insert_id;
        if ($id > 0 && $severity === 'critical') AlertService::fromObservation($id, $eventType, $message, $payload, $severity);
        return $id;
    }

    public static function hourlyCheck(): void {
        $settings = Settings::get()['observability'];
        $offline = class_exists('RoutesPro\\Mobile\\OfflineSyncService') ? \RoutesPro\Mobile\OfflineSyncService::stats() : [];
        $pending = (int)($offline['pending'] ?? 0);
        $failed = (int)($offline['failed'] ?? 0);
        if ($pending >= (int)$settings['queue_critical'] || $failed >= (int)$settings['queue_warning']) {
            self::record('offline_queue', $pending >= (int)$settings['queue_critical'] ? 'critical' : 'warning', 'Fila offline requer atenção', ['pending' => $pending, 'failed' => $failed]);
        }
        $uploads = wp_upload_dir();
        $baseDir = (string)($uploads['basedir'] ?? '');
        $bytes = self::fieldFlowStorageSize($baseDir);
        $mb = (int)round($bytes / MB_IN_BYTES);
        if ($mb >= (int)$settings['storage_warning_mb']) {
            self::record('storage_usage', 'warning', 'Armazenamento de uploads acima do limite de aviso', ['storage_mb' => $mb, 'threshold_mb' => (int)$settings['storage_warning_mb']]);
        }
        $missed = self::missedCronCount();
        if ($missed > 0) self::record('cron_missed', $missed >= 5 ? 'critical' : 'warning', 'Existem tarefas cron atrasadas', ['missed_jobs' => $missed]);
        update_option('fieldflow_observability_last_heartbeat', $now = current_time('mysql', true), false);
        AuditLog::record('observability.heartbeat', 'system', 0, null, ['at' => $now, 'pending' => $pending, 'failed' => $failed, 'storage_mb' => $mb, 'missed_cron' => $missed], ['source' => 'cron']);
    }

    public static function summary(): array {
        global $wpdb;
        $table = self::tableName();
        $exists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        $base = [
            'tables_ready'=>$exists,
            'open'=>0,
            'critical'=>0,
            'critical_active'=>0,
            'critical_historical'=>0,
            'critical_active_window_hours'=>self::HEALTH_ACTIVE_CRITICAL_HOURS,
            'warning'=>0,
            'events_24h'=>0,
            'slowest'=>[],
            'last_heartbeat'=>(string)get_option('fieldflow_observability_last_heartbeat',''),
        ];
        if (!$exists) return $base;
        $hours = max(1, (int)self::HEALTH_ACTIVE_CRITICAL_HOURS);
        $row = $wpdb->get_row(
            "SELECT SUM(status='open') open, " .
            "SUM(status='open' AND severity='critical') critical, " .
            "SUM(status='open' AND severity='critical' AND last_seen_at >= UTC_TIMESTAMP() - INTERVAL {$hours} HOUR) critical_active, " .
            "SUM(status='open' AND severity='critical' AND last_seen_at < UTC_TIMESTAMP() - INTERVAL {$hours} HOUR) critical_historical, " .
            "SUM(status='open' AND severity='warning') warning, " .
            "SUM(last_seen_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR) events_24h FROM {$table}",
            ARRAY_A
        ) ?: [];
        $base['open']=(int)($row['open']??0);
        $base['critical']=(int)($row['critical']??0);
        $base['critical_active']=(int)($row['critical_active']??0);
        $base['critical_historical']=(int)($row['critical_historical']??0);
        $base['warning']=(int)($row['warning']??0);
        $base['events_24h']=(int)($row['events_24h']??0);
        $base['slowest']=$wpdb->get_results("SELECT route, MAX(duration_ms) max_duration_ms, SUM(occurrences) occurrences FROM {$table} WHERE event_type='rest_request' AND route IS NOT NULL GROUP BY route ORDER BY max_duration_ms DESC LIMIT 5", ARRAY_A) ?: [];
        return $base;
    }

    public static function recent(int $limit = 60, string $status = ''): array {
        global $wpdb;
        $table = self::tableName();
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        $limit=max(1,min(250,$limit));
        if ($status !== '') return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status=%s ORDER BY last_seen_at DESC LIMIT %d",sanitize_key($status),$limit),ARRAY_A) ?: [];
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY last_seen_at DESC LIMIT %d",$limit),ARRAY_A) ?: [];
    }

    public static function cleanup(): void {
        global $wpdb;
        $settings = Settings::get()['observability'];
        $days = (int)$settings['retention_days'];
        $securityDays = (int)$settings['security_retention_days'];
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::tableName() . ' WHERE last_seen_at < %s', gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)));
        $securityTable = $wpdb->prefix . 'routespro_security_events';
        $wpdb->query($wpdb->prepare("DELETE FROM {$securityTable} WHERE last_seen_at < %s", gmdate('Y-m-d H:i:s', time() - $securityDays * DAY_IN_SECONDS)));
        if (class_exists('RoutesPro\\Repositories\\SystemLogRepository')) \RoutesPro\Repositories\SystemLogRepository::pruneOlderThanDays($days);
    }

    public static function missedCronCount(): int {
        $cron = _get_cron_array();
        if (!is_array($cron)) return 0;
        $now = time() - 300;
        $count = 0;
        foreach ($cron as $timestamp => $hooks) if ((int)$timestamp < $now) $count += is_array($hooks) ? count($hooks) : 1;
        return $count;
    }

    private static function sanitizeContext(array $context): array {
        foreach ($context as $key => $value) {
            if (preg_match('/token|secret|password|authorization|nonce|api.?key/i', (string)$key)) $context[$key] = '[redacted]';
            elseif (is_string($value)) $context[$key] = substr(sanitize_textarea_field($value),0,1000);
            elseif (is_object($value)) $context[$key] = '[object]';
        }
        return $context;
    }

    private static function relativePath(string $path): string {
        $root = defined('ABSPATH') ? wp_normalize_path(ABSPATH) : '';
        $path = wp_normalize_path($path);
        return $root && strpos($path,$root)===0 ? ltrim(substr($path,strlen($root)),'/') : basename($path);
    }

    private static function fieldFlowStorageSize(string $baseDir): int {
        if ($baseDir === '' || !is_dir($baseDir)) return 0;
        $directories = [
            'fieldflow-private-drafts',
            'fieldflow-private-expenses',
            'fieldflow-evidence-exports',
            'fieldflow-diagnostics',
            'fieldflow-exports',
            'routespro-exports',
        ];
        $size = 0;
        foreach ($directories as $directory) {
            $size += self::directorySize(trailingslashit($baseDir) . $directory, 10000);
        }
        return $size;
    }

    private static function directorySize(string $dir, int $maxFiles): int {
        if ($dir === '' || !is_dir($dir)) return 0;
        $size=0; $count=0;
        try {
            $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file){ if(++$count>$maxFiles) break; if($file->isFile()) $size += $file->getSize(); }
        } catch(\Throwable $e) { return 0; }
        return $size;
    }
}
