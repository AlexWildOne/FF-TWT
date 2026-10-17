<?php
namespace RoutesPro\Cloud;

use RoutesPro\Operations\Queue\QueueManager;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5C.5 external connector delivery foundation.
 *
 * 2.2.370 keeps the local control plane hard-locked and adds synthetic certification: routing intake,
 * deterministic idempotency keys and a dedicated persistent queue. Network
 * delivery remains hard-locked. This lets production validate real event
 * intake before the Broker delivery endpoint is enabled in the next gate.
 */
final class ConnectorDelivery {
    public const VERSION = '1.1.0';
    public const OPTION = 'fieldflow_connector_delivery_5c5_settings';
    public const DISPATCH_HOOK = 'fieldflow_connector_delivery_dispatch';
    public const NETWORK_DELIVERY_ENABLED = false;

    private const TYPES = ['external_api', 'webhook'];
    private static bool $registered = false;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_cloud_connector_jobs';
    }

    public static function defaults(): array {
        return [
            'mode' => 'shadow',
            'kill_switch' => 1,
            'batch_size' => 10,
            'timeout' => 20,
            'max_attempts' => 5,
            'base_delay_seconds' => 60,
            'circuit_failure_threshold' => 5,
            'circuit_open_seconds' => 900,
            'retention_days' => 30,
        ];
    }

    public static function settings(): array {
        $stored = get_option(self::OPTION, []);
        $s = wp_parse_args(is_array($stored) ? $stored : [], self::defaults());
        $s['mode'] = in_array((string)$s['mode'], ['shadow', 'queue'], true) ? (string)$s['mode'] : 'shadow';
        $s['kill_switch'] = !empty($s['kill_switch']) ? 1 : 0;
        $s['batch_size'] = max(1, min(50, absint($s['batch_size'] ?? 10)));
        $s['timeout'] = max(5, min(45, absint($s['timeout'] ?? 20)));
        $s['max_attempts'] = max(1, min(12, absint($s['max_attempts'] ?? 5)));
        $s['base_delay_seconds'] = max(30, min(3600, absint($s['base_delay_seconds'] ?? 60)));
        $s['circuit_failure_threshold'] = max(2, min(20, absint($s['circuit_failure_threshold'] ?? 5)));
        $s['circuit_open_seconds'] = max(60, min(86400, absint($s['circuit_open_seconds'] ?? 900)));
        $s['retention_days'] = max(7, min(180, absint($s['retention_days'] ?? 30)));
        return $s;
    }

    public static function save(array $input): array {
        $mode = in_array((string)($input['mode'] ?? ''), ['shadow', 'queue'], true) ? (string)$input['mode'] : 'shadow';
        $settings = [
            'mode' => $mode,
            'kill_switch' => !empty($input['kill_switch']) ? 1 : 0,
            'batch_size' => max(1, min(50, absint($input['batch_size'] ?? 10))),
            'timeout' => max(5, min(45, absint($input['timeout'] ?? 20))),
            'max_attempts' => max(1, min(12, absint($input['max_attempts'] ?? 5))),
            'base_delay_seconds' => max(30, min(3600, absint($input['base_delay_seconds'] ?? 60))),
            'circuit_failure_threshold' => max(2, min(20, absint($input['circuit_failure_threshold'] ?? 5))),
            'circuit_open_seconds' => max(60, min(86400, absint($input['circuit_open_seconds'] ?? 900))),
            'retention_days' => max(7, min(180, absint($input['retention_days'] ?? 30))),
        ];
        update_option(self::OPTION, $settings, false);
        return $settings;
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        // Priority 8 keeps the listener fail-open and ahead of non-critical integrations,
        // while preserving the certified Azure TWT listener at its existing priority.
        add_action('fieldflow_core_event', [self::class, 'captureEvent'], 8, 4);
        add_action(self::DISPATCH_HOOK, [self::class, 'dispatch']);
        add_action(QueueManager::CLEANUP_HOOK, [self::class, 'cleanup'], 20);
    }

    public static function supportedTypes(): array {
        return self::TYPES;
    }

    public static function isSupportedType(string $type): bool {
        return in_array(sanitize_key($type), self::TYPES, true);
    }

    public static function tableReady(): bool {
        global $wpdb;
        $table = self::table();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function mode(): string {
        return (string)self::settings()['mode'];
    }

    public static function killSwitch(): bool {
        return !empty(self::settings()['kill_switch']);
    }

    public static function networkDeliveryEnabled(): bool {
        return self::NETWORK_DELIVERY_ENABLED && self::mode() === 'live' && !self::killSwitch();
    }

    public static function captureEvent(string $eventName, array $payload, array $context, string $eventUuid): void {
        // Absolute fail-open: this connector can never break the core event bus.
        try {
            if (!self::tableReady() || !DestinationRegistry::ready()) return;
            $eventName = DestinationRegistry::normaliseEventPattern($eventName);
            $eventUuid = self::normaliseEventUuid($eventUuid);
            if ($eventUuid === '') return;

            $plan = DestinationRouter::routePlan($eventName, $context, $eventUuid);
            foreach ((array)($plan['destinations'] ?? []) as $item) {
                $type = sanitize_key((string)($item['type_key'] ?? ''));
                if (!self::isSupportedType($type)) continue;
                $destination = DestinationRegistry::getDestination(absint($item['destination_id'] ?? 0));
                if (!$destination || empty($destination['is_active'])) continue;
                self::enqueue($eventName, $payload, $context, $eventUuid, $destination, (string)($item['delivery_key'] ?? ''));
            }
        } catch (\Throwable $error) {
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('A intake 5C.5 falhou em modo fail-open.', $error, ['context_key'=>'connector_delivery_5c5']);
            }
        }
    }

    public static function enqueue(string $eventName, array $payload, array $context, string $eventUuid, array $destination, string $deliveryKey = ''): array {
        global $wpdb;
        if (!self::tableReady()) return ['ok'=>false,'reason'=>'table_not_ready','job_id'=>0];
        $type = sanitize_key((string)($destination['type_key'] ?? ''));
        if (!self::isSupportedType($type)) return ['ok'=>false,'reason'=>'destination_type_not_supported','job_id'=>0];

        $destinationUuid = sanitize_text_field((string)($destination['destination_uuid'] ?? ''));
        if ($destinationUuid === '') return ['ok'=>false,'reason'=>'destination_uuid_missing','job_id'=>0];
        $eventUuid = self::normaliseEventUuid($eventUuid);
        if ($eventUuid === '') return ['ok'=>false,'reason'=>'event_uuid_invalid','job_id'=>0];
        $deliveryKey = trim($deliveryKey) !== '' ? sanitize_text_field($deliveryKey) : DestinationRouter::deliveryKey($eventUuid, $destinationUuid);
        if (!preg_match('/^ff_del_[a-f0-9]{40}$/i', $deliveryKey)) return ['ok'=>false,'reason'=>'delivery_key_invalid','job_id'=>0];

        $settings = self::settings();
        $policy = self::retryPolicy($destination, $settings);
        $status = ((string)$settings['mode'] === 'queue' && empty($settings['kill_switch'])) ? 'pending' : 'shadow';
        $now = current_time('mysql', true);
        $identity = TenantIdentity::snapshot();
        $sourcePayloadJson = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($sourcePayloadJson)) return ['ok'=>false,'reason'=>'payload_encode_failed','job_id'=>0];
        $sourcePayloadHash = hash('sha256', $sourcePayloadJson);
        $envelope = [
            'contract'=>'fieldflow.connector.delivery.v1',
            'contract_version'=>'1.0',
            'tenant'=>$identity,
            'event'=>[
                'uuid'=>$eventUuid,
                'name'=>$eventName,
                'occurred_at'=>gmdate('c'),
            ],
            'context'=>self::safeContext($context),
            'destination'=>self::destinationSnapshot($destination),
            'delivery'=>[
                'key'=>$deliveryKey,
                'execution_mode'=>$status === 'pending' ? 'queue' : 'shadow',
                'network_delivery_enabled'=>false,
                'kill_switch'=>!empty($settings['kill_switch']),
            ],
            'data'=>$status === 'pending'
                ? $payload
                : ['shadow_redacted'=>true,'payload_sha256'=>$sourcePayloadHash],
        ];
        $body = wp_json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return ['ok'=>false,'reason'=>'payload_encode_failed','job_id'=>0];

        $inserted = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . self::table() . ' (delivery_key,tenant_id,installation_id,destination_id,destination_uuid,connector_type,event_uuid,event_name,client_id,project_id,execution_mode,status,attempts,max_attempts,base_delay_seconds,available_at,payload_sha256,payload_json,created_at,updated_at) VALUES (%s,%s,%s,%d,%s,%s,%s,%s,%d,%d,%s,%s,0,%d,%d,%s,%s,%s,%s,%s)',
            $deliveryKey,
            (string)$identity['tenant_id'],
            (string)$identity['installation_id'],
            absint($destination['id'] ?? 0),
            $destinationUuid,
            $type,
            $eventUuid,
            $eventName,
            !empty($context['client_id']) ? absint($context['client_id']) : absint($destination['client_id'] ?? 0),
            !empty($context['project_id']) ? absint($context['project_id']) : absint($destination['project_id'] ?? 0),
            $status === 'pending' ? 'queue' : 'shadow',
            $status,
            (int)$policy['max_attempts'],
            (int)$policy['base_delay_seconds'],
            $now,
            $sourcePayloadHash,
            $body,
            $now,
            $now
        ));

        if ($inserted === false) return ['ok'=>false,'reason'=>'queue_insert_failed','job_id'=>0];
        $jobId = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE delivery_key=%s LIMIT 1', $deliveryKey));
        if ($status === 'pending') self::scheduleDispatch(2, 'connector_delivery_enqueue');
        return ['ok'=>true,'reason'=>$inserted === 0 ? 'duplicate_ignored' : $status,'job_id'=>$jobId,'status'=>$status,'delivery_key'=>$deliveryKey];
    }

    /**
     * 2.2.370 dispatch is intentionally a hard gate. Pending jobs remain queued
     * until the Broker delivery contract is separately deployed and certified.
     */
    public static function dispatch(int $limit = 10): array {
        $limit = max(1, min(50, $limit));
        return [
            'ok'=>true,
            'claimed'=>0,
            'delivered'=>0,
            'retried'=>0,
            'dead'=>0,
            'blocked'=>true,
            'reason'=>'network_delivery_locked_2_2_370',
            'limit'=>$limit,
        ];
    }

    public static function dispatchNow(int $limit = 10): array {
        return self::dispatch($limit);
    }

    public static function retryDead(int $jobId = 0): int {
        global $wpdb;
        if (!self::tableReady()) return 0;
        $now = current_time('mysql', true);
        if ($jobId > 0) {
            return (int)$wpdb->query($wpdb->prepare(
                "UPDATE " . self::table() . " SET status='pending',attempts=0,available_at=%s,locked_at=NULL,lock_token=NULL,completed_at=NULL,last_http_code=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=%s WHERE id=%d AND status='dead'",
                $now, $now, $jobId
            ));
        }
        return (int)$wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status='pending',attempts=0,available_at=%s,locked_at=NULL,lock_token=NULL,completed_at=NULL,last_http_code=NULL,last_error_code=NULL,last_error_message=NULL,updated_at=%s WHERE status='dead'",
            $now, $now
        ));
    }

    public static function recent(int $limit = 30): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $limit = max(1, min(100, $limit));
        return $wpdb->get_results('SELECT id,delivery_key,destination_uuid,connector_type,event_name,execution_mode,status,attempts,max_attempts,client_id,project_id,available_at,completed_at,last_http_code,last_error_code,last_error_message,payload_sha256,created_at FROM ' . self::table() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    public static function health(): array {
        global $wpdb;
        $ready = self::tableReady();
        $stats = ['shadow'=>0,'pending'=>0,'running'=>0,'completed'=>0,'dead'=>0,'queued_24h'=>0];
        if ($ready) {
            $row = $wpdb->get_row(
                "SELECT
                    SUM(status='shadow') AS shadow,
                    SUM(status='pending') AS pending,
                    SUM(status='running') AS running,
                    SUM(status='completed') AS completed,
                    SUM(status='dead') AS dead,
                    SUM(created_at>=UTC_TIMESTAMP()-INTERVAL 24 HOUR) AS queued_24h
                 FROM " . self::table(), ARRAY_A
            ) ?: [];
            foreach ($stats as $key=>$value) $stats[$key] = (int)($row[$key] ?? 0);
        }
        $settings = self::settings();
        $eligible = 0;
        if (DestinationRegistry::ready()) {
            foreach (DestinationRegistry::listDestinations() as $destination) {
                if (!empty($destination['is_active']) && self::isSupportedType((string)($destination['type_key'] ?? ''))) $eligible++;
            }
        }
        return [
            'version'=>self::VERSION,
            'status'=>$ready ? 'ready' : 'critical',
            'table_ready'=>$ready,
            'mode'=>(string)$settings['mode'],
            'kill_switch'=>!empty($settings['kill_switch']),
            'network_delivery_enabled'=>false,
            'network_delivery_implemented'=>self::NETWORK_DELIVERY_ENABLED,
            'queue_enabled'=>(string)$settings['mode'] === 'queue' && empty($settings['kill_switch']),
            'eligible_destinations'=>$eligible,
            'stats'=>$stats,
            'policy'=>[
                'batch_size'=>(int)$settings['batch_size'],
                'timeout'=>(int)$settings['timeout'],
                'max_attempts'=>(int)$settings['max_attempts'],
                'base_delay_seconds'=>(int)$settings['base_delay_seconds'],
                'circuit_failure_threshold'=>(int)$settings['circuit_failure_threshold'],
                'circuit_open_seconds'=>(int)$settings['circuit_open_seconds'],
                'retention_days'=>(int)$settings['retention_days'],
            ],
            'broker_delivery_required'=>true,
            'secret_resolution_location'=>'azure_broker_only',
            'idempotency'=>'destination_plus_event_uuid',
        ];
    }

    public static function localProbe(): array {
        global $wpdb;
        $health = self::health();
        $eventUuid = '00000000-0000-4000-8000-00000000005c';
        $destinationUuid = 'ff_d_' . str_repeat('5', 24);
        $key1 = DestinationRouter::deliveryKey($eventUuid, $destinationUuid);
        $key2 = DestinationRouter::deliveryKey($eventUuid, $destinationUuid);
        $write = false;
        if (!empty($health['table_ready'])) {
            $now = current_time('mysql', true);
            $wpdb->delete(self::table(), ['delivery_key'=>$key1], ['%s']);
            $write = $wpdb->insert(self::table(), [
                'delivery_key'=>$key1,
                'tenant_id'=>TenantIdentity::tenantId(),
                'installation_id'=>TenantIdentity::installationId(),
                'destination_id'=>0,
                'destination_uuid'=>$destinationUuid,
                'connector_type'=>'webhook',
                'event_uuid'=>$eventUuid,
                'event_name'=>'connector_delivery.local_probe',
                'execution_mode'=>'shadow',
                'status'=>'shadow',
                'attempts'=>0,
                'max_attempts'=>1,
                'base_delay_seconds'=>60,
                'available_at'=>$now,
                'payload_sha256'=>hash('sha256', '{}'),
                'payload_json'=>'{}',
                'created_at'=>$now,
                'updated_at'=>$now,
            ]) !== false;
            if ($write) $wpdb->delete(self::table(), ['delivery_key'=>$key1], ['%s']);
        }
        $priority = has_action('fieldflow_core_event', [self::class, 'captureEvent']);
        $checks = [
            'table_ready'=>!empty($health['table_ready']),
            'delivery_key_deterministic'=>$key1 === $key2 && strpos($key1, 'ff_del_') === 0,
            'queue_write'=>$write,
            'core_event_intake'=>$priority !== false,
            'intake_fail_open_priority'=>$priority !== false && (int)$priority < 20,
            'dispatch_handler'=>has_action(self::DISPATCH_HOOK, [self::class, 'dispatch']) !== false,
            'queue_policy'=>class_exists('\\RoutesPro\\Operations\\Queue\\QueueSettings') && \RoutesPro\Operations\Queue\QueueSettings::isAllowed(self::DISPATCH_HOOK),
            'network_delivery_hard_locked'=>empty($health['network_delivery_enabled']) && empty($health['network_delivery_implemented']),
            'kill_switch_present'=>array_key_exists('kill_switch', self::settings()),
            'secret_resolution_outside_wordpress'=>(string)($health['secret_resolution_location'] ?? '') === 'azure_broker_only',
            'primary_azure_listener_untouched'=>has_action('fieldflow_core_event', [AzureSyncFoundation::class, 'captureEvent']) !== false,
            'client_azure_listener_untouched'=>has_action('fieldflow_core_event', [ClientAzureDelivery::class, 'captureEvent']) !== false,
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$health];
    }

    /**
     * Local certification helper for 5C.5 that exercises the real router and
     * queue/idempotency contract without emitting a global EventBus event.
     * This avoids touching the certified Azure TWT listener while still
     * proving that an active external destination is routable.
     */
    public static function certificationProbe(int $destinationId, string $expectedMode = 'shadow'): array {
        global $wpdb;

        $expectedMode = in_array($expectedMode, ['shadow', 'queue'], true) ? $expectedMode : 'shadow';
        $settings = self::settings();
        $checks = [
            'table_ready'=>self::tableReady(),
            'network_delivery_hard_locked'=>!self::NETWORK_DELIVERY_ENABLED && !self::networkDeliveryEnabled(),
            'mode_expected'=>(string)$settings['mode'] === $expectedMode,
            'kill_switch_safe'=>$expectedMode === 'shadow' ? !empty($settings['kill_switch']) : empty($settings['kill_switch']),
        ];
        if (in_array(false, $checks, true)) {
            return ['ok'=>false,'checks'=>$checks,'reason'=>'certification_settings_not_ready','destination_id'=>$destinationId,'mode'=>$expectedMode];
        }

        $destination = DestinationRegistry::getDestination($destinationId);
        $checks['destination_exists']=!empty($destination);
        $checks['destination_active']=!empty($destination['is_active']);
        $checks['destination_supported']=!empty($destination) && self::isSupportedType((string)($destination['type_key'] ?? ''));
        if (empty($destination) || empty($destination['is_active']) || !$checks['destination_supported']) {
            return ['ok'=>false,'checks'=>$checks,'reason'=>'certification_destination_not_eligible','destination_id'=>$destinationId,'mode'=>$expectedMode];
        }

        $route = self::certificationRoute($destination);
        if (is_wp_error($route)) {
            $checks['routing_rule_match']=false;
            return ['ok'=>false,'checks'=>$checks,'reason'=>$route->get_error_code(),'message'=>$route->get_error_message(),'destination_id'=>$destinationId,'mode'=>$expectedMode];
        }
        $checks['routing_rule_match']=true;
        $checks['route_contains_destination']=!empty($route['contains_destination']);
        if (empty($route['contains_destination'])) {
            return ['ok'=>false,'checks'=>$checks,'reason'=>'certification_route_does_not_include_destination','destination_id'=>$destinationId,'mode'=>$expectedMode,'route'=>$route];
        }

        $eventUuid = wp_generate_uuid4();
        $eventName = (string)$route['event_name'];
        $context = (array)$route['context'];
        $payload = [
            'certification'=>[
                'phase'=>'5C.5',
                'release'=>'2.2.370',
                'probe'=>'local_non_network',
                'mode'=>$expectedMode,
                'issued_at'=>gmdate('c'),
            ],
        ];
        $deliveryKey = DestinationRouter::deliveryKey($eventUuid, (string)$destination['destination_uuid']);

        $first = self::enqueue($eventName, $payload, $context, $eventUuid, $destination, $deliveryKey);
        $second = self::enqueue($eventName, $payload, $context, $eventUuid, $destination, $deliveryKey);
        $row = self::tableReady()
            ? ($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE delivery_key=%s LIMIT 1', $deliveryKey), ARRAY_A) ?: [])
            : [];
        $count = self::tableReady()
            ? (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE delivery_key=%s', $deliveryKey))
            : 0;

        $expectedStatus = $expectedMode === 'queue' ? 'pending' : 'shadow';
        $decoded = !empty($row['payload_json']) ? json_decode((string)$row['payload_json'], true) : [];
        if (!is_array($decoded)) $decoded = [];
        $checks['first_enqueue_ok']=!empty($first['ok']);
        $checks['duplicate_second_ok']=!empty($second['ok']) && (string)($second['reason'] ?? '') === 'duplicate_ignored';
        $checks['single_delivery_key_row']=$count === 1;
        $checks['status_expected']=(string)($row['status'] ?? '') === $expectedStatus;
        $checks['event_uuid_preserved']=(string)($row['event_uuid'] ?? '') === $eventUuid;
        $checks['payload_hash_present']=preg_match('/^[a-f0-9]{64}$/', (string)($row['payload_sha256'] ?? '')) === 1;
        $checks['shadow_payload_redacted']=$expectedMode !== 'shadow' || !empty($decoded['data']['shadow_redacted']);
        $checks['queue_payload_persisted']=$expectedMode !== 'queue' || !empty($decoded['data']['certification']);
        $checks['network_flag_false']=empty($decoded['delivery']['network_delivery_enabled']);

        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'reason'=>!in_array(false, $checks, true) ? 'certification_ok' : 'certification_failed',
            'destination_id'=>(int)$destination['id'],
            'destination_name'=>(string)$destination['name'],
            'destination_uuid'=>(string)$destination['destination_uuid'],
            'event_name'=>$eventName,
            'event_uuid'=>$eventUuid,
            'delivery_key'=>$deliveryKey,
            'job_id'=>(int)($row['id'] ?? 0),
            'status'=>(string)($row['status'] ?? ''),
            'mode'=>$expectedMode,
            'route'=>$route,
        ];
    }

    private static function certificationRoute(array $destination) {
        global $wpdb;
        if (!DestinationRegistry::ready()) {
            return new \WP_Error('certification_registry_not_ready', 'O Destination Registry ainda não está pronto.');
        }
        $destinationId = absint($destination['id'] ?? 0);
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . DestinationRegistry::rulesTable() . " WHERE destination_id=%d AND is_active=1 AND action_name='include' ORDER BY priority DESC,id DESC",
            $destinationId
        ), ARRAY_A) ?: [];
        if (!$rules) {
            return new \WP_Error('certification_no_active_include_rule', 'O destino não tem uma regra de routing INCLUDE ativa para certificar.');
        }

        foreach ($rules as $rule) {
            $pattern = DestinationRegistry::normaliseEventPattern((string)($rule['event_pattern'] ?? '*'));
            $eventName = self::certificationEventFromPattern($pattern);
            $clientId = absint($rule['client_id'] ?? 0) ?: absint($destination['client_id'] ?? 0);
            $projectId = absint($rule['project_id'] ?? 0) ?: absint($destination['project_id'] ?? 0);
            $context = ['client_id'=>$clientId,'project_id'=>$projectId,'entity_type'=>'connector_delivery_certification','entity_id'=>0];
            $eventUuid = wp_generate_uuid4();
            $plan = DestinationRouter::routePlan($eventName, $context, $eventUuid);
            $contains = false;
            foreach ((array)($plan['destinations'] ?? []) as $candidate) {
                if ((int)($candidate['destination_id'] ?? 0) === $destinationId) {
                    $contains = true;
                    break;
                }
            }
            if ($contains) {
                return [
                    'event_name'=>$eventName,
                    'event_pattern'=>$pattern,
                    'rule_id'=>(int)($rule['id'] ?? 0),
                    'context'=>$context,
                    'contains_destination'=>true,
                    'plan_destination_count'=>count((array)($plan['destinations'] ?? [])),
                ];
            }
        }

        return new \WP_Error('certification_no_matching_route', 'Existe uma regra INCLUDE, mas o routing final não inclui este destino para nenhum padrão ativo.');
    }

    private static function certificationEventFromPattern(string $pattern): string {
        $pattern = DestinationRegistry::normaliseEventPattern($pattern);
        if ($pattern === '' || $pattern === '*') return 'connector_delivery.certification';
        if (strpos($pattern, '*') === false) return $pattern;
        $candidate = str_replace('*', 'certification', $pattern);
        $candidate = DestinationRegistry::normaliseEventPattern($candidate);
        return $candidate !== '' ? $candidate : 'connector_delivery.certification';
    }

    public static function destinationSnapshot(array $destination): array {
        $type = sanitize_key((string)($destination['type_key'] ?? ''));
        $profile = ConnectorFoundation::profileFromDestination($destination);
        $identity = [];
        if ((string)($profile['auth_strategy'] ?? '') === 'entra_cross_tenant') {
            $identity = EntraCrossTenantFoundation::identityProfileFromDestination($destination);
        }
        return [
            'id'=>absint($destination['id'] ?? 0),
            'uuid'=>sanitize_text_field((string)($destination['destination_uuid'] ?? '')),
            'type'=>$type,
            'name'=>sanitize_text_field((string)($destination['name'] ?? 'Destino 5C.5')),
            'client_id'=>absint($destination['client_id'] ?? 0),
            'project_id'=>absint($destination['project_id'] ?? 0),
            'mode'=>DestinationRegistry::normaliseMode((string)($destination['mode'] ?? 'mirror')),
            'endpoint_ref'=>substr((string)($destination['endpoint_ref'] ?? ''), 0, 500),
            'secret_ref'=>substr((string)($destination['secret_ref'] ?? ''), 0, 500),
            'auth_strategy'=>sanitize_key((string)($profile['auth_strategy'] ?? '')),
            'connector_contract'=>sanitize_text_field((string)($profile['contract'] ?? '')),
            'entra'=>[
                'target_tenant_id'=>sanitize_text_field((string)($identity['target_tenant_id'] ?? '')),
                'resource_scope'=>sanitize_text_field((string)($identity['resource_scope'] ?? '')),
            ],
        ];
    }

    public static function cleanup(): void {
        global $wpdb;
        if (!self::tableReady()) return;
        $days = (int)self::settings()['retention_days'];
        $before = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare("DELETE FROM " . self::table() . " WHERE status IN ('shadow','completed') AND created_at<%s", $before));
    }

    private static function retryPolicy(array $destination, array $settings): array {
        $stored = json_decode((string)($destination['retry_policy_json'] ?? ''), true);
        if (!is_array($stored)) $stored = [];
        return [
            'max_attempts'=>max(1, min(12, absint($stored['max_attempts'] ?? $settings['max_attempts']))),
            'base_delay_seconds'=>max(30, min(3600, absint($stored['base_delay_seconds'] ?? $settings['base_delay_seconds']))),
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

    private static function normaliseEventUuid(string $eventUuid): string {
        $eventUuid = strtolower(trim($eventUuid));
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $eventUuid) ? $eventUuid : '';
    }

    private static function scheduleDispatch(int $delay, string $source): void {
        QueueManager::schedule(self::DISPATCH_HOOK, [], max(1, $delay), [
            'queue'=>'connector_delivery',
            'max_attempts'=>1,
            'priority'=>10,
            'source'=>$source,
            'idempotency_key'=>'connector-delivery-dispatch:' . gmdate('YmdHi', time() + max(1, $delay)),
            'meta'=>['phase'=>'5C.5','mode'=>self::mode(),'network_delivery'=>false],
        ]);
    }
}
