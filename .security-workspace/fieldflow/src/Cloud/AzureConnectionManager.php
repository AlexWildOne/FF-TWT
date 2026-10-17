<?php
namespace RoutesPro\Cloud;

use RoutesPro\Admin\Integrations;
use RoutesPro\Security\OutboundHttp;
use RoutesPro\Services\IntegrationPlatform;

if (!defined('ABSPATH')) exit;

final class AzureConnectionManager {
    public const SIGNATURE_VERSION = 'v1';

    public static function settings(): array {
        return Integrations::get();
    }

    public static function syncEnabled(): bool {
        $settings = self::settings();
        return !empty($settings['azure_cloud_sync_enabled']);
    }

    public static function destinationMode(): string {
        $settings = self::settings();
        $mode = sanitize_key((string)($settings['azure_cloud_destination_mode'] ?? 'fieldflow_cloud'));
        return in_array($mode, ['fieldflow_cloud','client_azure'], true) ? $mode : 'fieldflow_cloud';
    }

    public static function config() {
        $settings = self::settings();
        $url = esc_url_raw(trim((string)($settings['azure_cloud_endpoint'] ?? '')), ['https']);
        if ($url === '') return new \WP_Error('fieldflow_azure_not_configured', 'O endpoint seguro do Azure Cloud não está configurado.');

        $secret = trim((string)($settings['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_azure_secret_missing', 'O segredo HMAC do Azure Cloud não está configurado ou é demasiado curto.');

        $policy = OutboundHttp::configuredPolicy($url, 'fieldflow_azure_cloud_sync', [
            'max_timeout' => 45,
            'max_response_bytes' => 1048576,
        ]);
        $validated = OutboundHttp::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;

        return [
            'url' => $validated,
            'secret' => $secret,
            'timeout' => max(5, min(45, absint($settings['sync_timeout'] ?? 25))),
            'policy' => $policy,
            'mode' => self::destinationMode(),
        ];
    }

    public static function ready(): bool {
        return !is_wp_error(self::config());
    }

    public static function send(array $payload) {
        $config = self::config();
        if (is_wp_error($config)) return $config;

        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $body === '') return new \WP_Error('fieldflow_azure_payload_encode', 'Não foi possível codificar o payload Cloud.');

        $headers = self::signedHeaders($payload, $body, (string)$config['secret']);
        if (is_wp_error($headers)) return $headers;

        return OutboundHttp::post((string)$config['url'], [
            'timeout' => (int)$config['timeout'],
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'body' => $body,
        ], (array)$config['policy']);
    }

    public static function probe() {
        $identity = TenantIdentity::snapshot();
        $eventUuid = wp_generate_uuid4();
        $payload = [
            'contract' => 'fieldflow.cloud.event',
            'contract_version' => '1.0',
            'tenant' => $identity,
            'destination_mode' => self::destinationMode(),
            'event' => [
                'uuid' => $eventUuid,
                'name' => 'foundation.probe',
                'occurred_at' => gmdate('c'),
            ],
            'context' => [
                'entity_type' => 'foundation_probe',
                'entity_id' => 0,
            ],
            'payload' => [
                'probe' => true,
                'site_url' => home_url('/'),
            ],
        ];
        return self::send($payload);
    }

    public static function signedHeaders(array $payload, string $body, string $secret, ?int $timestamp = null) {
        $tenant = sanitize_text_field((string)($payload['tenant']['tenant_id'] ?? ''));
        $installation = sanitize_text_field((string)($payload['tenant']['installation_id'] ?? ''));
        $eventUuid = sanitize_text_field((string)($payload['event']['uuid'] ?? ''));
        if ($tenant === '' || $installation === '' || $eventUuid === '') {
            return new \WP_Error('fieldflow_azure_signature_context', 'O payload não contém tenant, installation ou event UUID necessários para assinatura.');
        }
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_azure_signature_secret', 'Segredo HMAC inválido.');

        $timestamp = $timestamp ?? time();
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", [
            'fieldflow-hmac-v1',
            (string)$timestamp,
            $tenant,
            $installation,
            $eventUuid,
            $bodyHash,
        ]);
        $signature = hash_hmac('sha256', $canonical, $secret);

        return [
            'X-FieldFlow-Signature' => self::SIGNATURE_VERSION . '=' . $signature,
            'X-FieldFlow-Timestamp' => (string)$timestamp,
            'X-FieldFlow-Tenant' => $tenant,
            'X-FieldFlow-Installation' => $installation,
            'X-FieldFlow-Event-Id' => $eventUuid,
            'X-FieldFlow-Body-SHA256' => $bodyHash,
            'X-FieldFlow-Contract-Version' => sanitize_text_field((string)($payload['contract_version'] ?? '1.0')),
            'User-Agent' => 'FieldFlow/' . (defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : 'unknown'),
        ];
    }

    public static function status(): array {
        $config = self::config();
        $settings = self::settings();
        return [
            'sync_enabled' => self::syncEnabled(),
            'destination_mode' => self::destinationMode(),
            'configured' => !is_wp_error($config),
            'auth_mode' => 'hmac_v1',
            'secret_configured' => strlen(trim((string)($settings['azure_cloud_hmac_secret'] ?? ''))) >= 32,
            'endpoint_host' => is_wp_error($config) ? '' : (string)(wp_parse_url((string)$config['url'], PHP_URL_HOST) ?: ''),
            'configuration_error' => is_wp_error($config) ? $config->get_error_message() : '',
        ];
    }
}
