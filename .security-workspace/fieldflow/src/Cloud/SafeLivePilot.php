<?php
namespace RoutesPro\Cloud;

use RoutesPro\Security\OutboundHttp;
use RoutesPro\Services\IntegrationPlatform;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5C.7 Safe Live Pilot.
 *
 * This gate proves a dynamic HTTPS target without opening the normal 5C.5
 * operational dispatcher. The Broker issues a short-lived token bound to the
 * exact endpoint SHA-256 and destination UUID. Only a strict metadata
 * projection can be sent, never the original operational payload.
 */
final class SafeLivePilot {
    public const VERSION = '1.0.0';
    public const PHASE = '5C.7';
    public const OPTION = 'fieldflow_connector_delivery_5c7_pilots';
    public const PREPARE_PATH = '/api/v1/pilot/prepare';
    public const DELIVER_PATH = '/api/v1/pilot/deliver';
    public const PREPARE_CONTRACT = 'fieldflow.connector.safe_live_pilot.prepare';
    public const PREPARE_CONTRACT_VERSION = '1.0';
    public const DELIVERY_CONTRACT = 'fieldflow.connector.safe_live_pilot.delivery';
    public const DELIVERY_CONTRACT_VERSION = '1.0';
    public const RECEIPT_CONTRACT = 'fieldflow.connector.safe_live_pilot.receipt';
    public const RECEIPT_CONTRACT_VERSION = '1.0';
    public const RECEIPT_CONTEXT = 'fieldflow-safe-live-pilot-receipt-v1';
    public const PILOT_TTL_SECONDS = 900;
    public const ONE_SHOT_TTL_SECONDS = 900;
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('fieldflow_core_event', [self::class, 'captureOperationalEvent'], 9, 4);
    }

    public static function health(): array {
        $settings = ConnectorDelivery::settings();
        $states = self::states();
        $prepared = 0;
        $certified = 0;
        $armed = 0;
        foreach ($states as $state) {
            if (!empty($state['prepared'])) $prepared++;
            if (!empty($state['synthetic_certified'])) $certified++;
            if (!empty($state['armed']) && (int)($state['armed_expires_at'] ?? 0) >= time()) $armed++;
        }
        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'status'=>ConnectorDelivery::tableReady() ? 'ready' : 'attention',
            'broker_min_version'=>'1.6.0',
            'safe_projection_only'=>true,
            'full_operational_payload_delivery_enabled'=>false,
            'normal_5c5_dispatcher_enabled'=>ConnectorDelivery::networkDeliveryEnabled(),
            'requires_shadow_mode'=>(string)$settings['mode'] === 'shadow',
            'requires_kill_switch'=>!empty($settings['kill_switch']),
            'pilot_ttl_seconds'=>self::PILOT_TTL_SECONDS,
            'one_shot_ttl_seconds'=>self::ONE_SHOT_TTL_SECONDS,
            'prepared_targets'=>$prepared,
            'synthetic_certified_targets'=>$certified,
            'armed_one_shots'=>$armed,
        ];
    }

    public static function localProbe(): array {
        $health = self::health();
        $checks = [
            'queue_foundation_ready'=>ConnectorDelivery::tableReady(),
            '5c5_operational_dispatcher_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
            'shadow_mode_restored'=>!empty($health['requires_shadow_mode']),
            'global_kill_switch_on'=>!empty($health['requires_kill_switch']),
            'safe_projection_only'=>!empty($health['safe_projection_only']),
            'full_payload_delivery_blocked'=>empty($health['full_operational_payload_delivery_enabled']),
            'pilot_listener_loaded'=>has_action('fieldflow_core_event', [self::class, 'captureOperationalEvent']) !== false,
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$health];
    }

    public static function probeBroker() {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) {
            return new \WP_Error('fieldflow_5c7_broker_host_invalid', 'O Entra Broker configurado não corresponde ao host dedicado TWT.');
        }
        $url = 'https://' . $host . '/api/v1/health';
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_connector_5c7_broker_health', [
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
            && version_compare((string)($body['version'] ?? '0.0.0'), '1.6.0', '>=')
            && !empty($body['safe_live_pilot_foundation'])
            && !empty($body['safe_live_pilot_dynamic_target_guarded'])
            && !empty($body['safe_live_pilot_tokenized'])
            && !empty($body['safe_live_pilot_one_shot'])
            && !empty($body['safe_live_pilot_safe_projection_only'])
            && !empty($body['safe_live_pilot_sink_available'])
            && !empty($body['operational_payload_delivery_blocked'])
            && empty($body['delivery_enabled']);
        return [
            'ok'=>$ok,
            'http_code'=>$http,
            'version'=>sanitize_text_field((string)($body['version'] ?? '')),
            'dynamic_target_guarded'=>!empty($body['safe_live_pilot_dynamic_target_guarded']),
            'tokenized'=>!empty($body['safe_live_pilot_tokenized']),
            'one_shot'=>!empty($body['safe_live_pilot_one_shot']),
            'safe_projection_only'=>!empty($body['safe_live_pilot_safe_projection_only']),
            'pilot_sink_available'=>!empty($body['safe_live_pilot_sink_available']),
            'full_payload_blocked'=>!empty($body['operational_payload_delivery_blocked']),
            'delivery_enabled'=>!empty($body['delivery_enabled']),
            'hmac_runtime_ready'=>!empty($body['hmac_runtime_ready']),
        ];
    }

    public static function eligibleDestinations(): array {
        $out = [];
        foreach (DestinationRegistry::listDestinations() as $destination) {
            if (empty($destination['is_active'])) continue;
            if (!ConnectorDelivery::isSupportedType((string)($destination['type_key'] ?? ''))) continue;
            $url = trim((string)($destination['endpoint_ref'] ?? ''));
            if (stripos($url, 'https://') !== 0) continue;
            $out[] = $destination;
        }
        return $out;
    }

    public static function ensureTwtPilotDestination() {
        $endpoint = 'https://' . EntraCrossTenantFoundation::BROKER_HOST . '/api/v1/pilot-sink';
        foreach (DestinationRegistry::listDestinations() as $destination) {
            if ((string)($destination['endpoint_ref'] ?? '') === $endpoint && (string)($destination['type_key'] ?? '') === 'webhook') {
                if (empty($destination['is_active'])) DestinationRegistry::setDestinationActive((int)$destination['id'], true);
                return (int)$destination['id'];
            }
        }
        return DestinationRegistry::createDestination([
            'name'=>'TWT Safe Live Pilot Receiver',
            'type_key'=>'webhook',
            'client_id'=>0,
            'project_id'=>0,
            'mode'=>'mirror',
            'endpoint_ref'=>$endpoint,
            'secret_ref'=>'',
            'is_active'=>1,
            'auth_strategy'=>'none',
            'retry_policy'=>['max_attempts'=>1,'base_delay_seconds'=>60],
        ]);
    }

    public static function prepareTarget(int $destinationId): array {
        $preflight = self::preflight($destinationId, false);
        if (empty($preflight['ok'])) return $preflight;
        $destination = $preflight['destination'];
        $endpoint = trim((string)$destination['endpoint_ref']);
        $endpointCheck = self::validateEndpoint($endpoint);
        if (is_wp_error($endpointCheck)) return self::failure($endpointCheck, $preflight['checks']);
        $broker = self::brokerConfig(self::PREPARE_PATH, 'fieldflow_connector_5c7_prepare');
        if (is_wp_error($broker)) return self::failure($broker, $preflight['checks']);

        $identity = TenantIdentity::snapshot();
        $eventUuid = wp_generate_uuid4();
        $payload = [
            'contract'=>self::PREPARE_CONTRACT,
            'contract_version'=>self::PREPARE_CONTRACT_VERSION,
            'tenant'=>$identity,
            'event'=>['uuid'=>$eventUuid,'name'=>'connector.safe_live_pilot.prepare','occurred_at'=>gmdate('c')],
            'destination'=>['uuid'=>(string)$destination['destination_uuid'],'type'=>(string)$destination['type_key']],
            'target'=>['url'=>$endpoint],
            'pilot'=>[
                'phase'=>self::PHASE,
                'ttl_seconds'=>self::PILOT_TTL_SECONDS,
                'safe_projection_only'=>true,
                'full_operational_payload_allowed'=>false,
            ],
            'data'=>['safe_projection'=>['event_name'=>'connector.safe_live_pilot.prepare','event_uuid'=>$eventUuid]],
        ];
        $response = self::postBroker($broker, $payload);
        if (is_wp_error($response)) return self::failure($response, $preflight['checks']);
        $body = (array)$response['body'];
        $ok = !empty($body['ok']) && !empty($body['safe_live_pilot']) && (string)($body['phase'] ?? '') === self::PHASE;
        $token = sanitize_text_field((string)($body['pilot_token'] ?? ''));
        $sha = strtolower(sanitize_text_field((string)($body['endpoint_sha256'] ?? '')));
        $targetUrl = esc_url_raw((string)($body['normalized_target_url'] ?? $endpoint), ['https']);
        $expires = absint($body['expires_at_unix'] ?? 0);
        $checks = array_merge($preflight['checks'], [
            'broker_prepare_http_200'=>(int)$response['http_code'] === 200,
            'pilot_prepare_accepted'=>$ok,
            'pilot_token_present'=>$token !== '',
            'endpoint_sha256_present'=>preg_match('/^[a-f0-9]{64}$/', $sha) === 1,
            'token_expiry_future'=>$expires > time(),
            'safe_projection_only'=>!empty($body['safe_projection_only']),
            'full_payload_still_blocked'=>empty($body['full_operational_payload_allowed']),
        ]);
        if (in_array(false, $checks, true)) return ['ok'=>false,'checks'=>$checks,'reason'=>'pilot_prepare_contract_failed'];
        $state = self::stateForDestination((string)$destination['destination_uuid']);
        $state = array_merge($state, [
            'destination_id'=>(int)$destination['id'],
            'destination_uuid'=>(string)$destination['destination_uuid'],
            'destination_name'=>(string)$destination['name'],
            'prepared'=>true,
            'prepared_at'=>time(),
            'target_url'=>$targetUrl,
            'endpoint_host'=>sanitize_text_field((string)($body['endpoint_host'] ?? wp_parse_url($targetUrl, PHP_URL_HOST))),
            'endpoint_sha256'=>$sha,
            'pilot_token'=>$token,
            'token_expires_at'=>$expires,
            'synthetic_certified'=>false,
            'synthetic_certified_at'=>0,
            'armed'=>false,
            'armed_expires_at'=>0,
            'consumed_at'=>0,
            'last_ok'=>false,
            'last_error'=>'',
        ]);
        self::saveState((string)$destination['destination_uuid'], $state);
        return [
            'ok'=>true,
            'checks'=>$checks,
            'reason'=>'pilot_target_prepared',
            'destination_id'=>(int)$destination['id'],
            'destination_name'=>(string)$destination['name'],
            'endpoint_host'=>(string)$state['endpoint_host'],
            'endpoint_sha256'=>$sha,
            'expires_at_unix'=>$expires,
        ];
    }

    public static function certifySynthetic(int $destinationId): array {
        $preflight = self::preflight($destinationId, true);
        if (empty($preflight['ok'])) return $preflight;
        $destination = $preflight['destination'];
        $state = $preflight['state'];
        $eventUuid = wp_generate_uuid4();
        $projection = [
            'event_name'=>'connector.safe_live_pilot.synthetic',
            'event_uuid'=>$eventUuid,
            'occurred_at'=>gmdate('c'),
            'client_id'=>absint($destination['client_id'] ?? 0),
            'project_id'=>absint($destination['project_id'] ?? 0),
            'synthetic_marker'=>'fieldflow-5c7-synthetic',
        ];
        $sent = self::sendPilot($destination, $state, 'synthetic', $projection);
        if (is_wp_error($sent)) return self::failure($sent, $preflight['checks']);
        $receipt = (array)($sent['receipt'] ?? []);
        $checks = array_merge($preflight['checks'], [
            'broker_http_200'=>(int)($sent['broker_http_status'] ?? 0) === 200,
            'pilot_delivery_performed'=>!empty($sent['delivery_performed']),
            'remote_http_2xx'=>(int)($sent['remote_http_status'] ?? 0) >= 200 && (int)($sent['remote_http_status'] ?? 0) < 300,
            'receipt_signature_verified'=>!empty($sent['receipt_verified']),
            'receipt_endpoint_bound'=>(string)($receipt['endpoint_sha256'] ?? '') === (string)$state['endpoint_sha256'],
            'receipt_destination_bound'=>(string)($receipt['destination_uuid'] ?? '') === (string)$destination['destination_uuid'],
            'safe_projection_only'=>!empty($sent['safe_projection_only']),
            'full_payload_blocked'=>empty($sent['full_operational_payload_allowed']),
            'normal_dispatcher_still_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ]);
        $ok = !in_array(false, $checks, true);
        $state['synthetic_certified'] = $ok;
        $state['synthetic_certified_at'] = $ok ? time() : 0;
        $state['last_ok'] = $ok;
        $state['last_kind'] = 'synthetic';
        $state['last_http_code'] = (int)($sent['remote_http_status'] ?? 0);
        $state['last_event_uuid'] = $eventUuid;
        $state['last_receipt'] = self::safeReceipt($receipt, !empty($sent['receipt_verified']));
        $state['last_error'] = $ok ? '' : 'synthetic_certification_failed';
        self::saveState((string)$destination['destination_uuid'], $state);
        return [
            'ok'=>$ok,
            'checks'=>$checks,
            'reason'=>$ok ? 'safe_live_pilot_target_certified' : 'safe_live_pilot_target_failed',
            'destination_id'=>(int)$destination['id'],
            'destination_name'=>(string)$destination['name'],
            'remote_http_status'=>(int)($sent['remote_http_status'] ?? 0),
            'receipt'=>$state['last_receipt'],
        ];
    }

    public static function armOneShot(int $destinationId, string $eventPattern): array {
        $preflight = self::preflight($destinationId, true);
        if (empty($preflight['ok'])) return $preflight;
        $destination = $preflight['destination'];
        $state = $preflight['state'];
        if (empty($state['synthetic_certified'])) {
            return ['ok'=>false,'checks'=>$preflight['checks'],'reason'=>'pilot_target_not_certified','message'=>'Certifica primeiro o alvo sintético 5C.7.'];
        }
        $eventPattern = DestinationRegistry::normaliseEventPattern($eventPattern);
        if ($eventPattern === '' || $eventPattern === '*') {
            return ['ok'=>false,'checks'=>$preflight['checks'],'reason'=>'pilot_event_pattern_too_broad','message'=>'O one-shot exige um evento explícito ou um padrão limitado, por exemplo submission.created.'];
        }
        $state['armed'] = true;
        $state['armed_event_pattern'] = $eventPattern;
        $state['armed_at'] = time();
        $state['armed_expires_at'] = time() + self::ONE_SHOT_TTL_SECONDS;
        $state['armed_by'] = get_current_user_id();
        $state['consumed_at'] = 0;
        $state['last_error'] = '';
        self::saveState((string)$destination['destination_uuid'], $state);
        $checks = array_merge($preflight['checks'], [
            'target_synthetic_certified'=>!empty($state['synthetic_certified']),
            'event_pattern_limited'=>$eventPattern !== '*' && $eventPattern !== '',
            'one_shot_expires'=>((int)$state['armed_expires_at'] - time()) <= self::ONE_SHOT_TTL_SECONDS,
            'full_payload_delivery_still_blocked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ]);
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'reason'=>'one_shot_armed',
            'destination_id'=>(int)$destination['id'],
            'destination_name'=>(string)$destination['name'],
            'event_pattern'=>$eventPattern,
            'expires_at_unix'=>(int)$state['armed_expires_at'],
        ];
    }

    public static function cancelPilot(int $destinationId): array {
        $destination = DestinationRegistry::getDestination($destinationId);
        if (!$destination) return ['ok'=>false,'reason'=>'destination_not_found'];
        $uuid = (string)$destination['destination_uuid'];
        $state = self::stateForDestination($uuid);
        $state['armed'] = false;
        $state['armed_expires_at'] = 0;
        $state['last_error'] = 'pilot_cancelled';
        self::saveState($uuid, $state);
        return ['ok'=>true,'reason'=>'pilot_cancelled','destination_name'=>(string)$destination['name']];
    }

    public static function certifyOneShot(int $destinationId): array {
        $arm = self::armOneShot($destinationId, 'submission.certification');
        if (empty($arm['ok'])) return $arm;
        $destination = DestinationRegistry::getDestination($destinationId);
        $eventUuid = wp_generate_uuid4();
        self::captureOperationalEvent('submission.certification', [
            'this_payload_must_never_leave_wordpress'=>'blocked-by-safe-projection',
            'answers'=>['secret'=>'never-forwarded'],
        ], [
            'entity_type'=>'submission',
            'entity_id'=>0,
            'client_id'=>absint($destination['client_id'] ?? 0),
            'project_id'=>absint($destination['project_id'] ?? 0),
            'submission_id'=>0,
        ], $eventUuid);
        $state = self::stateForDestination((string)$destination['destination_uuid']);
        $receipt = (array)($state['last_receipt'] ?? []);
        $checks = array_merge((array)($arm['checks'] ?? []), [
            'one_shot_consumed'=>empty($state['armed']) && (int)($state['consumed_at'] ?? 0) > 0,
            'one_shot_event_bound'=>(string)($state['last_event_uuid'] ?? '') === $eventUuid,
            'one_shot_delivered'=>!empty($state['last_ok']),
            'receipt_verified'=>!empty($receipt['verified']),
            'safe_projection_only'=>!empty($state['last_safe_projection_only']),
            'original_payload_not_forwarded'=>!empty($state['last_original_payload_blocked']),
            'normal_dispatcher_still_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
        ]);
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'reason'=>!in_array(false, $checks, true) ? 'safe_live_pilot_one_shot_certified' : 'safe_live_pilot_one_shot_failed',
            'destination_id'=>$destinationId,
            'destination_name'=>(string)$destination['name'],
            'event_uuid'=>$eventUuid,
            'remote_http_status'=>(int)($state['last_http_code'] ?? 0),
            'receipt'=>$receipt,
        ];
    }

    /**
     * Captures an EventBus event only when a manually armed one-shot matches.
     * The original $payload is deliberately ignored and never serialized.
     */
    public static function captureOperationalEvent(string $eventName, array $payload, array $context, string $eventUuid): void {
        try {
            $eventName = DestinationRegistry::normaliseEventPattern($eventName);
            if ($eventName === '' || !self::validEventUuid($eventUuid)) return;
            $states = self::states();
            if (!$states) return;
            foreach ($states as $uuid=>$state) {
                if (empty($state['armed'])) continue;
                if ((int)($state['armed_expires_at'] ?? 0) < time()) {
                    $state['armed'] = false;
                    $state['last_error'] = 'one_shot_expired';
                    self::saveState((string)$uuid, $state);
                    continue;
                }
                if (!self::eventMatches((string)($state['armed_event_pattern'] ?? ''), $eventName)) continue;
                $destination = DestinationRegistry::getDestination(absint($state['destination_id'] ?? 0));
                if (!$destination || empty($destination['is_active'])) continue;
                if (!self::scopeMatches($destination, $context)) continue;

                // Consume before any outbound call. A failed pilot never retries automatically.
                $state['armed'] = false;
                $state['consumed_at'] = time();
                $state['last_event_uuid'] = $eventUuid;
                $state['last_kind'] = 'one_shot';
                $state['last_original_payload_blocked'] = true;
                $state['last_safe_projection_only'] = true;
                self::saveState((string)$uuid, $state);

                $projection = self::safeProjection($eventName, $eventUuid, $context, false);
                $sent = self::sendPilot($destination, $state, 'one_shot', $projection);
                $state = self::stateForDestination((string)$uuid);
                if (is_wp_error($sent)) {
                    $state['last_ok'] = false;
                    $state['last_http_code'] = 0;
                    $state['last_error'] = $sent->get_error_code();
                    self::saveState((string)$uuid, $state);
                    IntegrationPlatform::log('connector_delivery_5c7', 'safe_live_pilot', 'error', [
                        'destination_uuid'=>(string)$uuid,'event'=>$eventName,'event_uuid'=>$eventUuid,
                    ], $sent->get_error_message());
                    continue;
                }
                $receipt = (array)($sent['receipt'] ?? []);
                $ok = !empty($sent['delivered']) && !empty($sent['receipt_verified']);
                $state['last_ok'] = $ok;
                $state['last_http_code'] = (int)($sent['remote_http_status'] ?? 0);
                $state['last_error'] = $ok ? '' : 'one_shot_remote_failed';
                $state['last_receipt'] = self::safeReceipt($receipt, !empty($sent['receipt_verified']));
                self::saveState((string)$uuid, $state);
                IntegrationPlatform::log('connector_delivery_5c7', 'safe_live_pilot', $ok ? 'success' : 'error', [
                    'destination_uuid'=>(string)$uuid,'event'=>$eventName,'event_uuid'=>$eventUuid,'http_code'=>(int)($sent['remote_http_status'] ?? 0),
                ], $ok ? '' : 'Safe live pilot one-shot did not complete.');
            }
        } catch (\Throwable $error) {
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('O Safe Live Pilot 5C.7 falhou em modo fail-open.', $error, ['context_key'=>'connector_delivery_5c7']);
            }
        }
    }

    public static function certificationState(int $destinationId): array {
        $destination = DestinationRegistry::getDestination($destinationId);
        if (!$destination) return [
            'destination_exists'=>false,
            'synthetic_certified'=>false,
            'one_shot_certified'=>false,
            'prepared'=>false,
            'consumed'=>false,
            'last_ok'=>false,
        ];
        $state = self::stateForDestination((string)$destination['destination_uuid']);
        $receipt = (array)($state['last_receipt'] ?? []);
        $oneShotCertified = (string)($state['last_kind'] ?? '') === 'one_shot'
            && !empty($state['last_ok'])
            && (int)($state['consumed_at'] ?? 0) > 0
            && !empty($receipt['verified'])
            && !empty($state['last_safe_projection_only'])
            && !empty($state['last_original_payload_blocked']);
        return [
            'destination_exists'=>true,
            'prepared'=>!empty($state['prepared']),
            'synthetic_certified'=>!empty($state['synthetic_certified']),
            'one_shot_certified'=>$oneShotCertified,
            'armed'=>!empty($state['armed']) && (int)($state['armed_expires_at'] ?? 0) >= time(),
            'consumed'=>(int)($state['consumed_at'] ?? 0) > 0,
            'consumed_at'=>(int)($state['consumed_at'] ?? 0),
            'last_ok'=>!empty($state['last_ok']),
            'last_kind'=>(string)($state['last_kind'] ?? ''),
            'last_http_code'=>(int)($state['last_http_code'] ?? 0),
            'last_error'=>(string)($state['last_error'] ?? ''),
        ];
    }

    public static function summary(): array {
        $rows = [];
        foreach (self::eligibleDestinations() as $destination) {
            $state = self::stateForDestination((string)$destination['destination_uuid']);
            $rows[] = [
                'destination_id'=>(int)$destination['id'],
                'destination_uuid'=>(string)$destination['destination_uuid'],
                'name'=>(string)$destination['name'],
                'type_key'=>(string)$destination['type_key'],
                'endpoint_host'=>(string)($state['endpoint_host'] ?? wp_parse_url((string)$destination['endpoint_ref'], PHP_URL_HOST)),
                'prepared'=>!empty($state['prepared']) && (int)($state['token_expires_at'] ?? 0) >= time(),
                'synthetic_certified'=>!empty($state['synthetic_certified']),
                'armed'=>!empty($state['armed']) && (int)($state['armed_expires_at'] ?? 0) >= time(),
                'consumed'=>(int)($state['consumed_at'] ?? 0) > 0 && empty($state['armed']),
                'consumed_at'=>(int)($state['consumed_at'] ?? 0),
                'one_shot_certified'=>(string)($state['last_kind'] ?? '') === 'one_shot' && !empty($state['last_ok']) && (int)($state['consumed_at'] ?? 0) > 0 && !empty($state['last_receipt']['verified']),
                'armed_event_pattern'=>(string)($state['armed_event_pattern'] ?? ''),
                'armed_expires_at'=>(int)($state['armed_expires_at'] ?? 0),
                'last_ok'=>!empty($state['last_ok']),
                'last_kind'=>(string)($state['last_kind'] ?? ''),
                'last_http_code'=>(int)($state['last_http_code'] ?? 0),
                'last_error'=>(string)($state['last_error'] ?? ''),
            ];
        }
        return $rows;
    }

    private static function preflight(int $destinationId, bool $requirePrepared): array {
        $settings = ConnectorDelivery::settings();
        $destination = DestinationRegistry::getDestination($destinationId);
        $state = $destination ? self::stateForDestination((string)$destination['destination_uuid']) : [];
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
            'prepared_not_required_or_ready'=>!$requirePrepared || (!empty($state['prepared']) && (int)($state['token_expires_at'] ?? 0) > time() && !empty($state['pilot_token'])),
            'full_operational_payload_delivery_blocked'=>true,
        ];
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'reason'=>!in_array(false, $checks, true) ? 'preflight_ok' : 'preflight_failed',
            'message'=>!in_array(false, $checks, true) ? '' : 'A 5C.7 exige SHADOW + Kill switch ON, destino API/Webhook HTTPS ativo e target preparado.',
            'destination'=>$destination,
            'state'=>$state,
        ];
    }

    private static function validateEndpoint(string $url) {
        if (stripos($url, 'https://') !== 0) return new \WP_Error('fieldflow_5c7_https_required', 'A 5C.7 aceita apenas um endpoint HTTPS real.');
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_connector_5c7_target_preflight', [
            'max_timeout'=>10,
            'max_response_bytes'=>65536,
            'allow_public_ip_literals'=>false,
            'max_redirects'=>0,
        ]);
        return OutboundHttp::validate($url, $policy);
    }

    private static function brokerConfig(string $path, string $purpose) {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) return new \WP_Error('fieldflow_5c7_broker_host_invalid', 'O Entra Broker 5C.7 não corresponde ao host dedicado TWT.');
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_5c7_hmac_missing', 'O HMAC TWT certificado não está disponível para a 5C.7.');
        $url = 'https://' . $host . $path;
        $policy = OutboundHttp::configuredPolicy($url, $purpose, [
            'max_timeout'=>30,
            'max_response_bytes'=>262144,
            'allow_public_ip_literals'=>false,
        ]);
        $validated = OutboundHttp::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;
        return ['url'=>$validated,'secret'=>$secret,'timeout'=>max(10,min(30,(int)($settings['timeout'] ?? 20))),'policy'=>$policy];
    }

    private static function postBroker(array $broker, array $payload) {
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_5c7_payload_encode', 'Não foi possível codificar o pedido 5C.7.');
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
            return new \WP_Error('fieldflow_5c7_broker_rejected', 'O Broker 5C.7 rejeitou o pedido.', [
                'http_status'=>$http,
                'broker_error'=>sanitize_key((string)($decoded['error'] ?? '')),
            ]);
        }
        return ['http_code'=>$http,'body'=>$decoded];
    }

    private static function sendPilot(array $destination, array $state, string $kind, array $projection) {
        $broker = self::brokerConfig(self::DELIVER_PATH, 'fieldflow_connector_5c7_delivery');
        if (is_wp_error($broker)) return $broker;
        $eventUuid = (string)($projection['event_uuid'] ?? '');
        if (!self::validEventUuid($eventUuid)) return new \WP_Error('fieldflow_5c7_event_uuid_invalid', 'O event UUID do piloto não é válido.');
        $deliveryKey = DestinationRouter::deliveryKey($eventUuid, (string)$destination['destination_uuid']);
        $payload = [
            'contract'=>self::DELIVERY_CONTRACT,
            'contract_version'=>self::DELIVERY_CONTRACT_VERSION,
            'tenant'=>TenantIdentity::snapshot(),
            'event'=>[
                'uuid'=>$eventUuid,
                'name'=>sanitize_text_field((string)($projection['event_name'] ?? 'connector.safe_live_pilot')),
                'occurred_at'=>sanitize_text_field((string)($projection['occurred_at'] ?? gmdate('c'))),
            ],
            'destination'=>['uuid'=>(string)$destination['destination_uuid'],'type'=>(string)$destination['type_key']],
            'delivery'=>['key'=>$deliveryKey],
            'target'=>['url'=>(string)$state['target_url']],
            'pilot'=>[
                'phase'=>self::PHASE,
                'kind'=>$kind,
                'token'=>(string)$state['pilot_token'],
                'safe_projection_only'=>true,
                'full_operational_payload_allowed'=>false,
            ],
            'data'=>['safe_projection'=>$projection],
        ];
        $response = self::postBroker($broker, $payload);
        if (is_wp_error($response)) return $response;
        $body = (array)$response['body'];
        if (empty($body['safe_live_pilot']) || (string)($body['phase'] ?? '') !== self::PHASE || empty($body['delivery_performed'])) {
            return new \WP_Error('fieldflow_5c7_broker_contract_invalid', 'O Broker respondeu sem o contrato Safe Live Pilot esperado.');
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
            'receipt'=>$receipt,
            'receipt_verified'=>self::verifyReceipt($receipt, (string)$broker['secret'], $deliveryKey, $eventUuid, (string)$destination['destination_uuid'], (string)$state['endpoint_sha256']),
        ];
    }

    private static function verifyReceipt(array $receipt, string $secret, string $deliveryKey, string $eventUuid, string $destinationUuid, string $endpointSha): bool {
        $signature = strtolower(trim((string)($receipt['signature'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $signature) || strlen($secret) < 32) return false;
        if ((string)($receipt['contract'] ?? '') !== self::RECEIPT_CONTRACT || (string)($receipt['contract_version'] ?? '') !== self::RECEIPT_CONTRACT_VERSION) return false;
        if ((string)($receipt['delivery_key'] ?? '') !== $deliveryKey) return false;
        if ((string)($receipt['event_uuid'] ?? '') !== $eventUuid) return false;
        if ((string)($receipt['destination_uuid'] ?? '') !== $destinationUuid) return false;
        if ((string)($receipt['endpoint_sha256'] ?? '') !== $endpointSha) return false;
        $canonical = implode("\n", [
            self::RECEIPT_CONTEXT,
            (string)$receipt['contract'],
            (string)$receipt['contract_version'],
            (string)$receipt['delivery_key'],
            (string)$receipt['event_uuid'],
            (string)$receipt['destination_uuid'],
            (string)$receipt['endpoint_sha256'],
            (string)absint($receipt['remote_http_status'] ?? 0),
            (string)($receipt['outcome'] ?? ''),
            (string)($receipt['receiver_request_id'] ?? ''),
            (string)($receipt['received_at'] ?? ''),
        ]);
        $expected = hash_hmac('sha256', $canonical, $secret);
        return hash_equals($expected, $signature);
    }

    private static function safeProjection(string $eventName, string $eventUuid, array $context, bool $synthetic): array {
        $out = [
            'event_name'=>$eventName,
            'event_uuid'=>$eventUuid,
            'occurred_at'=>gmdate('c'),
            'synthetic_marker'=>$synthetic ? 'fieldflow-5c7-synthetic' : '',
        ];
        foreach (['entity_type','entity_id','client_id','project_id','route_id','route_stop_id','location_id','submission_id','briefing_id'] as $key) {
            if (!array_key_exists($key, $context)) continue;
            $out[$key] = $key === 'entity_type' ? sanitize_key((string)$context[$key]) : absint($context[$key]);
        }
        return $out;
    }

    private static function scopeMatches(array $destination, array $context): bool {
        $destinationClient = absint($destination['client_id'] ?? 0);
        $destinationProject = absint($destination['project_id'] ?? 0);
        $eventClient = absint($context['client_id'] ?? 0);
        $eventProject = absint($context['project_id'] ?? 0);
        if ($destinationClient > 0 && $destinationClient !== $eventClient) return false;
        if ($destinationProject > 0 && $destinationProject !== $eventProject) return false;
        return true;
    }

    private static function eventMatches(string $pattern, string $eventName): bool {
        $pattern = DestinationRegistry::normaliseEventPattern($pattern);
        if ($pattern === '' || $pattern === '*') return false;
        if ($pattern === $eventName) return true;
        $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
        return preg_match($regex, $eventName) === 1;
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
        $state = is_array($states[$destinationUuid] ?? null) ? $states[$destinationUuid] : [];
        if (!empty($state['armed']) && (int)($state['armed_expires_at'] ?? 0) < time()) {
            $state['armed'] = false;
            $state['last_error'] = 'one_shot_expired';
            self::saveState($destinationUuid, $state);
        }
        return $state;
    }

    private static function saveState(string $destinationUuid, array $state): void {
        $states = self::states();
        $states[$destinationUuid] = $state;
        update_option(self::OPTION, $states, false);
    }

    private static function safeReceipt(array $receipt, bool $verified): array {
        return [
            'delivery_key'=>sanitize_text_field((string)($receipt['delivery_key'] ?? '')),
            'event_uuid'=>sanitize_text_field((string)($receipt['event_uuid'] ?? '')),
            'destination_uuid'=>sanitize_text_field((string)($receipt['destination_uuid'] ?? '')),
            'endpoint_sha256'=>substr(strtolower(sanitize_text_field((string)($receipt['endpoint_sha256'] ?? ''))), 0, 64),
            'remote_http_status'=>absint($receipt['remote_http_status'] ?? 0),
            'outcome'=>sanitize_key((string)($receipt['outcome'] ?? '')),
            'receiver_request_id'=>substr(sanitize_text_field((string)($receipt['receiver_request_id'] ?? '')), 0, 96),
            'received_at'=>sanitize_text_field((string)($receipt['received_at'] ?? '')),
            'verified'=>$verified,
        ];
    }

    private static function failure(\WP_Error $error, array $checks = []): array {
        return [
            'ok'=>false,
            'checks'=>$checks,
            'reason'=>$error->get_error_code(),
            'message'=>$error->get_error_message(),
            'data'=>$error->get_error_data(),
        ];
    }
}
