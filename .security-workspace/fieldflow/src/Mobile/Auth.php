<?php
namespace RoutesPro\Mobile;

use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

class Auth {
    const ACCESS_TTL = HOUR_IN_SECONDS;
    const REFRESH_TTL = 30 * DAY_IN_SECONDS;
    const CLEANUP_HOOK = 'fieldflow_mobile_session_cleanup';
    const REFRESH_REPLAY_TTL = 30;
    const FIELD_SESSION_FORM_CONTINUITY_CONTRACT = 'fieldflow-field-session-form-continuity-v1';

    private static $current_session = null;
    private static bool $authenticated_session = false;

    public static function has_authenticated_session(): bool {
        return self::$authenticated_session && is_array(self::$current_session)
            && (int)self::$current_session['user_id'] === get_current_user_id();
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_mobile_sessions';
    }

    public static function register_hooks(): void {
        add_filter('rest_authentication_errors', [self::class, 'authenticate_bearer'], 20);
        add_filter('rest_allowed_cors_headers', [self::class, 'allowed_cors_headers']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function authenticate_bearer($result) {
        if ($result !== null) return $result;
        if (is_user_logged_in()) return $result;

        $token = self::bearer_token();
        if ($token === '') return $result;

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE access_token_hash=%s AND revoked_at IS NULL AND access_expires_at > %s LIMIT 1',
            self::hash_token($token),
            current_time('mysql', true)
        ), ARRAY_A);
        if (!$row) {
            return new WP_Error('fieldflow_mobile_invalid_token', 'Invalid or expired mobile access token.', ['status' => 401]);
        }
        $policy = \RoutesPro\Security\SessionPolicy::validateMobileSession($row);
        if (is_wp_error($policy)) {
            self::revoke_by_id((int)$row['id']);
            return $policy;
        }
        if (Settings::is_device_blocked((string)($row['device_id'] ?? ''))) {
            self::revoke_by_id((int)$row['id']);
            return new WP_Error('fieldflow_mobile_device_blocked', 'This device has been blocked by an administrator.', ['status' => 403]);
        }
        $platform = Settings::platform((string)($row['platform'] ?? ''));
        if (!Settings::platform_enabled($platform)) {
            return new WP_Error('fieldflow_mobile_platform_disabled', 'This mobile platform is currently disabled.', ['status' => 503]);
        }

        $user = get_userdata((int)$row['user_id']);
        if (!$user) {
            return new WP_Error('fieldflow_mobile_invalid_user', 'The mobile session user no longer exists.', ['status' => 401]);
        }

        wp_set_current_user((int)$row['user_id']);
        self::$current_session = $row;
        self::$authenticated_session = true;
        self::touch((int)$row['id'], (string)($row['last_seen_at'] ?? ''));
        return $result;
    }

    public static function issue(int $user_id, array $device = []) {
        global $wpdb;
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return new WP_Error('fieldflow_mobile_invalid_user', 'Invalid user.', ['status' => 400]);
        }

        $access = self::random_token();
        $refresh = self::random_token();
        $now = time();
        $device_id = self::sanitize_device_id($device['device_id'] ?? '');
        if ($device_id === '') $device_id = 'device-' . substr(hash('sha256', $refresh), 0, 20);
        $platform = Settings::platform((string)($device['platform'] ?? ''));
        if ($platform !== '' && !Settings::platform_enabled($platform)) {
            return new WP_Error('fieldflow_mobile_platform_disabled', 'This mobile platform is currently disabled.', ['status' => 503]);
        }
        if (Settings::is_device_blocked($device_id)) {
            return new WP_Error('fieldflow_mobile_device_blocked', 'This device has been blocked by an administrator.', ['status' => 403]);
        }

        $wpdb->insert(self::table(), [
            'user_id' => $user_id,
            'device_id' => $device_id,
            'device_name' => sanitize_text_field((string)($device['device_name'] ?? '')),
            'platform' => $platform,
            'app_version' => sanitize_text_field((string)($device['app_version'] ?? '')),
            'access_token_hash' => self::hash_token($access),
            'refresh_token_hash' => self::hash_token($refresh),
            'access_expires_at' => gmdate('Y-m-d H:i:s', $now + Settings::access_ttl()),
            'refresh_expires_at' => gmdate('Y-m-d H:i:s', \RoutesPro\Security\SessionPolicy::mobileRefreshExpiry(['created_at'=>gmdate('Y-m-d H:i:s', $now)], $now)),
            'last_seen_at' => gmdate('Y-m-d H:i:s', $now),
            'last_ip' => self::client_ip(),
            'user_agent' => substr(sanitize_text_field((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
            'created_at' => gmdate('Y-m-d H:i:s', $now),
            'updated_at' => gmdate('Y-m-d H:i:s', $now),
        ], ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);

        if (!$wpdb->insert_id) {
            return new WP_Error('fieldflow_mobile_session_create_failed', $wpdb->last_error ?: 'Could not create mobile session.', ['status' => 500]);
        }
        if (class_exists('RoutesPro\\Mobile\\DeviceManager')) {
            DeviceManager::setControl($user_id, $device_id, ['force_logout' => 0]);
        }

        return [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'token_type' => 'FieldFlow',
            'access_header' => 'X-FieldFlow-Mobile-Token',
            'expires_in' => Settings::access_ttl(),
            'refresh_expires_in' => max(0, \RoutesPro\Security\SessionPolicy::mobileRefreshExpiry(['created_at'=>gmdate('Y-m-d H:i:s', $now)], $now) - $now),
            'session_id' => (int)$wpdb->insert_id,
            'device_id' => $device_id,
        ];
    }

    public static function refresh(string $refresh_token, array $device = []) {
        global $wpdb;
        $refresh_token = trim($refresh_token);
        if ($refresh_token === '') {
            return new WP_Error('fieldflow_mobile_refresh_required', 'Refresh token is required.', ['status' => 400]);
        }

        $refresh_hash = self::hash_token($refresh_token);
        $replayed = self::refresh_replay_get($refresh_hash, $device);
        if (is_array($replayed)) return $replayed;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE refresh_token_hash=%s AND revoked_at IS NULL AND refresh_expires_at > %s LIMIT 1',
            $refresh_hash,
            current_time('mysql', true)
        ), ARRAY_A);
        if (!$row) {
            // The winning concurrent request may have rotated the token after
            // our first replay lookup but before this SELECT completed.
            $replayed = self::refresh_replay_get($refresh_hash, $device);
            if (is_array($replayed)) return $replayed;
            return new WP_Error('fieldflow_mobile_invalid_refresh', 'Invalid or expired refresh token.', ['status' => 401]);
        }
        $policy = \RoutesPro\Security\SessionPolicy::validateMobileSession($row);
        if (is_wp_error($policy)) {
            self::revoke_by_id((int)$row['id']);
            return $policy;
        }
        if (Settings::is_device_blocked((string)($row['device_id'] ?? ''))) {
            self::revoke_by_id((int)$row['id']);
            return new WP_Error('fieldflow_mobile_device_blocked', 'This device has been blocked by an administrator.', ['status' => 403]);
        }
        $session_platform = Settings::platform((string)($row['platform'] ?? $device['platform'] ?? ''));
        if ($session_platform !== '' && !Settings::platform_enabled($session_platform)) {
            return new WP_Error('fieldflow_mobile_platform_disabled', 'This mobile platform is currently disabled.', ['status' => 503]);
        }

        $requested_device = self::sanitize_device_id($device['device_id'] ?? '');
        if ($requested_device !== '' && !hash_equals((string)$row['device_id'], $requested_device)) {
            return new WP_Error('fieldflow_mobile_device_mismatch', 'The refresh token belongs to another device.', ['status' => 401]);
        }

        $access = self::random_token();
        $refresh = self::random_token();
        $now = time();
        $updated = $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . ' SET access_token_hash=%s, refresh_token_hash=%s, access_expires_at=%s, refresh_expires_at=%s, app_version=%s, last_seen_at=%s, last_ip=%s, updated_at=%s WHERE id=%d AND refresh_token_hash=%s AND revoked_at IS NULL',
            self::hash_token($access),
            self::hash_token($refresh),
            gmdate('Y-m-d H:i:s', $now + Settings::access_ttl()),
            gmdate('Y-m-d H:i:s', \RoutesPro\Security\SessionPolicy::mobileRefreshExpiry($row, $now)),
            sanitize_text_field((string)($device['app_version'] ?? $row['app_version'] ?? '')),
            gmdate('Y-m-d H:i:s', $now),
            self::client_ip(),
            gmdate('Y-m-d H:i:s', $now),
            (int)$row['id'],
            $refresh_hash
        ));

        if ($updated !== 1) {
            if (!$wpdb->last_error) {
                $replayed = self::refresh_replay_get($refresh_hash, $device);
                if (is_array($replayed)) return $replayed;
            }
            return new WP_Error('fieldflow_mobile_refresh_failed', $wpdb->last_error ?: 'The refresh token was already rotated.', ['status' => $wpdb->last_error ? 500 : 409]);
        }

        $response = [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'token_type' => 'FieldFlow',
            'access_header' => 'X-FieldFlow-Mobile-Token',
            'expires_in' => Settings::access_ttl(),
            'refresh_expires_in' => max(0, \RoutesPro\Security\SessionPolicy::mobileRefreshExpiry($row, $now) - $now),
            'session_id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'device_id' => (string)$row['device_id'],
        ];
        self::refresh_replay_store($refresh_hash, $response, $row);
        return $response;
    }

    private static function refresh_replay_transient_key(string $refresh_hash): string {
        return 'fieldflow_mobile_refresh_replay_' . substr(hash_hmac('sha256', $refresh_hash, wp_salt('auth')), 0, 40);
    }

    private static function refresh_replay_secret(): string {
        return hash('sha256', wp_salt('auth') . '|fieldflow-mobile-refresh-replay-v1', true);
    }

    private static function refresh_replay_encrypt(array $payload): string {
        if (!function_exists('openssl_encrypt')) return '';
        try {
            $iv = random_bytes(12);
        } catch (\Throwable $e) {
            return '';
        }
        $tag = '';
        $json = wp_json_encode($payload);
        if (!is_string($json) || $json === '') return '';
        $cipher = openssl_encrypt(
            $json,
            'aes-256-gcm',
            self::refresh_replay_secret(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::FIELD_SESSION_FORM_CONTINUITY_CONTRACT
        );
        if (!is_string($cipher) || strlen($tag) !== 16) return '';
        return base64_encode($iv . $tag . $cipher);
    }

    private static function refresh_replay_decrypt(string $sealed): ?array {
        if ($sealed === '' || !function_exists('openssl_decrypt')) return null;
        $raw = base64_decode($sealed, true);
        if (!is_string($raw) || strlen($raw) <= 28) return null;
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $json = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            self::refresh_replay_secret(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::FIELD_SESSION_FORM_CONTINUITY_CONTRACT
        );
        if (!is_string($json) || $json === '') return null;
        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : null;
    }

    private static function refresh_replay_store(string $refresh_hash, array $response, array $row): void {
        $sealed = self::refresh_replay_encrypt([
            'expires_at' => time() + self::REFRESH_REPLAY_TTL,
            'session_id' => (int)($row['id'] ?? 0),
            'device_id' => (string)($row['device_id'] ?? ''),
            'response' => $response,
        ]);
        if ($sealed === '') return;
        set_transient(self::refresh_replay_transient_key($refresh_hash), $sealed, self::REFRESH_REPLAY_TTL);
    }

    private static function refresh_replay_get(string $refresh_hash, array $device = []): ?array {
        $sealed = get_transient(self::refresh_replay_transient_key($refresh_hash));
        if (!is_string($sealed) || $sealed === '') return null;
        $payload = self::refresh_replay_decrypt($sealed);
        if (!is_array($payload) || (int)($payload['expires_at'] ?? 0) < time()) return null;
        $requested_device = self::sanitize_device_id($device['device_id'] ?? '');
        $stored_device = self::sanitize_device_id($payload['device_id'] ?? '');
        if ($requested_device !== '' && ($stored_device === '' || !hash_equals($stored_device, $requested_device))) return null;
        $response = $payload['response'] ?? null;
        return is_array($response) ? $response : null;
    }

    public static function revoke_by_id(int $session_id): bool {
        global $wpdb;
        if ($session_id <= 0) return false;
        return $wpdb->update(self::table(), [
            'revoked_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $session_id], ['%s','%s'], ['%d']) !== false;
    }

    public static function revoke_by_device(string $device_id): bool {
        global $wpdb;
        $device_id = self::sanitize_device_id($device_id);
        if ($device_id === '') return false;
        return $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . ' SET revoked_at=%s, updated_at=%s WHERE device_id=%s AND revoked_at IS NULL',
            current_time('mysql', true),
            current_time('mysql', true),
            $device_id
        )) !== false;
    }

    public static function revoke_current(): bool {
        global $wpdb;
        $session = self::current_session();
        if (!$session) return true;
        return $wpdb->update(self::table(), [
            'revoked_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => (int)$session['id']], ['%s','%s'], ['%d']) !== false;
    }

    public static function revoke_all(int $user_id): bool {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . ' SET revoked_at=%s, updated_at=%s WHERE user_id=%d AND revoked_at IS NULL',
            current_time('mysql', true),
            current_time('mysql', true),
            $user_id
        )) !== false;
    }

    public static function current_session(): ?array {
        if (is_array(self::$current_session)) return self::$current_session;
        $token = self::bearer_token();
        if ($token === '') return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE access_token_hash=%s AND revoked_at IS NULL LIMIT 1',
            self::hash_token($token)
        ), ARRAY_A);
        self::$current_session = $row ?: null;
        return self::$current_session;
    }

    public static function login_rate_limit(string $username) {
        $key = 'ff_mobile_login_' . md5(strtolower(trim($username)) . '|' . self::client_ip());
        $attempts = (int)get_transient($key);
        if ($attempts >= Settings::login_attempts()) {
            return new WP_Error('fieldflow_mobile_login_rate_limited', 'Too many login attempts. Try again later.', ['status' => 429]);
        }
        set_transient($key, $attempts + 1, Settings::login_window());
        return true;
    }

    public static function clear_login_rate_limit(string $username): void {
        delete_transient('ff_mobile_login_' . md5(strtolower(trim($username)) . '|' . self::client_ip()));
    }

    public static function cleanup(): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE refresh_expires_at < %s OR (revoked_at IS NOT NULL AND revoked_at < %s)',
            current_time('mysql', true),
            gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
        ));
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . $wpdb->prefix . 'routespro_mobile_mutations WHERE expires_at < %s',
            current_time('mysql', true)
        ));
    }

    private static function touch(int $id, string $last_seen): void {
        if ($last_seen && strtotime($last_seen) > time() - 300) return;
        global $wpdb;
        $wpdb->update(self::table(), [
            'last_seen_at' => current_time('mysql', true),
            'last_ip' => self::client_ip(),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id], ['%s','%s','%s'], ['%d']);
    }

    public static function allowed_cors_headers(array $headers): array {
        foreach (['X-FieldFlow-Mobile-Token', 'X-FieldFlow-Token', 'X-FieldFlow-Mutation-Id', 'X-FieldFlow-Device-ID', 'X-FieldFlow-Correlation-ID', 'X-FieldFlow-App-Version'] as $header) {
            if (!in_array($header, $headers, true)) $headers[] = $header;
        }
        return $headers;
    }

    private static function bearer_token(): string {
        // Prefer a FieldFlow-specific header so JWT plugins do not intercept opaque mobile tokens.
        $custom = '';
        if (isset($_SERVER['HTTP_X_FIELDFLOW_MOBILE_TOKEN'])) {
            $custom = (string)$_SERVER['HTTP_X_FIELDFLOW_MOBILE_TOKEN'];
        } elseif (isset($_SERVER['HTTP_X_FIELDFLOW_TOKEN'])) {
            $custom = (string)$_SERVER['HTTP_X_FIELDFLOW_TOKEN'];
        }

        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ($custom === '') {
                $custom = (string)(
                    $headers['X-FieldFlow-Mobile-Token']
                    ?? $headers['x-fieldflow-mobile-token']
                    ?? $headers['X-FieldFlow-Token']
                    ?? $headers['x-fieldflow-token']
                    ?? ''
                );
            }
        }
        if (trim($custom) !== '') return trim($custom);

        // Legacy fallback for installations without another JWT plugin claiming Bearer headers.
        $header = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) $header = (string)$_SERVER['HTTP_AUTHORIZATION'];
        elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $header = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        if ($header === '' && $headers) {
            $header = (string)($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }
        return preg_match('/^Bearer\s+(.+)$/i', trim($header), $m) ? trim($m[1]) : '';
    }

    private static function random_token(): string {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return wp_generate_password(64, false, false);
        }
    }

    private static function hash_token(string $token): string {
        return hash('sha256', $token);
    }

    private static function sanitize_device_id($value): string {
        return substr(preg_replace('/[^A-Za-z0-9._:-]/', '', (string)$value) ?: '', 0, 191);
    }

    private static function client_ip(): string {
        return substr(sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 64);
    }
}
