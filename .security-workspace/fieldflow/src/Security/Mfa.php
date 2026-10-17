<?php
namespace RoutesPro\Security;

use RoutesPro\Integrations\SecretVault;
use WP_Error;
use WP_User;

if (!defined('ABSPATH')) exit;

/**
 * RFC 6238 TOTP MFA for privileged FieldFlow accounts.
 * Secrets are encrypted with the existing SecretVault; recovery codes are
 * stored only as one-way password hashes and are single-use.
 */
final class Mfa {
    public const VERSION = '1.0.0';
    private const SECRET_META = '_fieldflow_mfa_secret_enc';
    private const PENDING_META = '_fieldflow_mfa_pending';
    private const ENABLED_AT_META = '_fieldflow_mfa_enabled_at';
    private const RECOVERY_META = '_fieldflow_mfa_recovery_hashes';
    private const LAST_COUNTER_META = '_fieldflow_mfa_last_counter';
    public const GRACE_UNTIL_META = '_fieldflow_mfa_grace_until';
    private const RECOVERY_TRANSIENT_PREFIX = 'fieldflow_mfa_recovery_once_';
    private const VERIFIED_SESSIONS_META = '_fieldflow_mfa_verified_sessions';
    public const CHALLENGE_SLUG = 'fieldflow-mfa-challenge';

    public static function registerHooks(): void {
        add_action('admin_menu', [self::class, 'registerChallengePage'], 1);
        add_action('admin_init', [self::class, 'enforceAdminChallenge'], 1);
        add_action('template_redirect', [self::class, 'enforceFrontendChallenge'], 1);
        add_filter('rest_pre_dispatch', [self::class, 'enforceRestChallenge'], 5, 3);
        add_action('admin_post_fieldflow_mfa_verify_challenge', [self::class, 'verifyChallengeAction']);
        add_action('admin_notices', [self::class, 'graceNotice']);
        add_filter('login_message', [self::class, 'loginMessage']);
    }

    public static function registerChallengePage(): void {
        add_submenu_page(null, 'Verificação MFA | FieldFlow', 'Verificação MFA', 'routespro_manage', self::CHALLENGE_SLUG, [self::class, 'renderChallenge']);
    }

    public static function enforceAdminChallenge(): void {
        global $pagenow;
        $error = self::requestError();
        if (!is_wp_error($error)) return;
        $enabled = self::isEnabled(get_current_user_id());
        // Query parameters never exempt AJAX or an unrelated admin handler.
        if (!wp_doing_ajax()) {
            $page = (string)($_GET['page'] ?? '');
            $action = (string)($_POST['action'] ?? '');
            if ($pagenow === 'admin.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && empty($_REQUEST['action'])) {
                if ($enabled && $page === self::CHALLENGE_SLUG) return;
                if (!$enabled && $page === \RoutesPro\Admin\SecurityObservability::SLUG && (string)($_GET['tab'] ?? '') === 'auth') return;
            }
            if ($pagenow === 'admin-post.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_REQUEST['action'] ?? '') === $action) {
                if ($enabled && $action === 'fieldflow_mfa_verify_challenge') return;
                if (!$enabled && in_array($action, ['fieldflow_mfa_begin', 'fieldflow_mfa_activate'], true)) return;
            }
        }
        if (wp_doing_ajax()) {
            status_header(401);
            wp_die('MFA required.', 'MFA required', ['response'=>401]);
        }
        if (!$enabled) self::redirectToEnrollment();
        self::redirectToChallenge();
    }

    public static function requestError() {
        if (!is_user_logged_in() || !current_user_can('routespro_manage')) return true;
        if (class_exists('\\RoutesPro\\Mobile\\Auth') && \RoutesPro\Mobile\Auth::has_authenticated_session()) return true;
        $uid = get_current_user_id();
        if (!self::isEnabled($uid)) {
            if (SessionPolicy::mfaMode() !== 'enforce' || SessionPolicy::mfaGraceActiveForUser($uid)) return true;
            return new WP_Error('fieldflow_mfa_enrollment_required', 'Configura o MFA antes de continuar.', ['status'=>403]);
        }
        return self::isCurrentSessionVerified($uid) ? true : new WP_Error('fieldflow_mfa_required', 'MFA required.', ['status'=>401]);
    }

    public static function enforceRestChallenge($result, $server, \WP_REST_Request $request) {
        if (is_wp_error($result)) return $result;
        if ($request->get_method() === 'POST' && in_array($request->get_route(), [
            '/fieldflow-mobile/v1/auth/login', '/fieldflow-mobile/v1/auth/refresh',
        ], true)) return $result;
        $error = self::requestError();
        return is_wp_error($error) ? $error : $result;
    }

    public static function enforceFrontendChallenge(): void {
        if (!is_user_logged_in() || !current_user_can('routespro_manage')) return;
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (wp_doing_ajax()) return;
        if (!is_wp_error(self::requestError())) return;
        $uid = get_current_user_id();
        if (!self::isEnabled($uid)) {
            if (SessionPolicy::mfaMode() === 'enforce' && !SessionPolicy::mfaGraceActiveForUser($uid)) self::redirectToEnrollment();
            return;
        }
        if (!self::isCurrentSessionVerified($uid)) self::redirectToChallenge();
    }

    public static function renderChallenge(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $uid = get_current_user_id();
        if (!self::isEnabled($uid)) { self::redirectToEnrollment(); }
        if (self::isCurrentSessionVerified($uid)) { wp_safe_redirect(admin_url()); exit; }
        $notice = sanitize_key((string)($_GET['fieldflow_mfa_notice'] ?? ''));
        echo '<div class="wrap" style="max-width:680px;margin-top:42px"><div style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:28px;box-shadow:0 12px 35px rgba(0,0,0,.06)"><h1 style="margin-top:0">Verificação MFA</h1><p>Confirma o segundo fator para abrir a área administrativa FieldFlow. Podes usar um código de 6 dígitos do Authenticator ou um código de recuperação.</p>';
        if ($notice === 'invalid') echo '<div class="notice notice-error inline"><p>Código inválido ou já utilizado.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('fieldflow_mfa_verify_challenge');
        echo '<input type="hidden" name="action" value="fieldflow_mfa_verify_challenge"><p><label for="fieldflow-mfa-challenge-code"><strong>Código MFA</strong></label><br><input id="fieldflow-mfa-challenge-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus style="width:260px;font-size:24px;letter-spacing:.12em;padding:8px 12px"></p><p><button class="button button-primary button-large">Confirmar e continuar</button></p></form><p style="color:#646970">Sessão BO/Desktop: inatividade '.(int)(SessionPolicy::wpIdleSeconds()/MINUTE_IN_SECONDS).' min · limite absoluto '.(int)(SessionPolicy::wpAbsoluteSeconds()/HOUR_IN_SECONDS).' h.</p></div></div>';
    }

    public static function verifyChallengeAction(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_mfa_verify_challenge');
        $uid = get_current_user_id();
        $result = self::verifyLoginCode($uid, sanitize_text_field((string)($_POST['code'] ?? '')), true);
        if (is_wp_error($result)) {
            self::record('mfa.challenge_failed', 'medium', ['surface'=>'admin']);
            wp_safe_redirect(add_query_arg(['page'=>self::CHALLENGE_SLUG,'fieldflow_mfa_notice'=>'invalid'], admin_url('admin.php'))); exit;
        }
        self::markCurrentSessionVerified($uid);
        self::record('mfa.challenge_success', 'low', ['surface'=>'admin']);
        wp_safe_redirect(admin_url()); exit;
    }

    public static function graceNotice(): void {
        if (!current_user_can('routespro_manage')) return;
        $uid = get_current_user_id();
        if (self::isEnabled($uid) || SessionPolicy::mfaMode() !== 'enforce' || !SessionPolicy::mfaGraceActiveForUser($uid)) return;
        $url = add_query_arg(['page'=>\RoutesPro\Admin\SecurityObservability::SLUG,'tab'=>'auth'], admin_url('admin.php'));
        echo '<div class="notice notice-warning"><p><strong>FieldFlow MFA:</strong> esta conta está no período de adesão. Configura o segundo fator antes do fim da janela de segurança. <a href="'.esc_url($url).'">Configurar agora</a>.</p></div>';
    }

    public static function loginMessage(string $message): string {
        $reason = sanitize_key((string)($_GET['fieldflow_session'] ?? ''));
        if ($reason === 'idle') $message .= '<div id="login_error">A sessão administrativa expirou por inatividade. Inicia sessão novamente.</div>';
        elseif ($reason === 'absolute') $message .= '<div id="login_error">A sessão administrativa atingiu o limite máximo. Inicia sessão novamente.</div>';
        return $message;
    }

    public static function verifyMobileLogin(WP_User $user, array $body) {
        $uid = (int)$user->ID;
        if (!SessionPolicy::isPrivilegedUser($uid) || !SessionPolicy::mobilePrivilegedMfa()) return true;
        if (!self::isEnabled($uid)) {
            if (SessionPolicy::mfaMode() === 'enforce' && !SessionPolicy::mfaGraceActiveForUser($uid)) {
                return new WP_Error('fieldflow_mobile_mfa_enrollment_required', 'Esta conta privilegiada requer MFA antes de poder iniciar sessão na app.', ['status'=>403]);
            }
            return true;
        }
        $code = sanitize_text_field((string)($body['mfa_code'] ?? ''));
        $result = self::verifyLoginCode($uid, $code, true);
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            if (!is_array($data)) $data = [];
            $data['status'] = 401;
            return new WP_Error($result->get_error_code(), $result->get_error_message(), $data);
        }
        self::record('mfa.login_success', 'low', ['surface'=>'mobile']);
        return true;
    }

    public static function beginEnrollment(int $userId) {
        if ($userId <= 0 || !get_userdata($userId)) return new WP_Error('fieldflow_mfa_invalid_user', 'Utilizador inválido.');
        if (!SecretVault::canEncrypt()) return new WP_Error('fieldflow_mfa_vault_unavailable', 'O cofre criptográfico não está disponível neste servidor.');
        $secret = self::base32Encode(random_bytes(20));
        $enc = SecretVault::encrypt($secret);
        if ($enc === '') return new WP_Error('fieldflow_mfa_encrypt_failed', 'Não foi possível proteger a chave MFA.');
        update_user_meta($userId, self::PENDING_META, [
            'secret_enc'=>$enc,
            'created_at'=>time(),
        ]);
        self::record('mfa.enrollment_started', 'low', []);
        return $secret;
    }

    public static function pendingSecret(int $userId): string {
        $pending = get_user_meta($userId, self::PENDING_META, true);
        if (!is_array($pending)) return '';
        $created = (int)($pending['created_at'] ?? 0);
        if ($created <= 0 || $created < time() - HOUR_IN_SECONDS) {
            delete_user_meta($userId, self::PENDING_META);
            return '';
        }
        return SecretVault::decrypt((string)($pending['secret_enc'] ?? ''));
    }

    public static function activate(int $userId, string $code) {
        $secret = self::pendingSecret($userId);
        if ($secret === '') return new WP_Error('fieldflow_mfa_pending_missing', 'Inicia novamente a configuração do MFA.');
        $counter = self::matchingCounter($secret, $code, time());
        if ($counter === null) return new WP_Error('fieldflow_mfa_invalid_code', 'O código do Authenticator não é válido. Confirma a hora do dispositivo e tenta novamente.');
        $enc = SecretVault::encrypt($secret);
        if ($enc === '') return new WP_Error('fieldflow_mfa_encrypt_failed', 'Não foi possível proteger a chave MFA.');
        $recovery = self::generateRecoveryCodes(8);
        $hashes = [];
        foreach ($recovery as $plain) $hashes[] = wp_hash_password(self::normalizeRecovery($plain));
        update_user_meta($userId, self::SECRET_META, $enc);
        update_user_meta($userId, self::ENABLED_AT_META, current_time('mysql', true));
        update_user_meta($userId, self::RECOVERY_META, $hashes);
        update_user_meta($userId, self::LAST_COUNTER_META, $counter);
        delete_user_meta($userId, self::PENDING_META);
        delete_user_meta($userId, self::GRACE_UNTIL_META);
        set_transient(self::RECOVERY_TRANSIENT_PREFIX.$userId, $recovery, 10 * MINUTE_IN_SECONDS);
        if (get_current_user_id() === $userId) self::markCurrentSessionVerified($userId);
        self::record('mfa.activated', 'medium', []);
        return $recovery;
    }

    public static function disable(int $userId, string $code) {
        if (!self::isEnabled($userId)) return true;
        if (SessionPolicy::mfaMode() === 'enforce' && SessionPolicy::isPrivilegedUser($userId)) {
            return new WP_Error('fieldflow_mfa_required_by_policy', 'O MFA não pode ser desativado enquanto o enforcement estiver ativo para contas privilegiadas.');
        }
        $verified = self::verifyLoginCode($userId, $code, false);
        if (is_wp_error($verified)) return $verified;
        self::resetUser($userId);
        self::record('mfa.disabled', 'medium', []);
        return true;
    }

    public static function resetUser(int $userId): bool {
        foreach ([self::SECRET_META,self::PENDING_META,self::ENABLED_AT_META,self::RECOVERY_META,self::LAST_COUNTER_META,self::VERIFIED_SESSIONS_META] as $key) delete_user_meta($userId, $key);
        if (SessionPolicy::mfaMode() === 'enforce' && SessionPolicy::isPrivilegedUser($userId)) {
            $policy = SessionPolicy::get();
            update_user_meta($userId, self::GRACE_UNTIL_META, time() + ((int)($policy['mfa_grace_days'] ?? 7) * DAY_IN_SECONDS));
        } else {
            delete_user_meta($userId, self::GRACE_UNTIL_META);
        }
        delete_transient(self::RECOVERY_TRANSIENT_PREFIX.$userId);
        self::record('mfa.reset', 'high', ['target_scope'=>'privileged_account']);
        return true;
    }

    public static function isEnabled(int $userId): bool {
        $enc = (string)get_user_meta($userId, self::SECRET_META, true);
        return $enc !== '' && SecretVault::decrypt($enc) !== '';
    }

    public static function enabledAt(int $userId): string {
        return (string)get_user_meta($userId, self::ENABLED_AT_META, true);
    }

    public static function recoveryCodesOnce(int $userId): array {
        $codes = get_transient(self::RECOVERY_TRANSIENT_PREFIX.$userId);
        delete_transient(self::RECOVERY_TRANSIENT_PREFIX.$userId);
        return is_array($codes) ? array_values(array_map('strval', $codes)) : [];
    }

    public static function recoveryRemaining(int $userId): int {
        $hashes = get_user_meta($userId, self::RECOVERY_META, true);
        return is_array($hashes) ? count(array_filter($hashes, 'is_string')) : 0;
    }

    public static function otpauthUri(int $userId, string $secret): string {
        $user = get_userdata($userId);
        if (!$user || $secret === '') return '';
        $host = (string)wp_parse_url(home_url('/'), PHP_URL_HOST);
        $issuer = 'FieldFlow ' . ($host ?: get_bloginfo('name'));
        $account = (string)$user->user_login . '@' . ($host ?: 'wordpress');
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function privilegedSummary(): array {
        $users = get_users(['fields'=>['ID','display_name','user_login']]);
        $rows = [];
        $enabled = 0;
        $pending = 0;
        foreach ((array)$users as $user) {
            if (!($user instanceof WP_User) || !SessionPolicy::isPrivilegedUser((int)$user->ID)) continue;
            $isEnabled = self::isEnabled((int)$user->ID);
            $hasPending = self::pendingSecret((int)$user->ID) !== '';
            if ($isEnabled) $enabled++;
            if ($hasPending) $pending++;
            $rows[] = [
                'id'=>(int)$user->ID,
                'display_name'=>(string)$user->display_name,
                'user_login'=>(string)$user->user_login,
                'enabled'=>$isEnabled,
                'pending'=>$hasPending,
                'enabled_at'=>self::enabledAt((int)$user->ID),
                'recovery_remaining'=>self::recoveryRemaining((int)$user->ID),
            ];
        }
        return ['privileged'=>count($rows),'enabled'=>$enabled,'pending'=>$pending,'rows'=>$rows];
    }

    public static function isCurrentSessionVerified(int $userId = 0): bool {
        $userId = $userId > 0 ? $userId : get_current_user_id();
        if ($userId <= 0 || !function_exists('wp_get_session_token')) return false;
        $token = (string)wp_get_session_token();
        if ($token === '') return false;
        $key = self::sessionKey($token);
        $states = get_user_meta($userId, self::VERIFIED_SESSIONS_META, true);
        if (!is_array($states)) return false;
        $verifiedAt = (int)($states[$key] ?? 0);
        if ($verifiedAt <= 0) return false;
        if ($verifiedAt < time() - SessionPolicy::wpAbsoluteSeconds()) {
            unset($states[$key]); update_user_meta($userId, self::VERIFIED_SESSIONS_META, $states); return false;
        }
        return true;
    }

    public static function markCurrentSessionVerified(int $userId = 0): bool {
        $userId = $userId > 0 ? $userId : get_current_user_id();
        if ($userId <= 0 || !function_exists('wp_get_session_token')) return false;
        $token = (string)wp_get_session_token();
        if ($token === '') return false;
        $key = self::sessionKey($token);
        $states = get_user_meta($userId, self::VERIFIED_SESSIONS_META, true);
        if (!is_array($states)) $states = [];
        $now = time();
        foreach ($states as $k=>$when) if ((int)$when < $now - (7 * DAY_IN_SECONDS)) unset($states[$k]);
        $states[$key] = $now;
        if (count($states) > 20) { asort($states, SORT_NUMERIC); while (count($states) > 20) array_shift($states); }
        update_user_meta($userId, self::VERIFIED_SESSIONS_META, $states);
        return true;
    }

    public static function verifyTotp(string $secret, string $code, ?int $timestamp = null): bool {
        return self::matchingCounter($secret, $code, $timestamp ?? time()) !== null;
    }

    public static function totpAt(string $secret, int $timestamp): string {
        $key = self::base32Decode($secret);
        if ($key === '') return '';
        $counter = intdiv(max(0, $timestamp), 30);
        $high = intdiv($counter, 4294967296);
        $low = $counter % 4294967296;
        $binaryCounter = pack('N2', $high, $low);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string {
        if ($data === '') return '';
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    public static function base32Decode(string $value): string {
        $value = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $value));
        if ($value === '') return '';
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';
        foreach (str_split($value) as $char) {
            if (!isset($alphabet[$char])) return '';
            $bits .= str_pad(decbin((int)$alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) break;
            $out .= chr(bindec($chunk));
        }
        return $out;
    }

    private static function verifyLoginCode(int $userId, string $code, bool $consumeRecovery) {
        $code = trim($code);
        if ($code === '') return new WP_Error('fieldflow_mfa_code_required', 'Introduz o código de 6 dígitos do Authenticator ou um código de recuperação.');
        if (!self::attemptAllowed($userId)) return new WP_Error('fieldflow_mfa_rate_limited', 'Demasiadas tentativas MFA. Aguarda alguns minutos e tenta novamente.');
        $secret = SecretVault::decrypt((string)get_user_meta($userId, self::SECRET_META, true));
        if ($secret === '') return new WP_Error('fieldflow_mfa_secret_unreadable', 'A configuração MFA desta conta não pode ser lida. Contacta um administrador.');

        if (preg_match('/^\d{6}$/', $code)) {
            $counter = self::matchingCounter($secret, $code, time());
            if ($counter === null) return new WP_Error('fieldflow_mfa_invalid_code', 'Código MFA inválido.');
            $last = (int)get_user_meta($userId, self::LAST_COUNTER_META, true);
            if ($counter <= $last) return new WP_Error('fieldflow_mfa_replayed_code', 'Este código MFA já foi utilizado. Aguarda pelo próximo código.');
            update_user_meta($userId, self::LAST_COUNTER_META, $counter);
            self::clearAttemptLimit($userId);
            return true;
        }

        $normalized = self::normalizeRecovery($code);
        $hashes = get_user_meta($userId, self::RECOVERY_META, true);
        if ($normalized !== '' && is_array($hashes)) {
            foreach ($hashes as $index=>$hash) {
                if (!is_string($hash) || $hash === '') continue;
                if (wp_check_password($normalized, $hash)) {
                    if ($consumeRecovery) {
                        unset($hashes[$index]);
                        update_user_meta($userId, self::RECOVERY_META, array_values($hashes));
                    }
                    self::clearAttemptLimit($userId);
                    self::record('mfa.recovery_code_used', 'medium', []);
                    return true;
                }
            }
        }
        return new WP_Error('fieldflow_mfa_invalid_code', 'Código MFA ou código de recuperação inválido.');
    }

    private static function matchingCounter(string $secret, string $code, int $timestamp): ?int {
        $code = preg_replace('/\D/', '', trim($code));
        if (strlen($code) !== 6) return null;
        $base = intdiv(max(0, $timestamp), 30);
        foreach ([-1,0,1] as $offset) {
            $counter = $base + $offset;
            if ($counter < 0) continue;
            $candidate = self::totpAt($secret, $counter * 30);
            if ($candidate !== '' && hash_equals($candidate, $code)) return $counter;
        }
        return null;
    }

    private static function generateRecoveryCodes(int $count): array {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = [];
        for ($i=0; $i<$count; $i++) {
            $raw = '';
            for ($j=0; $j<12; $j++) $raw .= $alphabet[random_int(0, strlen($alphabet)-1)];
            $out[] = substr($raw,0,4).'-'.substr($raw,4,4).'-'.substr($raw,8,4);
        }
        return $out;
    }

    private static function normalizeRecovery(string $code): string {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
    }

    private static function attemptAllowed(int $userId): bool {
        $ip = sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'ff_mfa_try_' . md5($userId.'|'.$ip);
        $attempts = (int)get_transient($key);
        if ($attempts >= 10) return false;
        set_transient($key, $attempts + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private static function clearAttemptLimit(int $userId): void {
        $ip = sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        delete_transient('ff_mfa_try_' . md5($userId.'|'.$ip));
    }

    private static function sessionKey(string $token): string { return substr(hash_hmac('sha256', $token, (string)wp_salt('auth')), 0, 40); }
    private static function redirectToChallenge(): void { wp_safe_redirect(add_query_arg('page', self::CHALLENGE_SLUG, admin_url('admin.php'))); exit; }
    private static function redirectToEnrollment(): void { wp_safe_redirect(add_query_arg(['page'=>\RoutesPro\Admin\SecurityObservability::SLUG,'tab'=>'auth'], admin_url('admin.php'))); exit; }

    private static function record(string $type, string $severity, array $context): void {
        if (class_exists(SecurityService::class)) SecurityService::record($type, $severity, $context);
    }
}
