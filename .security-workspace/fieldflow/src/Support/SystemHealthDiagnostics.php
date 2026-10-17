<?php
namespace RoutesPro\Support;

use RoutesPro\Mobile\OfflineSyncService;
use RoutesPro\Observability\Monitor;
use RoutesPro\Operations\ProcessMonitor;
use RoutesPro\Operations\Queue\QueueManager;
use RoutesPro\Performance\LoadReadiness;
use RoutesPro\Security\OutboundHttp;
use RoutesPro\Security\SecurityService;
use RoutesPro\Security\SessionPolicy;

if (!defined('ABSPATH')) exit;

/**
 * Read-only, privacy-preserving diagnostic snapshot for System Health.
 *
 * The export is designed for support/certification handoff. It intentionally
 * excludes payloads, credentials, user IDs, device IDs, IPs and functional
 * content while keeping the technical evidence required to understand health
 * warnings.
 */
final class SystemHealthDiagnostics {
    public const SCHEMA = 'fieldflow.system-health-diagnostic.export';
    public const SCHEMA_VERSION = '1.0';

    public static function snapshot(): array {
        $now = time();
        $health = SystemHealth::status();
        $checks = [];
        $warningCounts = ['ok'=>0,'warning'=>0,'critical'=>0];
        foreach ((array)($health['checks'] ?? []) as $check) {
            $status = sanitize_key((string)($check['status'] ?? 'warning'));
            if (!isset($warningCounts[$status])) $status = 'warning';
            $warningCounts[$status]++;
            if ($status === 'ok') continue;
            $checks[] = [
                'key'=>sanitize_key((string)($check['key'] ?? 'unknown')),
                'label'=>self::cleanText((string)($check['label'] ?? '')),
                'status'=>$status,
                'detail'=>self::redactPaths((string)($check['detail'] ?? '')),
            ];
        }

        $performance = self::performanceSnapshot();
        $offline = self::offlineSnapshot();
        $observability = self::observabilitySnapshot();
        $processes = self::processSnapshot();
        $release = self::releaseSnapshot();

        return [
            'schema'=>self::SCHEMA,
            'schema_version'=>self::SCHEMA_VERSION,
            'generated_at_utc'=>gmdate('c', $now),
            'fieldflow_version'=>defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '',
            'privacy'=>[
                'sanitized'=>true,
                'request_payloads_included'=>false,
                'functional_content_included'=>false,
                'passwords_included'=>false,
                'tokens_included'=>false,
                'nonces_included'=>false,
                'cookies_included'=>false,
                'ip_addresses_or_hashes_included'=>false,
                'device_ids_included'=>false,
                'individual_user_ids_included'=>false,
                'filesystem_paths_redacted'=>true,
                'raw_device_errors_included'=>false,
            ],
            'health_overview'=>[
                'summary'=>sanitize_key((string)($health['summary'] ?? 'unknown')),
                'check_counts'=>$warningCounts,
                'active_warnings'=>$checks,
                'selected_counts'=>[
                    'observability_open'=>(int)($health['counts']['observability_open'] ?? 0),
                    'observability_critical'=>(int)($health['counts']['observability_critical'] ?? 0),
                    'observability_critical_historical'=>(int)($health['counts']['observability_critical_historical'] ?? 0),
                    'security_open'=>(int)($health['counts']['security_open'] ?? 0),
                    'security_critical'=>(int)($health['counts']['security_critical'] ?? 0),
                    'performance_samples'=>(int)($health['counts']['performance_samples'] ?? 0),
                    'queue_health_score'=>(int)($health['counts']['queue_health_score'] ?? 0),
                    'queue_pending'=>(int)($health['counts']['queue_pending'] ?? 0),
                    'queue_dead'=>(int)($health['counts']['queue_dead'] ?? 0),
                    'migration_failed'=>(int)($health['counts']['migration_failed'] ?? 0),
                    'missing_runtime_files'=>(int)($health['counts']['missing_runtime_files'] ?? 0),
                    'missing_release_files'=>(int)($health['counts']['missing_release_files'] ?? 0),
                ],
            ],
            'offline_first'=>$offline,
            'observability'=>$observability,
            'performance_and_load'=>$performance,
            'process_and_queue'=>$processes,
            'security_foundation'=>[
                'security_counts'=>class_exists(SecurityService::class) ? self::securityCounts() : [],
                'outbound_http_guard'=>class_exists(OutboundHttp::class) ? self::outboundStatus() : ['available'=>false],
                'authentication_policy'=>class_exists(SessionPolicy::class) ? self::authPolicyStatus() : ['available'=>false],
            ],
            'release_integrity'=>$release,
        ];
    }

    private static function authPolicyStatus(): array {
        $s = SessionPolicy::status();
        return [
            'available'=>true,
            'version'=>(string)($s['version'] ?? ''),
            'mfa_mode'=>(string)($s['mfa_mode'] ?? 'observe'),
            'session_mode'=>(string)($s['session_mode'] ?? 'observe'),
            'privileged_users'=>(int)($s['privileged_users'] ?? 0),
            'mfa_enabled_users'=>(int)($s['mfa_enabled_users'] ?? 0),
            'mobile_privileged_mfa'=>!empty($s['mobile_privileged_mfa']),
            'wp_privileged_idle_minutes'=>(int)($s['wp_privileged_idle_minutes'] ?? 0),
            'wp_privileged_absolute_hours'=>(int)($s['wp_privileged_absolute_hours'] ?? 0),
            'mobile_idle_days'=>(int)($s['mobile_idle_days'] ?? 0),
            'mobile_absolute_days'=>(int)($s['mobile_absolute_days'] ?? 0),
            'web_idle_minutes'=>(int)($s['web_idle_minutes'] ?? 0),
            'web_absolute_hours'=>(int)($s['web_absolute_hours'] ?? 0),
        ];
    }

    private static function offlineSnapshot(): array {
        $stats = class_exists(OfflineSyncService::class) ? OfflineSyncService::stats() : [];
        $base = [
            'stats'=>[
                'tables_ready'=>!empty($stats['tables_ready']),
                'devices'=>(int)($stats['devices'] ?? 0),
                'active_24h'=>(int)($stats['active_24h'] ?? 0),
                'pending'=>(int)($stats['pending'] ?? 0),
                'failed'=>(int)($stats['failed'] ?? 0),
                'conflicts'=>(int)($stats['conflicts'] ?? 0),
                'active_failed_24h'=>(int)($stats['active_failed_24h'] ?? 0),
                'active_conflicts_24h'=>(int)($stats['active_conflicts_24h'] ?? 0),
                'stale_attention_devices'=>(int)($stats['stale_attention_devices'] ?? 0),
                'last_sync_at_utc'=>self::utcDate((string)($stats['last_sync_at'] ?? '')),
            ],
            'affected_devices'=>0,
            'pending_devices'=>0,
            'failed_devices'=>0,
            'age_buckets'=>[
                'pending'=>self::emptyBuckets(),
                'failed'=>self::emptyBuckets(),
                'conflicts'=>self::emptyBuckets(),
            ],
            'groups'=>[],
        ];
        if (empty($stats['tables_ready'])) return $base;

        global $wpdb;
        $table = OfflineSyncService::deviceTable();
        $rows = $wpdb->get_results(
            "SELECT platform,app_version,queue_pending,queue_failed,last_error,last_seen_at,last_sync_at,meta_json FROM {$table} ORDER BY COALESCE(last_seen_at,updated_at,created_at) DESC LIMIT 500",
            ARRAY_A
        ) ?: [];

        $groups = [];
        foreach ($rows as $row) {
            $meta = json_decode((string)($row['meta_json'] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            $pending = array_key_exists('queue_pending', $meta)
                ? max(0, (int)$meta['queue_pending'])
                : max(0, (int)($row['queue_pending'] ?? 0));
            $conflicts = array_key_exists('queue_conflicts', $meta) ? max(0, (int)$meta['queue_conflicts']) : 0;
            $failed = array_key_exists('queue_failed', $meta)
                ? max(0, (int)$meta['queue_failed'])
                : max(0, (int)($row['queue_failed'] ?? 0) - $conflicts);
            if ($pending <= 0 && $failed <= 0 && $conflicts <= 0) continue;
            $base['affected_devices']++;
            if ($pending > 0) $base['pending_devices']++;
            if ($failed > 0) $base['failed_devices']++;

            $lastSeen = (string)($row['last_seen_at'] ?? '');
            if ($pending > 0) $base['age_buckets']['pending'][self::ageBucket($lastSeen)]++;
            if ($failed > 0) $base['age_buckets']['failed'][self::ageBucket($lastSeen)]++;
            if ($conflicts > 0) $base['age_buckets']['conflicts'][self::ageBucket($lastSeen)]++;
            $platform = sanitize_key((string)($row['platform'] ?? 'unknown')) ?: 'unknown';
            $appVersion = self::versionText((string)($row['app_version'] ?? ''));
            $runtimeBuild = self::versionText((string)($meta['runtime_build'] ?? ''));
            $errorCode = self::errorCode((string)($meta['last_error_code'] ?? ''), (string)($row['last_error'] ?? ''));
            $httpStatus = max(0, min(599, (int)($meta['last_http_status'] ?? 0)));
            $syncResult = sanitize_key((string)($meta['last_sync_result'] ?? ''));
            $network = sanitize_key((string)($meta['network_type'] ?? $meta['effective_type'] ?? ''));
            $key = implode('|', [$platform,$appVersion,$runtimeBuild,$errorCode,$httpStatus,$syncResult,$network]);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'platform'=>$platform,
                    'app_version'=>$appVersion,
                    'runtime_build'=>$runtimeBuild,
                    'error_code'=>$errorCode,
                    'http_status'=>$httpStatus,
                    'last_sync_result'=>$syncResult,
                    'network_type'=>$network,
                    'devices'=>0,
                    'pending'=>0,
                    'failed'=>0,
                    'conflicts'=>0,
                    'latest_seen_at_utc'=>'',
                    'latest_sync_at_utc'=>'',
                ];
            }
            $groups[$key]['devices']++;
            $groups[$key]['pending'] += $pending;
            $groups[$key]['failed'] += $failed;
            $groups[$key]['conflicts'] += $conflicts;
            $groups[$key]['latest_seen_at_utc'] = self::newerDate($groups[$key]['latest_seen_at_utc'], self::utcDate($lastSeen));
            $groups[$key]['latest_sync_at_utc'] = self::newerDate($groups[$key]['latest_sync_at_utc'], self::utcDate((string)($row['last_sync_at'] ?? '')));
        }
        $groups = array_values($groups);
        usort($groups, static function (array $a, array $b): int {
            $aw = ((int)$a['failed'] * 1000) + ((int)$a['conflicts'] * 100) + (int)$a['pending'];
            $bw = ((int)$b['failed'] * 1000) + ((int)$b['conflicts'] * 100) + (int)$b['pending'];
            return $bw <=> $aw;
        });
        $base['groups'] = array_slice($groups, 0, 100);
        return $base;
    }

    private static function observabilitySnapshot(): array {
        $summary = class_exists(Monitor::class) ? Monitor::summary() : [];
        return [
            'summary'=>[
                'tables_ready'=>!empty($summary['tables_ready']),
                'open'=>(int)($summary['open'] ?? 0),
                'critical'=>(int)($summary['critical'] ?? 0),
                'critical_active'=>(int)($summary['critical_active'] ?? 0),
                'critical_historical'=>(int)($summary['critical_historical'] ?? 0),
                'critical_active_window_hours'=>(int)($summary['critical_active_window_hours'] ?? 0),
                'warning'=>(int)($summary['warning'] ?? 0),
                'events_24h'=>(int)($summary['events_24h'] ?? 0),
                'last_heartbeat_utc'=>self::utcDate((string)($summary['last_heartbeat'] ?? '')),
                'missed_cron_jobs'=>class_exists(Monitor::class) ? Monitor::missedCronCount() : 0,
            ],
            'critical_open'=>self::observationRows('critical', 250),
            'warning_open'=>self::observationRows('warning', 100),
        ];
    }

    private static function observationRows(string $severity, int $limit): array {
        if (!class_exists(Monitor::class)) return [];
        global $wpdb;
        $table = Monitor::tableName();
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        $severity = in_array($severity, ['critical','warning'], true) ? $severity : 'warning';
        $limit = max(1, min(500, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type,severity,message,route,http_status,duration_ms,occurrences,first_seen_at,last_seen_at,payload_json FROM {$table} WHERE status='open' AND severity=%s ORDER BY last_seen_at DESC LIMIT %d",
            $severity,
            $limit
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $context = json_decode((string)($row['payload_json'] ?? ''), true);
            $eventType = sanitize_key((string)($row['event_type'] ?? ''));
            $safeContext = self::safeObservationContext(is_array($context) ? $context : []);
            $out[] = [
                'event_type'=>$eventType,
                'severity'=>$severity,
                'message'=>self::observationMessage($eventType, (string)($row['message'] ?? ''), $safeContext),
                'route'=>self::route((string)($row['route'] ?? '')),
                'http_status'=>max(0, min(599, (int)($row['http_status'] ?? 0))),
                'duration_ms'=>max(0, (int)($row['duration_ms'] ?? 0)),
                'occurrences'=>max(0, (int)($row['occurrences'] ?? 0)),
                'first_seen_at_utc'=>self::utcDate((string)($row['first_seen_at'] ?? '')),
                'last_seen_at_utc'=>self::utcDate((string)($row['last_seen_at'] ?? '')),
                'context'=>$safeContext,
            ];
        }
        return $out;
    }

    private static function performanceSnapshot(): array {
        if (!class_exists(LoadReadiness::class)) return ['available'=>false];
        $settings = LoadReadiness::settings();
        $hours = max(1, min(720, (int)($settings['gate_window_hours'] ?? 24)));
        $gate = LoadReadiness::gate($hours);
        $queue = is_array($gate['queue'] ?? null) ? $gate['queue'] : [];
        $categories = [];
        foreach ((array)($gate['categories'] ?? []) as $key => $row) {
            if (!is_array($row)) continue;
            $categories[sanitize_key((string)$key)] = [
                'status'=>sanitize_key((string)($row['status'] ?? 'collecting')),
                'samples'=>(int)($row['samples'] ?? 0),
                'avg_duration_ms'=>(int)($row['avg_duration_ms'] ?? 0),
                'p95_duration_ms'=>(int)($row['p95_duration_ms'] ?? 0),
                'max_duration_ms'=>(int)($row['max_duration_ms'] ?? 0),
                'avg_queries'=>(int)($row['avg_queries'] ?? 0),
                'p95_queries'=>(int)($row['p95_queries'] ?? 0),
                'max_queries'=>(int)($row['max_queries'] ?? 0),
                'peak_memory_bytes'=>(int)($row['peak_memory_bytes'] ?? 0),
                'errors'=>(int)($row['errors'] ?? 0),
                'error_rate_pct'=>(float)($row['error_rate_pct'] ?? 0),
                'client_errors'=>(int)($row['client_errors'] ?? 0),
                'client_error_rate_pct'=>(float)($row['client_error_rate_pct'] ?? 0),
                'thresholds'=>self::numericArray((array)($row['thresholds'] ?? [])),
                'reasons'=>array_values(array_map([self::class,'cleanText'], (array)($row['reasons'] ?? []))),
            ];
        }
        return [
            'available'=>true,
            'version'=>(string)($gate['version'] ?? LoadReadiness::VERSION),
            'gate_status'=>sanitize_key((string)($gate['status'] ?? 'collecting')),
            'window_hours'=>(int)($gate['window_hours'] ?? $hours),
            'samples'=>(int)($gate['samples'] ?? 0),
            'settings'=>[
                'enabled'=>!empty($settings['enabled']),
                'mode'=>sanitize_key((string)($settings['mode'] ?? 'observe')),
                'sample_rate'=>(int)($settings['sample_rate'] ?? 0),
                'retention_days'=>(int)($settings['retention_days'] ?? 0),
                'minimum_samples'=>(int)($settings['minimum_samples'] ?? 0),
                'error_rate_warning'=>(float)($settings['error_rate_warning'] ?? 0),
                'error_rate_critical'=>(float)($settings['error_rate_critical'] ?? 0),
            ],
            'categories'=>$categories,
            'queue'=>self::safeQueue($queue),
            'cron'=>self::safeCron(LoadReadiness::cronStatus()),
            'last_probe'=>self::safeProbe(get_option(LoadReadiness::LAST_PROBE_OPTION, [])),
        ];
    }

    private static function processSnapshot(): array {
        $health = class_exists(ProcessMonitor::class) ? ProcessMonitor::operationalHealth() : [];
        $overview = class_exists(ProcessMonitor::class) ? ProcessMonitor::overview() : [];
        $processes = [];
        foreach ($overview as $key => $row) {
            if (!is_array($row)) continue;
            $latest = is_array($row['latest'] ?? null) ? $row['latest'] : [];
            $circuit = is_array($row['circuit'] ?? null) ? $row['circuit'] : [];
            $processes[] = [
                'key'=>sanitize_key((string)$key),
                'label'=>self::cleanText((string)($row['label'] ?? '')),
                'type'=>self::cleanText((string)($row['type'] ?? '')),
                'expected_interval_seconds'=>max(0, (int)($row['expectedInterval'] ?? 0)),
                'scheduled_count'=>max(0, (int)($row['scheduled_count'] ?? 0)),
                'next_run_utc'=>!empty($row['next_run']) ? gmdate('c', (int)$row['next_run']) : '',
                'overdue'=>!empty($row['overdue']),
                'latest'=>[
                    'status'=>sanitize_key((string)($latest['status'] ?? '')),
                    'recovery_status'=>sanitize_key((string)($latest['recovery_status'] ?? '')),
                    'duration_ms'=>max(0, (int)($latest['duration_ms'] ?? 0)),
                    'last_error_code'=>self::simpleCode((string)($latest['error_code'] ?? $latest['last_error_code'] ?? '')),
                    'completed_at_utc'=>self::utcDate((string)($latest['completed_at'] ?? '')),
                ],
                'circuit'=>[
                    'state'=>sanitize_key((string)($circuit['state'] ?? 'closed')),
                    'failure_count'=>max(0, (int)($circuit['failure_count'] ?? 0)),
                    'last_error_code'=>self::simpleCode((string)($circuit['last_error_code'] ?? '')),
                    'last_failure_at_utc'=>self::utcDate((string)($circuit['last_failure_at'] ?? '')),
                    'last_success_at_utc'=>self::utcDate((string)($circuit['last_success_at'] ?? '')),
                    'retry_after_utc'=>self::utcDate((string)($circuit['retry_after'] ?? '')),
                ],
            ];
        }
        usort($processes, static function (array $a, array $b): int {
            if ($a['overdue'] === $b['overdue']) return strcmp((string)$a['key'], (string)$b['key']);
            return $a['overdue'] ? -1 : 1;
        });
        $queue = class_exists(QueueManager::class) ? QueueManager::status() : [];
        return [
            'operational_health'=>[
                'score'=>(int)($health['score'] ?? 0),
                'status'=>sanitize_key((string)($health['status'] ?? 'unknown')),
                'overdue'=>(int)($health['overdue'] ?? 0),
                'summary'=>self::numericArray((array)($health['summary'] ?? [])),
            ],
            'queue_modernization'=>self::safeModernQueue($queue),
            'processes'=>$processes,
        ];
    }

    private static function releaseSnapshot(): array {
        $manifest = class_exists(ProductionManifest::class) ? ProductionManifest::verify() : [];
        $gate = class_exists(ReleaseGate::class) ? ReleaseGate::latest() : [];
        $mismatches = count((array)($manifest['key_file_mismatches'] ?? []))
            + count((array)($manifest['manifest_file_mismatches'] ?? []));
        return [
            'production_manifest'=>[
                'ok'=>!empty($manifest['ok']),
                'manifest_found'=>!empty($manifest['manifest_found']),
                'manifest_version'=>self::versionText((string)($manifest['manifest_version'] ?? '')),
                'runtime_version'=>self::versionText((string)($manifest['runtime_version'] ?? '')),
                'expected_file_count'=>(int)($manifest['expected_file_count'] ?? 0),
                'actual_file_count'=>(int)($manifest['actual_file_count'] ?? 0),
                'mismatch_count'=>$mismatches,
                'unexpected_symlinks'=>count((array)($manifest['unexpected_symlinks'] ?? [])),
                'aggregate_sha256'=>self::sha((string)($manifest['actual_aggregate_sha256'] ?? '')),
                'error'=>self::cleanText((string)($manifest['error'] ?? '')),
            ],
            'release_gate'=>[
                'status'=>sanitize_key((string)($gate['status'] ?? 'not_run')),
                'score'=>(int)($gate['score'] ?? 0),
                'counts'=>self::numericArray((array)($gate['counts'] ?? [])),
                'generated_at_utc'=>self::utcDate((string)($gate['generated_at'] ?? '')),
                'duration_ms'=>max(0, (int)($gate['duration_ms'] ?? 0)),
                'gate_version'=>self::versionText((string)($gate['gate_version'] ?? '')),
            ],
        ];
    }

    private static function safeQueue(array $queue): array {
        return [
            'driver'=>sanitize_key((string)($queue['driver'] ?? '')),
            'fallback'=>sanitize_key((string)($queue['fallback'] ?? '')),
            'action_scheduler_available'=>!empty($queue['action_scheduler_available']),
            'action_scheduler_mode'=>sanitize_key((string)($queue['action_scheduler_mode'] ?? '')),
            'score'=>(int)($queue['score'] ?? 0),
            'status'=>sanitize_key((string)($queue['status'] ?? 'unknown')),
            'overdue'=>(int)($queue['overdue'] ?? 0),
            'summary'=>self::numericArray((array)($queue['summary'] ?? [])),
            'modernization'=>self::safeModernQueue((array)($queue['modernization'] ?? [])),
        ];
    }

    private static function safeModernQueue(array $row): array {
        $stats = is_array($row['stats'] ?? null) ? $row['stats'] : [];
        return [
            'version'=>self::versionText((string)($row['version'] ?? '')),
            'mode'=>sanitize_key((string)($row['mode'] ?? '')),
            'selected_driver'=>sanitize_key((string)($row['selected_driver'] ?? '')),
            'effective_driver'=>sanitize_key((string)($row['effective_driver'] ?? '')),
            'fallback'=>sanitize_key((string)($row['fallback'] ?? '')),
            'action_scheduler_available'=>!empty($row['action_scheduler_available']),
            'table_ready'=>!empty($row['table_ready']),
            'status'=>sanitize_key((string)($row['status'] ?? 'unknown')),
            'stats'=>self::numericArray($stats),
            'last_heartbeat_utc'=>self::utcDate((string)($row['last_heartbeat'] ?? '')),
            'heartbeat_recent'=>!empty($row['heartbeat_recent']),
        ];
    }

    private static function safeCron(array $row): array {
        return [
            'driver'=>sanitize_key((string)($row['driver'] ?? '')),
            'wp_cron_disabled'=>!empty($row['wp_cron_disabled']),
            'heartbeat_recent'=>!empty($row['heartbeat_recent']),
            'last_heartbeat_utc'=>self::utcDate((string)($row['last_heartbeat'] ?? '')),
            'recommended_interval_minutes'=>max(0, (int)($row['recommended_interval_minutes'] ?? 0)),
            'missed_jobs'=>class_exists(Monitor::class) ? Monitor::missedCronCount() : 0,
        ];
    }

    private static function safeProbe($probe): array {
        if (!is_array($probe)) return [];
        $profile = is_array($probe['database_profile'] ?? null) ? $probe['database_profile'] : [];
        $largest = [];
        foreach ((array)($profile['largest_tables'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $largest[] = [
                'table'=>sanitize_key((string)($row['table'] ?? '')),
                'rows_estimate'=>max(0, (int)($row['rows_estimate'] ?? 0)),
                'data_bytes'=>max(0, (int)($row['data_bytes'] ?? 0)),
                'index_bytes'=>max(0, (int)($row['index_bytes'] ?? 0)),
                'total_bytes'=>max(0, (int)($row['total_bytes'] ?? 0)),
            ];
            if (count($largest) >= 12) break;
        }
        return [
            'status'=>sanitize_key((string)($probe['status'] ?? '')),
            'source'=>sanitize_key((string)($probe['source'] ?? '')),
            'iterations'=>max(0, (int)($probe['iterations'] ?? 0)),
            'tables'=>max(0, (int)($probe['tables'] ?? 0)),
            'checks'=>max(0, (int)($probe['checks'] ?? 0)),
            'duration_ms'=>max(0, (int)($probe['duration_ms'] ?? 0)),
            'query_count'=>max(0, (int)($probe['query_count'] ?? 0)),
            'memory_peak_bytes'=>max(0, (int)($probe['memory_peak_bytes'] ?? 0)),
            'memory_delta_bytes'=>max(0, (int)($probe['memory_delta_bytes'] ?? 0)),
            'database_profile'=>[
                'table_count'=>max(0, (int)($profile['table_count'] ?? 0)),
                'data_bytes'=>max(0, (int)($profile['data_bytes'] ?? 0)),
                'index_bytes'=>max(0, (int)($profile['index_bytes'] ?? 0)),
                'total_bytes'=>max(0, (int)($profile['total_bytes'] ?? 0)),
                'largest_tables'=>$largest,
                'read_only'=>!empty($profile['read_only']),
            ],
            'executed_at_utc'=>self::utcDate((string)($probe['executed_at'] ?? '')),
            'read_only'=>!empty($probe['read_only']),
        ];
    }


    private static function observationMessage(string $eventType, string $message, array $context): string {
        if ($eventType === 'mobile_app_error') {
            $code = sanitize_key((string)($context['error_code'] ?? ''));
            if ($code === 'native_download_failed') return 'Falha no download nativo';
            if ($code === 'location_permission_denied') return 'Permissão de localização recusada';
            return 'Erro técnico da aplicação mobile';
        }
        if ($eventType === 'mobile_session_error') return 'Falha técnica de sessão mobile';
        return self::redactPaths($message);
    }

    private static function safeObservationContext(array $context): array {
        $allowed = [
            'pending','failed','missed_jobs','storage_mb','threshold_mb','status','error_code','method',
            'host','queue_pending','queue_failed','duration_ms','http_status','retryable','conflicts',
        ];
        $out = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $context)) continue;
            $value = $context[$key];
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) $out[$key] = $value;
            elseif (is_string($value)) $out[$key] = substr(sanitize_text_field($value), 0, 191);
        }
        return $out;
    }

    private static function securityCounts(): array {
        $row = SecurityService::counts();
        return [
            'open'=>(int)($row['open'] ?? 0),
            'high'=>(int)($row['high'] ?? 0),
            'critical'=>(int)($row['critical'] ?? 0),
            'total'=>(int)($row['total'] ?? 0),
        ];
    }

    private static function outboundStatus(): array {
        $row = OutboundHttp::status();
        return [
            'available'=>true,
            'version'=>self::versionText((string)($row['version'] ?? '')),
            'https_only'=>!empty($row['https_only']),
            'private_networks_blocked'=>!empty($row['private_networks_blocked']),
            'unsafe_url_rejection'=>!empty($row['unsafe_url_rejection']),
            'tls_verify_forced'=>!empty($row['tls_verify_forced']),
            'redirects_default'=>(int)($row['redirects_default'] ?? -1),
            'default_port'=>(int)($row['default_port'] ?? 0),
        ];
    }

    private static function errorCode(string $metaCode, string $rawError): string {
        $metaCode = self::simpleCode($metaCode);
        if ($metaCode !== '') return $metaCode;
        $rawError = trim($rawError);
        if ($rawError !== '' && preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', $rawError)) return self::simpleCode($rawError);
        return $rawError !== '' ? 'reported_error' : '';
    }

    private static function simpleCode(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_.:-]+/', '_', $value);
        return substr(trim((string)$value, '_'), 0, 96);
    }

    private static function route(string $route): string {
        $route = trim($route);
        if ($route === '') return '';
        $route = preg_replace('/\?.*$/', '', $route);
        return substr(sanitize_text_field((string)$route), 0, 191);
    }

    private static function cleanText(string $value): string {
        return substr(sanitize_text_field(wp_strip_all_tags($value)), 0, 500);
    }

    private static function versionText(string $value): string {
        $value = preg_replace('/[^A-Za-z0-9._+-]/', '', trim($value));
        return substr((string)$value, 0, 64);
    }

    private static function sha(string $value): string {
        $value = strtolower(trim($value));
        return preg_match('/^[a-f0-9]{64}$/', $value) ? $value : '';
    }

    private static function numericArray(array $values): array {
        $out = [];
        foreach ($values as $key => $value) {
            $key = sanitize_key((string)$key);
            if ($key === '') continue;
            if (is_bool($value)) $out[$key] = $value;
            elseif (is_int($value)) $out[$key] = $value;
            elseif (is_float($value)) $out[$key] = $value;
            elseif (is_numeric($value)) $out[$key] = strpos((string)$value, '.') !== false ? (float)$value : (int)$value;
        }
        return $out;
    }

    private static function emptyBuckets(): array {
        return ['under_1h'=>0,'1h_24h'=>0,'1d_7d'=>0,'over_7d'=>0,'unknown'=>0];
    }

    private static function ageBucket(string $date): string {
        $ts = self::timestamp($date);
        if ($ts <= 0) return 'unknown';
        $age = max(0, time() - $ts);
        if ($age < HOUR_IN_SECONDS) return 'under_1h';
        if ($age < DAY_IN_SECONDS) return '1h_24h';
        if ($age < 7 * DAY_IN_SECONDS) return '1d_7d';
        return 'over_7d';
    }

    private static function timestamp(string $date): int {
        $date = trim($date);
        if ($date === '') return 0;
        $ts = strtotime($date . (preg_match('/(?:Z|[+-]\d\d:\d\d)$/', $date) ? '' : ' UTC'));
        return $ts ? (int)$ts : 0;
    }

    private static function utcDate(string $date): string {
        $ts = self::timestamp($date);
        return $ts > 0 ? gmdate('c', $ts) : '';
    }

    private static function newerDate(string $a, string $b): string {
        if ($a === '') return $b;
        if ($b === '') return $a;
        return strtotime($b) > strtotime($a) ? $b : $a;
    }

    private static function redactPaths(string $value): string {
        $value = self::cleanText($value);
        $paths = [];
        if (defined('ABSPATH')) $paths[] = (string)ABSPATH;
        if (defined('WP_CONTENT_DIR')) $paths[] = (string)WP_CONTENT_DIR;
        $upload = wp_upload_dir(null, false);
        if (!empty($upload['basedir'])) $paths[] = (string)$upload['basedir'];
        foreach (array_unique(array_filter($paths)) as $path) {
            $normal = wp_normalize_path($path);
            if ($normal === '') continue;
            $value = str_replace([$normal, rtrim($normal, '/')], '[path]', wp_normalize_path($value));
        }
        $value = preg_replace('#(?<![A-Za-z0-9])/(?:storage|data|var|home|tmp|srv|opt|mnt|private|Users)(?:/[^\s\"\'<>]+)+#i', '[path]', (string)$value);
        $value = preg_replace('#\b[A-Za-z]:[\\/](?:[^\s\"\'<>]+[\\/])*[^\s\"\'<>]*#', '[path]', (string)$value);
        $value = preg_replace('/\b(?:Bearer|Basic)\s+[A-Za-z0-9._~+\/-]+=*/i', '[credential redacted]', (string)$value);
        return substr((string)$value, 0, 500);
    }
}
