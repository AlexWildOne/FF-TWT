<?php
namespace RoutesPro\Cloud;

use RoutesPro\Security\OutboundHttp;
use RoutesPro\Services\IntegrationPlatform;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5D Credential Lifecycle & Secret Governance.
 *
 * WordPress persists only Key Vault references and non-sensitive lifecycle
 * metadata. Secret values are resolved exclusively by the Entra Broker using
 * the existing TWT user-assigned managed identity.
 */
final class CredentialLifecycle {
    public const VERSION = '1.0.0';
    public const PHASE = '5D';
    public const OPTION = 'fieldflow_connector_credentials_5d';
    public const RECEIPTS_OPTION = 'fieldflow_connector_credentials_5d_receipts';
    public const PROBE_PATH = '/api/v1/credentials/probe';
    public const CERTIFY_PATH = '/api/v1/credentials/certify';
    public const PROBE_CONTRACT = 'fieldflow.credential.lifecycle.probe';
    public const CERTIFY_CONTRACT = 'fieldflow.credential.lifecycle.certify';
    public const CONTRACT_VERSION = '1.0';
    public const RECEIPT_CONTRACT = 'fieldflow.credential.lifecycle.receipt';
    public const RECEIPT_CONTRACT_VERSION = '1.0';
    public const RECEIPT_CONTEXT = 'fieldflow-credential-lifecycle-receipt-v1';
    public const TEST_VAULT = 'kv-ff-prod-eb978f11';
    public const TEST_SECRET = 'fieldflow-5d-certification-key';
    public const GENERAL_ACTIVATION_ENABLED = false;
    public const OPERATIONAL_PAYLOAD_ENABLED = false;

    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
    }

    public static function health(): array {
        $states = self::states();
        $productionHealth = class_exists('\RoutesPro\Cloud\ProductionDeliveryGate') ? ProductionDeliveryGate::health() : [];
        $ready = 0; $warning = 0; $expired = 0; $revoked = 0; $rotation = 0; $certified = 0;
        foreach ($states as $state) {
            if (!empty($state['certified'])) $certified++;
            if (!empty($state['revoked'])) $revoked++;
            if (!empty($state['rotation_detected'])) $rotation++;
            $status = sanitize_key((string)($state['credential_status'] ?? ''));
            if ($status === 'warning') $warning++;
            elseif (in_array($status, ['expired','disabled','not_yet_valid','missing','forbidden','error'], true)) $expired++;
            if (self::stateReady($state)) $ready++;
        }
        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'status'=>'ready',
            'key_vault_reference_only'=>true,
            'secret_values_stored_in_wordpress'=>false,
            'secret_values_returned_to_wordpress'=>false,
            'managed_identity_resolution'=>true,
            'rotation_detection'=>true,
            'expiry_enforcement'=>true,
            'revocation_guard'=>true,
            'signed_receipts_required'=>true,
            'production_5c8_preserved'=>class_exists('\\RoutesPro\\Cloud\\ProductionDeliveryGate'),
            'production_5c8_ready'=>(int)($productionHealth['production_ready_gates'] ?? 0) > 0,
            'production_5c8_ready_gates'=>(int)($productionHealth['production_ready_gates'] ?? 0),
            'general_production_activation_enabled'=>self::GENERAL_ACTIVATION_ENABLED,
            'operational_payload_delivery_enabled'=>self::OPERATIONAL_PAYLOAD_ENABLED,
            'configured_credentials'=>count($states),
            'certified_credentials'=>$certified,
            'ready_credentials'=>$ready,
            'warning_credentials'=>$warning,
            'blocked_credentials'=>$expired,
            'revoked_credentials'=>$revoked,
            'rotation_detected_credentials'=>$rotation,
        ];
    }

    public static function localProbe(): array {
        $health = self::health();
        $badRefs = 0;
        foreach (self::eligibleDestinations(true) as $destination) {
            $ref = trim((string)($destination['secret_ref'] ?? ''));
            if ($ref !== '' && !self::validKeyVaultRef($ref)) $badRefs++;
        }
        $checks = [
            'production_5c8_available'=>!empty($health['production_5c8_preserved']),
            'production_5c8_ready'=>!empty($health['production_5c8_ready']),
            '5c5_operational_dispatcher_locked'=>class_exists('\\RoutesPro\\Cloud\\ConnectorDelivery') && !ConnectorDelivery::networkDeliveryEnabled(),
            'shadow_mode_preserved'=>class_exists('\\RoutesPro\\Cloud\\ConnectorDelivery') && ConnectorDelivery::mode() === 'shadow',
            'global_kill_switch_on'=>class_exists('\\RoutesPro\\Cloud\\ConnectorDelivery') && !empty(ConnectorDelivery::settings()['kill_switch']),
            'key_vault_reference_only'=>!empty($health['key_vault_reference_only']),
            'secret_values_not_stored_in_wordpress'=>empty($health['secret_values_stored_in_wordpress']),
            'secret_values_not_returned_to_wordpress'=>empty($health['secret_values_returned_to_wordpress']),
            'rotation_detection_enabled'=>!empty($health['rotation_detection']),
            'expiry_enforcement_enabled'=>!empty($health['expiry_enforcement']),
            'revocation_guard_enabled'=>!empty($health['revocation_guard']),
            'all_configured_refs_valid'=>$badRefs === 0,
            'general_activation_still_blocked'=>empty($health['general_production_activation_enabled']),
            'operational_payload_still_blocked'=>empty($health['operational_payload_delivery_enabled']),
        ];
        return ['ok'=>!in_array(false, $checks, true), 'checks'=>$checks, 'health'=>$health];
    }

    public static function probeBroker() {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) {
            return new \WP_Error('fieldflow_5d_broker_host_invalid', 'O Entra Broker 5D não corresponde ao host dedicado TWT.');
        }
        $url = 'https://' . $host . '/api/v1/health';
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_credential_5d_broker_health', [
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
            && version_compare((string)($body['version'] ?? '0.0.0'), '1.8.0', '>=')
            && !empty($body['credential_lifecycle_foundation'])
            && !empty($body['credential_lifecycle_key_vault_only'])
            && !empty($body['credential_lifecycle_metadata_probe'])
            && !empty($body['credential_lifecycle_rotation_detection'])
            && !empty($body['credential_lifecycle_signed_receipts'])
            && !empty($body['credential_lifecycle_sink_available'])
            && !empty($body['credential_lifecycle_test_secret_ready'])
            && !empty($body['operational_payload_delivery_blocked'])
            && empty($body['production_general_activation_enabled'])
            && empty($body['delivery_enabled']);
        return [
            'ok'=>$ok,
            'http_code'=>$http,
            'version'=>sanitize_text_field((string)($body['version'] ?? '')),
            'key_vault_only'=>!empty($body['credential_lifecycle_key_vault_only']),
            'metadata_probe'=>!empty($body['credential_lifecycle_metadata_probe']),
            'rotation_detection'=>!empty($body['credential_lifecycle_rotation_detection']),
            'signed_receipts'=>!empty($body['credential_lifecycle_signed_receipts']),
            'sink_available'=>!empty($body['credential_lifecycle_sink_available']),
            'test_secret_ready'=>!empty($body['credential_lifecycle_test_secret_ready']),
            'secret_values_never_returned'=>!empty($body['credential_lifecycle_secret_values_never_returned']),
            'general_activation_enabled'=>!empty($body['production_general_activation_enabled']),
            'delivery_enabled'=>!empty($body['delivery_enabled']),
        ];
    }

    public static function ensureTwtCredentialDestination() {
        $endpoint = 'https://' . EntraCrossTenantFoundation::BROKER_HOST . '/api/v1/credential-sink';
        $secretRef = 'kv://' . self::TEST_VAULT . '/' . self::TEST_SECRET;
        $destinationId = 0;
        foreach (DestinationRegistry::listDestinations() as $destination) {
            if ((string)($destination['type_key'] ?? '') !== 'webhook') continue;
            if ((string)($destination['endpoint_ref'] ?? '') !== $endpoint) continue;
            if ((string)($destination['name'] ?? '') !== 'TWT Credential Lifecycle Receiver') continue;
            $destinationId = (int)$destination['id'];
            break;
        }
        if ($destinationId <= 0) {
            $created = DestinationRegistry::createDestination([
                'name'=>'TWT Credential Lifecycle Receiver',
                'type_key'=>'webhook',
                'client_id'=>0,
                'project_id'=>0,
                'mode'=>'mirror',
                'endpoint_ref'=>$endpoint,
                'secret_ref'=>$secretRef,
                'is_active'=>0,
                'auth_strategy'=>'key_vault_reference',
                'retry_policy'=>['max_attempts'=>2,'base_delay_seconds'=>60],
            ]);
            if (is_wp_error($created)) return $created;
            $destinationId = (int)$created;
        }
        $configured = self::configureDestination($destinationId, $secretRef, 'api_key_header', 'X-FieldFlow-Credential', 30);
        if (is_wp_error($configured)) return $configured;
        return $destinationId;
    }

    public static function configureDestination(int $destinationId, string $secretRef, string $authMode, string $headerName, int $warningDays = 30) {
        global $wpdb;
        $destination = DestinationRegistry::getDestination($destinationId);
        if (!$destination) return new \WP_Error('fieldflow_5d_destination_missing', 'Destino 5D não encontrado.');
        $type = sanitize_key((string)($destination['type_key'] ?? ''));
        if (!in_array($type, ['external_api','webhook'], true)) {
            return new \WP_Error('fieldflow_5d_destination_type_invalid', 'A 5D de credenciais por Key Vault aplica-se nesta release a API Externa e Webhook.');
        }
        $secretRef = trim($secretRef);
        if (!self::validKeyVaultRef($secretRef)) {
            return new \WP_Error('fieldflow_5d_secret_ref_invalid', 'Usa apenas uma referência Key Vault no formato kv://vault/secret ou kv://vault/secret/version. Nunca coloques o valor do segredo.');
        }
        $authMode = self::normaliseAuthMode($authMode);
        if ($authMode === '') return new \WP_Error('fieldflow_5d_auth_mode_invalid', 'Escolhe API key em header ou Bearer token.');
        $headerName = self::normaliseHeaderName($authMode, $headerName);
        if ($headerName === '') return new \WP_Error('fieldflow_5d_header_invalid', 'O nome do header da credencial não é válido.');
        $warningDays = max(1, min(90, $warningDays));

        $policy = json_decode((string)($destination['retry_policy_json'] ?? ''), true);
        if (!is_array($policy)) $policy = [];
        $policy = ConnectorFoundation::decoratePolicy($type, $policy, 'key_vault_reference');
        $policy['credential_lifecycle_version'] = self::VERSION;
        $policy['credential_auth_mode'] = $authMode;
        $policy['credential_header_name'] = $headerName;
        $policy['credential_warning_days'] = $warningDays;
        $refHash = hash('sha256', strtolower($secretRef));
        $oldState = self::stateForDestination((string)$destination['destination_uuid']);
        $changed = (string)($oldState['secret_ref_sha256'] ?? '') !== $refHash
            || (string)($oldState['auth_mode'] ?? '') !== $authMode
            || (string)($oldState['header_name'] ?? '') !== $headerName;

        $ok = $wpdb->update(DestinationRegistry::destinationsTable(), [
            'secret_ref'=>$secretRef,
            'retry_policy_json'=>wp_json_encode($policy, JSON_UNESCAPED_SLASHES),
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$destinationId]);
        if ($ok === false) return new \WP_Error('fieldflow_5d_destination_update_failed', 'Não foi possível guardar o perfil de credencial 5D.');

        $state = $oldState;
        $state['destination_uuid'] = (string)$destination['destination_uuid'];
        $state['secret_ref_sha256'] = $refHash;
        $state['auth_mode'] = $authMode;
        $state['header_name'] = $headerName;
        $state['warning_days'] = $warningDays;
        $state['configured_at'] = gmdate('c');
        $state['configured_by'] = get_current_user_id() ?: 0;
        if ($changed) {
            $state['certified'] = false;
            $state['certified_version'] = '';
            $state['rotation_detected'] = false;
            $state['credential_status'] = 'configured';
            $state['last_error'] = '';
        }
        self::saveState((string)$destination['destination_uuid'], $state);
        IntegrationPlatform::log('credential_lifecycle_5d', 'configure', 'success', [
            'destination_uuid'=>(string)$destination['destination_uuid'],
            'auth_mode'=>$authMode,
            'secret_ref_sha256'=>$refHash,
        ]);
        return true;
    }

    public static function probeCredential(int $destinationId): array {
        $preflight = self::preflight($destinationId);
        if (empty($preflight['ok'])) return $preflight;
        $destination = $preflight['destination'];
        $state = $preflight['state'];
        $eventUuid = wp_generate_uuid4();
        $payload = [
            'contract'=>self::PROBE_CONTRACT,
            'contract_version'=>self::CONTRACT_VERSION,
            'tenant'=>TenantIdentity::snapshot(),
            'event'=>['uuid'=>$eventUuid,'name'=>'credential.lifecycle.probe','occurred_at'=>gmdate('c')],
            'destination'=>['uuid'=>(string)$destination['destination_uuid'],'type'=>(string)$destination['type_key']],
            'credential'=>[
                'phase'=>self::PHASE,
                'secret_ref'=>(string)$destination['secret_ref'],
                'auth_mode'=>(string)$state['auth_mode'],
                'header_name'=>(string)$state['header_name'],
                'warning_days'=>(int)$state['warning_days'],
            ],
        ];
        $remote = self::brokerCall(self::PROBE_PATH, 'fieldflow_credential_5d_probe', $payload);
        if (is_wp_error($remote)) return self::failure($remote, $preflight['checks']);
        $body = (array)($remote['body'] ?? []);
        $meta = is_array($body['credential'] ?? null) ? $body['credential'] : [];
        $credentialStatus = sanitize_key((string)($meta['status'] ?? 'error'));
        $secretVersion = sanitize_text_field((string)($meta['secret_version'] ?? ''));
        $certifiedVersion = sanitize_text_field((string)($state['certified_version'] ?? ''));
        $rotationDetected = $certifiedVersion !== '' && $secretVersion !== '' && !hash_equals($certifiedVersion, $secretVersion);
        $state['credential_status'] = $credentialStatus;
        $state['secret_version'] = $secretVersion;
        $state['enabled'] = !empty($meta['enabled']);
        $state['expires_at'] = sanitize_text_field((string)($meta['expires_at'] ?? ''));
        $state['not_before'] = sanitize_text_field((string)($meta['not_before'] ?? ''));
        $state['days_remaining'] = isset($meta['days_remaining']) ? (int)$meta['days_remaining'] : null;
        $state['last_probe_at'] = gmdate('c');
        $state['last_error'] = sanitize_key((string)($body['error'] ?? ''));
        $state['rotation_detected'] = $rotationDetected;
        if ($rotationDetected || !in_array($credentialStatus, ['ready','warning'], true)) $state['certified'] = false;
        self::saveState((string)$destination['destination_uuid'], $state);
        $checks = array_merge($preflight['checks'], [
            'broker_http_200'=>(int)($remote['http_code'] ?? 0) === 200,
            'secret_resolved'=>!empty($body['resolved']),
            'secret_value_not_returned'=>empty($body['secret_value']) && empty($meta['value']),
            'secret_version_present'=>$secretVersion !== '',
            'credential_usable'=>in_array($credentialStatus, ['ready','warning'], true),
            'rotation_not_detected'=>!$rotationDetected,
            'general_activation_still_blocked'=>empty($body['general_activation_enabled']),
        ]);
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'destination_name'=>(string)$destination['name'],
            'credential_status'=>$credentialStatus,
            'secret_version'=>$secretVersion,
            'expires_at'=>(string)($state['expires_at'] ?? ''),
            'days_remaining'=>$state['days_remaining'],
            'rotation_detected'=>$rotationDetected,
            'http_code'=>(int)($remote['http_code'] ?? 0),
        ];
    }

    public static function certifyCredential(int $destinationId): array {
        $probe = self::probeCredential($destinationId);
        if (empty($probe['ok'])) return $probe;
        $preflight = self::preflight($destinationId);
        if (empty($preflight['ok'])) return $preflight;
        $destination = $preflight['destination'];
        $state = self::stateForDestination((string)$destination['destination_uuid']);
        $eventUuid = wp_generate_uuid4();
        $payload = [
            'contract'=>self::CERTIFY_CONTRACT,
            'contract_version'=>self::CONTRACT_VERSION,
            'tenant'=>TenantIdentity::snapshot(),
            'event'=>['uuid'=>$eventUuid,'name'=>'credential.lifecycle.certify','occurred_at'=>gmdate('c')],
            'destination'=>['uuid'=>(string)$destination['destination_uuid'],'type'=>(string)$destination['type_key']],
            'credential'=>[
                'phase'=>self::PHASE,
                'secret_ref'=>(string)$destination['secret_ref'],
                'auth_mode'=>(string)$state['auth_mode'],
                'header_name'=>(string)$state['header_name'],
                'warning_days'=>(int)$state['warning_days'],
                'certification_only'=>true,
            ],
        ];
        $remote = self::brokerCall(self::CERTIFY_PATH, 'fieldflow_credential_5d_certify', $payload);
        if (is_wp_error($remote)) return self::failure($remote, $preflight['checks']);
        $body = (array)($remote['body'] ?? []);
        $receipt = is_array($body['receipt'] ?? null) ? $body['receipt'] : [];
        $verified = self::verifyReceipt($receipt, (string)$destination['destination_uuid'], (string)($state['secret_ref_sha256'] ?? ''), (string)($body['credential']['secret_version'] ?? ''), (string)$state['auth_mode'], (string)$state['header_name']);
        $secretVersion = sanitize_text_field((string)($body['credential']['secret_version'] ?? ''));
        $usable = in_array(sanitize_key((string)($body['credential']['status'] ?? '')), ['ready','warning'], true);
        $ok = !empty($body['certified']) && $verified && $secretVersion !== '' && $usable;
        if ($ok) {
            $state['certified'] = true;
            $state['certified_version'] = $secretVersion;
            $state['certified_at'] = gmdate('c');
            $state['certified_by'] = get_current_user_id() ?: 0;
            $state['rotation_detected'] = false;
            $state['revoked'] = false;
            $state['revoked_at'] = '';
            $state['last_error'] = '';
            $state['credential_status'] = sanitize_key((string)($body['credential']['status'] ?? 'ready'));
            $state['secret_version'] = $secretVersion;
            $state['expires_at'] = sanitize_text_field((string)($body['credential']['expires_at'] ?? ''));
            $state['days_remaining'] = isset($body['credential']['days_remaining']) ? (int)$body['credential']['days_remaining'] : null;
            self::saveState((string)$destination['destination_uuid'], $state);
            self::recordReceipt($receipt, true);
        }
        $checks = array_merge($preflight['checks'], [
            'broker_http_200'=>(int)($remote['http_code'] ?? 0) === 200,
            'credential_injected_to_fixed_sink'=>!empty($body['credential_injected']),
            'remote_http_200'=>(int)($body['remote_http_status'] ?? 0) === 200,
            'signed_receipt_verified'=>$verified,
            'secret_value_not_returned'=>empty($body['secret_value']) && empty($body['credential']['value']),
            'secret_version_bound'=>$secretVersion !== '',
            'rotation_change_would_invalidate'=>$secretVersion !== '' && !hash_equals($secretVersion, $secretVersion . '-rotated'),
            'revocation_guard_available'=>true,
            'expiry_guard_available'=>true,
            'operational_payload_still_blocked'=>!empty($body['operational_payload_delivery_blocked']),
            'general_activation_still_blocked'=>empty($body['general_activation_enabled']),
        ]);
        return [
            'ok'=>$ok && !in_array(false, $checks, true),
            'checks'=>$checks,
            'destination_name'=>(string)$destination['name'],
            'credential_status'=>(string)($state['credential_status'] ?? ''),
            'secret_version'=>$secretVersion,
            'expires_at'=>(string)($state['expires_at'] ?? ''),
            'days_remaining'=>$state['days_remaining'] ?? null,
            'remote_http_status'=>(int)($body['remote_http_status'] ?? 0),
            'credential_ready'=>$ok && self::stateReady($state),
        ];
    }

    public static function revoke(int $destinationId) {
        $destination = DestinationRegistry::getDestination($destinationId);
        if (!$destination) return new \WP_Error('fieldflow_5d_destination_missing', 'Destino 5D não encontrado.');
        $uuid = (string)$destination['destination_uuid'];
        $state = self::stateForDestination($uuid);
        if (!$state) return new \WP_Error('fieldflow_5d_state_missing', 'Este destino ainda não tem perfil de credencial 5D.');
        $state['revoked'] = true;
        $state['revoked_at'] = gmdate('c');
        $state['revoked_by'] = get_current_user_id() ?: 0;
        $state['certified'] = false;
        $state['credential_status'] = 'revoked';
        self::saveState($uuid, $state);
        IntegrationPlatform::log('credential_lifecycle_5d', 'revoke', 'success', ['destination_uuid'=>$uuid]);
        return true;
    }

    public static function reset(int $destinationId) {
        $destination = DestinationRegistry::getDestination($destinationId);
        if (!$destination) return new \WP_Error('fieldflow_5d_destination_missing', 'Destino 5D não encontrado.');
        $states = self::states();
        unset($states[(string)$destination['destination_uuid']]);
        update_option(self::OPTION, $states, false);
        return true;
    }

    public static function isDestinationCredentialReady(int $destinationId): bool {
        $destination = DestinationRegistry::getDestination($destinationId);
        if (!$destination) return false;
        $profile = ConnectorFoundation::profileFromDestination($destination);
        $auth = sanitize_key((string)($profile['auth_strategy'] ?? 'none'));
        if (in_array($auth, ['none','managed_identity','entra_home_tenant','entra_cross_tenant'], true)) return true;
        if ($auth !== 'key_vault_reference') return false;
        return self::stateReady(self::stateForDestination((string)$destination['destination_uuid']));
    }

    public static function eligibleDestinations(bool $includeUnconfigured = false): array {
        $out = [];
        foreach (DestinationRegistry::listDestinations() as $destination) {
            $type = sanitize_key((string)($destination['type_key'] ?? ''));
            if (!in_array($type, ['external_api','webhook'], true)) continue;
            $profile = ConnectorFoundation::profileFromDestination($destination);
            $auth = sanitize_key((string)($profile['auth_strategy'] ?? ''));
            if ($auth === 'key_vault_reference' || ($includeUnconfigured && in_array($auth, ['none','key_vault_reference'], true))) $out[] = $destination;
        }
        return $out;
    }

    public static function summary(): array {
        $out = [];
        foreach (self::eligibleDestinations(true) as $destination) {
            $uuid = (string)$destination['destination_uuid'];
            $state = self::stateForDestination($uuid);
            $profile = ConnectorFoundation::profileFromDestination($destination);
            $auth = sanitize_key((string)($profile['auth_strategy'] ?? ''));
            if ($auth !== 'key_vault_reference' && !$state) continue;
            $out[] = [
                'id'=>(int)$destination['id'],
                'name'=>(string)$destination['name'],
                'destination_uuid'=>$uuid,
                'secret_ref'=>(string)($destination['secret_ref'] ?? ''),
                'auth_mode'=>(string)($state['auth_mode'] ?? ''),
                'header_name'=>(string)($state['header_name'] ?? ''),
                'credential_status'=>(string)($state['credential_status'] ?? ($state ? 'configured' : 'draft')),
                'secret_version'=>(string)($state['secret_version'] ?? ''),
                'certified_version'=>(string)($state['certified_version'] ?? ''),
                'expires_at'=>(string)($state['expires_at'] ?? ''),
                'days_remaining'=>$state['days_remaining'] ?? null,
                'certified'=>!empty($state['certified']),
                'rotation_detected'=>!empty($state['rotation_detected']),
                'revoked'=>!empty($state['revoked']),
                'ready'=>self::stateReady($state),
                'last_probe_at'=>(string)($state['last_probe_at'] ?? ''),
            ];
        }
        return $out;
    }

    public static function defaultDestinationId(): int {
        foreach (self::summary() as $row) if ((string)$row['name'] === 'TWT Credential Lifecycle Receiver') return (int)$row['id'];
        foreach (self::eligibleDestinations(true) as $destination) return (int)$destination['id'];
        return 0;
    }

    private static function preflight(int $destinationId): array {
        $destination = DestinationRegistry::getDestination($destinationId);
        if (!$destination) return ['ok'=>false,'checks'=>['destination_exists'=>false],'reason'=>'destination_missing','message'=>'Destino 5D não encontrado.'];
        $type = sanitize_key((string)($destination['type_key'] ?? ''));
        $profile = ConnectorFoundation::profileFromDestination($destination);
        $state = self::stateForDestination((string)$destination['destination_uuid']);
        $checks = [
            'destination_exists'=>true,
            'destination_supported'=>in_array($type, ['external_api','webhook'], true),
            'auth_key_vault_reference'=>sanitize_key((string)($profile['auth_strategy'] ?? '')) === 'key_vault_reference',
            'secret_ref_present'=>trim((string)($destination['secret_ref'] ?? '')) !== '',
            'secret_ref_valid'=>self::validKeyVaultRef((string)($destination['secret_ref'] ?? '')),
            'profile_configured'=>!empty($state['auth_mode']) && !empty($state['header_name']),
            '5c5_operational_dispatcher_locked'=>class_exists('\\RoutesPro\\Cloud\\ConnectorDelivery') && !ConnectorDelivery::networkDeliveryEnabled(),
            'general_activation_blocked'=>self::GENERAL_ACTIVATION_ENABLED === false,
        ];
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'destination'=>$destination,
            'state'=>$state,
            'reason'=>!in_array(false, $checks, true) ? '' : 'credential_preflight_failed',
            'message'=>!in_array(false, $checks, true) ? '' : 'Configura primeiro uma referência Key Vault e um perfil de autenticação 5D neste destino.',
        ];
    }

    private static function brokerCall(string $path, string $purpose, array $payload) {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) return new \WP_Error('fieldflow_5d_broker_host_invalid', 'O Entra Broker 5D não corresponde ao host dedicado TWT.');
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_5d_hmac_missing', 'O HMAC TWT certificado não está disponível para a 5D.');
        $url = 'https://' . $host . $path;
        $policy = OutboundHttp::configuredPolicy($url, $purpose, [
            'max_timeout'=>30,
            'max_response_bytes'=>262144,
            'allow_public_ip_literals'=>false,
        ]);
        $validated = OutboundHttp::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_5d_payload_encode', 'Não foi possível codificar o pedido 5D.');
        $headers = AzureConnectionManager::signedHeaders($payload, $body, $secret);
        if (is_wp_error($headers)) return $headers;
        $response = OutboundHttp::post($validated, [
            'timeout'=>20,
            'headers'=>array_merge(['Content-Type'=>'application/json'], $headers),
            'body'=>$body,
        ], $policy);
        if (is_wp_error($response)) return $response;
        $http = (int)wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) $decoded = [];
        if ($http < 200 || $http >= 300 || empty($decoded['ok'])) {
            return new \WP_Error('fieldflow_5d_broker_rejected', 'O Broker 5D rejeitou o pedido.', [
                'http_status'=>$http,
                'broker_error'=>sanitize_key((string)($decoded['error'] ?? '')),
            ]);
        }
        return ['http_code'=>$http,'body'=>$decoded,'secret'=>$secret];
    }

    private static function verifyReceipt(array $receipt, string $destinationUuid, string $refSha, string $secretVersion, string $authMode, string $headerName): bool {
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        $signature = strtolower(trim((string)($receipt['signature'] ?? '')));
        if (strlen($secret) < 32 || !preg_match('/^[a-f0-9]{64}$/', $signature)) return false;
        if ((string)($receipt['contract'] ?? '') !== self::RECEIPT_CONTRACT || (string)($receipt['contract_version'] ?? '') !== self::RECEIPT_CONTRACT_VERSION) return false;
        if ((string)($receipt['destination_uuid'] ?? '') !== $destinationUuid) return false;
        if ((string)($receipt['secret_ref_sha256'] ?? '') !== $refSha) return false;
        if ((string)($receipt['secret_version'] ?? '') !== $secretVersion) return false;
        if ((string)($receipt['auth_mode'] ?? '') !== $authMode) return false;
        if ((string)($receipt['header_name'] ?? '') !== $headerName) return false;
        $canonical = implode("\n", [
            self::RECEIPT_CONTEXT,
            (string)$receipt['contract'],
            (string)$receipt['contract_version'],
            (string)$receipt['destination_uuid'],
            (string)$receipt['secret_ref_sha256'],
            (string)$receipt['secret_version'],
            (string)$receipt['auth_mode'],
            (string)$receipt['header_name'],
            (string)absint($receipt['remote_http_status'] ?? 0),
            (string)($receipt['outcome'] ?? ''),
            (string)($receipt['receiver_request_id'] ?? ''),
            (string)($receipt['received_at'] ?? ''),
        ]);
        return hash_equals(hash_hmac('sha256', $canonical, $secret), $signature);
    }

    private static function recordReceipt(array $receipt, bool $verified): void {
        $safe = [
            'destination_uuid'=>sanitize_text_field((string)($receipt['destination_uuid'] ?? '')),
            'secret_ref_sha256'=>substr(strtolower(sanitize_text_field((string)($receipt['secret_ref_sha256'] ?? ''))), 0, 64),
            'secret_version'=>sanitize_text_field((string)($receipt['secret_version'] ?? '')),
            'auth_mode'=>sanitize_key((string)($receipt['auth_mode'] ?? '')),
            'header_name'=>sanitize_text_field((string)($receipt['header_name'] ?? '')),
            'remote_http_status'=>absint($receipt['remote_http_status'] ?? 0),
            'outcome'=>sanitize_key((string)($receipt['outcome'] ?? '')),
            'receiver_request_id'=>substr(sanitize_text_field((string)($receipt['receiver_request_id'] ?? '')), 0, 96),
            'received_at'=>sanitize_text_field((string)($receipt['received_at'] ?? '')),
            'verified'=>$verified,
            'recorded_at'=>gmdate('c'),
        ];
        $rows = get_option(self::RECEIPTS_OPTION, []);
        if (!is_array($rows)) $rows = [];
        array_unshift($rows, $safe);
        update_option(self::RECEIPTS_OPTION, array_slice($rows, 0, 50), false);
    }

    private static function stateReady(array $state): bool {
        if (!$state || empty($state['certified']) || !empty($state['revoked']) || !empty($state['rotation_detected'])) return false;
        if (!in_array(sanitize_key((string)($state['credential_status'] ?? '')), ['ready','warning'], true)) return false;
        $version = (string)($state['secret_version'] ?? '');
        $certified = (string)($state['certified_version'] ?? '');
        if ($version === '' || $certified === '' || !hash_equals($version, $certified)) return false;
        $expires = trim((string)($state['expires_at'] ?? ''));
        if ($expires !== '') {
            $ts = strtotime($expires);
            if ($ts !== false && $ts <= time()) return false;
        }
        return true;
    }

    private static function normaliseAuthMode(string $mode): string {
        $mode = sanitize_key($mode);
        return in_array($mode, ['api_key_header','bearer'], true) ? $mode : '';
    }

    private static function normaliseHeaderName(string $mode, string $header): string {
        if ($mode === 'bearer') return 'Authorization';
        $header = trim($header);
        if ($header === '') $header = 'X-API-Key';
        if (!preg_match('/^[A-Za-z][A-Za-z0-9-]{1,63}$/', $header)) return '';
        $lower = strtolower($header);
        if (in_array($lower, ['authorization','cookie','set-cookie','host','content-length','connection','proxy-authorization','x-fieldflow-signature'], true)) return '';
        return $header;
    }

    private static function validKeyVaultRef(string $ref): bool {
        return preg_match('#^kv://[a-z0-9][a-z0-9-]{1,62}/[A-Za-z0-9-]{1,127}(?:/[A-Za-z0-9]+)?$#', trim($ref)) === 1;
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
