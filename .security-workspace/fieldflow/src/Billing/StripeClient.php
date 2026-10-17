<?php
namespace RoutesPro\Billing;

use WP_Error;
use RoutesPro\Security\OutboundHttp;

if (!defined('ABSPATH')) exit;

class StripeClient {
    private string $secret;

    public function __construct(?string $secret = null) {
        $this->secret = $secret !== null ? $secret : Settings::secretKey();
    }

    public function isConfigured(): bool { return $this->secret !== ''; }

    public function createCheckoutSession(array $params, string $idempotencyKey) {
        return $this->request('POST', '/checkout/sessions', $params, $idempotencyKey);
    }

    public function retrieveSubscription(string $id) {
        return $this->request('GET', '/subscriptions/' . rawurlencode($id), ['expand'=>['latest_invoice','items.data.price']], '');
    }

    public function updateSubscription(string $id, array $params, string $idempotencyKey = '') {
        return $this->request('POST', '/subscriptions/' . rawurlencode($id), $params, $idempotencyKey);
    }

    public function retrieveCustomer(string $id) {
        return $this->request('GET', '/customers/' . rawurlencode($id), [], '');
    }

    public function createCustomer(array $params, string $idempotencyKey) {
        return $this->request('POST', '/customers', $params, $idempotencyKey);
    }

    public function createBillingPortalSession(array $params, string $idempotencyKey) {
        return $this->request('POST', '/billing_portal/sessions', $params, $idempotencyKey);
    }

    public function request(string $method, string $path, array $params = [], string $idempotencyKey = '') {
        if ($this->secret === '') return new WP_Error('fieldflow_billing_not_configured', 'A chave secreta Stripe ainda não está configurada.');
        $method = strtoupper($method);
        $url = 'https://api.stripe.com/v1' . $path;
        $flat = self::flatten($params);
        if ($method === 'GET' && $flat) $url .= '?' . http_build_query($flat, '', '&', PHP_QUERY_RFC3986);
        $args = [
            'method' => $method,
            'timeout' => 30,
            'redirection' => 0,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'FieldFlow-Billing/' . FIELDFLOW_VERSION,
            ],
        ];
        if ($method !== 'GET') $args['body'] = $flat;
        if ($idempotencyKey !== '') $args['headers']['Idempotency-Key'] = substr($idempotencyKey, 0, 255);
        $response = OutboundHttp::request($url, $args, [
            'purpose' => 'stripe',
            'allowed_hosts' => ['api.stripe.com'],
            'allowed_ports' => [443],
            'max_redirects' => 0,
            'max_timeout' => 45,
            'max_response_bytes' => 2097152,
        ]);
        if (is_wp_error($response)) return $response;
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = (string)wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if (!is_array($json)) $json = [];
        if ($code < 200 || $code >= 300) {
            $message = (string)($json['error']['message'] ?? 'A Stripe devolveu um erro HTTP ' . $code . '.');
            return new WP_Error('fieldflow_stripe_error', $message, ['status'=>$code,'stripe'=>$json]);
        }
        return $json;
    }

    private static function flatten(array $input, string $prefix = ''): array {
        $out = [];
        foreach ($input as $key => $value) {
            $name = $prefix === '' ? (string)$key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                foreach (self::flatten($value, $name) as $nestedKey => $nestedValue) $out[$nestedKey] = $nestedValue;
            } elseif (is_bool($value)) {
                $out[$name] = $value ? 'true' : 'false';
            } elseif ($value !== null) {
                $out[$name] = (string)$value;
            }
        }
        return $out;
    }
}
