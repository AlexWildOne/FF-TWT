<?php
namespace RoutesPro\Support;

use RoutesPro\Security\OutboundHttp;

if (!defined('ABSPATH')) exit;

class RemoteLicenseClient {
    public const TRUSTED_DEFAULT_HOST = 'func-fieldflow-licensing-prod-eb978f11.azurewebsites.net';
    public static function isReady(): bool {
        return self::baseUrl() !== '' && self::sharedSecret() !== '';
    }

    public static function baseUrl(): string {
        return rtrim((string) Config::get('license_remote_api_base', ''), '/');
    }

    public static function sharedSecret(): string {
        return (string) Config::get('license_remote_shared_secret', '');
    }

    public static function adminSecret(): string {
        return (string) Config::get('license_remote_admin_secret', '');
    }

    public static function timeout(): int {
        return max(5, (int) Config::get('license_remote_timeout', 15));
    }

    public static function productId(): string {
        return (string) Config::get('license_remote_product_id', 'fieldflow');
    }

    public static function siteContext(): array {
        $context = [
            'product_id' => self::productId(),
            'license_key' => (string) LicenseManager::get('key', ''),
            'domain' => LicenseManager::currentDomain(),
            'site_url' => home_url('/'),
            'admin_email' => get_option('admin_email'),
            'fingerprint' => LicenseManager::currentFingerprint(),
            'plugin_version' => defined('ROUTESPRO_VERSION') ? ROUTESPRO_VERSION : '',
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
        ];
        if (class_exists('\\RoutesPro\\Cloud\\LicenseInstallationLifecycle')) {
            $context = array_merge($context, \RoutesPro\Cloud\LicenseInstallationLifecycle::context());
        }
        return $context;
    }


    public static function connectivityProbe(): array {
        $base = self::baseUrl();
        $host = strtolower((string)(wp_parse_url($base, PHP_URL_HOST) ?: ''));
        $result = [
            'ok'=>false,
            'configured'=>self::isReady(),
            'host'=>$host,
            'https'=>strtolower((string)(wp_parse_url($base, PHP_URL_SCHEME) ?: '')) === 'https',
            'dns_resolved'=>false,
            'dns_public'=>false,
            'wordpress_url_validation'=>false,
            'trusted_wordpress_bypass'=>false,
            'http_reachable'=>false,
            'route_reachable'=>false,
            'shared_secret_accepted'=>false,
            'http_code'=>0,
            'message'=>'',
            'checked_at'=>current_time('mysql', true),
        ];
        if (!self::isReady()) {
            $result['message'] = 'API Base ou Shared Secret por configurar.';
            return $result;
        }

        $targetUrl = $base . '/license/validate';
        $policy = self::outboundPolicy('license_remote_probe');
        $diag = OutboundHttp::diagnose($targetUrl, $policy);
        $result['host'] = (string)($diag['host'] ?? $host);
        $result['https'] = !empty($diag['https']);
        $result['dns_resolved'] = !empty($diag['dns_resolved']);
        $result['dns_public'] = !empty($diag['dns_public']);
        $result['wordpress_url_validation'] = !empty($diag['wordpress_url_validation']);
        $result['trusted_wordpress_bypass'] = !empty($diag['trusted_wordpress_bypass']);
        if (empty($diag['ok'])) {
            $result['message'] = sanitize_text_field((string)($diag['error_message'] ?? 'A política de saída bloqueou o endpoint.'));
            return $result;
        }

        $payload = [
            'product_id'=>self::productId(),
            'license_key'=>'FF-PROBE-NONEXISTENT',
            'domain'=>'probe.invalid',
            'site_url'=>'https://probe.invalid/',
            'fingerprint'=>hash('sha256', 'fieldflow-6b1-connectivity-probe'),
            'probe'=>true,
        ];
        $json = wp_json_encode($payload);
        if (!is_string($json) || $json === '') {
            $result['message'] = 'Falha ao serializar o probe remoto.';
            return $result;
        }
        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $json, self::sharedSecret());
        $response = OutboundHttp::post($targetUrl, [
            'timeout'=>min(20, self::timeout()),
            'headers'=>[
                'Content-Type'=>'application/json; charset=utf-8',
                'Accept'=>'application/json',
                'X-RoutesPro-Time'=>$timestamp,
                'X-RoutesPro-Signature'=>$signature,
                'X-RoutesPro-Product'=>self::productId(),
            ],
            'body'=>$json,
        ], $policy);
        if (is_wp_error($response)) {
            $result['message'] = sanitize_text_field($response->get_error_message());
            return $result;
        }

        $code = (int)wp_remote_retrieve_response_code($response);
        $body = (string)wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $jsonResponse = is_array($data);
        $message = $jsonResponse ? sanitize_text_field((string)($data['message'] ?? '')) : '';
        $signatureRejected = $code === 401 || stripos($message, 'signature') !== false || stripos($message, 'hmac') !== false;
        $httpReachable = $code >= 200 && $code < 500;
        $routeReachable = $httpReachable && $jsonResponse;
        $secretAccepted = $routeReachable && !$signatureRejected;

        $result['http_code'] = $code;
        $result['http_reachable'] = $httpReachable;
        $result['route_reachable'] = $routeReachable;
        $result['shared_secret_accepted'] = $secretAccepted;
        $result['message'] = $message !== '' ? $message : ($routeReachable ? 'Rota de validação respondeu.' : 'A API respondeu, mas sem contrato JSON reconhecido.');
        $result['ok'] = $result['https'] && $routeReachable && $secretAccepted;
        return $result;
    }

    public static function outboundPolicy(string $purpose = 'license_remote'): array {
        $base = self::baseUrl();
        $host = strtolower((string)(wp_parse_url($base, PHP_URL_HOST) ?: ''));
        $overrides = [
            'max_timeout'=>60,
            'max_response_bytes'=>1048576,
        ];
        if ($host !== '' && hash_equals(self::TRUSTED_DEFAULT_HOST, $host)) {
            return OutboundHttp::trustedProviderPolicy($base, $purpose, [self::TRUSTED_DEFAULT_HOST], $overrides);
        }
        return OutboundHttp::configuredPolicy($base, $purpose, $overrides);
    }

    public static function post(string $path, array $payload, bool $useAdminSecret = false): array {
        if (!self::isReady()) {
            return ['success' => false, 'message' => 'API remota de licenciamento incompleta.'];
        }

        $payload = array_merge([
            'product_id' => self::productId(),
        ], $payload);

        $adminSecret = trim(self::adminSecret());
        if ($useAdminSecret && $adminSecret !== '') {
            // Diferentes versões da Function usam nomes diferentes.
            // Enviamos nos formatos mais comuns e a API ignora o que não precisar.
            $payload['admin_secret'] = $adminSecret;
            $payload['license_admin_secret'] = $adminSecret;
        }

        $json = wp_json_encode($payload);
        if (!is_string($json) || $json === '') {
            return ['success' => false, 'message' => 'Falha ao serializar pedido remoto.'];
        }

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $json, self::sharedSecret());

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'X-RoutesPro-Time' => $timestamp,
            'X-RoutesPro-Signature' => $signature,
            'X-RoutesPro-Product' => self::productId(),
        ];
        if ($useAdminSecret && $adminSecret !== '') {
            $headers['X-RoutesPro-Admin-Secret'] = $adminSecret;
            $headers['X-FieldFlow-Admin-Secret'] = $adminSecret;
            $headers['X-License-Admin-Secret'] = $adminSecret;
        }

        $targetUrl = self::baseUrl() . '/' . ltrim($path, '/');
        $response = OutboundHttp::post($targetUrl, [
            'timeout' => self::timeout(),
            'headers' => $headers,
            'body' => $json,
        ], self::outboundPolicy('license_remote'));

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = ['success' => false, 'message' => 'Resposta inválida da API.', 'raw' => $body];
        }
        $data['http_code'] = $code;
        return $data;
    }
}
