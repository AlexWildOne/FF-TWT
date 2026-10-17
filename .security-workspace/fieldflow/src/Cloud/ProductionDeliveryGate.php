<?php
namespace RoutesPro\Cloud;

use RoutesPro\Security\OutboundHttp;
use RoutesPro\Services\IntegrationPlatform;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5C.8 Production Delivery Gate.
 *
 * This release certifies a production-grade target and queue path while the
 * general operational dispatcher remains blocked. Only a strict metadata
 * projection is accepted. Raw operational payloads, secrets and arbitrary
 * production activation stay blocked until the 5D/5E closing gates.
 */
final class ProductionDeliveryGate {
    public const VERSION = '1.0.0';
    public const PHASE = '5C.8';
    public const OPTION = 'fieldflow_connector_delivery_5c8_gates';
    public const RECEIPTS_OPTION = 'fieldflow_connector_delivery_5c8_receipts';
    public const DISPATCH_HOOK = 'fieldflow_connector_production_gate_dispatch';
    public const PREPARE_PATH = '/api/v1/production/prepare';
    public const DELIVER_PATH = '/api/v1/production/deliver';
    public const PREPARE_CONTRACT = 'fieldflow.connector.production_gate.prepare';
    public const PREPARE_CONTRACT_VERSION = '1.0';
    public const DELIVERY_CONTRACT = 'fieldflow.connector.production_gate.delivery';
    public const DELIVERY_CONTRACT_VERSION = '1.0';
    public const RECEIPT_CONTRACT = 'fieldflow.connector.production_gate.receipt';
    public const RECEIPT_CONTRACT_VERSION = '1.0';
    public const RECEIPT_CONTEXT = 'fieldflow-production-gate-receipt-v1';
    public const TOKEN_TTL_SECONDS = 3600;
    public const GENERAL_ACTIVATION_ENABLED = false;
    public const FULL_OPERATIONAL_PAYLOAD_ENABLED = false;

    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action(self::DISPATCH_HOOK, [self::class, 'dispatchCertification'], 10, 0);
    }

    public static function health(): array {
        $settings = ConnectorDelivery::settings();
        $states = self::states();
        $prepared = 0;
        $targetCertified = 0;
        $queueCertified = 0;
        $productionReady = 0;
        foreach ($states as $state) {
            if (!empty($state['prepared']) && (int)($state['token_expires_at'] ?? 0) > time()) $prepared++;
            if (!empty($state['target_certified'])) $targetCertified++;
            if (!empty($state['queue_certified'])) $queueCertified++;
            if (!empty($state['production_ready'])) $productionReady++;
        }
        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'status'=>ConnectorDelivery::tableReady() ? 'ready' : 'attention',
            'broker_min_version'=>'1.7.0',
            'requires_5c7_certification'=>true,
            'requires_shadow_mode'=>(string)$settings['mode'] === 'shadow',
            'requires_kill_switch'=>!empty($settings['kill_switch']),
            'safe_projection_only'=>true,
            'full_operational_payload_delivery_enabled'=>self::FULL_OPERATIONAL_PAYLOAD_ENABLED,
            'general_production_activation_enabled'=>self::GENERAL_ACTIVATION_ENABLED,
            'normal_5c5_dispatcher_enabled'=>ConnectorDelivery::networkDeliveryEnabled(),
            'prepared_gates'=>$prepared,
            'target_certified_gates'=>$targetCertified,
            'queue_certified_gates'=>$queueCertified,
            'production_ready_gates'=>$productionReady,
            'token_ttl_seconds'=>self::TOKEN_TTL_SECONDS,
            'credential_lifecycle_required_next'=>true,
            'governance_recovery_required_next'=>true,
        ];
    }

    public static function localProbe(): array {
        $health = self::health();
        $checks = [
            'queue_foundation_ready'=>ConnectorDelivery::tableReady(),
            '5c5_operational_dispatcher_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
            'shadow_mode_restored'=>!empty($health['requires_shadow_mode']),
            'global_kill_switch_on'=>!empty($health['requires_kill_switch']),
            '5c7_available'=>class_exists('\\RoutesPro\\Cloud\\SafeLivePilot'),
            'production_gate_dispatcher_loaded'=>has_action(self::DISPATCH_HOOK, [self::class, 'dispatchCertification']) !== false,
            'safe_projection_only'=>!empty($health['safe_projection_only']),
            'full_payload_delivery_blocked'=>empty($health['full_operational_payload_delivery_enabled']),
            'general_activation_blocked'=>empty($health['general_production_activation_enabled']),
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$health];
    }

    public static function probeBroker() {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) {
            return new \WP_Error('fieldflow_5c8_broker_host_invalid', 'O Entra Broker configurado não corresponde ao host dedicado TWT.');
        }
        $url = 'https://' . $host . '/api/v1/health';
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_connector_5c8_broker_health', [
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
            && version_compare((string)($body['version'] ?? '0.0.0'), '1.7.0', '>=')
            && !empty($body['production_delivery_gate_foundation'])
            && !empty($body['production_delivery_gate_dynamic_target_guarded'])
            && !empty($body['production_delivery_gate_policy_bound'])
            && !empty($body['production_delivery_gate_safe_projection_only'])
            && !empty($body['production_delivery_gate_sink_available'])
            && !empty($body['production_delivery_gate_sink_accepts_5c7_prerequisite'])
            && !empty($body['operational_payload_delivery_blocked'])
            && empty($body['production_general_activation_enabled'])
            && empty($body['delivery_enabled']);
        return [
            'ok'=>$ok,
            'http_code'=>$http,
            'version'=>sanitize_text_field((string)($body['version'] ?? '')),
            'dynamic_target_guarded'=>!empty($body['production_delivery_gate_dynamic_target_guarded']),
            'policy_bound'=>!empty($body['production_delivery_gate_policy_bound']),
            'safe_projection_only'=>!empty($body['production_delivery_gate_safe_projection_only']),
            'sink_available'=>!empty($body['production_delivery_gate_sink_available']),
            'sink_accepts_5c7_prerequisite'=>!empty($body['production_delivery_gate_sink_accepts_5c7_prerequisite']),
            'full_payload_blocked'=>!empty($body['operational_payload_delivery_blocked']),
            'general_activation_enabled'=>!empty($body['production_general_activation_enabled']),
            'delivery_enabled'=>!empty($body['delivery_enabled']),
            'hmac_runtime_ready'=>!empty($body['hmac_runtime_ready']),
        ];
    }

    public static function eligibleDestinations(): array {
        $out = [];
        foreach (SafeLivePilot::eligibleDestinations() as $destination) {
            $cert = SafeLivePilot::certificationState((int)$destination['id']);
            if (empty($cert['synthetic_certified']) || empty($cert['one_shot_certified'])) continue;
            $out[] = $destination;
        }
        return $out;
    }

    public static function ensureTwtProductionDestination() {
        $endpoint = 'https://' . EntraCrossTenantFoundation::BROKER_HOST . '/api/v1/production-sink';
        $destinationId = 0;
        foreach (DestinationRegistry::listDestinations() as $destination) {
            if ((string)($destination['endpoint_ref'] ?? '') === $endpoint && (string)($destination['type_key'] ?? '') === 'webhook') {
                $destinationId = (int)$destination['id'];
                if (empty($destination['is_active'])) DestinationRegistry::setDestinationActive($destinationId, true);
                break;
            }
        }
        if ($destinationId <= 0) {
            $created = DestinationRegistry::createDestination([
                'name'=>'TWT Production Gate Receiver',
                'type_key'=>'webhook',
                'client_id'=>0,
                'project_id'=>0,
                'mode'=>'mirror',
                'endpoint_ref'=>$endpoint,
                'secret_ref'=>'',
                'is_active'=>1,
                'auth_strategy'=>'none',
                'retry_policy'=>['max_attempts'=>2,'base_delay_seconds'=>60],
            ]);
            if (is_wp_error($created)) return $created;
            $destinationId = (int)$created;
        }

        // Bootstrap the 5C.7 prerequisite using only the local synthetic path.
        // No operational payload is created or forwarded.
        $pilot = SafeLivePilot::certificationState($destinationId);
        if (empty($pilot['synthetic_certified']) || empty($pilot['one_shot_certified'])) {
            $prepared = SafeLivePilot::prepareTarget($destinationId);
            if (empty($prepared['ok'])) return new \WP_Error('fieldflow_5c8_twt_5c7_prepare_failed', (string)($prepared['message'] ?? 'Falhou a preparação automática 5C.7 do receiver TWT 5C.8.'));
            $synthetic = SafeLivePilot::certifySynthetic($destinationId);
            if (empty($synthetic['ok'])) return new \WP_Error('fieldflow_5c8_twt_5c7_synthetic_failed', (string)($synthetic['message'] ?? 'Falhou a certificação sintética 5C.7 do receiver TWT 5C.8.'));
            $oneShot = SafeLivePilot::certifyOneShot($destinationId);
            if (empty($oneShot['ok'])) return new \WP_Error('fieldflow_5c8_twt_5c7_one_shot_failed', (string)($oneShot['message'] ?? 'Falhou a certificação one-shot 5C.7 do receiver TWT 5C.8.'));
        }
        return $destinationId;
    }

    public static function prepareGate(int $destinationId, string $eventPattern, int $maxPerHour = 10): array {
        $preflight = self::preflight($destinationId, false);
        if (empty($preflight['ok'])) return $preflight;
        $destination = $preflight['destination'];
        $eventPattern = self::normaliseEventPattern($eventPattern);
        if ($eventPattern === '' || strpos($eventPattern, '*') !== false) {
            return ['ok'=>false,'checks'=>$preflight['checks'],'reason'=>'production_event_pattern_must_be_explicit','message'=>'A 5C.8 exige um evento explícito, por exemplo submission.created.'];
        }
        $maxPerHour = max(1, min(60, $maxPerHour));
        $profile = ConnectorFoundation::profileFromDestination($destination);
        $auth = sanitize_key((string)($profile['auth_strategy'] ?? 'none'));
        if ($auth === '') $auth = 'none';
        if ($auth !== 'none') {
            return ['ok'=>false,'checks'=>$preflight['checks'],'reason'=>'credential_lifecycle_required','message'=>'Este destino usa autenticação. A ativação de credenciais reais fica reservada à Fase 5D. Para certificar a 5C.8 usa o receiver TWT sem autenticação.'];
        }
        $endpoint = trim((string)$destination['endpoint_ref']);
        $broker = self::brokerConfig(self::PREPARE_PATH, 'fieldflow_connector_5c8_prepare');
        if (is_wp_error($broker)) return self::failure($broker, $preflight['checks']);
        $eventUuid = wp_generate_uuid4();
        $policy = self::policy($eventPattern, $maxPerHour);
        $payload = [
            'contract'=>self::PREPARE_CONTRACT,
            'contract_version'=>self::PREPARE_CONTRACT_VERSION,
            'tenant'=>TenantIdentity::snapshot(),
            'event'=>['uuid'=>$eventUuid,'name'=>'connector.production_gate.prepare','occurred_at'=>gmdate('c')],
            'destination'=>['uuid'=>(string)$destination['destination_uuid'],'type'=>(string)$destination['type_key']],
            'target'=>['url'=>$endpoint],
            'gate'=>[
                'phase'=>self::PHASE,
                'ttl_seconds'=>self::TOKEN_TTL_SECONDS,
                'safe_projection_only'=>true,
                'full_operational_payload_allowed'=>false,
                'general_activation_requested'=>false,
                'auth_strategy'=>'none',
            ],
            'policy'=>$policy,
            'data'=>['safe_projection'=>['event_name'=>'connector.production_gate.prepare','event_uuid'=>$eventUuid]],
        ];
        $response = self::postBroker($broker, $payload);
        if (is_wp_error($response)) return self::failure($response, $preflight['checks']);
        $body = (array)$response['body'];
        $token = sanitize_text_field((string)($body['production_token'] ?? ''));
        $endpointSha = strtolower(sanitize_text_field((string)($body['endpoint_sha256'] ?? '')));
        $policySha = strtolower(sanitize_text_field((string)($body['policy_sha256'] ?? '')));
        $targetUrl = esc_url_raw((string)($body['normalized_target_url'] ?? $endpoint), ['https']);
        $expires = absint($body['expires_at_unix'] ?? 0);
        $checks = array_merge($preflight['checks'], [
            'broker_prepare_http_200'=>(int)$response['http_code'] === 200,
            'production_prepare_accepted'=>!empty($body['production_delivery_gate']) && (string)($body['phase'] ?? '') === self::PHASE,
            'production_token_present'=>$token !== '',
            'endpoint_sha256_present'=>preg_match('/^[a-f0-9]{64}$/', $endpointSha) === 1,
            'policy_sha256_present'=>preg_match('/^[a-f0-9]{64}$/', $policySha) === 1,
            'token_expiry_future'=>$expires > time(),
            'safe_projection_only'=>!empty($body['safe_projection_only']),
            'full_payload_blocked'=>empty($body['full_operational_payload_allowed']),
            'general_activation_not_opened'=>empty($body['general_activation_enabled']),
        ]);
        $ok = !in_array(false, $checks, true);
        $state = [
            'destination_id'=>(int)$destination['id'],
            'destination_uuid'=>(string)$destination['destination_uuid'],
            'target_url'=>$targetUrl,
            'endpoint_host'=>(string)(wp_parse_url($targetUrl, PHP_URL_HOST) ?: ''),
            'endpoint_sha256'=>$endpointSha,
            'policy'=>$policy,
            'policy_sha256'=>$policySha,
            'production_token'=>$token,
            'token_expires_at'=>$expires,
            'prepared'=>$ok,
            'prepared_at'=>$ok ? time() : 0,
            'target_certified'=>false,
            'queue_certified'=>false,
            'production_ready'=>false,
            'general_activation_enabled'=>false,
            'last_ok'=>$ok,
            'last_http_code'=>(int)$response['http_code'],
            'last_error'=>$ok ? '' : 'production_prepare_failed',
        ];
        self::saveState((string)$destination['destination_uuid'], $state);
        return [
            'ok'=>$ok,
            'checks'=>$checks,
            'reason'=>$ok ? 'production_gate_prepared' : 'production_gate_prepare_failed',
            'destination_id'=>(int)$destination['id'],
            'destination_name'=>(string)$destination['name'],
            'endpoint_host'=>$state['endpoint_host'],
            'event_pattern'=>$eventPattern,
            'max_per_hour'=>$maxPerHour,
            'expires_at_unix'=>$expires,
        ];
    }

    public static function certifyTarget(int $destinationId): array {
        $preflight = self::preflight($destinationId, true);
        if (empty($preflight['ok'])) return $preflight;
        $destination = $preflight['destination'];
        $state = $preflight['state'];
        $eventUuid = wp_generate_uuid4();
        $projection = self::safeProjection((string)($state['policy']['event_pattern'] ?? 'submission.created'), $eventUuid, [
            'client_id'=>absint($destination['client_id'] ?? 0),
            'project_id'=>absint($destination['project_id'] ?? 0),
            'entity_type'=>'production_gate_certification',
            'entity_id'=>0,
        ], true);
        $sent = self::sendGate($destination, $state, 'synthetic', $projection, 'target_certification');
        if (is_wp_error($sent)) return self::failure($sent, $preflight['checks']);
        $receipt = (array)($sent['receipt'] ?? []);
        $checks = array_merge($preflight['checks'], [
            'broker_http_200'=>(int)($sent['broker_http_status'] ?? 0) === 200,
            'delivery_performed'=>!empty($sent['delivery_performed']),
            'remote_http_2xx'=>(int)($sent['remote_http_status'] ?? 0) >= 200 && (int)($sent['remote_http_status'] ?? 0) < 300,
            'receipt_signature_verified'=>!empty($sent['receipt_verified']),
            'receipt_endpoint_bound'=>(string)($receipt['endpoint_sha256'] ?? '') === (string)$state['endpoint_sha256'],
            'receipt_policy_bound'=>(string)($receipt['policy_sha256'] ?? '') === (string)$state['policy_sha256'],
            'safe_projection_only'=>!empty($sent['safe_projection_only']),
            'full_payload_blocked'=>empty($sent['full_operational_payload_allowed']),
            'general_activation_still_blocked'=>empty($sent['general_activation_enabled']),
            'normal_dispatcher_still_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ]);
        $ok = !in_array(false, $checks, true);
        $state['target_certified'] = $ok;
        $state['target_certified_at'] = $ok ? time() : 0;
        $state['last_ok'] = $ok;
        $state['last_http_code'] = (int)($sent['remote_http_status'] ?? 0);
        $state['last_receipt'] = self::safeReceipt($receipt, !empty($sent['receipt_verified']));
        $state['last_error'] = $ok ? '' : 'production_target_certification_failed';
        self::saveState((string)$destination['destination_uuid'], $state);
        return [
            'ok'=>$ok,
            'checks'=>$checks,
            'reason'=>$ok ? 'production_target_certified' : 'production_target_certification_failed',
            'destination_id'=>$destinationId,
            'destination_name'=>(string)$destination['name'],
            'remote_http_status'=>(int)($sent['remote_http_status'] ?? 0),
            'receipt'=>$state['last_receipt'],
        ];
    }

    public static function certifyQueuedDispatcher(int $destinationId): array {
        global $wpdb;
        $preflight = self::preflight($destinationId, true);
        if (empty($preflight['ok'])) return $preflight;
        $destination = $preflight['destination'];
        $state = $preflight['state'];
        if (empty($state['target_certified'])) {
            return ['ok'=>false,'checks'=>$preflight['checks'],'reason'=>'production_target_not_certified','message'=>'Certifica primeiro o target 5C.8.'];
        }
        $eventName = (string)($state['policy']['event_pattern'] ?? 'submission.created');
        $eventUuid = wp_generate_uuid4();
        $context = [
            'client_id'=>absint($destination['client_id'] ?? 0),
            'project_id'=>absint($destination['project_id'] ?? 0),
            'entity_type'=>'production_gate_queue_certification',
            'entity_id'=>0,
        ];
        $projection = self::safeProjection($eventName, $eventUuid, $context, true);
        $deliveryKey = DestinationRouter::deliveryKey($eventUuid, (string)$destination['destination_uuid']);
        $created = self::insertCertificationJob($destination, $state, $projection, $deliveryKey);
        if (empty($created['ok'])) return ['ok'=>false,'checks'=>$preflight['checks'],'reason'=>(string)($created['reason'] ?? 'queue_insert_failed'),'message'=>'Não foi possível criar o job 5C.8.'];
        $duplicate = self::insertCertificationJob($destination, $state, $projection, $deliveryKey);
        $summary = self::dispatchCertification(1);
        $row = self::jobByKey($deliveryKey);
        $receipt = self::receiptForKey($deliveryKey);
        $checks = array_merge($preflight['checks'], [
            'target_certified'=>!empty($state['target_certified']),
            'job_created'=>!empty($created['job_id']),
            'duplicate_suppressed'=>!empty($duplicate['ok']) && (string)($duplicate['reason'] ?? '') === 'duplicate_ignored',
            'one_row_per_delivery_key'=>self::countByKey($deliveryKey) === 1,
            'dispatcher_claimed'=>(int)($summary['claimed'] ?? 0) === 1,
            'broker_request_ok'=>(int)($summary['broker_ok'] ?? 0) === 1,
            'status_completed'=>(string)($row['status'] ?? '') === 'completed',
            'attempts_one'=>(int)($row['attempts'] ?? 0) === 1,
            'remote_http_200'=>(int)($row['last_http_code'] ?? 0) === 200,
            'signed_receipt_verified'=>!empty($receipt['verified']),
            'safe_projection_persisted'=>self::storedJobContainsOnlySafeProjection($row),
            'general_activation_still_blocked'=>!self::GENERAL_ACTIVATION_ENABLED,
            'normal_dispatcher_still_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ]);
        $ok = !in_array(false, $checks, true);
        $state = self::stateForDestination((string)$destination['destination_uuid']);
        $state['queue_certified'] = $ok;
        $state['queue_certified_at'] = $ok ? time() : 0;
        $state['production_ready'] = $ok && !empty($state['target_certified']);
        $state['production_ready_at'] = $state['production_ready'] ? time() : 0;
        $state['general_activation_enabled'] = false;
        $state['last_ok'] = $ok;
        $state['last_http_code'] = (int)($row['last_http_code'] ?? 0);
        $state['last_error'] = $ok ? '' : 'production_queue_certification_failed';
        self::saveState((string)$destination['destination_uuid'], $state);
        return [
            'ok'=>$ok,
            'checks'=>$checks,
            'reason'=>$ok ? 'production_delivery_gate_certified' : 'production_delivery_gate_failed',
            'destination_id'=>$destinationId,
            'destination_name'=>(string)$destination['name'],
            'job_id'=>(int)($row['id'] ?? 0),
            'delivery_key'=>$deliveryKey,
            'status'=>(string)($row['status'] ?? ''),
            'summary'=>$summary,
            'receipt'=>$receipt,
            'production_ready'=>!empty($state['production_ready']),
            'general_activation_enabled'=>false,
        ];
    }

    public static function resetGate(int $destinationId): array {
        $destination = DestinationRegistry::getDestination($destinationId);
        if (!$destination) return ['ok'=>false,'reason'=>'destination_not_found'];
        $states = self::states();
        unset($states[(string)$destination['destination_uuid']]);
        update_option(self::OPTION, $states, false);
        return ['ok'=>true,'reason'=>'production_gate_reset','destination_name'=>(string)$destination['name']];
    }

    public static function dispatchCertification(int $limit = 10): array {
        global $wpdb;
        $limit = max(1, min(20, $limit));
        $summary = ['ok'=>true,'claimed'=>0,'broker_ok'=>0,'delivered'=>0,'retried'=>0,'dead'=>0,'blocked'=>false,'reason'=>''];
        if (!ConnectorDelivery::tableReady()) return $summary + ['ok'=>false,'blocked'=>true,'reason'=>'queue_not_ready'];
        $broker = self::brokerConfig(self::DELIVER_PATH, 'fieldflow_connector_5c8_delivery');
        if (is_wp_error($broker)) return $summary + ['ok'=>false,'blocked'=>true,'reason'=>$broker->get_error_code()];

        for ($i=0; $i<$limit; $i++) {
            $candidate = self::nextCandidate();
            if (!$candidate) break;
            $job = self::claimJob((int)$candidate['id']);
            if (!$job) continue;
            $summary['claimed']++;
            $response = self::sendJobToBroker($job, $broker);
            if (is_wp_error($response)) {
                $state = self::failJob($job, 0, $response->get_error_code(), $response->get_error_message());
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }
            $summary['broker_ok']++;
            $remoteHttp = (int)($response['remote_http_status'] ?? 0);
            $receipt = (array)($response['receipt'] ?? []);
            if (empty($response['receipt_verified'])) {
                $state = self::failJob($job, $remoteHttp, 'receipt_signature_invalid', 'O receipt 5C.8 não passou a validação HMAC.');
                $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
                continue;
            }
            self::recordReceipt($receipt + ['verified'=>true]);
            if (!empty($response['delivered']) && $remoteHttp >= 200 && $remoteHttp < 300) {
                self::completeJob($job, $remoteHttp);
                $summary['delivered']++;
                continue;
            }
            $state = self::failJob($job, $remoteHttp, 'remote_retryable_failure', 'O target 5C.8 devolveu HTTP ' . $remoteHttp . '.');
            $summary[!empty($state['terminal']) ? 'dead' : 'retried']++;
        }
        return $summary;
    }

    public static function summary(): array {
        $rows = [];
        foreach (self::eligibleDestinations() as $destination) {
            $state = self::stateForDestination((string)$destination['destination_uuid']);
            $pilot = SafeLivePilot::certificationState((int)$destination['id']);
            $rows[] = [
                'destination_id'=>(int)$destination['id'],
                'destination_uuid'=>(string)$destination['destination_uuid'],
                'name'=>(string)$destination['name'],
                'type_key'=>(string)$destination['type_key'],
                'endpoint_host'=>(string)($state['endpoint_host'] ?? wp_parse_url((string)$destination['endpoint_ref'], PHP_URL_HOST)),
                'pilot_certified'=>!empty($pilot['synthetic_certified']) && !empty($pilot['one_shot_certified']),
                'prepared'=>!empty($state['prepared']) && (int)($state['token_expires_at'] ?? 0) > time(),
                'target_certified'=>!empty($state['target_certified']),
                'queue_certified'=>!empty($state['queue_certified']),
                'production_ready'=>!empty($state['production_ready']),
                'event_pattern'=>(string)($state['policy']['event_pattern'] ?? ''),
                'max_per_hour'=>absint($state['policy']['max_per_hour'] ?? 0),
                'general_activation_enabled'=>false,
                'last_ok'=>!empty($state['last_ok']),
                'last_http_code'=>absint($state['last_http_code'] ?? 0),
                'last_error'=>(string)($state['last_error'] ?? ''),
            ];
        }
        return $rows;
    }

    private static function preflight(int $destinationId, bool $requirePrepared): array {
        $settings = ConnectorDelivery::settings();
        $destination = DestinationRegistry::getDestination($destinationId);
        $state = $destination ? self::stateForDestination((string)$destination['destination_uuid']) : [];
        $pilot = $destination ? SafeLivePilot::certificationState($destinationId) : [];
        $endpoint = $destination ? trim((string)($destination['endpoint_ref'] ?? '')) : '';
        $checks = [
            'table_ready'=>ConnectorDelivery::tableReady(),
            'mode_shadow'=>(string)$settings['mode'] === 'shadow',
            'kill_switch_on'=>!empty($settings['kill_switch']),
            'normal_dispatcher_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
            'destination_exists'=>!empty($destination),
            'destination_active'=>!empty($destination['is_active']),
            'destination_supported'=>$destination ? ConnectorDelivery::isSupportedType((string)$destination['type_key']) : false,
            'https_target'=>stripos($endpoint, 'https://') === 0,
            '5c7_synthetic_certified'=>!empty($pilot['synthetic_certified']),
            '5c7_one_shot_certified'=>!empty($pilot['one_shot_certified']),
            'prepared_not_required_or_ready'=>!$requirePrepared || (!empty($state['prepared']) && (int)($state['token_expires_at'] ?? 0) > time() && !empty($state['production_token'])),
            'full_operational_payload_delivery_blocked'=>!self::FULL_OPERATIONAL_PAYLOAD_ENABLED,
            'general_activation_blocked'=>!self::GENERAL_ACTIVATION_ENABLED,
        ];
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'reason'=>!in_array(false, $checks, true) ? 'preflight_ok' : 'production_gate_preflight_failed',
            'message'=>!in_array(false, $checks, true) ? '' : 'A 5C.8 exige SHADOW + Kill switch ON, destino HTTPS ativo e a 5C.7 completamente certificada.',
            'destination'=>$destination,
            'state'=>$state,
            'pilot'=>$pilot,
        ];
    }

    private static function policy(string $eventPattern, int $maxPerHour): array {
        return [
            'event_pattern'=>$eventPattern,
            'payload_profile'=>'metadata_v1',
            'max_per_hour'=>max(1, min(60, $maxPerHour)),
            'retry_profile'=>'bounded_v1',
            'receipt_required'=>true,
            'safe_projection_only'=>true,
        ];
    }

    private static function normaliseEventPattern(string $pattern): string {
        $pattern = DestinationRegistry::normaliseEventPattern($pattern);
        return substr($pattern, 0, 96);
    }

    private static function safeProjection(string $eventName, string $eventUuid, array $context, bool $synthetic): array {
        $out = [
            'event_name'=>self::normaliseEventPattern($eventName),
            'event_uuid'=>$eventUuid,
            'occurred_at'=>gmdate('c'),
            'synthetic_marker'=>$synthetic ? 'fieldflow-5c8-production-gate' : '',
        ];
        foreach (['entity_type','entity_id','client_id','project_id','route_id','route_stop_id','location_id','submission_id','briefing_id'] as $key) {
            if (!array_key_exists($key, $context)) continue;
            $out[$key] = $key === 'entity_type' ? sanitize_key((string)$context[$key]) : absint($context[$key]);
        }
        return $out;
    }

    private static function insertCertificationJob(array $destination, array $state, array $projection, string $deliveryKey): array {
        global $wpdb;
        $now = current_time('mysql', true);
        $identity = TenantIdentity::snapshot();
        $payload = [
            'contract'=>'fieldflow.connector.production_gate.queue.v1',
            'contract_version'=>'1.0',
            'tenant'=>$identity,
            'event'=>[
                'uuid'=>(string)$projection['event_uuid'],
                'name'=>(string)$projection['event_name'],
                'occurred_at'=>(string)$projection['occurred_at'],
            ],
            'destination'=>[
                'uuid'=>(string)$destination['destination_uuid'],
                'type'=>(string)$destination['type_key'],
            ],
            'delivery'=>[
                'key'=>$deliveryKey,
                'execution_mode'=>'production_gate_test',
                'certification_only'=>true,
                'general_activation_enabled'=>false,
            ],
            'target'=>[
                'url'=>(string)$state['target_url'],
                'endpoint_sha256'=>(string)$state['endpoint_sha256'],
            ],
            'gate'=>[
                'phase'=>self::PHASE,
                'token'=>(string)$state['production_token'],
                'policy_sha256'=>(string)$state['policy_sha256'],
                'safe_projection_only'=>true,
                'full_operational_payload_allowed'=>false,
            ],
            'policy'=>(array)$state['policy'],
            'data'=>['safe_projection'=>$projection],
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
            (string)$projection['event_uuid'],
            (string)$projection['event_name'],
            absint($destination['client_id'] ?? 0),
            absint($destination['project_id'] ?? 0),
            'production_gate_test',
            'pending',
            2,
            60,
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

    private static function sendGate(array $destination, array $state, string $kind, array $projection, string $probe) {
        $broker = self::brokerConfig(self::DELIVER_PATH, 'fieldflow_connector_5c8_delivery');
        if (is_wp_error($broker)) return $broker;
        $eventUuid = (string)($projection['event_uuid'] ?? '');
        if (!self::validEventUuid($eventUuid)) return new \WP_Error('fieldflow_5c8_event_uuid_invalid', 'O event UUID 5C.8 não é válido.');
        $deliveryKey = DestinationRouter::deliveryKey($eventUuid, (string)$destination['destination_uuid']);
        $payload = [
            'contract'=>self::DELIVERY_CONTRACT,
            'contract_version'=>self::DELIVERY_CONTRACT_VERSION,
            'tenant'=>TenantIdentity::snapshot(),
            'event'=>['uuid'=>$eventUuid,'name'=>(string)$projection['event_name'],'occurred_at'=>(string)$projection['occurred_at']],
            'destination'=>['uuid'=>(string)$destination['destination_uuid'],'type'=>(string)$destination['type_key']],
            'delivery'=>['key'=>$deliveryKey,'kind'=>$kind,'certification_only'=>true,'probe'=>sanitize_key($probe)],
            'target'=>['url'=>(string)$state['target_url']],
            'gate'=>[
                'phase'=>self::PHASE,
                'token'=>(string)$state['production_token'],
                'policy_sha256'=>(string)$state['policy_sha256'],
                'safe_projection_only'=>true,
                'full_operational_payload_allowed'=>false,
                'general_activation_requested'=>false,
            ],
            'policy'=>(array)$state['policy'],
            'data'=>['safe_projection'=>$projection],
        ];
        $response = self::postBroker($broker, $payload);
        if (is_wp_error($response)) return $response;
        $body = (array)$response['body'];
        if (empty($body['production_delivery_gate']) || (string)($body['phase'] ?? '') !== self::PHASE || empty($body['delivery_performed'])) {
            return new \WP_Error('fieldflow_5c8_broker_contract_invalid', 'O Broker respondeu sem o contrato Production Delivery Gate esperado.');
        }
        $receipt = is_array($body['receipt'] ?? null) ? $body['receipt'] : [];
        return [
            'ok'=>true,
            'broker_http_status'=>(int)$response['http_code'],
            'delivery_performed'=>!empty($body['delivery_performed']),
            'delivered'=>!empty($body['delivered']),
            'remote_http_status'=>absint($body['remote_http_status'] ?? 0),
            'safe_projection_only'=>!empty($body['safe_projection_only']),
            'full_operational_payload_allowed'=>!empty($body['full_operational_payload_allowed']),
            'general_activation_enabled'=>!empty($body['general_activation_enabled']),
            'receipt'=>$receipt,
            'receipt_verified'=>self::verifyReceipt($receipt, (string)$broker['secret'], $deliveryKey, $eventUuid, (string)$destination['destination_uuid'], (string)$state['endpoint_sha256'], (string)$state['policy_sha256']),
        ];
    }

    private static function sendJobToBroker(array $job, array $broker) {
        $stored = json_decode((string)($job['payload_json'] ?? ''), true);
        if (!is_array($stored)) return new \WP_Error('fieldflow_5c8_job_payload_invalid', 'O job 5C.8 não contém JSON válido.');
        if ((string)($job['execution_mode'] ?? '') !== 'production_gate_test' || empty($stored['delivery']['certification_only'])) {
            return new \WP_Error('fieldflow_5c8_not_certification_job', 'A 2.2.373 aceita apenas jobs de certificação 5C.8.');
        }
        $destination = DestinationRegistry::getDestination(absint($job['destination_id'] ?? 0));
        if (!$destination) return new \WP_Error('fieldflow_5c8_destination_missing', 'O destino do job 5C.8 já não existe.');
        $state = self::stateForDestination((string)$job['destination_uuid']);
        if (empty($state['prepared']) || (int)($state['token_expires_at'] ?? 0) <= time()) return new \WP_Error('fieldflow_5c8_gate_expired', 'O token 5C.8 expirou. Prepara novamente o gate.');
        $projection = (array)($stored['data']['safe_projection'] ?? []);
        $payload = [
            'contract'=>self::DELIVERY_CONTRACT,
            'contract_version'=>self::DELIVERY_CONTRACT_VERSION,
            'tenant'=>['tenant_id'=>(string)$job['tenant_id'],'installation_id'=>(string)$job['installation_id']],
            'event'=>['uuid'=>(string)$job['event_uuid'],'name'=>(string)$job['event_name'],'occurred_at'=>(string)($stored['event']['occurred_at'] ?? gmdate('c'))],
            'destination'=>['uuid'=>(string)$job['destination_uuid'],'type'=>(string)$job['connector_type']],
            'delivery'=>['key'=>(string)$job['delivery_key'],'kind'=>'synthetic','certification_only'=>true,'probe'=>'queued_dispatcher'],
            'target'=>['url'=>(string)$state['target_url']],
            'gate'=>[
                'phase'=>self::PHASE,
                'token'=>(string)$state['production_token'],
                'policy_sha256'=>(string)$state['policy_sha256'],
                'safe_projection_only'=>true,
                'full_operational_payload_allowed'=>false,
                'general_activation_requested'=>false,
            ],
            'policy'=>(array)$state['policy'],
            'data'=>['safe_projection'=>$projection],
        ];
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_5c8_payload_encode', 'Não foi possível codificar a certificação 5C.8.');
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
        if ($http < 200 || $http >= 300 || empty($decoded['ok']) || empty($decoded['production_delivery_gate']) || empty($decoded['delivery_performed'])) {
            return new \WP_Error('fieldflow_5c8_broker_rejected', 'O Broker 5C.8 rejeitou o job de certificação.', ['http_status'=>$http,'broker_error'=>sanitize_key((string)($decoded['error'] ?? ''))]);
        }
        $receipt = is_array($decoded['receipt'] ?? null) ? $decoded['receipt'] : [];
        return [
            'ok'=>true,
            'delivered'=>!empty($decoded['delivered']),
            'remote_http_status'=>absint($decoded['remote_http_status'] ?? 0),
            'receipt'=>$receipt,
            'receipt_verified'=>self::verifyReceipt($receipt, (string)$broker['secret'], (string)$job['delivery_key'], (string)$job['event_uuid'], (string)$job['destination_uuid'], (string)$state['endpoint_sha256'], (string)$state['policy_sha256']),
        ];
    }

    private static function brokerConfig(string $path, string $purpose) {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) return new \WP_Error('fieldflow_5c8_broker_host_invalid', 'O Entra Broker 5C.8 não corresponde ao host dedicado TWT.');
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_5c8_hmac_missing', 'O HMAC TWT certificado não está disponível para a 5C.8.');
        $url = 'https://' . $host . $path;
        $policy = OutboundHttp::configuredPolicy($url, $purpose, [
            'max_timeout'=>30,
            'max_response_bytes'=>262144,
            'allow_public_ip_literals'=>false,
        ]);
        $validated = OutboundHttp::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;
        return ['url'=>$validated,'secret'=>$secret,'timeout'=>20,'policy'=>$policy];
    }

    private static function postBroker(array $broker, array $payload) {
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_5c8_payload_encode', 'Não foi possível codificar o pedido 5C.8.');
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
        if ($http < 200 || $http >= 300 || empty($decoded['ok'])) {
            return new \WP_Error('fieldflow_5c8_broker_rejected', 'O Broker 5C.8 rejeitou o pedido.', ['http_status'=>$http,'broker_error'=>sanitize_key((string)($decoded['error'] ?? ''))]);
        }
        return ['http_code'=>$http,'body'=>$decoded];
    }

    private static function verifyReceipt(array $receipt, string $secret, string $deliveryKey, string $eventUuid, string $destinationUuid, string $endpointSha, string $policySha): bool {
        $signature = strtolower(trim((string)($receipt['signature'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $signature) || strlen($secret) < 32) return false;
        if ((string)($receipt['contract'] ?? '') !== self::RECEIPT_CONTRACT || (string)($receipt['contract_version'] ?? '') !== self::RECEIPT_CONTRACT_VERSION) return false;
        if ((string)($receipt['delivery_key'] ?? '') !== $deliveryKey) return false;
        if ((string)($receipt['event_uuid'] ?? '') !== $eventUuid) return false;
        if ((string)($receipt['destination_uuid'] ?? '') !== $destinationUuid) return false;
        if ((string)($receipt['endpoint_sha256'] ?? '') !== $endpointSha) return false;
        if ((string)($receipt['policy_sha256'] ?? '') !== $policySha) return false;
        $canonical = implode("\n", [
            self::RECEIPT_CONTEXT,
            (string)$receipt['contract'],
            (string)$receipt['contract_version'],
            (string)$receipt['delivery_key'],
            (string)$receipt['event_uuid'],
            (string)$receipt['destination_uuid'],
            (string)$receipt['endpoint_sha256'],
            (string)$receipt['policy_sha256'],
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
            "UPDATE " . ConnectorDelivery::table() . " SET status=IF(attempts>=max_attempts,'dead','pending'),available_at=%s,completed_at=IF(attempts>=max_attempts,%s,NULL),locked_at=NULL,lock_token=NULL,last_error_code='stale_lock_recovered',last_error_message='Um lock de certificação 5C.8 foi recuperado.',updated_at=%s WHERE execution_mode='production_gate_test' AND status='running' AND locked_at IS NOT NULL AND locked_at<%s",
            $now, $now, $now, $staleBefore
        ));
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . ConnectorDelivery::table() . " WHERE execution_mode='production_gate_test' AND status='pending' AND available_at<=%s ORDER BY id ASC LIMIT 1",
            $now
        ), ARRAY_A) ?: [];
    }

    private static function claimJob(int $id): array {
        global $wpdb;
        $now = current_time('mysql', true);
        $token = wp_generate_uuid4();
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . ConnectorDelivery::table() . " SET status='running',attempts=attempts+1,locked_at=%s,lock_token=%s,updated_at=%s WHERE id=%d AND execution_mode='production_gate_test' AND status='pending' AND available_at<=%s",
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
        IntegrationPlatform::log('connector_delivery_5c8', 'production_gate', 'success', [
            'delivery_key'=>(string)$job['delivery_key'],'event'=>(string)$job['event_name'],'job_id'=>(int)$job['id'],'http_code'=>$httpCode,
        ]);
    }

    private static function failJob(array $job, int $httpCode, string $code, string $message): array {
        global $wpdb;
        $attempts = max(1, (int)($job['attempts'] ?? 1));
        $maxAttempts = max(1, (int)($job['max_attempts'] ?? 2));
        $terminal = $attempts >= $maxAttempts;
        $delay = min(300, 60 * (2 ** max(0, $attempts - 1)));
        $now = current_time('mysql', true);
        $wpdb->update(ConnectorDelivery::table(), [
            'status'=>$terminal ? 'dead' : 'pending',
            'available_at'=>$terminal ? (string)$job['available_at'] : gmdate('Y-m-d H:i:s', time() + $delay),
            'completed_at'=>$terminal ? $now : null,
            'locked_at'=>null,'lock_token'=>null,
            'last_http_code'=>$httpCode > 0 ? $httpCode : null,
            'last_error_code'=>substr(sanitize_key($code), 0, 96),
            'last_error_message'=>substr(wp_strip_all_tags($message), 0, 500),
            'updated_at'=>$now,
        ], ['id'=>(int)$job['id']]);
        return ['terminal'=>$terminal,'delay'=>$terminal ? 0 : $delay];
    }

    private static function storedJobContainsOnlySafeProjection(array $row): bool {
        $decoded = json_decode((string)($row['payload_json'] ?? ''), true);
        if (!is_array($decoded)) return false;
        $data = (array)($decoded['data'] ?? []);
        if (array_keys($data) !== ['safe_projection']) return false;
        $projection = (array)($data['safe_projection'] ?? []);
        $forbidden = ['answers','responses','photos','uploads','password','token','access_token','api_key','client_secret','notes','comment','free_text'];
        $flat = strtolower((string)wp_json_encode($projection));
        foreach ($forbidden as $needle) if (strpos($flat, $needle) !== false) return false;
        return true;
    }

    private static function recordReceipt(array $receipt): void {
        $safe = self::safeReceipt($receipt, !empty($receipt['verified']));
        $safe['signature'] = substr(sanitize_text_field((string)($receipt['signature'] ?? '')), 0, 64);
        $safe['recorded_at'] = gmdate('c');
        $rows = self::receipts(100);
        array_unshift($rows, $safe);
        update_option(self::RECEIPTS_OPTION, array_slice($rows, 0, 50), false);
    }

    private static function receipts(int $limit = 50): array {
        $rows = get_option(self::RECEIPTS_OPTION, []);
        if (!is_array($rows)) return [];
        return array_slice($rows, 0, max(1, min(100, $limit)));
    }

    private static function receiptForKey(string $deliveryKey): array {
        foreach (self::receipts(50) as $receipt) if ((string)($receipt['delivery_key'] ?? '') === $deliveryKey) return $receipt;
        return [];
    }

    private static function safeReceipt(array $receipt, bool $verified): array {
        return [
            'delivery_key'=>sanitize_text_field((string)($receipt['delivery_key'] ?? '')),
            'event_uuid'=>sanitize_text_field((string)($receipt['event_uuid'] ?? '')),
            'destination_uuid'=>sanitize_text_field((string)($receipt['destination_uuid'] ?? '')),
            'endpoint_sha256'=>substr(strtolower(sanitize_text_field((string)($receipt['endpoint_sha256'] ?? ''))), 0, 64),
            'policy_sha256'=>substr(strtolower(sanitize_text_field((string)($receipt['policy_sha256'] ?? ''))), 0, 64),
            'remote_http_status'=>absint($receipt['remote_http_status'] ?? 0),
            'outcome'=>sanitize_key((string)($receipt['outcome'] ?? '')),
            'receiver_request_id'=>substr(sanitize_text_field((string)($receipt['receiver_request_id'] ?? '')), 0, 96),
            'received_at'=>sanitize_text_field((string)($receipt['received_at'] ?? '')),
            'verified'=>$verified,
        ];
    }

    private static function jobByKey(string $deliveryKey): array {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . ConnectorDelivery::table() . ' WHERE delivery_key=%s LIMIT 1', $deliveryKey), ARRAY_A) ?: [];
    }

    private static function countByKey(string $deliveryKey): int {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . ConnectorDelivery::table() . ' WHERE delivery_key=%s', $deliveryKey));
    }

    private static function validEventUuid(string $eventUuid): bool {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', strtolower(trim($eventUuid))) === 1;
    }

    private static function states(): array {
        $states = get_option(self::OPTION, []);
        return is_array($states) ? $states : [];
    }

    private static function stateForDestination(string $destinationUuid): array {
        $states = self::states();
        return is_array($states[$destinationUuid] ?? null) ? $states[$destinationUuid] : [];
    }

    private static function saveState(string $destinationUuid, array $state): void {
        $states = self::states();
        $states[$destinationUuid] = $state;
        update_option(self::OPTION, $states, false);
    }

    private static function failure(\WP_Error $error, array $checks = []): array {
        return ['ok'=>false,'checks'=>$checks,'reason'=>$error->get_error_code(),'message'=>$error->get_error_message(),'data'=>$error->get_error_data()];
    }
}
