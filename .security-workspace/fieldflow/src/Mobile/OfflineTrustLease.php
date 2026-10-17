<?php
namespace RoutesPro\Mobile;

use RoutesPro\Cloud\Phase6StableRelease;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

/**
 * Bounded offline-trust evidence derived from the Azure-backed Phase 6 Stable
 * certification state. The token is intentionally non-enforcing in 2.2.454:
 * it gives the device tamper-resistant continuity evidence and lets the server
 * verify what certification snapshot was current when an offline report was
 * protected, without making network availability a runtime prerequisite.
 */
final class OfflineTrustLease {
    public const PROTOCOL = 'fieldflow-offline-trust-v1';
    public const TTL = 72 * HOUR_IN_SECONDS;
    public const REFRESH_AFTER = 12 * HOUR_IN_SECONDS;

    public static function restIssue(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('fieldflow_offline_trust_auth_required', 'Authentication required.', ['status' => 401]);
        }
        $policy = class_exists(DeviceManager::class) ? DeviceManager::assertOfflineAllowed($request) : [];
        if (is_wp_error($policy)) return $policy;
        $deviceId = self::deviceId($request);
        if ($deviceId === '') {
            return new WP_Error('fieldflow_offline_trust_device_missing', 'Device identity is required.', ['status' => 400]);
        }
        $cert = self::certificationState();
        if (empty($cert['certified'])) {
            return new WP_REST_Response([
                'ok' => true,
                'eligible' => false,
                'protocol' => self::PROTOCOL,
                'reason' => 'stable_revalidation_pending',
                'release' => defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '',
                'server_time' => current_time('mysql', true),
            ], 200);
        }
        $lease = self::issue(get_current_user_id(), $deviceId, $cert);
        return new WP_REST_Response([
            'ok' => true,
            'eligible' => true,
            'protocol' => self::PROTOCOL,
            'lease' => $lease['token'],
            'issued_at' => $lease['claims']['iat'],
            'expires_at' => $lease['claims']['exp'],
            'refresh_after' => $lease['claims']['refresh_after'],
            'release' => $lease['claims']['release'],
            'certified_at' => $lease['claims']['certified_at'],
            'certification_fingerprint_sha256' => $lease['claims']['cert_fp'],
            'server_time' => current_time('mysql', true),
        ], 200);
    }

    public static function issue(int $userId, string $deviceId, ?array $cert = null): array {
        $cert = is_array($cert) ? $cert : self::certificationState();
        if ($userId <= 0 || $deviceId === '' || empty($cert['certified'])) return ['token' => '', 'claims' => []];
        $now = time();
        $claims = [
            'v' => 1,
            'protocol' => self::PROTOCOL,
            'uid' => $userId,
            'device_sha256' => hash('sha256', $deviceId),
            'release' => defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '',
            'cert_fp' => (string)($cert['fingerprint_sha256'] ?? ''),
            'snapshot_sha256' => (string)($cert['snapshot_sha256'] ?? ''),
            'certified_at' => (string)($cert['certified_at'] ?? ''),
            'iat' => $now,
            'refresh_after' => $now + self::REFRESH_AFTER,
            'exp' => $now + self::TTL,
        ];
        $payload = self::base64url((string)wp_json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $payload, self::secret());
        return ['token' => $payload . '.' . $signature, 'claims' => $claims];
    }

    public static function verify(string $token, int $userId, string $deviceId): array {
        $token = trim($token);
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
            return ['valid' => false, 'reason' => 'malformed', 'claims' => []];
        }
        $expected = hash_hmac('sha256', $parts[0], self::secret());
        if (!hash_equals($expected, strtolower($parts[1]))) return ['valid' => false, 'reason' => 'signature', 'claims' => []];
        $claims = json_decode(self::base64urlDecode($parts[0]), true);
        if (!is_array($claims)) return ['valid' => false, 'reason' => 'payload', 'claims' => []];
        if ((string)($claims['protocol'] ?? '') !== self::PROTOCOL) return ['valid' => false, 'reason' => 'protocol', 'claims' => $claims];
        if (absint($claims['uid'] ?? 0) !== $userId) return ['valid' => false, 'reason' => 'user', 'claims' => $claims];
        if (!hash_equals((string)($claims['device_sha256'] ?? ''), hash('sha256', $deviceId))) return ['valid' => false, 'reason' => 'device', 'claims' => $claims];
        if (absint($claims['exp'] ?? 0) < time()) return ['valid' => false, 'reason' => 'expired', 'claims' => $claims];
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        if ($release === '' || !hash_equals($release, (string)($claims['release'] ?? ''))) return ['valid' => false, 'reason' => 'release', 'claims' => $claims];
        $cert = self::certificationState();
        if (empty($cert['certified'])) return ['valid' => false, 'reason' => 'certification_pending', 'claims' => $claims];
        $claimFp = strtolower((string)($claims['cert_fp'] ?? ''));
        $currentFp = strtolower((string)($cert['fingerprint_sha256'] ?? ''));
        if ($claimFp === '' || $currentFp === '' || !hash_equals($currentFp, $claimFp)) return ['valid' => false, 'reason' => 'certification_changed', 'claims' => $claims];
        return ['valid' => true, 'reason' => 'verified', 'claims' => $claims];
    }

    public static function certificationState(): array {
        if (!class_exists(Phase6StableRelease::class)) return ['certified' => false];
        $state = get_option(Phase6StableRelease::STATE_OPTION, []);
        $snapshot = get_option(Phase6StableRelease::SNAPSHOT_OPTION, []);
        $state = is_array($state) ? $state : [];
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $release = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $snapshotRelease = (string)($snapshot['release'] ?? '');
        $fingerprint = strtolower((string)($state['baseline_fingerprint_sha256'] ?? ''));
        $snapshotSha = strtolower((string)($state['snapshot_sha256'] ?? ($snapshot['snapshot_sha256'] ?? '')));
        $certified = !empty($state['stable_certified'])
            && $release !== ''
            && hash_equals($release, Phase6StableRelease::STABLE_RELEASE)
            && hash_equals($release, $snapshotRelease)
            && preg_match('/^[a-f0-9]{64}$/', $fingerprint) === 1
            && preg_match('/^[a-f0-9]{64}$/', $snapshotSha) === 1;
        return [
            'certified' => $certified,
            'release' => $release,
            'fingerprint_sha256' => $fingerprint,
            'snapshot_sha256' => $snapshotSha,
            'certified_at' => sanitize_text_field((string)($state['certified_at'] ?? '')),
        ];
    }

    private static function deviceId(WP_REST_Request $request): string {
        $body = $request->get_json_params() ?: [];
        $session = class_exists(Auth::class) ? (Auth::current_session() ?: []) : [];
        $value = (string)($request->get_header('x-fieldflow-device-id') ?: ($body['device_id'] ?? '') ?: ($session['device_id'] ?? ''));
        return substr(preg_replace('/[^A-Za-z0-9._:-]/', '', $value) ?: '', 0, 191);
    }

    private static function secret(): string {
        return hash('sha256', wp_salt('auth') . '|fieldflow-offline-trust-v1', true);
    }

    private static function base64url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $value): string {
        $padding = strlen($value) % 4;
        if ($padding) $value .= str_repeat('=', 4 - $padding);
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return is_string($decoded) ? $decoded : '';
    }
}
