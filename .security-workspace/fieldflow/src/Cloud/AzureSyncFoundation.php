<?php
namespace RoutesPro\Cloud;

use RoutesPro\Operations\Queue\QueueManager;
use RoutesPro\Services\IntegrationPlatform;

if (!defined('ABSPATH')) exit;

final class AzureSyncFoundation {
    public const VERSION = '1.3.0';
    public const DISPATCH_HOOK = 'fieldflow_azure_sync_dispatch';
    public const CLEANUP_HOOK = 'fieldflow_azure_sync_cleanup';
    private static bool $registered = false;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_cloud_sync_jobs';
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('fieldflow_core_event', [self::class, 'captureEvent'], 20, 4);
        add_action(self::DISPATCH_HOOK, [self::class, 'dispatch']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
        add_action('init', [self::class, 'ensureCleanupSchedule'], 40);
    }

    public static function ensureCleanupSchedule(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + (4 * HOUR_IN_SECONDS), 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function captureEvent(string $eventName, array $payload, array $context, string $eventUuid): void {
        if (!AzureConnectionManager::syncEnabled()) return;
        if (!self::eventEnabled($eventName)) return;

        // Phase 3C enriches briefing and stock events only on the Azure delivery path.
        // The EventBus payload consumed by webhooks, billing and other listeners is unchanged.
        if ($eventName === 'briefing.published' && class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill')) {
            $briefingId = absint($context['briefing_id'] ?? $context['entity_id'] ?? ($payload['data']['briefing_id'] ?? 0));
            if ($briefingId > 0) {
                $briefing = BriefingStockBackfill::buildBriefingContract($briefingId, false);
                if (!empty($briefing['ok'])) {
                    self::enqueue('briefing.published', (array)$briefing['contract'], (array)$briefing['context'], $eventUuid);
                    return;
                }
            }
        }

        if ($eventName === 'stock.imported' && class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill')) {
            $batchId = absint($context['entity_id'] ?? ($payload['data']['batch_id'] ?? 0));
            if ($batchId > 0) {
                $stock = BriefingStockBackfill::enqueueLiveStockImport($batchId, $eventUuid);
                if (!empty($stock['ok'])) return;
            }
        }

        // Phase 3B enriches only the Azure delivery path. The central EventBus
        // payload seen by webhooks, billing and other listeners remains unchanged.
        if ($eventName === 'visit.completed' && class_exists('\\RoutesPro\\Cloud\\OperationalBackfill')) {
            $stopId = absint($context['route_stop_id'] ?? $context['entity_id'] ?? $payload['route_stop_id'] ?? 0);
            if ($stopId > 0) {
                $visit = OperationalBackfill::buildVisitContract($stopId, false);
                if (!empty($visit['ok'])) {
                    self::enqueue('visit.completed', (array)$visit['contract'], (array)$visit['context'], $eventUuid);
                    $visitData = (array)($visit['data'] ?? []);
                    $routeId = absint($visitData['route_id'] ?? 0);
                    $locationId = absint($visitData['location_id'] ?? 0);

                    if ($routeId > 0) {
                        $route = OperationalBackfill::buildRouteContract($routeId, false);
                        if (!empty($route['ok'])) {
                            self::enqueue(
                                'route.snapshot',
                                (array)$route['contract'],
                                (array)$route['context'],
                                OperationalBackfill::relatedEventUuid($eventUuid, 'route.snapshot', $routeId)
                            );
                        }
                    }
                    if ($locationId > 0) {
                        $location = OperationalBackfill::buildLocationContract($locationId, false);
                        if (!empty($location['ok'])) {
                            self::enqueue(
                                'location.snapshot',
                                (array)$location['contract'],
                                (array)$location['context'],
                                OperationalBackfill::relatedEventUuid($eventUuid, 'location.snapshot', $locationId)
                            );
                        }
                    }
                    return;
                }
            }
        }

        self::enqueue($eventName, $payload, $context, $eventUuid);
    }

    public static function eventEnabled(string $eventName): bool {
        $settings = AzureConnectionManager::settings();
        if (strpos($eventName, 'intelligence.') === 0) {
            return !empty($settings['azure_cloud_sync_intelligence']);
        }
        $map = [
            'submission.created' => 'azure_cloud_sync_submissions',
            'submission.updated' => 'azure_cloud_sync_submissions',
            'visit.completed' => 'azure_cloud_sync_visits',
            'briefing.published' => 'azure_cloud_sync_briefings',
            'stock.imported' => 'azure_cloud_sync_stocks',
        ];
        $key = $map[$eventName] ?? '';
        return $key !== '' && !empty($settings[$key]);
    }

    public static function enqueue(string $eventName, array $payload, array $context, string $eventUuid): array {
        global $wpdb;
        if (!self::tableReady()) return ['ok'=>false,'reason'=>'table_missing','job_id'=>0];
        $eventUuid = sanitize_text_field($eventUuid);
        if ($eventUuid === '') $eventUuid = wp_generate_uuid4();
        $identity = TenantIdentity::snapshot();
        $settings = AzureConnectionManager::settings();
        $maxAttempts = max(1, min(12, absint($settings['azure_cloud_sync_max_attempts'] ?? 5)));
        $now = current_time('mysql', true);
        $envelope = [
            'contract' => 'fieldflow.cloud.event',
            'contract_version' => '1.0',
            'tenant' => $identity,
            'destination_mode' => AzureConnectionManager::destinationMode(),
            'event' => [
                'uuid' => $eventUuid,
                'name' => sanitize_text_field($eventName),
                'occurred_at' => $now,
            ],
            'context' => self::safeContext($context),
            'payload' => $payload,
        ];

        $inserted = $wpdb->insert(self::table(), [
            'sync_uuid' => wp_generate_uuid4(),
            'tenant_id' => (string)$identity['tenant_id'],
            'installation_id' => (string)$identity['installation_id'],
            'destination' => 'azure',
            'event_uuid' => $eventUuid,
            'event_name' => substr(sanitize_text_field($eventName), 0, 96),
            'client_id' => !empty($context['client_id']) ? absint($context['client_id']) : null,
            'project_id' => !empty($context['project_id']) ? absint($context['project_id']) : null,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'available_at' => $now,
            'payload_json' => wp_json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === false) {
            $existing = (int)$wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . self::table() . ' WHERE destination=%s AND event_uuid=%s LIMIT 1',
                'azure', $eventUuid
            ));
            if ($existing > 0) return ['ok'=>true,'duplicate'=>true,'job_id'=>$existing];
            return ['ok'=>false,'reason'=>'insert_failed','job_id'=>0];
        }

        QueueManager::schedule(self::DISPATCH_HOOK, [], 3, [
            'queue' => 'cloud_sync',
            'max_attempts' => 1,
            'priority' => 10,
            'source' => 'azure_sync',
            'idempotency_key' => 'azure-sync-dispatch:' . gmdate('YmdHi'),
            'meta' => ['destination'=>'azure'],
        ]);
        return ['ok'=>true,'duplicate'=>false,'job_id'=>(int)$wpdb->insert_id];
    }

    public static function dispatch(int $requestedLimit = 0): array {
        $settings = AzureConnectionManager::settings();
        $configuredLimit = max(1, min(100, absint($settings['azure_cloud_sync_batch_size'] ?? 20)));
        $limit = $requestedLimit > 0 ? max(1, min(100, $requestedLimit)) : $configuredLimit;
        $summary = ['ok'=>true,'enabled'=>AzureConnectionManager::syncEnabled(),'configured'=>AzureConnectionManager::ready(),'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];
        if (!$summary['enabled']) return $summary;
        if (!$summary['configured']) {
            IntegrationPlatform::log('azure_cloud', 'dispatch', 'paused', [], 'Azure cloud sync is enabled but no valid endpoint is configured.');
            return $summary;
        }
        if (!self::tableReady()) {
            $summary['ok'] = false;
            return $summary;
        }

        $token = wp_generate_uuid4();
        for ($i = 0; $i < $limit; $i++) {
            $job = self::claimOne($token);
            if (!$job) break;
            $summary['claimed']++;
            $payload = json_decode((string)($job['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                self::fail($job, 'invalid_payload', 'Stored cloud sync payload is invalid JSON.');
                $summary['dead']++;
                continue;
            }

            $response = AzureConnectionManager::send($payload);
            if (is_wp_error($response)) {
                $state = self::fail($job, $response->get_error_code(), $response->get_error_message());
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }
            $code = (int)wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) {
                $state = self::fail($job, 'http_' . $code, 'Azure endpoint returned HTTP ' . $code . '.');
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }
            self::complete($job, $code);
            $summary['completed']++;
        }

        $next = self::nextPendingTimestamp();
        if ($next > 0) {
            QueueManager::schedule(self::DISPATCH_HOOK, [], max(1, $next - time()), [
                'queue' => 'cloud_sync',
                'max_attempts' => 1,
                'priority' => 10,
                'source' => 'azure_sync_retry',
                'idempotency_key' => 'azure-sync-dispatch:' . gmdate('YmdHi', max(time(), $next)),
            ]);
        }
        return $summary;
    }

    /**
     * Synchronous backoffice kick that reuses the same Cloud dispatcher.
     * It bypasses WP-Cron only as the wake-up mechanism. Delivery, HMAC,
     * retries and state transitions remain exactly the same.
     */
    public static function dispatchNow(int $limit = 10): array {
        return self::dispatch(max(1, min(25, $limit)));
    }

    public static function health(): array {
        global $wpdb;
        $identity = TenantIdentity::snapshot();
        $connection = AzureConnectionManager::status();
        $stats = ['pending'=>0,'running'=>0,'completed_24h'=>0,'dead'=>0,'oldest_pending_seconds'=>0];
        $lastSuccess = '';
        $lastError = '';
        if (self::tableReady()) {
            $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(status='pending') pending,SUM(status='running') running,SUM(status='completed' AND completed_at>=%s) completed_24h,SUM(status='dead') dead,MIN(IF(status='pending',available_at,NULL)) oldest_pending FROM " . self::table(),
                $since
            ), ARRAY_A) ?: [];
            foreach (['pending','running','completed_24h','dead'] as $key) $stats[$key] = (int)($row[$key] ?? 0);
            $oldest = !empty($row['oldest_pending']) ? strtotime((string)$row['oldest_pending'] . ' UTC') : 0;
            $stats['oldest_pending_seconds'] = $oldest > 0 ? max(0, time() - $oldest) : 0;
            $lastSuccess = (string)$wpdb->get_var("SELECT completed_at FROM " . self::table() . " WHERE status='completed' ORDER BY id DESC LIMIT 1");
            $lastError = (string)$wpdb->get_var("SELECT last_error_message FROM " . self::table() . " WHERE last_error_message IS NOT NULL AND last_error_message<>'' ORDER BY id DESC LIMIT 1");
        }
        $status = !self::tableReady() ? 'critical' : (!empty($connection['sync_enabled']) && empty($connection['configured']) ? 'attention' : ($stats['dead'] > 0 ? 'attention' : 'ready'));
        return [
            'version' => self::VERSION,
            'status' => $status,
            'table_ready' => self::tableReady(),
            'tenant_id' => (string)$identity['tenant_id'],
            'installation_id' => (string)$identity['installation_id'],
            'connection' => $connection,
            'stats' => $stats,
            'last_success' => $lastSuccess,
            'last_error' => substr($lastError, 0, 300),
        ];
    }

    public static function localProbe(): array {
        global $wpdb;
        $health = self::health();
        $checks = [
            'table_ready' => !empty($health['table_ready']),
            'tenant_identity' => !empty($health['tenant_id']) && !empty($health['installation_id']),
            'event_listener' => has_action('fieldflow_core_event', [self::class, 'captureEvent']) !== false,
            'dispatch_handler' => has_action(self::DISPATCH_HOOK, [self::class, 'dispatch']) !== false,
            'queue_policy' => class_exists('\\RoutesPro\\Operations\\Queue\\QueueSettings') && \RoutesPro\Operations\Queue\QueueSettings::isAllowed(self::DISPATCH_HOOK),
        ];
        $write = false;
        if (!empty($health['table_ready'])) {
            $probeUuid = wp_generate_uuid4();
            $now = current_time('mysql', true);
            $write = $wpdb->insert(self::table(), [
                'sync_uuid'=>$probeUuid,
                'tenant_id'=>(string)$health['tenant_id'],
                'installation_id'=>(string)$health['installation_id'],
                'destination'=>'probe',
                'event_uuid'=>$probeUuid,
                'event_name'=>'foundation.probe',
                'status'=>'completed',
                'attempts'=>0,
                'max_attempts'=>1,
                'available_at'=>$now,
                'completed_at'=>$now,
                'payload_json'=>'{}',
                'created_at'=>$now,
                'updated_at'=>$now,
            ]) !== false;
            if ($write) $wpdb->delete(self::table(), ['destination'=>'probe','event_uuid'=>$probeUuid], ['%s','%s']);
        }
        $checks['queue_write'] = $write;
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$health];
    }

    public static function retryDead(int $jobId = 0): int {
        global $wpdb;
        if (!self::tableReady()) return 0;
        $now = current_time('mysql', true);
        if ($jobId > 0) {
            return (int)$wpdb->query($wpdb->prepare(
                "UPDATE " . self::table() . " SET status='pending',attempts=0,available_at=%s,locked_at=NULL,lock_token=NULL,completed_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=%s WHERE id=%d AND status='dead'",
                $now, $now, $jobId
            ));
        }
        return (int)$wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status='pending',attempts=0,available_at=%s,locked_at=NULL,lock_token=NULL,completed_at=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=%s WHERE status='dead'",
            $now, $now
        ));
    }

    public static function recent(int $limit = 30): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $limit = max(1, min(100, $limit));
        return $wpdb->get_results('SELECT id,event_name,status,attempts,max_attempts,client_id,project_id,available_at,completed_at,last_http_code,last_error_code,last_error_message,created_at FROM ' . self::table() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    public static function cleanup(): void {
        global $wpdb;
        if (!self::tableReady()) return;
        $completedBefore = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));
        $deadBefore = gmdate('Y-m-d H:i:s', time() - (180 * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status='completed' AND completed_at<%s", $completedBefore));
        $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status='dead' AND completed_at<%s", $deadBefore));
    }

    private static function tableReady(): bool {
        global $wpdb;
        $table = self::table();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function claimOne(string $token): array {
        global $wpdb;
        $now = current_time('mysql', true);
        $id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE status='pending' AND available_at<=%s ORDER BY id ASC LIMIT 1",
            $now
        ));
        if ($id <= 0) return [];
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status='running',attempts=attempts+1,locked_at=%s,lock_token=%s,updated_at=%s WHERE id=%d AND status='pending' AND available_at<=%s",
            $now, $token, $now, $id, $now
        ));
        if ($updated !== 1) return [];
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d AND lock_token=%s', $id, $token), ARRAY_A) ?: [];
    }

    private static function complete(array $job, int $httpCode): void {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->update(self::table(), [
            'status'=>'completed','completed_at'=>$now,'locked_at'=>null,'lock_token'=>null,'last_http_code'=>$httpCode,
            'last_error_code'=>null,'last_error_message'=>null,'updated_at'=>$now,
        ], ['id'=>(int)$job['id']]);
        IntegrationPlatform::log('azure_cloud', 'event_push', 'success', ['event'=>(string)$job['event_name'],'http_code'=>$httpCode,'job_id'=>(int)$job['id']]);
    }

    private static function fail(array $job, string $code, string $message): array {
        global $wpdb;
        $attempts = max(1, (int)($job['attempts'] ?? 1));
        $maxAttempts = max(1, (int)($job['max_attempts'] ?? 5));
        $terminal = $attempts >= $maxAttempts;
        $backoff = [60,300,900,1800,3600,7200];
        $delay = $backoff[min($attempts - 1, count($backoff) - 1)] ?? 7200;
        $now = current_time('mysql', true);
        $wpdb->update(self::table(), [
            'status'=>$terminal ? 'dead' : 'pending',
            'available_at'=>$terminal ? (string)$job['available_at'] : gmdate('Y-m-d H:i:s', time() + $delay),
            'completed_at'=>$terminal ? $now : null,
            'locked_at'=>null,'lock_token'=>null,
            'last_error_code'=>substr(sanitize_key($code), 0, 96),
            'last_error_message'=>substr(wp_strip_all_tags($message), 0, 500),
            'updated_at'=>$now,
        ], ['id'=>(int)$job['id']]);
        IntegrationPlatform::log('azure_cloud', 'event_push', $terminal ? 'dead' : 'retry', [
            'event'=>(string)$job['event_name'],'attempt'=>$attempts,'max_attempts'=>$maxAttempts,'job_id'=>(int)$job['id']
        ], $message);
        return ['terminal'=>$terminal,'delay'=>$terminal ? 0 : $delay];
    }

    private static function nextPendingTimestamp(): int {
        global $wpdb;
        if (!self::tableReady()) return 0;
        $next = (string)$wpdb->get_var("SELECT MIN(available_at) FROM " . self::table() . " WHERE status='pending'");
        return $next !== '' ? max(0, strtotime($next . ' UTC')) : 0;
    }

    private static function safeContext(array $context): array {
        $out = [];
        foreach (['entity_type','entity_id','actor_user_id','client_id','project_id','route_id','route_stop_id','location_id','submission_id','briefing_id'] as $key) {
            if (!array_key_exists($key, $context)) continue;
            $out[$key] = $key === 'entity_type' ? sanitize_key((string)$context[$key]) : absint($context[$key]);
        }
        return $out;
    }
}
