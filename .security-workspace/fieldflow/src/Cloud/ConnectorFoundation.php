<?php
namespace RoutesPro\Cloud;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5C.1 connector contracts.
 *
 * This layer is intentionally shadow-only. It validates non-sensitive target
 * references and authentication intent, enriches routing metadata and exposes
 * diagnostics. It does not resolve secrets, obtain tokens or perform outbound
 * HTTP/SQL/Power BI calls.
 */
final class ConnectorFoundation {
    public const VERSION = '1.5.0';
    public const MODE = 'shadow';

    private const TYPES = [
        'external_api',
        'webhook',
        'data_warehouse',
        'client_powerbi',
    ];

    public static function supportedTypes(): array {
        return self::TYPES;
    }

    public static function is5cType(string $type): bool {
        return in_array(sanitize_key($type), self::TYPES, true);
    }

    public static function contract(string $type): array {
        $type = sanitize_key($type);
        $contracts = [
            'external_api' => [
                'contract' => 'fieldflow.connector.external_api.v1',
                'connector' => 'external_api_shadow',
                'transport' => 'https_api',
                'target_schemes' => ['https', 'api'],
                'auth_strategies' => ['entra_cross_tenant', 'managed_identity', 'key_vault_reference', 'none'],
                'default_auth' => 'entra_cross_tenant',
                'future_phase' => '5C.5',
            ],
            'webhook' => [
                'contract' => 'fieldflow.connector.webhook.v1',
                'connector' => 'webhook_shadow',
                'transport' => 'https_webhook',
                'target_schemes' => ['https', 'webhook'],
                'auth_strategies' => ['key_vault_reference', 'entra_cross_tenant', 'managed_identity', 'none'],
                'default_auth' => 'key_vault_reference',
                'future_phase' => '5C.5',
            ],
            'data_warehouse' => [
                'contract' => 'fieldflow.connector.data_warehouse.v1',
                'connector' => 'data_warehouse_shadow',
                'transport' => 'azure_data',
                'target_schemes' => ['azure-sql', 'warehouse', 'synapse', 'fabric'],
                'auth_strategies' => ['managed_identity', 'entra_home_tenant', 'entra_cross_tenant', 'key_vault_reference'],
                'default_auth' => 'managed_identity',
                'future_phase' => '5C.3',
            ],
            'client_powerbi' => [
                'contract' => 'fieldflow.connector.client_powerbi.v1',
                'connector' => 'client_powerbi_shadow',
                'transport' => 'powerbi_reporting',
                'target_schemes' => ['powerbi', 'https'],
                'auth_strategies' => ['entra_cross_tenant', 'entra_home_tenant', 'managed_identity', 'none'],
                'default_auth' => 'entra_cross_tenant',
                'future_phase' => '5C.4',
            ],
        ];
        return (array)($contracts[$type] ?? []);
    }

    public static function normaliseAuthStrategy(string $type, string $strategy): string {
        $contract = self::contract($type);
        if (!$contract) return '';
        $strategy = sanitize_key($strategy);
        $allowed = (array)($contract['auth_strategies'] ?? []);
        if ($strategy !== '' && in_array($strategy, $allowed, true)) return $strategy;
        return (string)($contract['default_auth'] ?? 'none');
    }

    public static function targetScheme(string $targetRef): string {
        $targetRef = trim($targetRef);
        if ($targetRef === '') return '';
        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $targetRef, $matches)) {
            return strtolower((string)$matches[1]);
        }
        return '';
    }

    public static function validateTargetRef(string $type, string $targetRef, bool $required = true) {
        $type = sanitize_key($type);
        if (!self::is5cType($type)) return true;
        $targetRef = trim($targetRef);
        if ($targetRef === '') {
            return $required
                ? new \WP_Error('fieldflow_5c_target_missing', 'Indica um endpoint ou referência técnica não sensível para este conector 5C.')
                : true;
        }
        $contract = self::contract($type);
        $scheme = self::targetScheme($targetRef);
        if (!in_array($scheme, (array)($contract['target_schemes'] ?? []), true)) {
            return new \WP_Error(
                'fieldflow_5c_target_scheme_invalid',
                'A referência deste conector 5C não usa um formato permitido. Formatos: ' . implode(', ', (array)($contract['target_schemes'] ?? [])) . '://.'
            );
        }
        if ($scheme === 'https') {
            $url = esc_url_raw($targetRef, ['https']);
            if ($url === '') return new \WP_Error('fieldflow_5c_target_https_invalid', 'O endpoint HTTPS do conector 5C não é válido.');
            if ($type === 'client_powerbi') {
                $host = strtolower((string)(wp_parse_url($url, PHP_URL_HOST) ?: ''));
                if ($host !== 'app.powerbi.com' && substr($host, -12) !== '.powerbi.com') {
                    return new \WP_Error('fieldflow_5c_powerbi_host_invalid', 'Para Power BI usa uma referência powerbi:// ou um URL oficial powerbi.com.');
                }
            }
        }
        return true;
    }

    public static function validateAuthStrategy(string $type, string $strategy) {
        if (!self::is5cType($type)) return true;
        $contract = self::contract($type);
        $strategy = sanitize_key($strategy);
        if ($strategy === '') $strategy = (string)($contract['default_auth'] ?? 'none');
        if (!in_array($strategy, (array)($contract['auth_strategies'] ?? []), true)) {
            return new \WP_Error('fieldflow_5c_auth_strategy_invalid', 'A estratégia de autenticação escolhida não é suportada por este contrato 5C.');
        }
        return true;
    }

    public static function decoratePolicy(string $type, array $policy, string $authStrategy = ''): array {
        if (!self::is5cType($type)) return $policy;
        $contract = self::contract($type);
        $policy['connector_foundation_version'] = self::VERSION;
        $policy['connector_contract'] = (string)($contract['contract'] ?? '');
        $policy['connector_transport'] = (string)($contract['transport'] ?? '');
        $policy['auth_strategy'] = self::normaliseAuthStrategy($type, $authStrategy);
        $policy['execution_mode'] = self::MODE;
        $policy['shadow_only'] = true;
        $policy['delivery_state'] = 'phase_5c_shadow';
        return $policy;
    }

    public static function profileFromDestination(array $destination): array {
        $type = sanitize_key((string)($destination['type_key'] ?? ''));
        $contract = self::contract($type);
        if (!$contract) return [];
        $policy = json_decode((string)($destination['retry_policy_json'] ?? ''), true);
        if (!is_array($policy)) $policy = [];
        $executionMode = self::MODE;
        $shadowOnly = true;
        if (class_exists('\RoutesPro\Cloud\ConnectorDelivery') && ConnectorDelivery::isSupportedType($type)) {
            $executionMode = ConnectorDelivery::mode();
            $shadowOnly = $executionMode === 'shadow';
        }
        $profile = [
            'version' => sanitize_text_field((string)($policy['connector_foundation_version'] ?? self::VERSION)),
            'contract' => sanitize_text_field((string)($policy['connector_contract'] ?? ($contract['contract'] ?? ''))),
            'connector' => sanitize_key((string)($contract['connector'] ?? 'connector_5c_shadow')),
            'transport' => sanitize_key((string)($policy['connector_transport'] ?? ($contract['transport'] ?? ''))),
            'auth_strategy' => self::normaliseAuthStrategy($type, (string)($policy['auth_strategy'] ?? '')),
            'execution_mode' => $executionMode,
            'shadow_only' => $shadowOnly,
            'future_phase' => sanitize_text_field((string)($contract['future_phase'] ?? '5C')),
        ];
        if ($profile['auth_strategy'] === 'entra_cross_tenant' && class_exists('\RoutesPro\Cloud\EntraCrossTenantFoundation')) {
            $profile['entra'] = EntraCrossTenantFoundation::identityProfileFromDestination($destination);
        }
        return $profile;
    }

    public static function routeMetadata(array $destination): array {
        $profile = self::profileFromDestination($destination);
        if (!$profile) return [];
        $metadata = [
            'delivery_connector' => (string)$profile['connector'],
            'delivery_ready' => false,
            'execution_mode' => (string)$profile['execution_mode'],
            'connector_contract' => (string)$profile['contract'],
            'connector_transport' => (string)$profile['transport'],
            'auth_strategy' => (string)$profile['auth_strategy'],
            'future_phase' => (string)$profile['future_phase'],
        ];
        if (class_exists('\RoutesPro\Cloud\ConnectorDelivery') && ConnectorDelivery::isSupportedType((string)($destination['type_key'] ?? ''))) {
            $delivery55 = ConnectorDelivery::health();
            $metadata['delivery_connector'] = 'connector_delivery_5c5_foundation';
            $metadata['execution_mode'] = (string)($delivery55['mode'] ?? 'shadow');
            $metadata['queue_ready'] = !empty($delivery55['queue_enabled']);
            $metadata['delivery_ready'] = !empty($delivery55['network_delivery_enabled']);
            $metadata['kill_switch'] = !empty($delivery55['kill_switch']);
            $metadata['delivery_phase_gate'] = '5C.5';
        }
        if (!empty($profile['entra']) && is_array($profile['entra'])) {
            $metadata['entra_identity_mode'] = 'external_tenant_shadow';
            $metadata['entra_target_tenant_id'] = (string)($profile['entra']['target_tenant_id'] ?? '');
            $metadata['entra_resource_scope'] = (string)($profile['entra']['resource_scope'] ?? '');
            $metadata['entra_credential_model'] = 'workload_identity_federation';
            if (class_exists('\RoutesPro\Cloud\EntraTenantOnboarding')) {
                $onboarding = EntraTenantOnboarding::statusForDestination($destination);
                $metadata['entra_onboarding_status'] = (string)($onboarding['status'] ?? 'draft');
                $metadata['entra_identity_ready'] = !empty($onboarding['ready']);
            }
        }
        if (class_exists('\RoutesPro\Cloud\ResourceAuthorizationFoundation')
            && ResourceAuthorizationFoundation::isSupportedDestination($destination)
            && ResourceAuthorizationFoundation::usesSupportedIdentity($destination)) {
            $identity = ResourceAuthorizationFoundation::identityContext($destination);
            $resource = ResourceAuthorizationFoundation::profileFromDestination($destination);
            $metadata['entra_identity_mode'] = (string)($identity['mode'] ?? 'shadow');
            $metadata['entra_target_tenant_id'] = (string)($identity['target_tenant_id'] ?? '');
            $metadata['entra_resource_scope'] = (string)($identity['resource_scope'] ?? '');
            $metadata['entra_credential_model'] = (string)($identity['credential_model'] ?? 'workload_identity_federation');
            $metadata['entra_identity_ready'] = !empty($identity['ready']);
            $metadata['resource_authorization_mode'] = 'read_only_probe';
            $metadata['resource_authorization_kind'] = (string)($resource['kind'] ?? '');
            $metadata['resource_authorization_status'] = (string)($resource['status'] ?? 'draft');
            $metadata['resource_authorization_ready'] = !empty($resource['authorized']);
        }
        return $metadata;
    }

    public static function validateRegisteredDestination(array $destination): array {
        $type = sanitize_key((string)($destination['type_key'] ?? ''));
        $checks = [
            'type_5c' => self::is5cType($type),
            'shadow_lock' => self::MODE === 'shadow',
            'contract_present' => self::contract($type) !== [],
        ];
        if (!$checks['type_5c']) {
            return ['ok'=>false, 'checks'=>$checks, 'error'=>'O destino não pertence à Foundation 5C.1.'];
        }
        $targetCheck = self::validateTargetRef($type, (string)($destination['endpoint_ref'] ?? ''), !empty($destination['is_active']));
        $profile = self::profileFromDestination($destination);
        $authCheck = self::validateAuthStrategy($type, (string)($profile['auth_strategy'] ?? ''));
        $checks['target_ref_valid'] = !is_wp_error($targetCheck);
        $checks['auth_strategy_valid'] = !is_wp_error($authCheck);
        $checks['delivery_disabled'] = empty(self::routeMetadata($destination)['delivery_ready']);
        $checks['secret_not_resolved_by_wordpress'] = true;
        $error = '';
        if (is_wp_error($targetCheck)) $error = $targetCheck->get_error_message();
        elseif (is_wp_error($authCheck)) $error = $authCheck->get_error_message();
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'profile'=>$profile,
            'error'=>$error,
        ];
    }

    public static function health(): array {
        $contracts = [];
        foreach (self::TYPES as $type) {
            $contract = self::contract($type);
            $contracts[$type] = [
                'contract'=>(string)($contract['contract'] ?? ''),
                'transport'=>(string)($contract['transport'] ?? ''),
                'default_auth'=>(string)($contract['default_auth'] ?? ''),
                'future_phase'=>(string)($contract['future_phase'] ?? ''),
            ];
        }
        $active = 0;
        $total = 0;
        if (DestinationRegistry::ready()) {
            foreach (DestinationRegistry::listDestinations() as $destination) {
                if (!self::is5cType((string)($destination['type_key'] ?? ''))) continue;
                $total++;
                if (!empty($destination['is_active'])) $active++;
            }
        }
        $complete = count(array_filter($contracts, static function(array $contract): bool {
            return $contract['contract'] !== '' && $contract['transport'] !== '' && $contract['default_auth'] !== '';
        }));
        $delivery55 = class_exists('\RoutesPro\Cloud\ConnectorDelivery') ? ConnectorDelivery::health() : [];
        return [
            'version'=>self::VERSION,
            'status'=>$complete === count(self::TYPES) ? 'ready' : 'attention',
            'mode'=>self::MODE,
            'contracts_total'=>count(self::TYPES),
            'contracts_ready'=>$complete,
            'destinations_total'=>$total,
            'active_destinations'=>$active,
            'network_delivery_enabled'=>false,
            'secret_resolution_enabled'=>false,
            'token_acquisition_enabled'=>false,
            'delivery_5c5'=>$delivery55,
            'contracts'=>$contracts,
        ];
    }

    public static function localProbe(): array {
        $health = self::health();
        $contractsOk = (int)$health['contracts_ready'] === (int)$health['contracts_total'];
        $samples = [
            'external_api' => 'api://planned/client-erp',
            'webhook' => 'webhook://planned/client-events',
            'data_warehouse' => 'azure-sql://server.database.windows.net/database',
            'client_powerbi' => 'powerbi://workspace/client-report',
        ];
        $targetsOk = true;
        foreach ($samples as $type=>$target) {
            if (is_wp_error(self::validateTargetRef($type, $target, true))) {
                $targetsOk = false;
                break;
            }
        }
        $checks = [
            'four_connector_contracts'=>$contractsOk && (int)$health['contracts_total'] === 4,
            'shadow_mode_locked'=>self::MODE === 'shadow',
            'network_delivery_disabled'=>empty($health['network_delivery_enabled']),
            'secret_resolution_disabled'=>empty($health['secret_resolution_enabled']),
            'token_acquisition_disabled'=>empty($health['token_acquisition_enabled']),
            'technical_target_refs_valid'=>$targetsOk,
            'azure_twt_listener_unchanged'=>has_action('fieldflow_core_event', [AzureSyncFoundation::class, 'captureEvent']) !== false,
            'client_azure_5b_listener_present'=>class_exists('\\RoutesPro\\Cloud\\ClientAzureDelivery') && has_action('fieldflow_core_event', [ClientAzureDelivery::class, 'captureEvent']) !== false,
            'no_5c_sender_hook'=>has_action('fieldflow_core_event', [self::class, 'captureEvent']) === false,
            'delivery_5c5_intake_loaded'=>class_exists('\RoutesPro\Cloud\ConnectorDelivery') && has_action('fieldflow_core_event', [ConnectorDelivery::class, 'captureEvent']) !== false,
        ];
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'health'=>$health,
        ];
    }
}
