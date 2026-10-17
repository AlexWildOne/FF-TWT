<?php
namespace RoutesPro\Security;

if (!defined('ABSPATH')) exit;

/**
 * Central outbound HTTP guard for FieldFlow.
 *
 * Goals:
 * - HTTPS only.
 * - Public destinations only (no localhost, private/reserved/link-local IPs).
 * - Exact/suffix host allowlists where the integration has a known provider.
 * - Port policy (443 by default).
 * - Redirects disabled by default; when enabled they are followed manually and
 *   every hop is revalidated before any request is sent.
 * - TLS verification and WordPress unsafe URL rejection cannot be disabled by
 *   callers.
 * - Response sizes and timeouts are bounded.
 */
final class OutboundHttp {
    public const VERSION = '1.0.2';
    private const TRUSTED_WP_VALIDATION_BYPASS_HOSTS = [
        'func-fieldflow-licensing-prod-eb978f11.azurewebsites.net',
    ];
    private const DEFAULT_MAX_BYTES = 2097152; // 2 MiB.

    public static function status(): array {
        return [
            'version' => self::VERSION,
            'https_only' => true,
            'private_networks_blocked' => true,
            'unsafe_url_rejection' => true,
            'trusted_fixed_host_bypass' => true,
            'tls_verify_forced' => true,
            'redirects_default' => 0,
            'default_port' => 443,
            'default_max_response_bytes' => self::DEFAULT_MAX_BYTES,
        ];
    }

    public static function get(string $url, array $args = [], array $policy = []) {
        $args['method'] = 'GET';
        return self::request($url, $args, $policy);
    }

    public static function post(string $url, array $args = [], array $policy = []) {
        $args['method'] = 'POST';
        return self::request($url, $args, $policy);
    }

    public static function request(string $url, array $args = [], array $policy = []) {
        $policy = self::normalisePolicy($policy);
        $validated = self::validate($url, $policy);
        if (is_wp_error($validated)) return $validated;

        $currentUrl = $validated;
        $initialHost = self::normaliseHost((string)wp_parse_url($currentUrl, PHP_URL_HOST));
        $remainingRedirects = (int)$policy['max_redirects'];
        $requestArgs = $args;

        while (true) {
            $validated = self::validate($currentUrl, $policy);
            if (is_wp_error($validated)) return $validated;
            $currentUrl = $validated;

            $requestArgs['method'] = strtoupper((string)($requestArgs['method'] ?? 'GET'));
            $requestArgs['timeout'] = max(1, min((int)$policy['max_timeout'], (int)($requestArgs['timeout'] ?? 20)));
            $requestArgs['redirection'] = 0;
            $requestArgs['sslverify'] = true;

            $requestedLimit = isset($requestArgs['limit_response_size']) ? max(1, (int)$requestArgs['limit_response_size']) : (int)$policy['max_response_bytes'];
            $requestArgs['limit_response_size'] = min((int)$policy['max_response_bytes'], $requestedLimit);

            $wordpressAccepted = !function_exists('wp_http_validate_url') || wp_http_validate_url($currentUrl) !== false;
            $trustedBypass = !$wordpressAccepted && self::canBypassWordPressValidation($currentUrl, $policy);
            if ($trustedBypass) {
                if (!function_exists('wp_remote_request')) {
                    return self::blocked('wordpress_transport_missing', $currentUrl, $policy, 'O transporte HTTP do WordPress não está disponível.');
                }
                // Only the single pinned FieldFlow licensing Function can reach this branch.
                // Our HTTPS, exact-host, port and private-network checks already ran above.
                $requestArgs['reject_unsafe_urls'] = false;
                $response = wp_remote_request($currentUrl, $requestArgs);
            } else {
                $requestArgs['reject_unsafe_urls'] = true;
                $response = wp_safe_remote_request($currentUrl, $requestArgs);
            }
            if (is_wp_error($response)) return $response;

            $status = (int)wp_remote_retrieve_response_code($response);
            if (!in_array($status, [301, 302, 303, 307, 308], true)) return $response;

            $location = trim((string)wp_remote_retrieve_header($response, 'location'));
            if ($location === '') {
                return self::blocked('redirect_without_location', $currentUrl, $policy, 'Resposta de redirecionamento sem destino válido.');
            }
            if ($remainingRedirects < 1) {
                return self::blocked('redirect_blocked', $currentUrl, $policy, 'Redirecionamentos não são permitidos para esta integração.');
            }

            $target = self::resolveRedirect($currentUrl, $location);
            if ($target === '') {
                return self::blocked('redirect_invalid', $currentUrl, $policy, 'O destino de redirecionamento é inválido.');
            }
            $targetValidated = self::validate($target, $policy);
            if (is_wp_error($targetValidated)) return $targetValidated;

            $targetHost = self::normaliseHost((string)wp_parse_url($targetValidated, PHP_URL_HOST));
            if (!empty($policy['redirect_same_host']) && $initialHost !== '' && !hash_equals($initialHost, $targetHost)) {
                return self::blocked('redirect_cross_host', $targetValidated, $policy, 'Redirecionamento para outro host bloqueado.');
            }

            if ($status === 303) {
                $requestArgs['method'] = 'GET';
                unset($requestArgs['body']);
            }

            $currentUrl = $targetValidated;
            $remainingRedirects--;
        }
    }

    /**
     * Validate an outbound destination and return the normalized URL or WP_Error.
     */
    public static function validate(string $url, array $policy = []) {
        $policy = self::normalisePolicy($policy);
        $url = trim($url);
        if ($url === '') return self::blocked('empty_url', '', $policy, 'Destino externo vazio.');

        $url = esc_url_raw($url, ['https']);
        if ($url === '') return self::blocked('invalid_url', '', $policy, 'Destino externo inválido.');

        $parts = wp_parse_url($url);
        if (!is_array($parts)) return self::blocked('invalid_url', $url, $policy, 'Destino externo inválido.');

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = self::normaliseHost((string)($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int)$parts['port'] : 443;
        if ($scheme !== 'https') return self::blocked('https_required', $url, $policy, 'Apenas HTTPS é permitido em ligações externas.');
        if ($host === '') return self::blocked('host_missing', $url, $policy, 'O destino externo não contém um host válido.');
        if (isset($parts['user']) || isset($parts['pass'])) return self::blocked('userinfo_blocked', $url, $policy, 'Credenciais embutidas no URL não são permitidas.');
        if (!in_array($port, (array)$policy['allowed_ports'], true)) return self::blocked('port_blocked', $url, $policy, 'A porta do destino externo não está autorizada.');

        if (self::isLocalHostname($host)) return self::blocked('local_host_blocked', $url, $policy, 'Hosts locais ou internos não são permitidos.');
        if (!self::hostMatchesAllowlist($host, (array)$policy['allowed_hosts'], (array)$policy['allowed_host_suffixes'])) {
            return self::blocked('host_not_allowed', $url, $policy, 'O host do destino não pertence à allowlist desta integração.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (empty($policy['allow_public_ip_literals'])) return self::blocked('ip_literal_blocked', $url, $policy, 'Destinos configurados diretamente por endereço IP não são permitidos.');
            if (!self::isPublicIp($host)) return self::blocked('private_ip_blocked', $url, $policy, 'Endereços IP privados ou reservados não são permitidos.');
        } else {
            $resolved = self::resolveHostIps($host);
            foreach ($resolved as $ip) {
                if (!self::isPublicIp($ip)) return self::blocked('dns_private_ip_blocked', $url, $policy, 'O host resolve para uma rede privada ou reservada.');
            }
        }

        // WordPress performs a second unsafe-URL validation immediately before
        // the transport request (via wp_safe_remote_request). This catches DNS
        // changes between our policy check and dispatch as far as the WP HTTP API allows.
        if (function_exists('wp_http_validate_url') && wp_http_validate_url($url) === false) {
            if (!self::canBypassWordPressValidation($url, $policy)) {
                return self::blocked('wordpress_url_rejected', $url, $policy, 'O WordPress classificou o destino como inseguro.');
            }
        }

        return $url;
    }

    /**
     * Safe diagnostics for an outbound target. Never returns query strings or secrets.
     */
    public static function diagnose(string $url, array $policy = []): array {
        $policy = self::normalisePolicy($policy);
        $trimmed = trim($url);
        $parts = wp_parse_url($trimmed);
        $host = is_array($parts) ? self::normaliseHost((string)($parts['host'] ?? '')) : '';
        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        $port = is_array($parts) && isset($parts['port']) ? (int)$parts['port'] : 443;
        $ips = ($host !== '' && !filter_var($host, FILTER_VALIDATE_IP)) ? self::resolveHostIps($host) : ($host !== '' ? [$host] : []);
        $dnsPublic = true;
        foreach ($ips as $ip) {
            if (!self::isPublicIp((string)$ip)) { $dnsPublic = false; break; }
        }
        $wpAccepted = !function_exists('wp_http_validate_url') || wp_http_validate_url($trimmed) !== false;
        $trustedBypass = !$wpAccepted && self::canBypassWordPressValidation($trimmed, $policy);
        $validated = self::validate($trimmed, $policy);
        return [
            'ok'=>!is_wp_error($validated),
            'host'=>$host,
            'https'=>$scheme === 'https',
            'port'=>$port,
            'dns_resolved'=>count($ips) > 0,
            'dns_public'=>$dnsPublic,
            'dns_ip_count'=>count($ips),
            'wordpress_url_validation'=>$wpAccepted,
            'trusted_wordpress_bypass'=>$trustedBypass,
            'policy_purpose'=>(string)$policy['purpose'],
            'error_code'=>is_wp_error($validated) ? (string)$validated->get_error_code() : '',
            'error_message'=>is_wp_error($validated) ? (string)$validated->get_error_message() : '',
        ];
    }

    /**
     * Exact host policy for an administrator-configured HTTPS endpoint.
     * This keeps custom integrations supported while preventing redirects or
     * runtime manipulation from changing the destination host.
     */
    public static function configuredPolicy(string $url, string $purpose, array $overrides = []): array {
        $host = self::normaliseHost((string)(wp_parse_url(trim($url), PHP_URL_HOST) ?: ''));
        $policy = array_merge([
            'purpose' => $purpose,
            'allowed_hosts' => $host !== '' ? [$host] : [],
            'allowed_ports' => [443],
            'max_redirects' => 0,
            'redirect_same_host' => true,
        ], $overrides);
        return self::normalisePolicy($policy);
    }

    /**
     * Policy for a small set of FieldFlow-owned, immutable provider hosts.
     * It does not weaken generic configured endpoints.
     */
    public static function trustedProviderPolicy(string $url, string $purpose, array $trustedHosts, array $overrides = []): array {
        $policy = self::configuredPolicy($url, $purpose, $overrides);
        $policy['wordpress_validation_bypass_hosts'] = array_values(array_unique(array_filter(array_map([self::class, 'normaliseHost'], $trustedHosts))));
        return self::normalisePolicy($policy);
    }

    private static function normalisePolicy(array $policy): array {
        $defaults = [
            'purpose' => 'generic',
            'allowed_hosts' => [],
            'allowed_host_suffixes' => [],
            'allowed_ports' => [443],
            'allow_public_ip_literals' => true,
            'wordpress_validation_bypass_hosts' => [],
            'max_redirects' => 0,
            'redirect_same_host' => true,
            'max_timeout' => 120,
            'max_response_bytes' => self::DEFAULT_MAX_BYTES,
        ];
        $policy = array_merge($defaults, $policy);
        $policy['purpose'] = substr(sanitize_key((string)$policy['purpose']), 0, 80) ?: 'generic';
        $policy['allowed_hosts'] = array_values(array_unique(array_filter(array_map([self::class, 'normaliseHost'], (array)$policy['allowed_hosts']))));
        $policy['allowed_host_suffixes'] = array_values(array_unique(array_filter(array_map(static function($suffix) {
            return ltrim(self::normaliseHost((string)$suffix), '.');
        }, (array)$policy['allowed_host_suffixes']))));
        $policy['wordpress_validation_bypass_hosts'] = array_values(array_unique(array_filter(array_map([self::class, 'normaliseHost'], (array)$policy['wordpress_validation_bypass_hosts']))));
        $ports = [];
        foreach ((array)$policy['allowed_ports'] as $port) {
            $port = (int)$port;
            if ($port > 0 && $port <= 65535) $ports[] = $port;
        }
        $policy['allowed_ports'] = array_values(array_unique($ports ?: [443]));
        $policy['max_redirects'] = max(0, min(3, (int)$policy['max_redirects']));
        $policy['redirect_same_host'] = !empty($policy['redirect_same_host']);
        $policy['allow_public_ip_literals'] = !empty($policy['allow_public_ip_literals']);
        $policy['max_timeout'] = max(3, min(180, (int)$policy['max_timeout']));
        $policy['max_response_bytes'] = max(65536, min(16777216, (int)$policy['max_response_bytes']));
        return $policy;
    }


    private static function canBypassWordPressValidation(string $url, array $policy): bool {
        $parts = wp_parse_url($url);
        if (!is_array($parts)) return false;
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = self::normaliseHost((string)($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int)$parts['port'] : 443;
        if ($scheme !== 'https' || $port !== 443 || $host === '') return false;
        if (!in_array((string)($policy['purpose'] ?? ''), ['license_remote','license_remote_probe'], true)) return false;
        if (!in_array($host, (array)($policy['wordpress_validation_bypass_hosts'] ?? []), true)) return false;
        if (!in_array($host, self::TRUSTED_WP_VALIDATION_BYPASS_HOSTS, true)) return false;
        return self::hostMatchesAllowlist($host, (array)$policy['allowed_hosts'], (array)$policy['allowed_host_suffixes']);
    }

    private static function hostMatchesAllowlist(string $host, array $exact, array $suffixes): bool {
        if (!$exact && !$suffixes) return true;
        foreach ($exact as $allowed) {
            $allowed = self::normaliseHost((string)$allowed);
            if ($allowed !== '' && hash_equals($allowed, $host)) return true;
        }
        foreach ($suffixes as $suffix) {
            $suffix = ltrim(self::normaliseHost((string)$suffix), '.');
            if ($suffix === '') continue;
            if ($host === $suffix) return true;
            $needle = '.' . $suffix;
            if (strlen($host) > strlen($needle) && substr($host, -strlen($needle)) === $needle) return true;
        }
        return false;
    }

    private static function normaliseHost(string $host): string {
        $host = strtolower(trim($host, " \t\n\r\0\x0B.[]"));
        if ($host === '') return '';
        if (function_exists('idn_to_ascii') && !filter_var($host, FILTER_VALIDATE_IP)) {
            $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii = @idn_to_ascii($host, $flags, $variant);
            if (is_string($ascii) && $ascii !== '') $host = strtolower($ascii);
        }
        return $host;
    }

    private static function isLocalHostname(string $host): bool {
        if ($host === 'localhost' || $host === 'localhost.localdomain') return true;
        if (strpos($host, '.') === false && !filter_var($host, FILTER_VALIDATE_IP)) return true;
        foreach (['.localhost', '.local', '.internal', '.lan', '.home', '.home.arpa', '.test'] as $suffix) {
            if (strlen($host) >= strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) return true;
        }
        return false;
    }

    private static function isPublicIp(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function resolveHostIps(string $host): array {
        $ips = [];
        if (function_exists('dns_get_record')) {
            try {
                $type = 0;
                if (defined('DNS_A')) $type |= DNS_A;
                if (defined('DNS_AAAA')) $type |= DNS_AAAA;
                if ($type) {
                    $records = @dns_get_record($host, $type);
                    if (is_array($records)) {
                        foreach ($records as $record) {
                            if (!empty($record['ip'])) $ips[] = (string)$record['ip'];
                            if (!empty($record['ipv6'])) $ips[] = (string)$record['ipv6'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // wp_safe_remote_request still performs the transport-level safety check.
            }
        }
        if (!$ips && function_exists('gethostbynamel')) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) $ips = array_merge($ips, $v4);
        }
        return array_values(array_unique(array_filter($ips, static function($ip) {
            return filter_var((string)$ip, FILTER_VALIDATE_IP) !== false;
        })));
    }

    private static function resolveRedirect(string $base, string $location): string {
        $location = trim($location);
        if ($location === '') return '';
        if (preg_match('#^https://#i', $location)) return $location;
        if (preg_match('#^http://#i', $location)) return $location; // validate() will reject it.

        $parts = wp_parse_url($base);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';
        $origin = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
        if (!empty($parts['port'])) $origin .= ':' . (int)$parts['port'];
        if (strpos($location, '//') === 0) return strtolower((string)$parts['scheme']) . ':' . $location;
        if ($location[0] === '/') return $origin . $location;

        $path = (string)($parts['path'] ?? '/');
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        if (!is_string($dir) || $dir === '') $dir = '/';
        return $origin . $dir . $location;
    }

    private static function blocked(string $reason, string $url, array $policy, string $message) {
        $host = self::normaliseHost((string)(wp_parse_url($url, PHP_URL_HOST) ?: ''));
        if (class_exists('RoutesPro\\Security\\SecurityService')) {
            SecurityService::record('outbound.blocked', 'medium', [
                'route' => 'outbound/' . substr(sanitize_key((string)($policy['purpose'] ?? 'generic')), 0, 80),
                'classification' => 'outbound_blocked',
                'reason_code' => substr(sanitize_key($reason), 0, 96),
                'host' => substr($host, 0, 191),
            ]);
        }
        return new \WP_Error('fieldflow_outbound_' . sanitize_key($reason), $message, [
            'purpose' => substr(sanitize_key((string)($policy['purpose'] ?? 'generic')), 0, 80),
            'host' => substr($host, 0, 191),
        ]);
    }
}
