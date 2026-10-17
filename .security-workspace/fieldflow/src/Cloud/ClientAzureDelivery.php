<?php
namespace RoutesPro\Cloud;

use RoutesPro\Operations\Queue\QueueManager;
use RoutesPro\Services\IntegrationPlatform;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5B independent asynchronous queue for Azure destinations owned by clients.
 *
 * The certified TWT Azure path stays owned by AzureSyncFoundation. This queue
 * handles only routed client_azure destinations and talks exclusively to the
 * TWT Relay. A failure outside TWT therefore never blocks the primary path.
 */
final class ClientAzureDelivery {
    public const VERSION = '1.1.0';
    private const INTAKE_OPTION = 'fieldflow_client_azure_intake_recent';
    public const DISPATCH_HOOK = 'fieldflow_client_azure_dispatch';
    public const CLEANUP_HOOK = 'fieldflow_client_azure_cleanup';
    private static bool $registered = false;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_cloud_delivery_jobs';
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('fieldflow_core_event', [self::class, 'captureEvent'], 5, 4);
        add_action(self::DISPATCH_HOOK, [self::class, 'dispatch']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
        add_action('init', [self::class, 'ensureCleanupSchedule'], 41);
    }

    public static function ensureCleanupSchedule(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + (5 * HOUR_IN_SECONDS), 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function mode(): string {
        return ClientAzureRelayConnection::mode();
    }

    public static function captureEvent(string $eventName, array $payload, array $context, string $eventUuid): void {
        $mode = self::mode();
        $trace = [
            'received_at_utc'=>gmdate('c'),
            'event_name'=>DestinationRegistry::normaliseEventPattern($eventName),
            'event_uuid'=>sanitize_text_field($eventUuid),
            'mode'=>$mode,
            'status'=>'received',
            'reason'=>'',
            'client_id'=>absint($context['client_id'] ?? 0),
            'project_id'=>absint($context['project_id'] ?? 0),
            'submission_id'=>absint($context['submission_id'] ?? 0),
            'routed'=>0,
            'enqueued'=>0,
        ];

        try {
            if (!in_array($mode, ['queue','live'], true)) {
                $trace['status'] = 'ignored';
                $trace['reason'] = 'mode_not_queue_or_live';
                self::recordIntake($trace);
                return;
            }
            if (!DestinationRegistry::ready()) {
                $trace['status'] = 'ignored';
                $trace['reason'] = 'registry_not_ready';
                self::recordIntake($trace);
                return;
            }
            if (!DestinationRegistry::hasActiveExternalDestinations()) {
                $trace['status'] = 'ignored';
                $trace['reason'] = 'no_active_external_destination';
                self::recordIntake($trace);
                return;
            }

            $context = self::hydrateContext($eventName, $payload, $context);
            $trace['client_id'] = absint($context['client_id'] ?? 0);
            $trace['project_id'] = absint($context['project_id'] ?? 0);
            $trace['submission_id'] = absint($context['submission_id'] ?? 0);

            $plan = DestinationRouter::routePlan($eventName, $context, $eventUuid);
            $seenClientAzure = 0;
            $invalidConfig = 0;
            $enqueueFailures = 0;
            foreach ((array)$plan['destinations'] as $item) {
                if ((string)($item['type_key'] ?? '') !== 'client_azure') continue;
                $seenClientAzure++;
                $destination = DestinationRegistry::getDestination(absint($item['destination_id'] ?? 0));
                if (!$destination || empty($destination['is_active'])) continue;
                if (!ClientAzureRelayConnection::destinationConfigReady($destination)) {
                    $invalidConfig++;
                    continue;
                }
                $trace['routed']++;
                $queued = self::enqueue($eventName, $payload, $context, $eventUuid, $destination, (string)($item['delivery_key'] ?? ''));
                if (!empty($queued['ok'])) {
                    $trace['enqueued']++;
                } else {
                    $enqueueFailures++;
                }
            }

            if ($trace['enqueued'] > 0) {
                $trace['status'] = 'enqueued';
                $trace['reason'] = '';
            } elseif ($seenClientAzure === 0) {
                $trace['status'] = 'not_routed';
                $trace['reason'] = 'no_client_azure_route_for_scope';
            } elseif ($invalidConfig > 0) {
                $trace['status'] = 'not_enqueued';
                $trace['reason'] = 'destination_config_not_ready';
            } elseif ($enqueueFailures > 0) {
                $trace['status'] = 'error';
                $trace['reason'] = 'queue_insert_failed';
            } else {
                $trace['status'] = 'not_enqueued';
                $trace['reason'] = 'destination_not_eligible';
            }
            self::recordIntake($trace);
        } catch (\Throwable $e) {
            $trace['status'] = 'error';
            $trace['reason'] = 'capture_exception:' . sanitize_key((new \ReflectionClass($e))->getShortName());
            self::recordIntake($trace);
            // Fail open by design. A 5B observer failure must never interrupt the
            // certified primary Azure listener or the operational request.
            try {
                if (class_exists('\RoutesPro\Support\Logger')) {
                    \RoutesPro\Support\Logger::captureThrowable('Falha isolada no intake Client Azure 5B.', $e, [
                        'context_key'=>'client_azure_5b_intake',
                        'client_id'=>absint($trace['client_id'] ?? 0),
                        'project_id'=>absint($trace['project_id'] ?? 0),
                    ]);
                }
            } catch (\Throwable $ignored) {
                // Never allow diagnostics to affect the primary path.
            }
        }
    }

    public static function intakeRecent(int $limit = 20): array {
        $rows = get_option(self::INTAKE_OPTION, []);
        if (!is_array($rows)) return [];
        $limit = max(1, min(50, $limit));
        return array_slice($rows, 0, $limit);
    }

    private static function recordIntake(array $trace): void {
        $safe = [
            'received_at_utc'=>sanitize_text_field((string)($trace['received_at_utc'] ?? gmdate('c'))),
            'event_name'=>DestinationRegistry::normaliseEventPattern((string)($trace['event_name'] ?? 'system.unknown')),
            'event_uuid'=>substr(sanitize_text_field((string)($trace['event_uuid'] ?? '')), 0, 64),
            'mode'=>sanitize_key((string)($trace['mode'] ?? self::mode())),
            'status'=>sanitize_key((string)($trace['status'] ?? 'unknown')),
            'reason'=>substr(sanitize_text_field((string)($trace['reason'] ?? '')), 0, 160),
            'client_id'=>absint($trace['client_id'] ?? 0),
            'project_id'=>absint($trace['project_id'] ?? 0),
            'submission_id'=>absint($trace['submission_id'] ?? 0),
            'routed'=>absint($trace['routed'] ?? 0),
            'enqueued'=>absint($trace['enqueued'] ?? 0),
        ];
        $rows = get_option(self::INTAKE_OPTION, []);
        if (!is_array($rows)) $rows = [];
        array_unshift($rows, $safe);
        $rows = array_slice($rows, 0, 25);
        update_option(self::INTAKE_OPTION, $rows, false);
    }

    private static function hydrateContext(string $eventName, array $payload, array $context): array {
        $sources = [$payload];
        foreach (['data','context','submission','visit','route'] as $key) {
            if (!empty($payload[$key]) && is_array($payload[$key])) $sources[] = $payload[$key];
        }
        foreach ($sources as $source) {
            foreach (['client_id','project_id','route_id','route_stop_id','location_id','submission_id','actor_user_id'] as $key) {
                if (empty($context[$key]) && !empty($source[$key])) $context[$key] = absint($source[$key]);
            }
            if (empty($context['submission_id']) && !empty($source['id']) && strpos($eventName, 'submission.') === 0) {
                $context['submission_id'] = absint($source['id']);
            }
            if (empty($context['actor_user_id']) && !empty($source['user_id'])) $context['actor_user_id'] = absint($source['user_id']);
        }

        $submissionId = absint($context['submission_id'] ?? 0);
        if ($submissionId <= 0 && strpos($eventName, 'submission.') === 0 && sanitize_key((string)($context['entity_type'] ?? '')) === 'submission') {
            $submissionId = absint($context['entity_id'] ?? 0);
            if ($submissionId > 0) $context['submission_id'] = $submissionId;
        }
        if ($submissionId > 0 && class_exists('\RoutesPro\Core\CanonicalStateService')) {
            $canonical = \RoutesPro\Core\CanonicalStateService::submission($submissionId);
            $state = is_array($canonical['data'] ?? null) ? $canonical['data'] : [];
            foreach (['client_id','project_id','route_id','route_stop_id','location_id','user_id'] as $key) {
                $target = $key === 'user_id' ? 'actor_user_id' : $key;
                if (empty($context[$target]) && !empty($state[$key])) $context[$target] = absint($state[$key]);
            }
        }

        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $stopId = absint($context['route_stop_id'] ?? 0);
        if ($stopId > 0 && (empty($context['route_id']) || empty($context['client_id']) || empty($context['project_id']) || empty($context['location_id']))) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT rs.route_id,rs.location_id,r.client_id,r.project_id FROM {$px}route_stops rs INNER JOIN {$px}routes r ON r.id=rs.route_id WHERE rs.id=%d LIMIT 1",
                $stopId
            ), ARRAY_A);
            if (is_array($row)) {
                foreach (['route_id','location_id','client_id','project_id'] as $key) {
                    if (empty($context[$key]) && !empty($row[$key])) $context[$key] = absint($row[$key]);
                }
            }
        }
        $routeId = absint($context['route_id'] ?? 0);
        if ($routeId > 0 && (empty($context['client_id']) || empty($context['project_id']))) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT client_id,project_id FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A);
            if (is_array($row)) {
                if (empty($context['client_id']) && !empty($row['client_id'])) $context['client_id'] = absint($row['client_id']);
                if (empty($context['project_id']) && !empty($row['project_id'])) $context['project_id'] = absint($row['project_id']);
            }
        }
        return $context;
    }

    public static function enqueue(string $eventName, array $payload, array $context, string $eventUuid, array $destination, string $deliveryKey = ''): array {
        global $wpdb;
        if (!self::tableReady()) return ['ok'=>false,'reason'=>'table_missing','job_id'=>0];

        $destinationUuid = sanitize_text_field((string)($destination['destination_uuid'] ?? ''));
        if ($destinationUuid === '' || (string)($destination['type_key'] ?? '') !== 'client_azure') {
            return ['ok'=>false,'reason'=>'destination_invalid','job_id'=>0];
        }

        $eventUuid = sanitize_text_field($eventUuid);
        if ($eventUuid === '') $eventUuid = wp_generate_uuid4();
        $deliveryKey = trim($deliveryKey) !== '' ? sanitize_text_field($deliveryKey) : DestinationRouter::deliveryKey($eventUuid, $destinationUuid);
        $identity = TenantIdentity::snapshot();
        $policy = self::retryPolicy($destination);
        $now = current_time('mysql', true);
        $relayEnvelope = [
            'contract'=>'fieldflow.client_azure.relay',
            'contract_version'=>'1.0',
            'tenant'=>$identity,
            'event'=>[
                'uuid'=>$eventUuid,
                'name'=>DestinationRegistry::normaliseEventPattern($eventName),
                'occurred_at'=>gmdate('c'),
            ],
            'context'=>self::safeContext($context),
            'destination'=>self::destinationSnapshot($destination),
            'delivery'=>[
                'key'=>$deliveryKey,
                'probe'=>false,
                'async'=>true,
            ],
            'payload'=>$payload,
        ];
        $encoded = wp_json_encode($relayEnvelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') return ['ok'=>false,'reason'=>'payload_encode_failed','job_id'=>0];

        $inserted = $wpdb->insert(self::table(), [
            'delivery_key'=>$deliveryKey,
            'tenant_id'=>(string)$identity['tenant_id'],
            'installation_id'=>(string)$identity['installation_id'],
            'destination_id'=>(int)$destination['id'],
            'destination_uuid'=>$destinationUuid,
            'event_uuid'=>$eventUuid,
            'event_name'=>substr(DestinationRegistry::normaliseEventPattern($eventName), 0, 96),
            'client_id'=>!empty($context['client_id']) ? absint($context['client_id']) : (!empty($destination['client_id']) ? absint($destination['client_id']) : null),
            'project_id'=>!empty($context['project_id']) ? absint($context['project_id']) : (!empty($destination['project_id']) ? absint($destination['project_id']) : null),
            'status'=>'pending',
            'attempts'=>0,
            'max_attempts'=>(int)$policy['max_attempts'],
            'base_delay_seconds'=>(int)$policy['base_delay_seconds'],
            'available_at'=>$now,
            'payload_json'=>$encoded,
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);

        if ($inserted === false) {
            $existing = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE delivery_key=%s LIMIT 1', $deliveryKey));
            if ($existing > 0) return ['ok'=>true,'duplicate'=>true,'job_id'=>$existing];
            return ['ok'=>false,'reason'=>'insert_failed','job_id'=>0];
        }

        if (self::mode() === 'live') self::scheduleDispatch(3, 'client_azure_event');
        return ['ok'=>true,'duplicate'=>false,'job_id'=>(int)$wpdb->insert_id];
    }

    public static function dispatch(int $requestedLimit = 0): array {
        $relay = ClientAzureRelayConnection::status();
        $limit = $requestedLimit > 0 ? max(1, min(50, $requestedLimit)) : max(1, min(50, (int)($relay['batch_size'] ?? 10)));
        $summary = ['ok'=>true,'mode'=>self::mode(),'relay_configured'=>!empty($relay['configured']),'claimed'=>0,'relayed'=>0,'retried'=>0,'dead'=>0];

        if (self::mode() !== 'live') return $summary;
        if (empty($relay['configured']) || !self::tableReady()) {
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
                $state = self::fail($job, 'invalid_payload', 'O payload persistido da entrega 5B não contém JSON válido.');
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }

            $response = ClientAzureRelayConnection::send($payload);
            if (is_wp_error($response)) {
                $state = self::fail($job, $response->get_error_code(), $response->get_error_message());
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }

            $code = (int)wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) {
                $body = substr(wp_strip_all_tags((string)wp_remote_retrieve_body($response)), 0, 300);
                $state = self::fail($job, 'relay_http_' . $code, 'Relay 5B devolveu HTTP ' . $code . ($body !== '' ? ': ' . $body : '.'));
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }

            self::complete($job, $code);
            $summary['relayed']++;
        }

        $next = self::nextPendingTimestamp();
        if ($next > 0) self::scheduleDispatch(max(1, $next - time()), 'client_azure_retry');
        return $summary;
    }

    public static function dispatchNow(int $limit = 10): array {
        return self::dispatch(max(1, min(25, $limit)));
    }

    public static function health(): array {
        global $wpdb;
        $stats = ['pending'=>0,'running'=>0,'relayed_24h'=>0,'dead'=>0,'oldest_pending_seconds'=>0];
        $lastError = '';
        $lastRelay = '';
        $ready = self::tableReady();

        if ($ready) {
            $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(status='pending') pending,SUM(status='running') running,SUM(status='completed' AND completed_at>=%s) relayed_24h,SUM(status='dead') dead,MIN(IF(status='pending',available_at,NULL)) oldest_pending FROM " . self::table(),
                $since
            ), ARRAY_A) ?: [];
            foreach (['pending','running','relayed_24h','dead'] as $key) $stats[$key] = (int)($row[$key] ?? 0);
            $oldest = !empty($row['oldest_pending']) ? strtotime((string)$row['oldest_pending'] . ' UTC') : 0;
            $stats['oldest_pending_seconds'] = $oldest > 0 ? max(0, time() - $oldest) : 0;
            $lastRelay = (string)$wpdb->get_var("SELECT completed_at FROM " . self::table() . " WHERE status='completed' ORDER BY id DESC LIMIT 1");
            $lastError = (string)$wpdb->get_var("SELECT last_error_message FROM " . self::table() . " WHERE last_error_message IS NOT NULL AND last_error_message<>'' ORDER BY id DESC LIMIT 1");
        }

        $relay = ClientAzureRelayConnection::status();
        $mode = self::mode();
        $status = !$ready ? 'critical' : (($mode === 'live' && empty($relay['configured'])) || $stats['dead'] > 0 ? 'attention' : 'ready');
        return [
            'version'=>self::VERSION,
            'status'=>$status,
            'table_ready'=>$ready,
            'mode'=>$mode,
            'relay'=>$relay,
            'stats'=>$stats,
            'last_relayed_at'=>$lastRelay,
            'last_error'=>substr($lastError, 0, 300),
        ];
    }

    public static function localProbe(): array {
        global $wpdb;
        $health = self::health();
        $eventUuid = '00000000-0000-4000-8000-00000000005b';
        $destinationUuid = 'ff_d_5b_local_probe';
        $key1 = DestinationRouter::deliveryKey($eventUuid, $destinationUuid);
        $key2 = DestinationRouter::deliveryKey($eventUuid, $destinationUuid);
        $write = false;

        if (!empty($health['table_ready'])) {
            $now = current_time('mysql', true);
            $write = $wpdb->insert(self::table(), [
                'delivery_key'=>$key1,
                'tenant_id'=>TenantIdentity::tenantId(),
                'installation_id'=>TenantIdentity::installationId(),
                'destination_id'=>0,
                'destination_uuid'=>$destinationUuid,
                'event_uuid'=>$eventUuid,
                'event_name'=>'client_azure.local_probe',
                'status'=>'completed',
                'attempts'=>0,
                'max_attempts'=>1,
                'base_delay_seconds'=>60,
                'available_at'=>$now,
                'completed_at'=>$now,
                'payload_json'=>'{}',
                'created_at'=>$now,
                'updated_at'=>$now,
            ]) !== false;
            if ($write) $wpdb->delete(self::table(), ['delivery_key'=>$key1], ['%s']);
        }

        $intakePriority = has_action('fieldflow_core_event', [self::class, 'captureEvent']);
        $checks = [
            'delivery_table_ready'=>!empty($health['table_ready']),
            'delivery_key_deterministic'=>$key1 === $key2 && strpos($key1, 'ff_del_') === 0,
            'queue_write'=>$write,
            'core_event_intake'=>$intakePriority !== false,
            'intake_fail_open_priority'=>$intakePriority !== false && (int)$intakePriority < 20,
            'dispatch_handler'=>has_action(self::DISPATCH_HOOK, [self::class, 'dispatch']) !== false,
            'queue_policy'=>class_exists('\\RoutesPro\\Operations\\Queue\\QueueSettings') && \RoutesPro\Operations\Queue\QueueSettings::isAllowed(self::DISPATCH_HOOK),
            'primary_azure_listener_untouched'=>has_action('fieldflow_core_event', [AzureSyncFoundation::class, 'captureEvent']) !== false,
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$health];
    }

    public static function retryDead(int $jobId = 0): int {
        global $wpdb;
        if (!self::tableReady()) return 0;
        $now = current_time('mysql', true);
        if ($jobId > 0) {
            $count = (int)$wpdb->query($wpdb->prepare(
                "UPDATE " . self::table() . " SET status='pending',attempts=0,available_at=%s,locked_at=NULL,lock_token=NULL,completed_at=NULL,last_http_code=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=%s WHERE id=%d AND status='dead'",
                $now, $now, $jobId
            ));
        } else {
            $count = (int)$wpdb->query($wpdb->prepare(
                "UPDATE " . self::table() . " SET status='pending',attempts=0,available_at=%s,locked_at=NULL,lock_token=NULL,completed_at=NULL,last_http_code=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=%s WHERE status='dead'",
                $now, $now
            ));
        }
        if ($count > 0 && self::mode() === 'live') self::scheduleDispatch(2, 'client_azure_manual_retry');
        return $count;
    }

    public static function recent(int $limit = 30): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $limit = max(1, min(100, $limit));
        return $wpdb->get_results('SELECT id,delivery_key,destination_uuid,event_name,status,attempts,max_attempts,client_id,project_id,available_at,completed_at,last_http_code,last_error_code,last_error_message,created_at FROM ' . self::table() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    public static function destinationSnapshot(array $destination): array {
        $retry = self::retryPolicy($destination);
        return [
            'id'=>absint($destination['id'] ?? 0),
            'uuid'=>sanitize_text_field((string)($destination['destination_uuid'] ?? '')),
            'type'=>'client_azure',
            'name'=>sanitize_text_field((string)($destination['name'] ?? 'Azure do Cliente')),
            'client_id'=>absint($destination['client_id'] ?? 0),
            'project_id'=>absint($destination['project_id'] ?? 0),
            'mode'=>DestinationRegistry::normaliseMode((string)($destination['mode'] ?? 'mirror')),
            'endpoint_ref'=>substr((string)($destination['endpoint_ref'] ?? ''), 0, 500),
            'secret_ref'=>substr((string)($destination['secret_ref'] ?? ''), 0, 500),
            'retry_policy'=>$retry,
        ];
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

    private static function retryPolicy(array $destination): array {
        $stored = json_decode((string)($destination['retry_policy_json'] ?? ''), true);
        if (!is_array($stored)) $stored = [];
        return [
            'max_attempts'=>max(1, min(12, absint($stored['max_attempts'] ?? 5))),
            'base_delay_seconds'=>max(30, min(3600, absint($stored['base_delay_seconds'] ?? 60))),
            'strategy'=>'exponential',
            'auth_mode'=>sanitize_key((string)($stored['auth_mode'] ?? 'key_vault_function_key')) ?: 'key_vault_function_key',
        ];
    }

    private static function safeContext(array $context): array {
        $out = [];
        foreach (['entity_type','entity_id','actor_user_id','client_id','project_id','route_id','route_stop_id','location_id','submission_id','briefing_id'] as $key) {
            if (!array_key_exists($key, $context)) continue;
            $out[$key] = $key === 'entity_type' ? sanitize_key((string)$context[$key]) : absint($context[$key]);
        }
        return $out;
    }

    private static function scheduleDispatch(int $delay, string $source): void {
        QueueManager::schedule(self::DISPATCH_HOOK, [], max(1, $delay), [
            'queue'=>'client_azure',
            'max_attempts'=>1,
            'priority'=>11,
            'source'=>$source,
            'idempotency_key'=>'client-azure-dispatch:' . gmdate('YmdHi', time() + max(1, $delay)),
            'meta'=>['mode'=>self::mode()],
        ]);
    }

    private static function claimOne(string $token): array {
        global $wpdb;
        $now = current_time('mysql', true);
        $staleBefore = gmdate('Y-m-d H:i:s', time() - (10 * MINUTE_IN_SECONDS));
        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status=IF(attempts>=max_attempts,'dead','pending'),available_at=%s,completed_at=IF(attempts>=max_attempts,%s,NULL),locked_at=NULL,lock_token=NULL,last_error_code='stale_lock_recovered',last_error_message='Um lock 5B antigo foi recuperado automaticamente.',updated_at=%s WHERE status='running' AND locked_at IS NOT NULL AND locked_at<%s",
            $now, $now, $now, $staleBefore
        ));
        $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::table() . " WHERE status='pending' AND available_at<=%s ORDER BY id ASC LIMIT 1", $now));
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
        IntegrationPlatform::log('client_azure', 'relay_accept', 'success', [
            'event'=>(string)$job['event_name'],'delivery_key'=>(string)$job['delivery_key'],'http_code'=>$httpCode,'job_id'=>(int)$job['id']
        ]);
    }

    private static function fail(array $job, string $code, string $message): array {
        global $wpdb;
        $attempts = max(1, (int)($job['attempts'] ?? 1));
        $maxAttempts = max(1, (int)($job['max_attempts'] ?? 5));
        $base = max(30, min(3600, (int)($job['base_delay_seconds'] ?? 60)));
        $terminal = $attempts >= $maxAttempts;
        $delay = min(21600, $base * (2 ** max(0, $attempts - 1)));
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
        IntegrationPlatform::log('client_azure', 'relay_accept', $terminal ? 'dead' : 'retry', [
            'event'=>(string)$job['event_name'],'delivery_key'=>(string)$job['delivery_key'],'attempt'=>$attempts,'max_attempts'=>$maxAttempts,'job_id'=>(int)$job['id']
        ], $message);
        return ['terminal'=>$terminal,'delay'=>$terminal ? 0 : $delay];
    }

    private static function nextPendingTimestamp(): int {
        global $wpdb;
        if (!self::tableReady()) return 0;
        $next = (string)$wpdb->get_var("SELECT MIN(available_at) FROM " . self::table() . " WHERE status='pending'");
        return $next !== '' ? max(0, strtotime($next . ' UTC')) : 0;
    }
}
