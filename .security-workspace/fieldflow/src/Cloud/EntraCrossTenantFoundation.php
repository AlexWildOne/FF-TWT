<?php
namespace RoutesPro\Cloud;

use RoutesPro\Security\OutboundHttp;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5C.2 Microsoft Entra cross-tenant identity foundation.
 *
 * This is an identity/control-plane layer only. It never delivers connector
 * payloads. WordPress stores only non-secret broker metadata and destination
 * tenant/scope intent. Token acquisition happens exclusively in the dedicated
 * TWT Azure Entra Broker through workload identity federation.
 */
final class EntraCrossTenantFoundation {
    public const VERSION = '1.2.0';
    public const MODE = 'shadow';
    public const OPTION = 'fieldflow_entra_cross_tenant_settings';
    public const BROKER_HOST = 'func-fieldflow-entrabroker-prod-eb978f11.azurewebsites.net';
    public const TOKEN_PROBE_PATH = '/api/v1/token-probe';

    private const BUILTIN_SCOPES = [
        'https://management.azure.com/.default',
        'https://database.windows.net/.default',
        'https://analysis.windows.net/powerbi/api/.default',
        'https://api.fabric.microsoft.com/.default',
    ];

    public static function defaults(): array {
        return [
            'broker_endpoint' => '',
            'broker_client_id' => '',
            'home_tenant_id' => '',
            'timeout' => 20,
        ];
    }

    public static function settings(): array {
        $stored = get_option(self::OPTION, []);
        $settings = wp_parse_args(is_array($stored) ? $stored : [], self::defaults());
        $settings['broker_endpoint'] = self::normaliseBrokerEndpoint((string)($settings['broker_endpoint'] ?? ''));
        $settings['broker_client_id'] = self::normaliseGuid((string)($settings['broker_client_id'] ?? ''));
        $settings['home_tenant_id'] = self::normaliseGuid((string)($settings['home_tenant_id'] ?? ''));
        $settings['timeout'] = max(5, min(45, absint($settings['timeout'] ?? 20)));
        return $settings;
    }

    public static function save(array $input) {
        $rawEndpoint = trim((string)($input['broker_endpoint'] ?? ''));
        $endpoint = self::normaliseBrokerEndpoint($rawEndpoint);
        if ($rawEndpoint !== '' && $endpoint === '') {
            return new \WP_Error('fieldflow_5c2_broker_endpoint_invalid', 'O Entra Broker 5C.2 tem de usar o endpoint HTTPS dedicado da TWT.');
        }

        $rawClientId = trim((string)($input['broker_client_id'] ?? ''));
        $clientId = self::normaliseGuid($rawClientId);
        if ($rawClientId !== '' && $clientId === '') {
            return new \WP_Error('fieldflow_5c2_client_id_invalid', 'O Application (client) ID do Entra Broker não é um GUID válido.');
        }

        $rawTenantId = trim((string)($input['home_tenant_id'] ?? ''));
        $tenantId = self::normaliseGuid($rawTenantId);
        if ($rawTenantId !== '' && $tenantId === '') {
            return new \WP_Error('fieldflow_5c2_home_tenant_invalid', 'O Directory (tenant) ID TWT não é um GUID válido.');
        }

        $any = $endpoint !== '' || $clientId !== '' || $tenantId !== '';
        if ($any && ($endpoint === '' || $clientId === '' || $tenantId === '')) {
            return new \WP_Error('fieldflow_5c2_config_incomplete', 'Preenche endpoint, Application client ID e Directory tenant ID em conjunto. Nenhum deles é secreto.');
        }

        $settings = [
            'broker_endpoint' => $endpoint,
            'broker_client_id' => $clientId,
            'home_tenant_id' => $tenantId,
            'timeout' => max(5, min(45, absint($input['timeout'] ?? 20))),
        ];
        update_option(self::OPTION, $settings, false);
        return $settings;
    }

    public static function configured(): bool {
        $settings = self::settings();
        if ($settings['broker_endpoint'] === '' || $settings['broker_client_id'] === '' || $settings['home_tenant_id'] === '') return false;
        $azure = AzureConnectionManager::settings();
        return strlen(trim((string)($azure['azure_cloud_hmac_secret'] ?? ''))) >= 32;
    }

    public static function status(): array {
        $settings = self::settings();
        $azure = AzureConnectionManager::settings();
        $secretConfigured = strlen(trim((string)($azure['azure_cloud_hmac_secret'] ?? ''))) >= 32;
        $entraDestinations = 0;
        $entraDestinationsReady = 0;
        if (DestinationRegistry::ready()) {
            foreach (DestinationRegistry::listDestinations() as $destination) {
                if (!ConnectorFoundation::is5cType((string)($destination['type_key'] ?? ''))) continue;
                $profile = ConnectorFoundation::profileFromDestination($destination);
                if ((string)($profile['auth_strategy'] ?? '') !== 'entra_cross_tenant') continue;
                $entraDestinations++;
                $identity = self::identityProfileFromDestination($destination);
                if (!is_wp_error(self::validateTenantId((string)($identity['target_tenant_id'] ?? ''), true))
                    && !is_wp_error(self::validateResourceScope((string)($identity['resource_scope'] ?? ''), true))) {
                    $entraDestinationsReady++;
                }
            }
        }
        return [
            'version' => self::VERSION,
            'status' => self::configured() ? 'ready' : 'foundation_ready',
            'mode' => self::MODE,
            'configured' => self::configured(),
            'broker_endpoint' => (string)$settings['broker_endpoint'],
            'broker_host' => $settings['broker_endpoint'] !== '' ? (string)(wp_parse_url((string)$settings['broker_endpoint'], PHP_URL_HOST) ?: '') : '',
            'broker_client_id' => (string)$settings['broker_client_id'],
            'home_tenant_id' => (string)$settings['home_tenant_id'],
            'hmac_reused' => true,
            'hmac_secret_configured' => $secretConfigured,
            'credential_model' => 'workload_identity_federation',
            'managed_identity_required' => 'user_assigned',
            'wordpress_client_secret_stored' => false,
            'wordpress_certificate_stored' => false,
            'token_acquisition_location' => 'azure_entra_broker',
            'network_delivery_enabled' => false,
            'control_plane_probe_enabled' => self::configured(),
            'external_tenant_onboarding_loaded' => class_exists('\RoutesPro\Cloud\EntraTenantOnboarding'),
            'entra_destinations' => $entraDestinations,
            'entra_destinations_ready' => $entraDestinationsReady,
            'timeout' => (int)$settings['timeout'],
        ];
    }

    public static function normaliseGuid(string $value): string {
        $value = strtolower(trim($value));
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value) ? $value : '';
    }

    public static function validateTenantId(string $tenantId, bool $required = true) {
        $tenantId = trim($tenantId);
        if ($tenantId === '') return $required ? new \WP_Error('fieldflow_5c2_target_tenant_missing', 'Indica o Directory (tenant) ID do cliente para Entra cross-tenant.') : true;
        if (self::normaliseGuid($tenantId) === '') {
            return new \WP_Error('fieldflow_5c2_target_tenant_invalid', 'O tenant Entra do cliente tem de ser um GUID específico. Não são aceites common ou organizations.');
        }
        return true;
    }

    public static function defaultScopeForType(string $type): string {
        $type = sanitize_key($type);
        if ($type === 'data_warehouse') return 'https://database.windows.net/.default';
        if ($type === 'client_powerbi') return 'https://analysis.windows.net/powerbi/api/.default';
        return '';
    }

    public static function validateResourceScope(string $scope, bool $required = true) {
        $scope = trim($scope);
        if ($scope === '') return $required ? new \WP_Error('fieldflow_5c2_scope_missing', 'Indica o resource scope Entra terminado em /.default.') : true;
        if (in_array($scope, self::BUILTIN_SCOPES, true)) return true;
        if (preg_match('#^api://[A-Za-z0-9._-]{3,180}/\.default$#', $scope)) return true;
        return new \WP_Error('fieldflow_5c2_scope_invalid', 'O resource scope 5C.2 não é permitido. Usa Azure SQL, Power BI, Fabric, Azure Resource Manager ou api://.../.default.');
    }

    public static function identityProfileFromDestination(array $destination): array {
        $policy = json_decode((string)($destination['retry_policy_json'] ?? ''), true);
        if (!is_array($policy)) $policy = [];
        $type = sanitize_key((string)($destination['type_key'] ?? ''));
        $scope = trim((string)($policy['entra_resource_scope'] ?? ''));
        if ($scope === '') $scope = self::defaultScopeForType($type);
        return [
            'version' => sanitize_text_field((string)($policy['entra_foundation_version'] ?? self::VERSION)),
            'target_tenant_id' => self::normaliseGuid((string)($policy['entra_target_tenant_id'] ?? '')),
            'resource_scope' => sanitize_text_field($scope),
            'credential_model' => 'workload_identity_federation',
            'execution_mode' => self::MODE,
            'secretless' => true,
        ];
    }

    public static function decoratePolicy(array $policy, string $targetTenantId, string $resourceScope): array {
        $tenantId = self::normaliseGuid($targetTenantId);
        $scope = trim($resourceScope);
        $policy['entra_foundation_version'] = self::VERSION;
        $policy['entra_target_tenant_id'] = $tenantId;
        $policy['entra_resource_scope'] = sanitize_text_field($scope);
        $policy['entra_credential_model'] = 'workload_identity_federation';
        $policy['entra_execution_mode'] = self::MODE;
        $policy['entra_secretless'] = true;
        return $policy;
    }

    public static function validateDestinationIdentity(array $destination, bool $required = true): array {
        $profile = ConnectorFoundation::profileFromDestination($destination);
        $checks = [
            'connector_5c' => ConnectorFoundation::is5cType((string)($destination['type_key'] ?? '')),
            'entra_auth_selected' => (string)($profile['auth_strategy'] ?? '') === 'entra_cross_tenant',
            'shadow_lock' => self::MODE === 'shadow',
            'secretless_model' => true,
            'broker_control_plane_only' => true,
        ];
        if (!$checks['connector_5c'] || !$checks['entra_auth_selected']) {
            return ['ok'=>false,'checks'=>$checks,'error'=>'Este destino não usa Microsoft Entra cross-tenant.'];
        }
        $identity = self::identityProfileFromDestination($destination);
        $tenantCheck = self::validateTenantId((string)$identity['target_tenant_id'], $required);
        $scopeCheck = self::validateResourceScope((string)$identity['resource_scope'], $required);
        $checks['target_tenant_valid'] = !is_wp_error($tenantCheck);
        $checks['resource_scope_valid'] = !is_wp_error($scopeCheck);
        $checks['connector_delivery_still_disabled'] = empty(ConnectorFoundation::routeMetadata($destination)['delivery_ready']);
        $error = '';
        if (is_wp_error($tenantCheck)) $error = $tenantCheck->get_error_message();
        elseif (is_wp_error($scopeCheck)) $error = $scopeCheck->get_error_message();
        return [
            'ok' => !in_array(false, $checks, true),
            'checks' => $checks,
            'identity' => $identity,
            'broker' => self::status(),
            'error' => $error,
        ];
    }

    public static function localProbe(): array {
        $status = self::status();
        $settings = self::settings();
        $checks = [
            'shadow_mode_locked' => self::MODE === 'shadow',
            'connector_delivery_disabled' => empty($status['network_delivery_enabled']),
            'workload_identity_federation_model' => (string)$status['credential_model'] === 'workload_identity_federation',
            'user_assigned_identity_required' => (string)$status['managed_identity_required'] === 'user_assigned',
            'no_client_secret_in_wordpress' => empty($status['wordpress_client_secret_stored']),
            'no_certificate_in_wordpress' => empty($status['wordpress_certificate_stored']),
            'hmac_twt_available' => !empty($status['hmac_secret_configured']),
            'broker_endpoint_shape' => $settings['broker_endpoint'] === '' || self::normaliseBrokerEndpoint((string)$settings['broker_endpoint']) !== '',
            'broker_client_id_shape' => $settings['broker_client_id'] === '' || self::normaliseGuid((string)$settings['broker_client_id']) !== '',
            'home_tenant_id_shape' => $settings['home_tenant_id'] === '' || self::normaliseGuid((string)$settings['home_tenant_id']) !== '',
            'sql_scope_allowed' => !is_wp_error(self::validateResourceScope('https://database.windows.net/.default', true)),
            'powerbi_scope_allowed' => !is_wp_error(self::validateResourceScope('https://analysis.windows.net/powerbi/api/.default', true)),
            'custom_api_scope_allowed' => !is_wp_error(self::validateResourceScope('api://00000000-0000-4000-8000-000000000001/.default', true)),
            'external_tenant_onboarding_control_plane' => class_exists('\RoutesPro\Cloud\EntraTenantOnboarding'),
            'resource_authorization_5c3_loaded' => class_exists('\RoutesPro\Cloud\ResourceAuthorizationFoundation'),
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'status'=>$status];
    }

    public static function probeHomeTenant() {
        $settings = self::settings();
        if (!self::configured()) return new \WP_Error('fieldflow_5c2_broker_not_configured', 'Configura primeiro o Entra Broker 5C.2 com os valores seguros devolvidos pelo Azure.');
        return self::sendTokenProbe((string)$settings['home_tenant_id'], 'https://management.azure.com/.default', 'home_tenant');
    }

    public static function probeDestination(array $destination) {
        $validation = self::validateDestinationIdentity($destination, true);
        if (empty($validation['ok'])) return new \WP_Error('fieldflow_5c2_destination_invalid', (string)($validation['error'] ?? 'O destino Entra 5C.2 não está pronto.'));
        if (!self::configured()) return new \WP_Error('fieldflow_5c2_broker_not_configured', 'Configura primeiro o Entra Broker 5C.2.');
        $identity = (array)($validation['identity'] ?? []);
        return self::sendTokenProbe((string)$identity['target_tenant_id'], (string)$identity['resource_scope'], 'destination');
    }

    private static function sendTokenProbe(string $targetTenantId, string $resourceScope, string $probeKind) {
        $config = self::config();
        if (is_wp_error($config)) return $config;
        $tenantCheck = self::validateTenantId($targetTenantId, true);
        if (is_wp_error($tenantCheck)) return $tenantCheck;
        $scopeCheck = self::validateResourceScope($resourceScope, true);
        if (is_wp_error($scopeCheck)) return $scopeCheck;

        $identity = TenantIdentity::snapshot();
        $eventUuid = wp_generate_uuid4();
        $payload = [
            'contract' => 'fieldflow.entra.cross_tenant.probe',
            'contract_version' => '1.0',
            'tenant' => $identity,
            'event' => [
                'uuid' => $eventUuid,
                'name' => 'entra.cross_tenant.probe',
                'occurred_at' => gmdate('c'),
            ],
            'target' => [
                'tenant_id' => self::normaliseGuid($targetTenantId),
                'scope' => $resourceScope,
            ],
            'probe' => [
                'kind' => sanitize_key($probeKind),
                'fieldflow_version' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            ],
        ];
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_5c2_payload_encode', 'Não foi possível codificar o probe Entra 5C.2.');
        $headers = AzureConnectionManager::signedHeaders($payload, $body, (string)$config['secret']);
        if (is_wp_error($headers)) return $headers;
        return OutboundHttp::post((string)$config['url'], [
            'timeout' => (int)$config['timeout'],
            'headers' => array_merge(['Content-Type'=>'application/json'], $headers),
            'body' => $body,
        ], (array)$config['policy']);
    }

    private static function config() {
        $settings = self::settings();
        $url = (string)$settings['broker_endpoint'];
        if ($url === '') return new \WP_Error('fieldflow_5c2_endpoint_missing', 'O endpoint do Entra Broker 5C.2 ainda não está configurado.');
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_5c2_hmac_missing', 'O HMAC TWT certificado não está disponível para autenticar o Entra Broker.');
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_entra_broker', [
            'max_timeout' => 45,
            'max_response_bytes' => 524288,
        ]);
        $validated = OutboundHttp::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;
        return ['url'=>$validated,'secret'=>$secret,'timeout'=>(int)$settings['timeout'],'policy'=>$policy];
    }

    private static function normaliseBrokerEndpoint(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        $url = esc_url_raw($value, ['https']);
        if ($url === '' || strtolower((string)(wp_parse_url($url, PHP_URL_SCHEME) ?: '')) !== 'https') return '';
        $host = strtolower((string)(wp_parse_url($url, PHP_URL_HOST) ?: ''));
        $port = (int)(wp_parse_url($url, PHP_URL_PORT) ?: 443);
        $path = (string)(wp_parse_url($url, PHP_URL_PATH) ?: '');
        if ($host !== self::BROKER_HOST || $port !== 443 || rtrim($path, '/') !== self::TOKEN_PROBE_PATH) return '';
        return substr($url, 0, 500);
    }
}
