<?php
namespace RoutesPro\Cloud;

use RoutesPro\Security\OutboundHttp;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5B transport between WordPress and the dedicated TWT Client-Azure relay.
 *
 * WordPress never stores client credentials. It reuses the already-certified
 * TWT HMAC trust only to reach a TWT-controlled relay. Client credentials are
 * represented exclusively by Azure Key Vault references.
 */
final class ClientAzureRelayConnection {
    public const VERSION = '1.0.0';
    public const RELAY_HOST = 'func-fieldflow-clientrelay-prod-eb978f11.azurewebsites.net';
    public const OPTION = 'fieldflow_client_azure_relay_settings';

    public static function defaults(): array {
        return [
            'mode' => 'shadow',
            'relay_endpoint' => '',
            'batch_size' => 10,
            'timeout' => 20,
        ];
    }

    public static function settings(): array {
        $stored = get_option(self::OPTION, []);
        $settings = wp_parse_args(is_array($stored) ? $stored : [], self::defaults());
        $mode = sanitize_key((string)($settings['mode'] ?? 'shadow'));
        $settings['mode'] = in_array($mode, ['shadow','queue','live'], true) ? $mode : 'shadow';
        $settings['relay_endpoint'] = self::normaliseRelayEndpoint((string)($settings['relay_endpoint'] ?? ''));
        $settings['batch_size'] = max(1, min(50, absint($settings['batch_size'] ?? 10)));
        $settings['timeout'] = max(5, min(45, absint($settings['timeout'] ?? 20)));
        return $settings;
    }

    public static function save(array $input) {
        $mode = sanitize_key((string)($input['mode'] ?? 'shadow'));
        if (!in_array($mode, ['shadow','queue','live'], true)) $mode = 'shadow';
        $endpointRaw = trim((string)($input['relay_endpoint'] ?? ''));
        $endpoint = self::normaliseRelayEndpoint($endpointRaw);
        if ($endpointRaw !== '' && $endpoint === '') {
            return new \WP_Error('fieldflow_5b_relay_endpoint_invalid', 'O Relay 5B tem de usar o endpoint HTTPS dedicado do Relay Azure TWT.');
        }
        if ($mode === 'live' && $endpoint === '') {
            return new \WP_Error('fieldflow_5b_relay_missing', 'Configura primeiro o endpoint do Relay Azure TWT antes de ativar o modo Live.');
        }
        $settings = [
            'mode'=>$mode,
            'relay_endpoint'=>$endpoint,
            'batch_size'=>max(1, min(50, absint($input['batch_size'] ?? 10))),
            'timeout'=>max(5, min(45, absint($input['timeout'] ?? 20))),
        ];
        update_option(self::OPTION, $settings, false);
        return $settings;
    }

    public static function mode(): string {
        return (string)self::settings()['mode'];
    }

    public static function configured(): bool {
        $settings = self::settings();
        if ((string)$settings['relay_endpoint'] === '') return false;
        $azure = AzureConnectionManager::settings();
        return strlen(trim((string)($azure['azure_cloud_hmac_secret'] ?? ''))) >= 32;
    }

    public static function ready(): bool {
        return self::mode() === 'live' && self::configured();
    }

    public static function status(): array {
        $settings = self::settings();
        $endpoint = (string)$settings['relay_endpoint'];
        $azure = AzureConnectionManager::settings();
        $secretConfigured = strlen(trim((string)($azure['azure_cloud_hmac_secret'] ?? ''))) >= 32;
        return [
            'version'=>self::VERSION,
            'mode'=>(string)$settings['mode'],
            'configured'=>$endpoint !== '' && $secretConfigured,
            'relay_endpoint'=>$endpoint,
            'relay_host'=>$endpoint !== '' ? (string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: '') : '',
            'hmac_reused'=>true,
            'hmac_secret_configured'=>$secretConfigured,
            'batch_size'=>(int)$settings['batch_size'],
            'timeout'=>(int)$settings['timeout'],
        ];
    }

    public static function send(array $payload) {
        $config = self::config();
        if (is_wp_error($config)) return $config;
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_5b_payload_encode', 'Não foi possível codificar o payload do Relay 5B.');
        $headers = AzureConnectionManager::signedHeaders($payload, $body, (string)$config['secret']);
        if (is_wp_error($headers)) return $headers;
        return OutboundHttp::post((string)$config['url'], [
            'timeout'=>(int)$config['timeout'],
            'headers'=>array_merge(['Content-Type'=>'application/json'], $headers),
            'body'=>$body,
        ], (array)$config['policy']);
    }

    public static function probeDestination(array $destination) {
        $destinationUuid = sanitize_text_field((string)($destination['destination_uuid'] ?? ''));
        if ($destinationUuid === '' || (string)($destination['type_key'] ?? '') !== 'client_azure') {
            return new \WP_Error('fieldflow_5b_probe_destination_invalid', 'Seleciona um destino Azure do Cliente válido.');
        }
        if (!self::destinationConfigReady($destination)) {
            return new \WP_Error('fieldflow_5b_destination_not_ready', 'O destino 5B precisa de endpoint HTTPS Azure e referência Key Vault válida.');
        }
        $eventUuid = wp_generate_uuid4();
        $identity = TenantIdentity::snapshot();
        $payload = [
            'contract'=>'fieldflow.client_azure.relay',
            'contract_version'=>'1.0',
            'tenant'=>$identity,
            'event'=>[
                'uuid'=>$eventUuid,
                'name'=>'client_azure.probe',
                'occurred_at'=>gmdate('c'),
            ],
            'context'=>[
                'entity_type'=>'client_azure_probe',
                'entity_id'=>0,
                'client_id'=>absint($destination['client_id'] ?? 0),
                'project_id'=>absint($destination['project_id'] ?? 0),
            ],
            'destination'=>ClientAzureDelivery::destinationSnapshot($destination),
            'delivery'=>[
                'key'=>DestinationRouter::deliveryKey($eventUuid, $destinationUuid),
                'probe'=>true,
                'async'=>false,
            ],
            'payload'=>[
                'probe'=>true,
                'fieldflow_version'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            ],
        ];
        return self::send($payload);
    }

    public static function destinationConfigReady(array $destination): bool {
        if ((string)($destination['type_key'] ?? '') !== 'client_azure') return false;
        $endpoint = trim((string)($destination['endpoint_ref'] ?? ''));
        $secretRef = trim((string)($destination['secret_ref'] ?? ''));
        if ($endpoint === '' || strtolower((string)(wp_parse_url($endpoint, PHP_URL_SCHEME) ?: '')) !== 'https') return false;
        return strpos($secretRef, 'kv://') === 0 || preg_match('#^https://[^/]+\\.vault\\.azure\\.net/secrets/#i', $secretRef) === 1;
    }

    private static function config() {
        $settings = self::settings();
        $url = (string)$settings['relay_endpoint'];
        if ($url === '') return new \WP_Error('fieldflow_5b_relay_not_configured', 'O endpoint Relay 5B ainda não está configurado.');
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_5b_hmac_missing', 'O HMAC TWT certificado não está disponível para autenticar o Relay 5B.');
        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_client_azure_relay', [
            'max_timeout'=>45,
            'max_response_bytes'=>1048576,
        ]);
        $validated = OutboundHttp::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;
        return [
            'url'=>$validated,
            'secret'=>$secret,
            'timeout'=>(int)$settings['timeout'],
            'policy'=>$policy,
        ];
    }

    private static function normaliseRelayEndpoint(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        $url = esc_url_raw($value, ['https']);
        if ($url === '' || strtolower((string)(wp_parse_url($url, PHP_URL_SCHEME) ?: '')) !== 'https') return '';
        $host = strtolower((string)(wp_parse_url($url, PHP_URL_HOST) ?: ''));
        $port = (int)(wp_parse_url($url, PHP_URL_PORT) ?: 443);
        if ($host !== self::RELAY_HOST || $port !== 443) return '';
        return substr($url, 0, 500);
    }
}
