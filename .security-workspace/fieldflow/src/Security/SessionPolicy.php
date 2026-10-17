<?php
namespace RoutesPro\Security;

use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Central authentication/session policy for privileged WordPress sessions,
 * native mobile tokens and the isolated FieldFlow WebView bridge.
 *
 * The policy is intentionally role-aware: field users keep a low-friction
 * mobile experience while administrative sessions receive stricter limits.
 */
final class SessionPolicy {
    public const VERSION = '1.0.0';
    public const OPTION = 'fieldflow_session_policy';
    private const WP_STATE_META = '_fieldflow_privileged_session_state';
    private static ?array $cache = null;

    public static function defaults(): array {
        return [
            'mfa_mode' => 'observe',
            'mfa_grace_days' => 7,
            'mfa_enforced_at' => '',
            'session_mode' => 'observe',
            'session_enforced_at' => '',
            'mobile_privileged_mfa' => 0,
            'wp_privileged_idle_minutes' => 480,
            'wp_privileged_absolute_hours' => 12,
            'mobile_idle_days' => 7,
            'mobile_absolute_days' => 30,
            'web_idle_minutes' => 60,
            'web_absolute_hours' => 24,
        ];
    }

    public static function registerHooks(): void {
        add_filter('auth_cookie_expiration', [self::class, 'authCookieExpiration'], 20, 3);
        add_action('init', [self::class, 'enforceWordPressSession'], 2);
    }

    public static function get(): array {
        if (self::$cache !== null) return self::$cache;
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        return self::$cache = self::sanitize(array_replace(self::defaults(), $saved));
    }

    public static function save(array $input) {
        $current = self::get();
        $clean = self::sanitize(array_replace($current, $input));
        $oldMode = (string)($current['mfa_mode'] ?? 'observe');
        $newMode = (string)($clean['mfa_mode'] ?? 'observe');
        $oldSessionMode = (string)($current['session_mode'] ?? 'observe');
        $newSessionMode = (string)($clean['session_mode'] ?? 'observe');
        if ($newMode === 'enforce' && $oldMode !== 'enforce') {
            $uid = get_current_user_id();
            if ($uid <= 0 || !class_exists(Mfa::class) || !Mfa::isEnabled($uid)) {
                return new WP_Error('fieldflow_mfa_enforcement_requires_enrollment', 'Ativa primeiro o MFA na tua conta antes de ligares o enforcement.');
            }
            if (!Mfa::isCurrentSessionVerified($uid)) {
                return new WP_Error('fieldflow_mfa_enforcement_requires_stepup', 'Confirma primeiro o MFA nesta sessão antes de ligares o enforcement.');
            }
            $clean['mfa_enforced_at'] = current_time('mysql', true);
        }
        if ($newMode !== 'enforce') $clean['mfa_enforced_at'] = '';
        if ($newSessionMode === 'enforce' && $oldSessionMode !== 'enforce') {
            $clean['session_enforced_at'] = current_time('mysql', true);
        }
        if ($newSessionMode !== 'enforce') $clean['session_enforced_at'] = '';
        update_option(self::OPTION, $clean, false);
        self::$cache = $clean;
        self::record('session_policy.updated', 'medium', ['mfa_mode'=>$newMode,'session_mode'=>$newSessionMode]);
        return $clean;
    }

    public static function sanitize(array $value): array {
        $d = self::defaults();
        $out = array_replace($d, $value);
        $out['mfa_mode'] = in_array((string)($out['mfa_mode'] ?? ''), ['observe','enforce'], true) ? (string)$out['mfa_mode'] : 'observe';
        $out['mfa_grace_days'] = max(0, min(30, absint($out['mfa_grace_days'] ?? 7)));
        $out['mfa_enforced_at'] = self::sanitizeUtcMysql((string)($out['mfa_enforced_at'] ?? ''));
        $out['session_mode'] = in_array((string)($out['session_mode'] ?? ''), ['observe','enforce'], true) ? (string)$out['session_mode'] : 'observe';
        $out['session_enforced_at'] = self::sanitizeUtcMysql((string)($out['session_enforced_at'] ?? ''));
        $out['mobile_privileged_mfa'] = empty($out['mobile_privileged_mfa']) ? 0 : 1;
        $out['wp_privileged_idle_minutes'] = max(15, min(1440, absint($out['wp_privileged_idle_minutes'] ?? 480)));
        $out['wp_privileged_absolute_hours'] = max(1, min(168, absint($out['wp_privileged_absolute_hours'] ?? 12)));
        $out['mobile_idle_days'] = max(1, min(90, absint($out['mobile_idle_days'] ?? 7)));
        $out['mobile_absolute_days'] = max($out['mobile_idle_days'], min(180, absint($out['mobile_absolute_days'] ?? 30)));
        $out['web_idle_minutes'] = max(10, min(1440, absint($out['web_idle_minutes'] ?? 60)));
        $out['web_absolute_hours'] = max(1, min(168, absint($out['web_absolute_hours'] ?? 24)));
        return $out;
    }

    public static function isPrivilegedUser(int $userId): bool {
        if ($userId <= 0) return false;
        $user = get_userdata($userId);
        return $user ? user_can($user, 'routespro_manage') : false;
    }

    public static function mfaMode(): string { return (string)self::get()['mfa_mode']; }
    public static function sessionMode(): string { return (string)self::get()['session_mode']; }
    public static function mobilePrivilegedMfa(): bool { return !empty(self::get()['mobile_privileged_mfa']); }
    public static function wpIdleSeconds(): int { return (int)self::get()['wp_privileged_idle_minutes'] * MINUTE_IN_SECONDS; }
    public static function wpAbsoluteSeconds(): int { return (int)self::get()['wp_privileged_absolute_hours'] * HOUR_IN_SECONDS; }
    public static function mobileIdleSeconds(): int { return (int)self::get()['mobile_idle_days'] * DAY_IN_SECONDS; }
    public static function mobileAbsoluteSeconds(): int { return (int)self::get()['mobile_absolute_days'] * DAY_IN_SECONDS; }
    public static function webIdleSeconds(): int { return (int)self::get()['web_idle_minutes'] * MINUTE_IN_SECONDS; }
    public static function webAbsoluteSeconds(): int { return (int)self::get()['web_absolute_hours'] * HOUR_IN_SECONDS; }

    public static function authCookieExpiration(int $length, int $userId, bool $remember): int {
        if (self::sessionMode() !== 'enforce') return $length;
        if (!self::isPrivilegedUser($userId)) return $length;
        if (self::isNativeWebViewContext()) return $length;
        return min($length, self::wpAbsoluteSeconds());
    }

    /**
     * Enforce idle and absolute lifetime for privileged WordPress/desktop sessions.
     * Native WebView sessions have their own policy and are deliberately excluded.
     */
    public static function enforceWordPressSession(): void {
        if (self::sessionMode() !== 'enforce') return;
        if (!is_user_logged_in()) return;
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        if (defined('WP_CLI') && WP_CLI) return;
        if (self::isNativeWebViewContext()) return;
        $uid = get_current_user_id();
        if (!self::isPrivilegedUser($uid)) return;
        if (!function_exists('wp_get_session_token')) return;
        $token = (string)wp_get_session_token();
        if ($token === '') return;

        $now = time();
        $key = self::sessionStateKey($token);
        $state = get_user_meta($uid, self::WP_STATE_META, true);
        if (!is_array($state)) $state = [];
        $row = is_array($state[$key] ?? null) ? $state[$key] : [];
        $started = max(0, (int)($row['started_at'] ?? 0));
        $last = max(0, (int)($row['last_seen_at'] ?? 0));
        if ($started <= 0) $started = $now;
        if ($last <= 0) $last = $now;

        $reason = '';
        if ($now - $started > self::wpAbsoluteSeconds()) $reason = 'absolute';
        elseif ($now - $last > self::wpIdleSeconds()) $reason = 'idle';
        if ($reason !== '') {
            unset($state[$key]);
            update_user_meta($uid, self::WP_STATE_META, self::pruneStates($state, $now));
            if (class_exists('WP_Session_Tokens')) {
                \WP_Session_Tokens::get_instance($uid)->destroy($token);
            }
            wp_clear_auth_cookie();
            wp_set_current_user(0);
            self::record('session.privileged_expired', 'medium', ['reason'=>$reason]);
            if (wp_doing_ajax()) {
                status_header(401);
                wp_die('Sessão expirada. Inicia sessão novamente.', 'Sessão expirada', ['response'=>401]);
            }
            $url = self::publicLoginUrl($reason);
            wp_safe_redirect($url);
            exit;
        }

        if ($now - $last >= 300 || empty($row)) {
            $state[$key] = ['started_at'=>$started, 'last_seen_at'=>$now];
            update_user_meta($uid, self::WP_STATE_META, self::pruneStates($state, $now));
        }
    }

    public static function validateMobileSession(array $row) {
        if (self::sessionMode() !== 'enforce') return true;
        $now = time();
        $created = self::absoluteBaseline(self::timestamp((string)($row['created_at'] ?? '')));
        $last = self::timestamp((string)($row['last_seen_at'] ?? ''));
        if ($created > 0 && $now > $created + self::mobileAbsoluteSeconds()) {
            return new WP_Error('fieldflow_mobile_absolute_session_expired', 'A sessão móvel atingiu o limite máximo. Inicia sessão novamente.', ['status'=>401]);
        }
        if ($last > 0 && $now > $last + self::mobileIdleSeconds()) {
            return new WP_Error('fieldflow_mobile_inactive_session_expired', 'A sessão móvel expirou por inatividade. Inicia sessão novamente.', ['status'=>401]);
        }
        return true;
    }

    public static function mobileRefreshExpiry(array $row, int $now = 0): int {
        $now = $now > 0 ? $now : time();
        $configured = class_exists('RoutesPro\\Mobile\\Settings') ? \RoutesPro\Mobile\Settings::refresh_ttl() : self::mobileAbsoluteSeconds();
        if (self::sessionMode() !== 'enforce') return $now + $configured;
        $created = self::absoluteBaseline(self::timestamp((string)($row['created_at'] ?? '')) ?: $now);
        return min($now + $configured, $created + self::mobileAbsoluteSeconds());
    }

    public static function validateWebSession(array $row) {
        if (self::sessionMode() !== 'enforce') return true;
        // Phase 9S emergency continuity: field users are already bounded by the
        // parent mobile refresh session (idle + absolute limits). Applying the
        // privileged 60-minute WebView idle policy to merchandisers caused a
        // camera/maps/background interval to look like a full logout. Keep the
        // stricter WebView policy only for privileged users.
        $userId = absint($row['user_id'] ?? 0);
        if ($userId > 0 && !self::isPrivilegedUser($userId)) return true;
        $now = time();
        $created = self::absoluteBaseline(self::timestamp((string)($row['created_at'] ?? '')));
        $last = self::timestamp((string)($row['last_seen_at'] ?? ''));
        if ($created > 0 && $now > $created + self::webAbsoluteSeconds()) {
            return new WP_Error('fieldflow_mobile_web_absolute_expired', 'A sessão do portal atingiu o limite máximo. Reabre o portal na aplicação.', ['status'=>401]);
        }
        if ($last > 0 && $now > $last + self::webIdleSeconds()) {
            return new WP_Error('fieldflow_mobile_web_idle_expired', 'A sessão do portal expirou por inatividade. Reabre o portal na aplicação.', ['status'=>401]);
        }
        return true;
    }

    public static function webExpiry(array $row, int $parentExpiry, int $now = 0): int {
        $now = $now > 0 ? $now : time();
        $webConfigured = class_exists('RoutesPro\\Mobile\\Settings') ? \RoutesPro\Mobile\Settings::web_ttl() : self::webAbsoluteSeconds();
        $candidates = [$now + $webConfigured];
        $userId = absint($row['user_id'] ?? 0);
        if (self::sessionMode() === 'enforce' && ($userId <= 0 || self::isPrivilegedUser($userId))) {
            $created = self::absoluteBaseline(self::timestamp((string)($row['created_at'] ?? '')) ?: $now);
            $candidates[] = $created + self::webAbsoluteSeconds();
        }
        if ($parentExpiry > 0) $candidates[] = $parentExpiry;
        return min($candidates);
    }

    public static function mfaGraceActiveForUser(int $userId): bool {
        if (!self::isPrivilegedUser($userId)) return false;
        $s = self::get();
        if (($s['mfa_mode'] ?? 'observe') !== 'enforce') return false;
        if (class_exists(Mfa::class)) {
            $userGrace = (int)get_user_meta($userId, Mfa::GRACE_UNTIL_META, true);
            if ($userGrace > time()) return true;
        }
        $enforced = self::timestamp((string)($s['mfa_enforced_at'] ?? ''));
        if ($enforced <= 0) return false;
        return time() <= $enforced + ((int)$s['mfa_grace_days'] * DAY_IN_SECONDS);
    }

    public static function mfaGraceEndsAt(): string {
        $s = self::get();
        $enforced = self::timestamp((string)($s['mfa_enforced_at'] ?? ''));
        if ($enforced <= 0) return '';
        return gmdate('Y-m-d H:i:s', $enforced + ((int)$s['mfa_grace_days'] * DAY_IN_SECONDS));
    }

    public static function status(): array {
        $s = self::get();
        $summary = class_exists(Mfa::class) ? Mfa::privilegedSummary() : ['privileged'=>0,'enabled'=>0,'pending'=>0];
        return [
            'version'=>self::VERSION,
            'mfa_mode'=>(string)$s['mfa_mode'],
            'mfa_grace_days'=>(int)$s['mfa_grace_days'],
            'mfa_enforced_at'=>(string)$s['mfa_enforced_at'],
            'session_mode'=>(string)$s['session_mode'],
            'session_enforced_at'=>(string)$s['session_enforced_at'],
            'mfa_grace_ends_at'=>self::mfaGraceEndsAt(),
            'mobile_privileged_mfa'=>!empty($s['mobile_privileged_mfa']),
            'privileged_users'=>(int)($summary['privileged'] ?? 0),
            'mfa_enabled_users'=>(int)($summary['enabled'] ?? 0),
            'mfa_pending_users'=>(int)($summary['pending'] ?? 0),
            'wp_privileged_idle_minutes'=>(int)$s['wp_privileged_idle_minutes'],
            'wp_privileged_absolute_hours'=>(int)$s['wp_privileged_absolute_hours'],
            'mobile_idle_days'=>(int)$s['mobile_idle_days'],
            'mobile_absolute_days'=>(int)$s['mobile_absolute_days'],
            'web_idle_minutes'=>(int)$s['web_idle_minutes'],
            'web_absolute_hours'=>(int)$s['web_absolute_hours'],
        ];
    }

    private static function absoluteBaseline(int $created): int {
        $enforced = self::timestamp((string)(self::get()['session_enforced_at'] ?? ''));
        return max($created, $enforced);
    }

    private static function publicLoginUrl(string $reason): string {
        $base = (string)apply_filters('fieldflow_session_login_url', home_url('/login/'));
        if ($base === '') $base = wp_login_url();
        return add_query_arg([
            'fieldflow_session'=>$reason === 'idle' ? 'idle' : 'absolute',
            'redirect_to'=>admin_url(),
        ], $base);
    }

    private static function isNativeWebViewContext(): bool {
        return class_exists('RoutesPro\\Mobile\\WebViewBridge') && \RoutesPro\Mobile\WebViewBridge::is_native_context();
    }

    private static function sessionStateKey(string $token): string {
        return substr(hash_hmac('sha256', $token, (string)wp_salt('auth')), 0, 40);
    }

    private static function pruneStates(array $states, int $now): array {
        $maxAge = max(self::wpAbsoluteSeconds(), DAY_IN_SECONDS) + DAY_IN_SECONDS;
        foreach ($states as $key=>$row) {
            if (!is_array($row) || (int)($row['last_seen_at'] ?? 0) < $now - $maxAge) unset($states[$key]);
        }
        if (count($states) > 20) {
            uasort($states, static fn($a,$b)=>(int)($b['last_seen_at'] ?? 0) <=> (int)($a['last_seen_at'] ?? 0));
            $states = array_slice($states, 0, 20, true);
        }
        return $states;
    }

    private static function timestamp(string $value): int {
        if ($value === '') return 0;
        $ts = strtotime($value . (preg_match('/(?:Z|[+-]\d\d:?\d\d)$/', $value) ? '' : ' UTC'));
        return $ts ?: 0;
    }

    private static function sanitizeUtcMysql(string $value): string {
        $ts = self::timestamp(trim($value));
        return $ts > 0 ? gmdate('Y-m-d H:i:s', $ts) : '';
    }

    private static function record(string $type, string $severity, array $context): void {
        if (class_exists(SecurityService::class)) SecurityService::record($type, $severity, $context);
    }
}
