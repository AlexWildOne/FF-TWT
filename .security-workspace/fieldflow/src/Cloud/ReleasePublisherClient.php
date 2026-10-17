<?php
namespace RoutesPro\Cloud;

use RoutesPro\Security\OutboundHttp;
use RoutesPro\Support\RemoteLicenseClient;

if (!defined('ABSPATH')) exit;

/**
 * Server-to-server client for the one-purpose Azure release publisher sidecar.
 * The sidecar never exposes Storage credentials and writes only immutable FieldFlow ZIPs.
 */
final class ReleasePublisherClient {
    public const VERSION = '1.0.0';
    public const ENDPOINT_OPTION = 'fieldflow_release_publisher_base';
    public const DEFAULT_BASE = 'https://func-fieldflow-release-publisher-prod-eb978f11.azurewebsites.net';

    public static function baseUrl(): string {
        $configured = trim((string)get_option(self::ENDPOINT_OPTION, ''));
        $base = $configured !== '' ? $configured : self::DEFAULT_BASE;
        return rtrim(esc_url_raw($base, ['https']), '/');
    }

    public static function setBaseUrl(string $url): string {
        $url = rtrim(esc_url_raw(trim($url), ['https']), '/');
        if ($url === '' || strtolower((string)(wp_parse_url($url, PHP_URL_SCHEME) ?: '')) !== 'https') {
            throw new \RuntimeException('O endpoint do Release Publisher tem de usar HTTPS.');
        }
        update_option(self::ENDPOINT_OPTION, $url, false);
        return $url;
    }

    public static function isReady(): bool {
        return self::baseUrl() !== '' && RemoteLicenseClient::sharedSecret() !== '' && RemoteLicenseClient::adminSecret() !== '';
    }

    public static function health(): array {
        $url = self::baseUrl() . '/api/health';
        $response = OutboundHttp::request($url, [
            'method'=>'GET',
            'timeout'=>15,
            'headers'=>['Accept'=>'application/json'],
        ], OutboundHttp::configuredPolicy($url, 'release_publisher_health', [
            'max_timeout'=>20,
            'max_response_bytes'=>262144,
        ]));
        if (is_wp_error($response)) return ['ok'=>false,'message'=>$response->get_error_message(),'url'=>$url];
        $code = (int)wp_remote_retrieve_response_code($response);
        $data = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($data)) $data = [];
        $data['http_code'] = $code;
        $data['ok'] = $code === 200 && !empty($data['ok']) && (string)($data['service'] ?? '') === 'fieldflow-release-publisher';
        $data['url'] = $url;
        if (empty($data['message'])) $data['message'] = $data['ok'] ? 'Release Publisher online.' : 'Release Publisher não respondeu com o contrato esperado.';
        return $data;
    }

    public static function uploadPackage(string $path, array $package, string $channel): array {
        if (!self::isReady()) return ['success'=>false,'message'=>'Release Publisher ou segredos de publicação por configurar.'];
        if (!is_file($path) || !is_readable($path)) return ['success'=>false,'message'=>'Pacote de release indisponível.'];

        $body = file_get_contents($path);
        if (!is_string($body) || $body === '') return ['success'=>false,'message'=>'Não foi possível ler o ZIP para publicação.'];
        $channel = sanitize_key($channel);
        if (!in_array($channel, ['stable','beta'], true)) $channel = 'stable';
        $version = sanitize_text_field((string)($package['version'] ?? ''));
        // 9D0B: Azure publisher supports exactly three numeric segments, optionally with prerelease/build suffixes.
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', $version)) {
            return ['success'=>false,'message'=>'Versao rejeitada antes do upload: o Release Publisher requer tres segmentos numericos (ex.: 2.2.625).'];
        }
        $sha256 = strtolower(sanitize_text_field((string)($package['sha256'] ?? '')));
        $bytes = (int)($package['bytes'] ?? strlen($body));
        if ($version === '' || !preg_match('/^[a-f0-9]{64}$/', $sha256) || $bytes !== strlen($body)) {
            return ['success'=>false,'message'=>'Metadados locais do pacote inválidos.'];
        }

        $timestamp = (string)time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, (string)RemoteLicenseClient::sharedSecret());
        $query = http_build_query(['version'=>$version,'channel'=>$channel], '', '&', PHP_QUERY_RFC3986);
        $url = self::baseUrl() . '/api/release/upload?' . $query;
        $adminSecret = (string)RemoteLicenseClient::adminSecret();
        $response = OutboundHttp::post($url, [
            'timeout'=>180,
            'headers'=>[
                'Content-Type'=>'application/zip',
                'Accept'=>'application/json',
                'X-RoutesPro-Time'=>$timestamp,
                'X-RoutesPro-Signature'=>$signature,
                'X-RoutesPro-Product'=>RemoteLicenseClient::productId(),
                'X-RoutesPro-Admin-Secret'=>$adminSecret,
                'X-FieldFlow-Admin-Secret'=>$adminSecret,
                'X-License-Admin-Secret'=>$adminSecret,
                'X-FieldFlow-Package-SHA256'=>$sha256,
                'X-FieldFlow-Package-Bytes'=>(string)$bytes,
            ],
            'body'=>$body,
            'limit_response_size'=>1048576,
        ], OutboundHttp::configuredPolicy($url, 'release_publisher_upload', [
            'max_timeout'=>180,
            'max_response_bytes'=>1048576,
        ]));
        unset($body);
        if (is_wp_error($response)) return ['success'=>false,'message'=>$response->get_error_message()];
        $code = (int)wp_remote_retrieve_response_code($response);
        $data = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($data)) $data = ['success'=>false,'message'=>'Resposta inválida do Release Publisher.'];
        $data['http_code'] = $code;
        if ($code < 200 || $code >= 300) {
            $data['success'] = false;
            return $data;
        }
        if (!empty($data['success'])) {
            $remoteVersion = sanitize_text_field((string)($data['version'] ?? ''));
            $remoteSha = strtolower(sanitize_text_field((string)($data['package_sha256'] ?? '')));
            $remoteBytes = (int)($data['package_size'] ?? 0);
            $expectedBlob = 'fieldflow/' . $channel . '/' . $version . '/fieldflow-' . $version . '.zip';
            $remoteBlob = sanitize_text_field((string)($data['blob_name'] ?? ''));
            if ($remoteVersion !== $version || $remoteSha === '' || !hash_equals($sha256, $remoteSha) || $remoteBytes !== $bytes || $remoteBlob !== $expectedBlob) {
                return ['success'=>false,'http_code'=>$code,'message'=>'O Release Publisher devolveu metadados diferentes do ZIP validado.'];
            }
        }
        return $data;
    }

    public static function publishCatalog(array $uploaded, array $package, string $channel, array $options = []): array {
        $payload = [
            'operation'=>'publish',
            'version'=>sanitize_text_field((string)($package['version'] ?? '')),
            'channel'=>in_array(sanitize_key($channel), ['stable','beta'], true) ? sanitize_key($channel) : 'stable',
            'blob_container'=>sanitize_text_field((string)($uploaded['blob_container'] ?? 'fieldflow-private-releases')),
            'blob_name'=>sanitize_text_field((string)($uploaded['blob_name'] ?? '')),
            'package_sha256'=>strtolower(sanitize_text_field((string)($package['sha256'] ?? ''))),
            'requires_wp'=>sanitize_text_field((string)($options['requires_wp'] ?? '6.4')),
            'requires_php'=>sanitize_text_field((string)($options['requires_php'] ?? '7.4')),
            'tested_wp'=>sanitize_text_field((string)($options['tested_wp'] ?? get_bloginfo('version'))),
            'allowed_plans'=>array_values(array_filter(array_map('sanitize_key', (array)($options['allowed_plans'] ?? ['legacy','start','operations','intelligence','enterprise','pro','fieldflow-pro'])))),
            'notes'=>sanitize_text_field((string)($options['notes'] ?? 'FieldFlow private release published from WordPress Release Manager.')),
        ];
        if ($payload['blob_name'] === '') return ['success'=>false,'message'=>'O Release Publisher não devolveu o Blob da release.'];
        return RemoteLicenseClient::post('/license/updates', $payload, true);
    }

    public static function inspect(string $version, string $channel = 'stable'): array {
        return RemoteLicenseClient::post('/license/updates', [
            'operation'=>'inspect',
            'channel'=>in_array(sanitize_key($channel), ['stable','beta'], true) ? sanitize_key($channel) : 'stable',
            'version'=>sanitize_text_field($version),
        ], true);
    }

    public static function findRelease(array $response, string $version, string $channel): array {
        $items = [];
        foreach (['release','latest_release'] as $key) if (is_array($response[$key] ?? null)) $items[] = $response[$key];
        foreach ((array)($response['releases'] ?? []) as $row) if (is_array($row)) $items[] = $row;
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if (is_array($data['release'] ?? null)) $items[] = $data['release'];
        foreach ((array)($data['releases'] ?? []) as $row) if (is_array($row)) $items[] = $row;
        $items[] = $response;
        foreach ($items as $row) {
            if (!is_array($row)) continue;
            $v = sanitize_text_field((string)($row['version'] ?? ''));
            $c = sanitize_key((string)($row['channel'] ?? ''));
            if ($v === $version && ($c === '' || $c === $channel)) return $row;
        }
        return [];
    }
}
