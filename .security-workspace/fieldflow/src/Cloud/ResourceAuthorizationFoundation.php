<?php
namespace RoutesPro\Cloud;

use RoutesPro\Security\OutboundHttp;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5C.3 resource authorization foundation.
 *
 * Identity and resource authorization are deliberately separated:
 * - 5C.2 proves the TWT broker identity can obtain an app-only token in the
 *   customer's Microsoft Entra tenant.
 * - 5C.3 proves that same identity is actually authorized to a concrete,
 *   read-only resource target.
 *
 * No connector delivery is enabled by this class. WordPress stores only public
 * resource identifiers and probe state. Access tokens remain inside the Azure
 * Entra Broker and are never returned, persisted or logged.
 */
final class ResourceAuthorizationFoundation {
    public const VERSION = '1.2.0';
    public const MODE = 'shadow';
    public const RESOURCE_PROBE_PATH = '/api/v1/resource-probe';
    public const CONTRACT = 'fieldflow.entra.resource_authorization.probe';
    public const CONTRACT_VERSION = '1.0';

    private const STATUSES = ['draft','configured','probe_failed','authorized'];

    public static function supportedKinds(): array {
        return [
            'azure_sql' => [
                'label' => 'Azure SQL',
                'types' => ['data_warehouse'],
                'scope' => 'https://database.windows.net/.default',
                'probe' => 'SELECT 1, read-only',
            ],
            'powerbi_workspace' => [
                'label' => 'Power BI Workspace',
                'types' => ['client_powerbi'],
                'scope' => 'https://analysis.windows.net/powerbi/api/.default',
                'probe' => 'GET Power BI workspace, read-only',
            ],
            'fabric_workspace' => [
                'label' => 'Microsoft Fabric Workspace',
                'types' => ['client_powerbi'],
                'scope' => 'https://api.fabric.microsoft.com/.default',
                'probe' => 'GET Fabric workspace, read-only',
            ],
        ];
    }

    public static function kindForDestination(array $destination): string {
        $type = sanitize_key((string)($destination['type_key'] ?? ''));
        if ($type === 'data_warehouse') return 'azure_sql';
        if ($type === 'client_powerbi') {
            $policy = self::policy($destination);
            $scope = trim((string)($policy['entra_resource_scope'] ?? ''));
            if ($scope === 'https://api.fabric.microsoft.com/.default') return 'fabric_workspace';
            return 'powerbi_workspace';
        }
        return '';
    }

    public static function isSupportedDestination(array $destination): bool {
        return self::kindForDestination($destination) !== '';
    }

    public static function profileFromDestination(array $destination): array {
        $policy = self::policy($destination);
        $kind = sanitize_key((string)($policy['resource_auth_kind'] ?? self::kindForDestination($destination)));
        if (!isset(self::supportedKinds()[$kind])) $kind = self::kindForDestination($destination);
        $status = sanitize_key((string)($policy['resource_auth_status'] ?? 'draft'));
        if (!in_array($status, self::STATUSES, true)) $status = 'draft';
        return [
            'version' => sanitize_text_field((string)($policy['resource_auth_version'] ?? self::VERSION)),
            'kind' => $kind,
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'authorized' => $status === 'authorized' && !empty($policy['resource_auth_verified']),
            'verified' => !empty($policy['resource_auth_verified']),
            'verified_at' => absint($policy['resource_auth_verified_at'] ?? 0),
            'last_probe_at' => absint($policy['resource_auth_last_probe_at'] ?? 0),
            'last_http_status' => absint($policy['resource_auth_last_http_status'] ?? 0),
            'last_remote_http_status' => absint($policy['resource_auth_last_remote_http_status'] ?? 0),
            'last_probe_code' => sanitize_key((string)($policy['resource_auth_last_probe_code'] ?? '')),
            'error' => sanitize_text_field((string)($policy['resource_auth_error'] ?? '')),
            'sql_server' => self::normaliseSqlServer((string)($policy['resource_auth_sql_server'] ?? '')),
            'sql_database' => self::normaliseSqlDatabase((string)($policy['resource_auth_sql_database'] ?? '')),
            'powerbi_workspace_id' => EntraCrossTenantFoundation::normaliseGuid((string)($policy['resource_auth_powerbi_workspace_id'] ?? '')),
            'delivery_enabled' => false,
            'read_only_probe' => true,
        ];
    }

    public static function summary(?array $destinations = null): array {
        if ($destinations === null) $destinations = DestinationRegistry::listDestinations();
        $summary = [
            'version' => self::VERSION,
            'mode' => self::MODE,
            'control_plane_ready' => class_exists('\RoutesPro\Cloud\EntraTenantOnboarding') && EntraCrossTenantFoundation::configured(),
            'delivery_enabled' => false,
            'read_only_probe' => true,
            'eligible' => 0,
            'scope_ready' => 0,
            'configured' => 0,
            'authorized' => 0,
            'failed' => 0,
            'home_eligible' => 0,
            'external_eligible' => 0,
        ];
        foreach ((array)$destinations as $destination) {
            if (!self::isEligible($destination)) continue;
            $summary['eligible']++;
            $identityContext = self::identityContext($destination);
            if ((string)($identityContext['mode'] ?? '') === 'home_tenant') $summary['home_eligible']++;
            elseif ((string)($identityContext['mode'] ?? '') === 'external_tenant') $summary['external_eligible']++;
            if (!is_wp_error(self::validateIdentityScopeForKind($destination))) $summary['scope_ready']++;
            $profile = self::profileFromDestination($destination);
            $validation = self::validateConfiguration($destination, false);
            if (!empty($validation['ok']) && !empty($validation['configured'])) $summary['configured']++;
            if (!empty($profile['authorized'])) $summary['authorized']++;
            elseif ($profile['status'] === 'probe_failed') $summary['failed']++;
        }
        return $summary;
    }

    public static function localProbe(): array {
        $checks = [
            'shadow_mode_locked' => self::MODE === 'shadow',
            'delivery_disabled' => true,
            'identity_gate_loaded' => class_exists('\RoutesPro\Cloud\EntraTenantOnboarding'),
            'broker_configured' => EntraCrossTenantFoundation::configured(),
            'tokens_never_stored_in_wordpress' => true,
            'client_secrets_never_stored_in_wordpress' => true,
            'azure_sql_scope_exact' => (string)(self::supportedKinds()['azure_sql']['scope'] ?? '') === 'https://database.windows.net/.default',
            'powerbi_scope_exact' => (string)(self::supportedKinds()['powerbi_workspace']['scope'] ?? '') === 'https://analysis.windows.net/powerbi/api/.default',
            'fabric_scope_exact' => (string)(self::supportedKinds()['fabric_workspace']['scope'] ?? '') === 'https://api.fabric.microsoft.com/.default',
            'sql_target_validator' => self::normaliseSqlServer('example.database.windows.net') === 'example.database.windows.net',
            'powerbi_workspace_validator' => EntraCrossTenantFoundation::normaliseGuid('00000000-0000-4000-8000-000000000001') !== '',
            'home_tenant_resource_identity_supported' => true,
            'external_tenant_resource_identity_supported' => class_exists('\RoutesPro\Cloud\EntraTenantOnboarding'),
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'summary'=>self::summary()];
    }

    public static function configureDestination(int $id, array $input) {
        global $wpdb;
        $destination = DestinationRegistry::getDestination($id);
        if (!$destination) return new \WP_Error('fieldflow_5c3_destination_not_found', 'Destino não encontrado.');
        if (!self::isEligible($destination)) {
            return new \WP_Error('fieldflow_5c3_identity_not_ready', 'A identidade deste destino ainda não está pronta para a autorização do recurso 5C.3.');
        }
        $kind = self::kindForDestination($destination);
        if ($kind === '') return new \WP_Error('fieldflow_5c3_type_unsupported', 'Este tipo de destino ainda não tem probe de autorização 5C.3.');

        $scopeCheck = self::validateIdentityScopeForKind($destination);
        if (is_wp_error($scopeCheck)) return $scopeCheck;

        $changes = [
            'resource_auth_version' => self::VERSION,
            'resource_auth_kind' => $kind,
            'resource_auth_status' => 'configured',
            'resource_auth_verified' => false,
            'resource_auth_verified_at' => 0,
            'resource_auth_last_probe_at' => 0,
            'resource_auth_last_http_status' => 0,
            'resource_auth_last_remote_http_status' => 0,
            'resource_auth_last_probe_code' => '',
            'resource_auth_error' => '',
            'resource_auth_delivery_enabled' => false,
            'resource_auth_read_only_probe' => true,
            'resource_auth_sql_server' => '',
            'resource_auth_sql_database' => '',
            'resource_auth_powerbi_workspace_id' => '',
        ];

        if ($kind === 'azure_sql') {
            $server = self::normaliseSqlServer((string)($input['sql_server'] ?? ''));
            $database = self::normaliseSqlDatabase((string)($input['sql_database'] ?? ''));
            if ($server === '') return new \WP_Error('fieldflow_5c3_sql_server_invalid', 'Indica o servidor Azure SQL completo, por exemplo cliente.database.windows.net.');
            if ($database === '') return new \WP_Error('fieldflow_5c3_sql_database_invalid', 'Indica o nome da base de dados Azure SQL.');
            $changes['resource_auth_sql_server'] = $server;
            $changes['resource_auth_sql_database'] = $database;
        } elseif (in_array($kind, ['powerbi_workspace','fabric_workspace'], true)) {
            $workspace = EntraCrossTenantFoundation::normaliseGuid((string)($input['powerbi_workspace_id'] ?? ''));
            if ($workspace === '') return new \WP_Error('fieldflow_5c3_powerbi_workspace_invalid', 'Indica um Workspace ID Power BI/Fabric válido.');
            $changes['resource_auth_powerbi_workspace_id'] = $workspace;

        }

        $policy = self::policy($destination);
        foreach ($changes as $key=>$value) $policy[$key] = $value;
        $ok = $wpdb->update(DestinationRegistry::destinationsTable(), [
            'retry_policy_json' => wp_json_encode($policy, JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql', true),
        ], ['id'=>$id]);
        if ($ok === false) return new \WP_Error('fieldflow_5c3_config_update_failed', 'Não foi possível guardar a autorização 5C.3 do recurso.');
        return true;
    }

    public static function resetDestination(int $id) {
        global $wpdb;
        $destination = DestinationRegistry::getDestination($id);
        if (!$destination) return new \WP_Error('fieldflow_5c3_destination_not_found', 'Destino não encontrado.');
        $policy = self::policy($destination);
        foreach ([
            'resource_auth_status'=>'draft',
            'resource_auth_verified'=>false,
            'resource_auth_verified_at'=>0,
            'resource_auth_last_probe_at'=>0,
            'resource_auth_last_http_status'=>0,
            'resource_auth_last_remote_http_status'=>0,
            'resource_auth_last_probe_code'=>'',
            'resource_auth_error'=>'',
            'resource_auth_delivery_enabled'=>false,
        ] as $key=>$value) $policy[$key] = $value;
        $ok = $wpdb->update(DestinationRegistry::destinationsTable(), [
            'retry_policy_json'=>wp_json_encode($policy, JSON_UNESCAPED_SLASHES),
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$id]);
        return $ok === false ? new \WP_Error('fieldflow_5c3_reset_failed', 'Não foi possível reiniciar o estado 5C.3.') : true;
    }

    public static function probeBrokerHealth() {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) return new \WP_Error('fieldflow_5c3_broker_host_invalid', 'O Entra Broker configurado não corresponde ao host dedicado TWT.');
        $url = 'https://' . $host . '/api/v1/health';
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_entra_resource_health', [
            'max_timeout'=>20,
            'max_response_bytes'=>131072,
            'allow_public_ip_literals'=>false,
        ]);
        return OutboundHttp::get($url, ['timeout'=>20], $policy);
    }

    public static function probeDestination(array $destination) {
        if (!self::isEligible($destination)) return new \WP_Error('fieldflow_5c3_identity_not_ready', 'A identidade deste destino ainda não está READY para a 5C.3.');
        $validation = self::validateConfiguration($destination, true);
        if (empty($validation['ok'])) return new \WP_Error('fieldflow_5c3_resource_invalid', (string)($validation['error'] ?? 'Configuração 5C.3 inválida.'));
        $profile = (array)($validation['profile'] ?? []);
        $identity = self::identityContext($destination);
        $config = self::brokerConfig();
        if (is_wp_error($config)) return $config;

        $resource = ['kind'=>(string)$profile['kind']];
        if ($profile['kind'] === 'azure_sql') {
            $resource['server'] = (string)$profile['sql_server'];
            $resource['database'] = (string)$profile['sql_database'];
        } elseif (in_array($profile['kind'], ['powerbi_workspace','fabric_workspace'], true)) {
            $resource['workspace_id'] = (string)$profile['powerbi_workspace_id'];
        }

        $eventUuid = wp_generate_uuid4();
        $payload = [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'tenant' => TenantIdentity::snapshot(),
            'event' => [
                'uuid' => $eventUuid,
                'name' => 'entra.resource_authorization.probe',
                'occurred_at' => gmdate('c'),
            ],
            'destination' => [
                'uuid' => sanitize_text_field((string)($destination['destination_uuid'] ?? '')),
                'type' => sanitize_key((string)($destination['type_key'] ?? '')),
            ],
            'target' => [
                'tenant_id' => (string)($identity['target_tenant_id'] ?? ''),
                'scope' => (string)($identity['resource_scope'] ?? ''),
            ],
            'resource' => $resource,
            'probe' => [
                'read_only' => true,
                'delivery_enabled' => false,
                'fieldflow_version' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            ],
        ];
        $body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_5c3_payload_encode', 'Não foi possível codificar o probe 5C.3.');
        $headers = AzureConnectionManager::signedHeaders($payload, $body, (string)$config['secret']);
        if (is_wp_error($headers)) return $headers;

        $response = OutboundHttp::post((string)$config['url'], [
            'timeout'=>(int)$config['timeout'],
            'headers'=>array_merge(['Content-Type'=>'application/json'], $headers),
            'body'=>$body,
        ], (array)$config['policy']);
        if (is_wp_error($response)) {
            self::recordProbeResult($destination, false, 0, 0, 'broker_transport_error', $response->get_error_message());
            return $response;
        }
        $http = (int)wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) $decoded = [];
        $authorized = $http >= 200 && $http < 300 && !empty($decoded['ok']) && !empty($decoded['authorized']);
        $remoteHttp = absint($decoded['remote_http_status'] ?? 0);
        $code = sanitize_key((string)($decoded['error'] ?? ($authorized ? 'authorized' : 'resource_probe_failed')));
        $error = $authorized ? '' : sanitize_text_field((string)($decoded['message'] ?? $decoded['error'] ?? 'O recurso não autorizou o probe read-only.'));
        self::recordProbeResult($destination, $authorized, $http, $remoteHttp, $code, $error);

        if (!$authorized) {
            return new \WP_Error('fieldflow_5c3_probe_failed', $error !== '' ? $error : 'O recurso não autorizou o probe 5C.3.', [
                'http_status'=>$http,
                'probe_code'=>$code,
                'remote_http_status'=>$remoteHttp,
            ]);
        }
        return [
            'ok'=>true,
            'authorized'=>true,
            'kind'=>sanitize_key((string)($decoded['resource_kind'] ?? $profile['kind'])),
            'http_status'=>$http,
            'remote_http_status'=>$remoteHttp,
            'probe_code'=>sanitize_key((string)($decoded['probe_code'] ?? 'authorized')),
            'token_exposed'=>false,
            'delivery_performed'=>false,
        ];
    }

    public static function validateConfiguration(array $destination, bool $required = true): array {
        $profile = self::profileFromDestination($destination);
        $kind = (string)($profile['kind'] ?? '');
        $configured = false;
        $error = '';
        if ($kind === 'azure_sql') {
            $configured = $profile['sql_server'] !== '' && $profile['sql_database'] !== '';
            if ($required && !$configured) $error = 'Configura servidor e base de dados Azure SQL.';
        } elseif (in_array($kind, ['powerbi_workspace','fabric_workspace'], true)) {
            $configured = $profile['powerbi_workspace_id'] !== '';
            if ($required && !$configured) $error = 'Configura o Workspace ID Power BI/Fabric.';
        } else {
            $error = 'Tipo de recurso 5C.3 não suportado.';
        }
        return ['ok'=>$error === '','configured'=>$configured,'profile'=>$profile,'error'=>$error];
    }

    public static function usesSupportedIdentity(array $destination): bool {
        if (!self::isSupportedDestination($destination)) return false;
        if (!ConnectorFoundation::is5cType((string)($destination['type_key'] ?? ''))) return false;
        $connector = ConnectorFoundation::profileFromDestination($destination);
        return in_array((string)($connector['auth_strategy'] ?? ''), ['entra_cross_tenant','entra_home_tenant'], true);
    }

    public static function identityContext(array $destination): array {
        $connector = ConnectorFoundation::profileFromDestination($destination);
        $strategy = (string)($connector['auth_strategy'] ?? '');
        $kind = self::kindForDestination($destination);
        $expectedScope = (string)(self::supportedKinds()[$kind]['scope'] ?? '');

        if ($strategy === 'entra_cross_tenant') {
            $identity = EntraCrossTenantFoundation::identityProfileFromDestination($destination);
            $onboarding = class_exists('\RoutesPro\Cloud\EntraTenantOnboarding')
                ? EntraTenantOnboarding::statusForDestination($destination)
                : ['ready'=>false,'status_label'=>'DRAFT'];
            return [
                'mode'=>'external_tenant',
                'strategy'=>$strategy,
                'ready'=>!empty($onboarding['ready']),
                'status_label'=>(string)($onboarding['status_label'] ?? 'DRAFT'),
                'target_tenant_id'=>(string)($identity['target_tenant_id'] ?? ''),
                'resource_scope'=>(string)($identity['resource_scope'] ?? ''),
                'credential_model'=>'workload_identity_federation',
            ];
        }

        if ($strategy === 'entra_home_tenant' && $kind !== '') {
            $settings = EntraCrossTenantFoundation::settings();
            $tenantId = EntraCrossTenantFoundation::normaliseGuid((string)($settings['home_tenant_id'] ?? ''));
            return [
                'mode'=>'home_tenant',
                'strategy'=>$strategy,
                'ready'=>EntraCrossTenantFoundation::configured() && $tenantId !== '' && $expectedScope !== '',
                'status_label'=>'HOME TWT',
                'target_tenant_id'=>$tenantId,
                'resource_scope'=>$expectedScope,
                'credential_model'=>'workload_identity_federation',
            ];
        }

        return [
            'mode'=>'unsupported',
            'strategy'=>$strategy,
            'ready'=>false,
            'status_label'=>'NÃO SUPORTADO',
            'target_tenant_id'=>'',
            'resource_scope'=>'',
            'credential_model'=>'',
        ];
    }

    public static function isEligible(array $destination): bool {
        if (!self::usesSupportedIdentity($destination)) return false;
        $identity = self::identityContext($destination);
        return !empty($identity['ready']);
    }

    public static function validateIdentityScopeForKind(array $destination) {
        $kind = self::kindForDestination($destination);
        if ($kind === '') return new \WP_Error('fieldflow_5c3_type_unsupported', 'Este tipo de destino ainda não tem autorização de recurso 5C.3.');
        $identity = self::identityContext($destination);
        if (empty($identity['ready'])) return new \WP_Error('fieldflow_5c3_identity_not_ready', 'A identidade deste destino ainda não está pronta.');
        $scope = trim((string)($identity['resource_scope'] ?? ''));
        if ($scope === '') return new \WP_Error('fieldflow_5c3_scope_missing', 'Não existe resource scope Microsoft Entra para este destino.');
        $expected = (string)(self::supportedKinds()[$kind]['scope'] ?? '');
        return $scope === $expected
            ? true
            : new \WP_Error('fieldflow_5c3_scope_mismatch', 'O resource scope da identidade não corresponde ao tipo de recurso 5C.3.');
    }

    public static function statusLabel(string $status): string {
        $labels = [
            'draft'=>'DRAFT',
            'configured'=>'CONFIGURADO',
            'probe_failed'=>'ACESSO NÃO VALIDADO',
            'authorized'=>'READY, RECURSO',
        ];
        return $labels[$status] ?? 'DRAFT';
    }

    public static function normaliseSqlServer(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('#^tcp:#i', '', $value);
        $value = preg_replace('/,1433$/', '', (string)$value);
        if (!is_string($value) || strlen($value) > 253) return '';
        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.database\.windows\.net$/', $value) ? $value : '';
    }

    public static function normaliseSqlDatabase(string $value): string {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128) return '';
        if (!preg_match('/^[\p{L}\p{N}_.()\- ]+$/u', $value)) return '';
        return $value;
    }

    private static function brokerConfig() {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) return new \WP_Error('fieldflow_5c3_broker_host_invalid', 'O Entra Broker 5C.2 não corresponde ao host dedicado TWT.');
        $url = 'https://' . $host . self::RESOURCE_PROBE_PATH;
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_5c3_hmac_missing', 'O HMAC TWT certificado não está disponível.');
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_entra_resource_probe', [
            'max_timeout'=>45,
            'max_response_bytes'=>262144,
            'allow_public_ip_literals'=>false,
        ]);
        $validated = OutboundHttp::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;
        return ['url'=>$validated,'secret'=>$secret,'timeout'=>max(10,min(45,(int)($settings['timeout'] ?? 20))),'policy'=>$policy];
    }

    private static function recordProbeResult(array $destination, bool $authorized, int $httpStatus, int $remoteHttpStatus, string $code, string $error): void {
        global $wpdb;
        $id = absint($destination['id'] ?? 0);
        if ($id <= 0 || !DestinationRegistry::tableReady(DestinationRegistry::destinationsTable())) return;
        $policy = self::policy($destination);
        $now = time();
        $policy['resource_auth_version'] = self::VERSION;
        $policy['resource_auth_status'] = $authorized ? 'authorized' : 'probe_failed';
        $policy['resource_auth_verified'] = $authorized;
        $policy['resource_auth_verified_at'] = $authorized ? $now : 0;
        $policy['resource_auth_last_probe_at'] = $now;
        $policy['resource_auth_last_http_status'] = max(0, $httpStatus);
        $policy['resource_auth_last_remote_http_status'] = max(0, $remoteHttpStatus);
        $policy['resource_auth_last_probe_code'] = sanitize_key($code);
        $policy['resource_auth_error'] = substr(sanitize_text_field($error), 0, 300);
        $policy['resource_auth_delivery_enabled'] = false;
        $policy['resource_auth_read_only_probe'] = true;
        $wpdb->update(DestinationRegistry::destinationsTable(), [
            'retry_policy_json'=>wp_json_encode($policy, JSON_UNESCAPED_SLASHES),
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$id]);
    }

    private static function policy(array $destination): array {
        $policy = json_decode((string)($destination['retry_policy_json'] ?? ''), true);
        return is_array($policy) ? $policy : [];
    }
}
