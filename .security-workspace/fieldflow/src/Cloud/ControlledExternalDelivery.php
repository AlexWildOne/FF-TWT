<?php
namespace RoutesPro\Cloud;

use RoutesPro\Security\OutboundHttp;
use RoutesPro\Services\IntegrationPlatform;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5C.6 controlled external delivery certification.
 *
 * Safety boundary:
 * - operational EventBus delivery remains hard-locked in ConnectorDelivery 5C.5;
 * - this sender can claim only synthetic rows with execution_mode=controlled_test;
 * - WordPress never supplies an arbitrary target URL or secret to the Broker;
 * - the Broker 1.5.0 forwards only to its fixed, non-persistent certification receiver;
 * - receipts are HMAC signed and verified before a job can be completed.
 */
final class ControlledExternalDelivery {
    public const VERSION = '1.0.0';
    public const PHASE = '5C.6';
    public const BROKER_PATH = '/api/v1/controlled-delivery';
    public const CONTRACT = 'fieldflow.connector.controlled_delivery';
    public const CONTRACT_VERSION = '1.0';
    public const RECEIPT_CONTRACT = 'fieldflow.connector.controlled_receipt';
    public const RECEIPT_CONTRACT_VERSION = '1.0';
    public const RECEIPT_CONTEXT = 'fieldflow-controlled-receipt-v1';
    public const CIRCUIT_OPTION = 'fieldflow_connector_delivery_5c6_circuit_state';
    public const RECEIPTS_OPTION = 'fieldflow_connector_delivery_5c6_receipts';

    private const BEHAVIORS = ['success', 'fail_first', 'fail_always'];

    public static function health(): array {
        $broker = self::brokerConfig();
        $settings = ConnectorDelivery::settings();
        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'status'=>ConnectorDelivery::tableReady() && !is_wp_error($broker) ? 'ready' : 'attention',
            'table_ready'=>ConnectorDelivery::tableReady(),
            'broker_configured'=>!is_wp_error($broker),
            'certification_only'=>true,
            'operational_delivery_enabled'=>false,
            'arbitrary_target_urls_allowed'=>false,
            'secret_resolution_location'=>'azure_broker_only',
            'signed_receipts_required'=>true,
            'mode'=>(string)$settings['mode'],
            'kill_switch'=>!empty($settings['kill_switch']),
            'circuit_failure_threshold'=>(int)$settings['circuit_failure_threshold'],
            'circuit_open_seconds'=>(int)$settings['circuit_open_seconds'],
            'receipt_count'=>count(self::receipts()),
        ];
    }

    public static function localProbe(): array {
        $health = self::health();
        $checks = [
            'queue_5c5_certified'=>ConnectorDelivery::tableReady(),
            'broker_configured'=>!empty($health['broker_configured']),
            'certification_only'=>!empty($health['certification_only']),
            'operational_delivery_blocked'=>empty($health['operational_delivery_enabled']) && !ConnectorDelivery::networkDeliveryEnabled(),
            'arbitrary_targets_blocked'=>empty($health['arbitrary_target_urls_allowed']),
            'signed_receipts_required'=>!empty($health['signed_receipts_required']),
            'secret_resolution_outside_wordpress'=>(string)$health['secret_resolution_location'] === 'azure_broker_only',
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$health];
    }

    public static function probeBroker() {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) {
            return new \WP_Error('fieldflow_5c6_broker_host_invalid', 'O Entra Broker configurado não corresponde ao host dedicado TWT.');
        }
        $url = 'https://' . $host . '/api/v1/health';
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_connector_5c6_broker_health', [
            'max_timeout'=>20,
            'max_response_bytes'=>131072,
            'allow_public_ip_literals'=>false,
        ]);
        $response = OutboundHttp::get($url, ['timeout'=>20], $policy);
        if (is_wp_error($response)) return $response;
        $http = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) $body = [];
        $ok = $http >= 200 && $http < 300
            && !empty($body['ok'])
            && version_compare((string)($body['version'] ?? '0.0.0'), '1.5.0', '>=')
            && !empty($body['controlled_delivery_foundation'])
            && !empty($body['controlled_delivery_certification_only'])
            && !empty($body['controlled_delivery_receiver_fixed'])
            && !empty($body['controlled_delivery_signed_receipts'])
            && empty($body['delivery_enabled']);
        return [
            'ok'=>$ok,
            'http_code'=>$http,
            'version'=>sanitize_text_field((string)($body['version'] ?? '')),
            'controlled_delivery_foundation'=>!empty($body['controlled_delivery_foundation']),
            'certification_only'=>!empty($body['controlled_delivery_certification_only']),
            'receiver_fixed'=>!empty($body['controlled_delivery_receiver_fixed']),
            'signed_receipts'=>!empty($body['controlled_delivery_signed_receipts']),
            'operational_delivery_enabled'=>!empty($body['delivery_enabled']),
            'hmac_runtime_ready'=>!empty($body['hmac_runtime_ready']),
        ];
    }

    public static function certifyDelivery(int $destinationId): array {
        $checks = self::preflightChecks($destinationId);
        if (empty($checks['ok'])) return $checks;
        $destination = $checks['destination'];
        self::resetCircuit((string)$destination['destination_uuid']);

        $created = self::createCertificationJob($destination, 'success', 3, 'delivery');
        if (is_wp_error($created)) return self::failureFromError($created, $checks['checks']);
        $duplicate = self::insertCertificationJob($destination, $created['event_name'], $created['context'], $created['event_uuid'], $created['delivery_key'], 'success', 3, 'delivery');
        $summary = self::dispatchControlled(1);
        $row = self::jobByKey((string)$created['delivery_key']);
        $receipt = self::receiptForKey((string)$created['delivery_key']);

        $outChecks = array_merge($checks['checks'], [
            'job_created'=>!empty($created['job_id']),
            'duplicate_suppressed'=>!empty($duplicate['ok']) && (string)($duplicate['reason'] ?? '') === 'duplicate_ignored',
            'one_row_per_delivery_key'=>self::countByKey((string)$created['delivery_key']) === 1,
            'dispatcher_claimed'=>(int)($summary['claimed'] ?? 0) === 1,
            'broker_request_ok'=>(int)($summary['broker_ok'] ?? 0) === 1,
            'status_completed'=>(string)($row['status'] ?? '') === 'completed',
            'attempts_one'=>(int)($row['attempts'] ?? 0) === 1,
            'remote_http_200'=>(int)($row['last_http_code'] ?? 0) === 200,
            'signed_receipt_verified'=>!empty($receipt['verified']),
            'receipt_delivery_key_bound'=>(string)($receipt['delivery_key'] ?? '') === (string)$created['delivery_key'],
            'receipt_event_uuid_bound'=>(string)($receipt['event_uuid'] ?? '') === (string)$created['event_uuid'],
            'receipt_destination_bound'=>(string)($receipt['destination_uuid'] ?? '') === (string)$destination['destination_uuid'],
            'operational_dispatch_still_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ]);
        return [
            'ok'=>!in_array(false, $outChecks, true),
            'checks'=>$outChecks,
            'reason'=>!in_array(false, $outChecks, true) ? 'controlled_delivery_certified' : 'controlled_delivery_certification_failed',
            'destination_id'=>(int)$destination['id'],
            'destination_name'=>(string)$destination['name'],
            'job_id'=>(int)($row['id'] ?? 0),
            'delivery_key'=>(string)$created['delivery_key'],
            'event_uuid'=>(string)$created['event_uuid'],
            'status'=>(string)($row['status'] ?? ''),
            'summary'=>$summary,
            'receipt'=>$receipt,
        ];
    }

    public static function certifyRetryAndDeadLetter(int $destinationId): array {
        $checks = self::preflightChecks($destinationId);
        if (empty($checks['ok'])) return $checks;
        $destination = $checks['destination'];
        $uuid = (string)$destination['destination_uuid'];
        self::resetCircuit($uuid);

        $retryJob = self::createCertificationJob($destination, 'fail_first', 3, 'retry');
        if (is_wp_error($retryJob)) return self::failureFromError($retryJob, $checks['checks']);
        $first = self::dispatchControlled(1);
        $retryAfterFirst = self::jobByKey((string)$retryJob['delivery_key']);
        $retryScheduledAt = !empty($retryAfterFirst['available_at']) ? strtotime((string)$retryAfterFirst['available_at'] . ' UTC') : 0;
        self::forceAvailableNow((int)($retryAfterFirst['id'] ?? 0));
        $second = self::dispatchControlled(1);
        $retryFinal = self::jobByKey((string)$retryJob['delivery_key']);
        $retryReceipt = self::receiptForKey((string)$retryJob['delivery_key']);

        self::resetCircuit($uuid);
        $deadJob = self::createCertificationJob($destination, 'fail_always', 2, 'dead_letter');
        if (is_wp_error($deadJob)) return self::failureFromError($deadJob, $checks['checks']);
        $deadFirst = self::dispatchControlled(1);
        $deadMid = self::jobByKey((string)$deadJob['delivery_key']);
        self::forceAvailableNow((int)($deadMid['id'] ?? 0));
        $deadSecond = self::dispatchControlled(1);
        $deadFinal = self::jobByKey((string)$deadJob['delivery_key']);

        $outChecks = array_merge($checks['checks'], [
            'retry_first_attempt_claimed'=>(int)($first['claimed'] ?? 0) === 1,
            'retry_first_remote_503'=>(int)($retryAfterFirst['last_http_code'] ?? 0) === 503,
            'retry_status_pending_after_first'=>(string)($retryAfterFirst['status'] ?? '') === 'pending',
            'retry_backoff_scheduled'=>$retryScheduledAt > time(),
            'retry_second_attempt_claimed'=>(int)($second['claimed'] ?? 0) === 1,
            'retry_completed_on_second'=>(string)($retryFinal['status'] ?? '') === 'completed' && (int)($retryFinal['attempts'] ?? 0) === 2,
            'retry_signed_receipt_verified'=>!empty($retryReceipt['verified']),
            'dead_first_attempt_claimed'=>(int)($deadFirst['claimed'] ?? 0) === 1,
            'dead_second_attempt_claimed'=>(int)($deadSecond['claimed'] ?? 0) === 1,
            'dead_letter_reached'=>(string)($deadFinal['status'] ?? '') === 'dead' && (int)($deadFinal['attempts'] ?? 0) === 2,
            'dead_error_recorded'=>(string)($deadFinal['last_error_code'] ?? '') !== '',
            'operational_dispatch_still_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ]);
        $ok = !in_array(false, $outChecks, true);
        if ($ok && !empty($deadFinal['id'])) self::cleanupSyntheticFailureJob((int)$deadFinal['id']);
        self::resetCircuit($uuid);
        return [
            'ok'=>$ok,
            'checks'=>$outChecks,
            'reason'=>$ok ? 'controlled_retry_dead_letter_certified' : 'controlled_retry_dead_letter_failed',
            'destination_id'=>(int)$destination['id'],
            'destination_name'=>(string)$destination['name'],
            'retry_job_id'=>(int)($retryFinal['id'] ?? 0),
            'retry_status'=>(string)($retryFinal['status'] ?? ''),
            'dead_job_id'=>(int)($deadFinal['id'] ?? 0),
            'dead_status'=>(string)($deadFinal['status'] ?? ''),
            'dead_job_cleaned_up'=>$ok,
        ];
    }

    public static function certifyCircuitBreaker(int $destinationId): array {
        $checks = self::preflightChecks($destinationId);
        if (empty($checks['ok'])) return $checks;
        $destination = $checks['destination'];
        $uuid = (string)$destination['destination_uuid'];
        self::resetCircuit($uuid);
        $threshold = max(2, min(20, (int)ConnectorDelivery::settings()['circuit_failure_threshold']));
        $job = self::createCertificationJob($destination, 'fail_always', min(20, $threshold + 2), 'circuit_breaker');
        if (is_wp_error($job)) return self::failureFromError($job, $checks['checks']);

        $networkAttempts = 0;
        for ($i = 0; $i < $threshold; $i++) {
            $row = self::jobByKey((string)$job['delivery_key']);
            if (!$row || (string)$row['status'] !== 'pending') break;
            self::forceAvailableNow((int)$row['id']);
            $run = self::dispatchControlled(1);
            $networkAttempts += (int)($run['claimed'] ?? 0);
        }
        $afterFailures = self::jobByKey((string)$job['delivery_key']);
        $circuit = self::circuitStatus($uuid);
        self::forceAvailableNow((int)($afterFailures['id'] ?? 0));
        $blockedRun = self::dispatchControlled(1);
        $afterBlocked = self::jobByKey((string)$job['delivery_key']);

        $outChecks = array_merge($checks['checks'], [
            'failure_threshold_exercised'=>$networkAttempts === $threshold,
            'circuit_open'=>!empty($circuit['open']),
            'circuit_open_until_future'=>(int)($circuit['open_until'] ?? 0) > time(),
            'next_dispatch_blocked_by_circuit'=>(int)($blockedRun['circuit_open'] ?? 0) === 1,
            'blocked_dispatch_did_not_increment_attempts'=>(int)($afterBlocked['attempts'] ?? 0) === (int)($afterFailures['attempts'] ?? 0),
            'operational_dispatch_still_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ]);
        $ok = !in_array(false, $outChecks, true);
        if (!empty($afterBlocked['id'])) self::cleanupSyntheticFailureJob((int)$afterBlocked['id']);
        self::resetCircuit($uuid);
        return [
            'ok'=>$ok,
            'checks'=>$outChecks,
            'reason'=>$ok ? 'controlled_circuit_breaker_certified' : 'controlled_circuit_breaker_failed',
            'destination_id'=>(int)$destination['id'],
            'destination_name'=>(string)$destination['name'],
            'threshold'=>$threshold,
            'network_attempts'=>$networkAttempts,
            'job_cleaned_up'=>true,
        ];
    }

    public static function dispatchControlled(int $limit = 1): array {
        global $wpdb;
        $limit = max(1, min(10, $limit));
        $settings = ConnectorDelivery::settings();
        $summary = ['ok'=>true,'certification_only'=>true,'claimed'=>0,'broker_ok'=>0,'delivered'=>0,'retried'=>0,'dead'=>0,'circuit_open'=>0,'blocked'=>false,'reason'=>''];
        if ((string)$settings['mode'] !== 'queue' || !empty($settings['kill_switch'])) {
            $summary['ok'] = false;
            $summary['blocked'] = true;
            $summary['reason'] = 'controlled_delivery_requires_queue_and_kill_switch_off';
            return $summary;
        }
        if (!ConnectorDelivery::tableReady()) {
            $summary['ok'] = false;
            $summary['blocked'] = true;
            $summary['reason'] = 'connector_queue_not_ready';
            return $summary;
        }
        $broker = self::brokerConfig();
        if (is_wp_error($broker)) {
            $summary['ok'] = false;
            $summary['blocked'] = true;
            $summary['reason'] = $broker->get_error_code();
            return $summary;
        }

        for ($i = 0; $i < $limit; $i++) {
            $candidate = self::nextCandidate();
            if (!$candidate) break;
            $circuit = self::circuitStatus((string)$candidate['destination_uuid']);
            if (!empty($circuit['open'])) {
                $summary['circuit_open']++;
                $summary['reason'] = 'circuit_open';
                $wpdb->update(ConnectorDelivery::table(), [
                    'available_at'=>gmdate('Y-m-d H:i:s', (int)$circuit['open_until']),
                    'last_error_code'=>'circuit_open',
                    'last_error_message'=>'Circuit breaker 5C.6 aberto para a certificação controlada.',
                    'updated_at'=>current_time('mysql', true),
                ], ['id'=>(int)$candidate['id']]);
                break;
            }

            $job = self::claimJob((int)$candidate['id']);
            if (!$job) continue;
            $summary['claimed']++;
            $response = self::sendToBroker($job, $broker);
            if (is_wp_error($response)) {
                self::recordCircuitFailure((string)$job['destination_uuid']);
                $state = self::failJob($job, 0, $response->get_error_code(), $response->get_error_message());
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }
            $summary['broker_ok']++;
            $remoteHttp = (int)($response['remote_http_status'] ?? 0);
            $receipt = (array)($response['receipt'] ?? []);
            if (empty($response['receipt_verified'])) {
                self::recordCircuitFailure((string)$job['destination_uuid']);
                $state = self::failJob($job, $remoteHttp, 'receipt_signature_invalid', 'O Broker devolveu um receipt que não passou a validação HMAC.');
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }
            self::recordReceipt($receipt + ['verified'=>true]);
            if (!empty($response['delivered']) && (string)($receipt['outcome'] ?? '') === 'delivered' && $remoteHttp >= 200 && $remoteHttp < 300) {
                self::completeJob($job, $remoteHttp);
                self::resetCircuit((string)$job['destination_uuid']);
                $summary['delivered']++;
                continue;
            }
            self::recordCircuitFailure((string)$job['destination_uuid']);
            $state = self::failJob($job, $remoteHttp, 'remote_retryable_failure', 'O receiver controlado devolveu HTTP ' . $remoteHttp . '.');
            $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
        }
        return $summary;
    }

    public static function receipts(int $limit = 50): array {
        $rows = get_option(self::RECEIPTS_OPTION, []);
        if (!is_array($rows)) return [];
        return array_slice($rows, 0, max(1, min(100, $limit)));
    }

    public static function circuitStatus(string $destinationUuid): array {
        $all = get_option(self::CIRCUIT_OPTION, []);
        if (!is_array($all)) $all = [];
        $key = strtolower(trim($destinationUuid));
        $row = is_array($all[$key] ?? null) ? $all[$key] : [];
        $openUntil = absint($row['open_until'] ?? 0);
        $open = $openUntil > time();
        return [
            'open'=>$open,
            'open_until'=>$open ? $openUntil : 0,
            'consecutive_failures'=>$open ? absint($row['consecutive_failures'] ?? 0) : 0,
        ];
    }

    public static function resetCircuit(string $destinationUuid = ''): void {
        $all = get_option(self::CIRCUIT_OPTION, []);
        if (!is_array($all)) $all = [];
        if ($destinationUuid === '') {
            update_option(self::CIRCUIT_OPTION, [], false);
            return;
        }
        unset($all[strtolower(trim($destinationUuid))]);
        update_option(self::CIRCUIT_OPTION, $all, false);
    }

    private static function preflightChecks(int $destinationId): array {
        $settings = ConnectorDelivery::settings();
        $destination = DestinationRegistry::getDestination($destinationId);
        $brokerProbe = self::probeBroker();
        $checks = [
            'table_ready'=>ConnectorDelivery::tableReady(),
            'mode_queue'=>(string)$settings['mode'] === 'queue',
            'kill_switch_off'=>empty($settings['kill_switch']),
            'destination_exists'=>!empty($destination),
            'destination_active'=>!empty($destination['is_active']),
            'destination_supported'=>!empty($destination) && ConnectorDelivery::isSupportedType((string)($destination['type_key'] ?? '')),
            'broker_1_5_ready'=>!is_wp_error($brokerProbe) && !empty($brokerProbe['ok']),
            'operational_dispatch_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ];
        if (in_array(false, $checks, true)) {
            return [
                'ok'=>false,
                'checks'=>$checks,
                'reason'=>'controlled_delivery_preflight_failed',
                'message'=>is_wp_error($brokerProbe) ? $brokerProbe->get_error_message() : 'Confirma Queue, Kill switch OFF, destino ativo e Broker 1.5.0 READY.',
                'destination'=>$destination ?: [],
            ];
        }
        return ['ok'=>true,'checks'=>$checks,'destination'=>$destination,'broker'=>$brokerProbe];
    }

    private static function createCertificationJob(array $destination, string $behavior, int $maxAttempts, string $probe) {
        $route = self::certificationRoute($destination);
        if (is_wp_error($route)) return $route;
        $eventUuid = wp_generate_uuid4();
        $deliveryKey = DestinationRouter::deliveryKey($eventUuid, (string)$destination['destination_uuid']);
        $insert = self::insertCertificationJob($destination, (string)$route['event_name'], (array)$route['context'], $eventUuid, $deliveryKey, $behavior, $maxAttempts, $probe);
        if (empty($insert['ok'])) return new \WP_Error('fieldflow_5c6_job_insert_failed', 'Não foi possível criar o job sintético 5C.6.');
        return $insert + ['event_name'=>(string)$route['event_name'],'context'=>(array)$route['context'],'event_uuid'=>$eventUuid,'delivery_key'=>$deliveryKey];
    }

    private static function insertCertificationJob(array $destination, string $eventName, array $context, string $eventUuid, string $deliveryKey, string $behavior, int $maxAttempts, string $probe): array {
        global $wpdb;
        if (!in_array($behavior, self::BEHAVIORS, true)) return ['ok'=>false,'reason'=>'behavior_invalid'];
        $now = current_time('mysql', true);
        $identity = TenantIdentity::snapshot();
        $payload = [
            'contract'=>'fieldflow.connector.delivery.v1',
            'contract_version'=>'1.0',
            'tenant'=>$identity,
            'event'=>['uuid'=>$eventUuid,'name'=>$eventName,'occurred_at'=>gmdate('c')],
            'context'=>[
                'client_id'=>absint($context['client_id'] ?? 0),
                'project_id'=>absint($context['project_id'] ?? 0),
                'entity_type'=>'connector_delivery_certification',
                'entity_id'=>0,
            ],
            'destination'=>[
                'id'=>absint($destination['id'] ?? 0),
                'uuid'=>(string)$destination['destination_uuid'],
                'type'=>sanitize_key((string)$destination['type_key']),
                'name'=>sanitize_text_field((string)$destination['name']),
            ],
            'delivery'=>[
                'key'=>$deliveryKey,
                'execution_mode'=>'controlled_test',
                'certification_only'=>true,
                'network_delivery_enabled'=>true,
            ],
            'data'=>[
                'certification'=>[
                    'phase'=>self::PHASE,
                    'release'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '2.2.372',
                    'probe'=>sanitize_key($probe),
                    'behavior'=>$behavior,
                    'marker'=>wp_generate_password(24, false, false),
                    'issued_at'=>gmdate('c'),
                ],
            ],
        ];
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return ['ok'=>false,'reason'=>'payload_encode_failed'];
        $hash = hash('sha256', $body);
        $inserted = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . ConnectorDelivery::table() . ' (delivery_key,tenant_id,installation_id,destination_id,destination_uuid,connector_type,event_uuid,event_name,client_id,project_id,execution_mode,status,attempts,max_attempts,base_delay_seconds,available_at,payload_sha256,payload_json,created_at,updated_at) VALUES (%s,%s,%s,%d,%s,%s,%s,%s,%d,%d,%s,%s,0,%d,%d,%s,%s,%s,%s,%s)',
            $deliveryKey,
            (string)$identity['tenant_id'],
            (string)$identity['installation_id'],
            absint($destination['id'] ?? 0),
            (string)$destination['destination_uuid'],
            sanitize_key((string)$destination['type_key']),
            $eventUuid,
            $eventName,
            absint($context['client_id'] ?? 0),
            absint($context['project_id'] ?? 0),
            'controlled_test',
            'pending',
            max(1, min(20, $maxAttempts)),
            max(30, min(3600, (int)ConnectorDelivery::settings()['base_delay_seconds'])),
            $now,
            $hash,
            $body,
            $now,
            $now
        ));
        if ($inserted === false) return ['ok'=>false,'reason'=>'queue_insert_failed'];
        $jobId = (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . ConnectorDelivery::table() . ' WHERE delivery_key=%s LIMIT 1', $deliveryKey));
        return ['ok'=>true,'reason'=>$inserted === 0 ? 'duplicate_ignored' : 'pending','job_id'=>$jobId];
    }

    private static function certificationRoute(array $destination) {
        global $wpdb;
        $destinationId = absint($destination['id'] ?? 0);
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . DestinationRegistry::rulesTable() . " WHERE destination_id=%d AND is_active=1 AND action_name='include' ORDER BY priority DESC,id DESC",
            $destinationId
        ), ARRAY_A) ?: [];
        if (!$rules) return new \WP_Error('fieldflow_5c6_no_include_rule', 'O destino precisa de uma regra INCLUDE ativa para a certificação 5C.6.');
        foreach ($rules as $rule) {
            $pattern = DestinationRegistry::normaliseEventPattern((string)($rule['event_pattern'] ?? '*'));
            $eventName = self::eventFromPattern($pattern);
            $clientId = absint($rule['client_id'] ?? 0) ?: absint($destination['client_id'] ?? 0);
            $projectId = absint($rule['project_id'] ?? 0) ?: absint($destination['project_id'] ?? 0);
            $context = ['client_id'=>$clientId,'project_id'=>$projectId,'entity_type'=>'connector_delivery_certification','entity_id'=>0];
            $plan = DestinationRouter::routePlan($eventName, $context, wp_generate_uuid4());
            foreach ((array)($plan['destinations'] ?? []) as $candidate) {
                if ((int)($candidate['destination_id'] ?? 0) === $destinationId) return ['event_name'=>$eventName,'context'=>$context];
            }
        }
        return new \WP_Error('fieldflow_5c6_no_matching_route', 'O routing final não inclui este destino para as regras ativas.');
    }

    private static function eventFromPattern(string $pattern): string {
        if ($pattern === '' || $pattern === '*') return 'connector_delivery.certification';
        if (strpos($pattern, '*') === false) return $pattern;
        $candidate = DestinationRegistry::normaliseEventPattern(str_replace('*', 'certification', $pattern));
        return $candidate !== '' ? $candidate : 'connector_delivery.certification';
    }

    private static function brokerConfig() {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) return new \WP_Error('fieldflow_5c6_broker_host_invalid', 'O Entra Broker 5C.2 não corresponde ao host dedicado TWT.');
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_5c6_hmac_missing', 'O HMAC TWT certificado não está disponível para a 5C.6.');
        $url = 'https://' . $host . self::BROKER_PATH;
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_connector_5c6_controlled_delivery', [
            'max_timeout'=>30,
            'max_response_bytes'=>262144,
            'allow_public_ip_literals'=>false,
        ]);
        $validated = OutboundHttp::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;
        return ['url'=>$validated,'secret'=>$secret,'timeout'=>max(10,min(30,(int)($settings['timeout'] ?? 20))),'policy'=>$policy];
    }

    private static function sendToBroker(array $job, array $broker) {
        $stored = json_decode((string)($job['payload_json'] ?? ''), true);
        if (!is_array($stored)) return new \WP_Error('fieldflow_5c6_payload_invalid', 'O job 5C.6 não contém JSON válido.');
        $cert = (array)($stored['data']['certification'] ?? []);
        $behavior = sanitize_key((string)($cert['behavior'] ?? ''));
        if (!in_array($behavior, self::BEHAVIORS, true) || (string)($cert['phase'] ?? '') !== self::PHASE) {
            return new \WP_Error('fieldflow_5c6_not_synthetic', 'O Controlled Delivery aceita apenas jobs sintéticos 5C.6.');
        }
        $payload = [
            'contract'=>self::CONTRACT,
            'contract_version'=>self::CONTRACT_VERSION,
            'tenant'=>[
                'tenant_id'=>(string)$job['tenant_id'],
                'installation_id'=>(string)$job['installation_id'],
            ],
            'event'=>[
                'uuid'=>(string)$job['event_uuid'],
                'name'=>(string)$job['event_name'],
                'occurred_at'=>gmdate('c'),
            ],
            'destination'=>[
                'uuid'=>(string)$job['destination_uuid'],
                'type'=>(string)$job['connector_type'],
            ],
            'delivery'=>[
                'key'=>(string)$job['delivery_key'],
                'attempt'=>(int)$job['attempts'],
                'max_attempts'=>(int)$job['max_attempts'],
                'certification_behavior'=>$behavior,
                'certification_only'=>true,
            ],
            'data'=>[
                'certification'=>[
                    'phase'=>self::PHASE,
                    'release'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '2.2.372',
                    'probe'=>sanitize_key((string)($cert['probe'] ?? 'controlled_delivery')),
                    'marker'=>substr(sanitize_text_field((string)($cert['marker'] ?? '')), 0, 96),
                ],
            ],
        ];
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_5c6_payload_encode', 'Não foi possível codificar a certificação 5C.6.');
        $headers = AzureConnectionManager::signedHeaders($payload, $body, (string)$broker['secret']);
        if (is_wp_error($headers)) return $headers;
        $response = OutboundHttp::post((string)$broker['url'], [
            'timeout'=>(int)$broker['timeout'],
            'headers'=>array_merge(['Content-Type'=>'application/json'], $headers),
            'body'=>$body,
        ], (array)$broker['policy']);
        if (is_wp_error($response)) return $response;
        $http = (int)wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) $decoded = [];
        if ($http < 200 || $http >= 300 || empty($decoded['ok']) || empty($decoded['controlled_delivery']) || empty($decoded['certification_only']) || empty($decoded['delivery_performed'])) {
            return new \WP_Error('fieldflow_5c6_broker_rejected', 'O Broker 5C.6 rejeitou o pedido controlado.', ['http_status'=>$http]);
        }
        $receipt = is_array($decoded['receipt'] ?? null) ? $decoded['receipt'] : [];
        return [
            'ok'=>true,
            'broker_http_status'=>$http,
            'delivered'=>!empty($decoded['delivered']),
            'remote_http_status'=>absint($decoded['remote_http_status'] ?? 0),
            'receipt'=>$receipt,
            'receipt_verified'=>self::verifyReceipt($receipt, (string)$broker['secret'], $job),
        ];
    }

    private static function verifyReceipt(array $receipt, string $secret, array $job): bool {
        $signature = strtolower(trim((string)($receipt['signature'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $signature) || strlen($secret) < 32) return false;
        if ((string)($receipt['contract'] ?? '') !== self::RECEIPT_CONTRACT || (string)($receipt['contract_version'] ?? '') !== self::RECEIPT_CONTRACT_VERSION) return false;
        if ((string)($receipt['delivery_key'] ?? '') !== (string)$job['delivery_key']) return false;
        if ((string)($receipt['event_uuid'] ?? '') !== (string)$job['event_uuid']) return false;
        if ((string)($receipt['destination_uuid'] ?? '') !== (string)$job['destination_uuid']) return false;
        $canonical = implode("\n", [
            self::RECEIPT_CONTEXT,
            (string)$receipt['contract'],
            (string)$receipt['contract_version'],
            (string)$receipt['delivery_key'],
            (string)$receipt['event_uuid'],
            (string)$receipt['destination_uuid'],
            (string)absint($receipt['remote_http_status'] ?? 0),
            (string)($receipt['outcome'] ?? ''),
            (string)($receipt['receiver_request_id'] ?? ''),
            (string)($receipt['received_at'] ?? ''),
        ]);
        $expected = hash_hmac('sha256', $canonical, $secret);
        return hash_equals($expected, $signature);
    }

    private static function nextCandidate(): array {
        global $wpdb;
        $now = current_time('mysql', true);
        $staleBefore = gmdate('Y-m-d H:i:s', time() - (10 * MINUTE_IN_SECONDS));
        $wpdb->query($wpdb->prepare(
            "UPDATE " . ConnectorDelivery::table() . " SET status=IF(attempts>=max_attempts,'dead','pending'),available_at=%s,completed_at=IF(attempts>=max_attempts,%s,NULL),locked_at=NULL,lock_token=NULL,last_error_code='stale_lock_recovered',last_error_message='Um lock sintético 5C.6 foi recuperado automaticamente.',updated_at=%s WHERE execution_mode='controlled_test' AND status='running' AND locked_at IS NOT NULL AND locked_at<%s",
            $now, $now, $now, $staleBefore
        ));
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . ConnectorDelivery::table() . " WHERE execution_mode='controlled_test' AND status='pending' AND available_at<=%s ORDER BY id ASC LIMIT 1",
            $now
        ), ARRAY_A) ?: [];
    }

    private static function claimJob(int $id): array {
        global $wpdb;
        $now = current_time('mysql', true);
        $token = wp_generate_uuid4();
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . ConnectorDelivery::table() . " SET status='running',attempts=attempts+1,locked_at=%s,lock_token=%s,updated_at=%s WHERE id=%d AND execution_mode='controlled_test' AND status='pending' AND available_at<=%s",
            $now, $token, $now, $id, $now
        ));
        if ($updated !== 1) return [];
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . ConnectorDelivery::table() . ' WHERE id=%d AND lock_token=%s', $id, $token), ARRAY_A) ?: [];
    }

    private static function completeJob(array $job, int $httpCode): void {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->update(ConnectorDelivery::table(), [
            'status'=>'completed','completed_at'=>$now,'locked_at'=>null,'lock_token'=>null,'last_http_code'=>$httpCode,
            'last_error_code'=>null,'last_error_message'=>null,'updated_at'=>$now,
        ], ['id'=>(int)$job['id']]);
        IntegrationPlatform::log('connector_delivery_5c6', 'controlled_delivery', 'success', [
            'delivery_key'=>(string)$job['delivery_key'],'event'=>(string)$job['event_name'],'job_id'=>(int)$job['id'],'http_code'=>$httpCode,
        ]);
    }

    private static function failJob(array $job, int $httpCode, string $code, string $message): array {
        global $wpdb;
        $attempts = max(1, (int)($job['attempts'] ?? 1));
        $maxAttempts = max(1, (int)($job['max_attempts'] ?? 3));
        $base = max(30, min(3600, (int)($job['base_delay_seconds'] ?? 60)));
        $terminal = $attempts >= $maxAttempts;
        $delay = min(21600, $base * (2 ** max(0, $attempts - 1)));
        $now = current_time('mysql', true);
        $wpdb->update(ConnectorDelivery::table(), [
            'status'=>$terminal ? 'dead' : 'pending',
            'available_at'=>$terminal ? (string)$job['available_at'] : gmdate('Y-m-d H:i:s', time() + $delay),
            'completed_at'=>$terminal ? $now : null,
            'locked_at'=>null,
            'lock_token'=>null,
            'last_http_code'=>$httpCode > 0 ? $httpCode : null,
            'last_error_code'=>substr(sanitize_key($code), 0, 96),
            'last_error_message'=>substr(wp_strip_all_tags($message), 0, 500),
            'updated_at'=>$now,
        ], ['id'=>(int)$job['id']]);
        IntegrationPlatform::log('connector_delivery_5c6', 'controlled_delivery', $terminal ? 'dead' : 'retry', [
            'delivery_key'=>(string)$job['delivery_key'],'event'=>(string)$job['event_name'],'job_id'=>(int)$job['id'],'attempt'=>$attempts,'max_attempts'=>$maxAttempts,'http_code'=>$httpCode,
        ], $message);
        return ['terminal'=>$terminal,'delay'=>$terminal ? 0 : $delay];
    }

    private static function recordCircuitFailure(string $destinationUuid): void {
        $all = get_option(self::CIRCUIT_OPTION, []);
        if (!is_array($all)) $all = [];
        $key = strtolower(trim($destinationUuid));
        $row = is_array($all[$key] ?? null) ? $all[$key] : [];
        $failures = absint($row['consecutive_failures'] ?? 0) + 1;
        $settings = ConnectorDelivery::settings();
        $threshold = max(2, min(20, (int)$settings['circuit_failure_threshold']));
        $openUntil = $failures >= $threshold ? time() + max(60, (int)$settings['circuit_open_seconds']) : 0;
        $all[$key] = ['consecutive_failures'=>$failures,'open_until'=>$openUntil,'updated_at'=>time()];
        update_option(self::CIRCUIT_OPTION, $all, false);
    }

    private static function recordReceipt(array $receipt): void {
        $safe = [
            'delivery_key'=>sanitize_text_field((string)($receipt['delivery_key'] ?? '')),
            'event_uuid'=>sanitize_text_field((string)($receipt['event_uuid'] ?? '')),
            'destination_uuid'=>sanitize_text_field((string)($receipt['destination_uuid'] ?? '')),
            'remote_http_status'=>absint($receipt['remote_http_status'] ?? 0),
            'outcome'=>sanitize_key((string)($receipt['outcome'] ?? '')),
            'receiver_request_id'=>substr(sanitize_text_field((string)($receipt['receiver_request_id'] ?? '')), 0, 96),
            'received_at'=>sanitize_text_field((string)($receipt['received_at'] ?? '')),
            'signature'=>substr(sanitize_text_field((string)($receipt['signature'] ?? '')), 0, 64),
            'verified'=>!empty($receipt['verified']),
            'recorded_at'=>gmdate('c'),
        ];
        $rows = self::receipts(100);
        array_unshift($rows, $safe);
        update_option(self::RECEIPTS_OPTION, array_slice($rows, 0, 50), false);
    }

    private static function receiptForKey(string $deliveryKey): array {
        foreach (self::receipts(50) as $receipt) {
            if ((string)($receipt['delivery_key'] ?? '') === $deliveryKey) return $receipt;
        }
        return [];
    }

    private static function forceAvailableNow(int $jobId): void {
        global $wpdb;
        if ($jobId <= 0) return;
        $wpdb->update(ConnectorDelivery::table(), ['available_at'=>current_time('mysql', true),'updated_at'=>current_time('mysql', true)], ['id'=>$jobId]);
    }

    private static function cleanupSyntheticFailureJob(int $jobId): void {
        global $wpdb;
        if ($jobId <= 0) return;
        $wpdb->delete(ConnectorDelivery::table(), ['id'=>$jobId,'execution_mode'=>'controlled_test'], ['%d','%s']);
    }

    private static function jobByKey(string $deliveryKey): array {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . ConnectorDelivery::table() . ' WHERE delivery_key=%s LIMIT 1', $deliveryKey), ARRAY_A) ?: [];
    }

    private static function countByKey(string $deliveryKey): int {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . ConnectorDelivery::table() . ' WHERE delivery_key=%s', $deliveryKey));
    }

    private static function failureFromError(\WP_Error $error, array $checks = []): array {
        return ['ok'=>false,'checks'=>$checks,'reason'=>$error->get_error_code(),'message'=>$error->get_error_message()];
    }
}
