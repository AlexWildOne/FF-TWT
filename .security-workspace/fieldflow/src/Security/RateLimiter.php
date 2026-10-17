<?php
namespace RoutesPro\Security;

use WP_Error;

if (!defined('ABSPATH')) exit;

class RateLimiter {
    public static function enforce(string $bucket, int $limit, int $window = 60, string $identity = '') {
        if ($limit <= 0) return true;
        $identity = $identity !== '' ? $identity : self::identity();
        $key = 'ff_rl_' . md5($bucket . '|' . $identity . '|' . floor(time() / max(1, $window)));
        $hits = (int)get_transient($key);
        if ($hits >= $limit) {
            SecurityService::record('rate_limit.exceeded', 'warning', [
                'bucket' => $bucket,
                'identity_hash' => hash('sha256', $identity),
                'limit' => $limit,
                'window_seconds' => $window,
            ]);
            return new WP_Error('fieldflow_rate_limited', 'Demasiados pedidos. Tenta novamente dentro de momentos.', [
                'status' => 429,
                'retry_after' => $window,
            ]);
        }
        set_transient($key, $hits + 1, $window + 5);
        return true;
    }

    public static function identity(): string {
        $uid = get_current_user_id();
        $ip = sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $device = sanitize_text_field((string)($_SERVER['HTTP_X_FIELDFLOW_DEVICE_ID'] ?? ''));
        return ($uid > 0 ? 'u:' . $uid : 'ip:' . $ip) . ($device !== '' ? '|d:' . $device : '');
    }
}
