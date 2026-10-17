<?php
namespace RoutesPro\Billing;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\SaaS\PlanRepository;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

class SignupService {
    public const COOKIE = 'fieldflow_signup_intent';
    public const USER_TOKEN_META = 'fieldflow_signup_intent_token';
    public const USER_CLIENT_META = 'fieldflow_signup_client_id';
    private static bool $registered = false;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_billing_signup_intents';
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('template_redirect', [self::class, 'captureIntentFromRequest'], 1);
        add_action('um_user_register', [self::class, 'onUltimateMemberRegister'], 20, 2);
        add_filter('um_after_email_confirmation_redirect', [self::class, 'afterEmailConfirmationRedirect'], 20, 3);
        add_action('um_registration_after_auto_login', [self::class, 'afterAutoLoginRegistration'], 20, 1);
        add_action('wp_login', [self::class, 'onLogin'], 20, 2);
        add_action('fieldflow_billing_maintenance', [self::class, 'maintenance'], 20);
    }

    public static function isEnabled(): bool {
        $settings = Settings::get();
        return !empty($settings['onboarding_enabled']);
    }

    public static function ultimateMemberAvailable(): bool {
        return function_exists('UM') || shortcode_exists('ultimatemember');
    }

    public static function createIntent(int $planId, string $interval, string $source = 'web', string $resumeUrl = '', array $consent = []) {
        global $wpdb;
        if (!self::isEnabled()) return new WP_Error('fieldflow_signup_disabled', 'O registo com subscrição está desativado.', ['status'=>503]);
        if (!self::tableReady()) return new WP_Error('fieldflow_signup_table', 'A tabela de onboarding ainda não está disponível.', ['status'=>503]);
        $interval = $interval === 'year' ? 'year' : 'month';
        $plan = PlanRepository::plan($planId);
        if (!$plan || empty($plan['is_active']) || (string)($plan['slug'] ?? '') === 'legacy') {
            return new WP_Error('fieldflow_signup_plan', 'O plano selecionado não está disponível para subscrição automática.', ['status'=>400]);
        }
        if (!self::checkoutReady($planId, $interval)) {
            return new WP_Error('fieldflow_signup_checkout_unavailable', 'Este plano ainda não está pronto para checkout neste ambiente.', ['status'=>503]);
        }
        $billingSettings = Settings::get();
        if (!empty($billingSettings['legal_consent_required'])) {
            if (!class_exists(ProductionHardening::class) || !ProductionHardening::tablesReady()) {
                return new WP_Error('fieldflow_signup_consent_storage', 'O registo de consentimentos ainda não está disponível. Atualiza novamente o plugin antes de abrir as subscrições.', ['status'=>503]);
            }
            if (empty($consent['terms_accepted']) || empty($consent['privacy_accepted'])) {
                return new WP_Error('fieldflow_signup_consent_required', 'É necessário aceitar os Termos e Condições e a Política de Privacidade para continuar.', ['status'=>400]);
            }
        }
        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            $token = wp_generate_password(64, false, false);
        }
        $expires = time() + 2 * DAY_IN_SECONDS;
        $resumeUrl = self::sanitizeResumeUrl($resumeUrl);
        $inserted = $wpdb->insert(self::table(), [
            'token_hash' => hash('sha256', $token),
            'user_id' => get_current_user_id(),
            'client_id' => 0,
            'plan_id' => $planId,
            'billing_interval' => $interval,
            'environment' => Settings::mode(),
            'status' => is_user_logged_in() ? 'authenticated' : 'created',
            'email' => is_user_logged_in() ? sanitize_email((string)wp_get_current_user()->user_email) : '',
            'source' => sanitize_key($source ?: 'web'),
            'expires_at' => wp_date('Y-m-d H:i:s', $expires),
            'meta_json' => wp_json_encode(['created_ip_hash'=>self::ipHash(), 'user_agent'=>substr(sanitize_text_field((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),0,255), 'resume_url'=>$resumeUrl, 'recovery_token_enc'=>\RoutesPro\Integrations\SecretVault::encrypt($token), 'recovery_count'=>0]),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        if (!$inserted) return new WP_Error('fieldflow_signup_create_failed', 'Não foi possível iniciar a subscrição.', ['status'=>500]);
        $id = (int)$wpdb->insert_id;
        if (class_exists(ProductionHardening::class)) ProductionHardening::recordConsent($id, $consent, get_current_user_id(), 0);
        self::setCookie($token, $expires);
        if (is_user_logged_in()) {
            $attached = self::attachUser($token, get_current_user_id(), 'Intent criado por utilizador autenticado.');
            if (is_wp_error($attached)) {
                self::updateIntent($id, ['status'=>'cancelled']);
                self::clearCookie();
                return $attached;
            }
        }
        self::audit('billing.signup_intent_created', $id, ['plan_id'=>$planId, 'interval'=>$interval, 'source'=>$source]);
        return [
            'id' => $id,
            'token' => $token,
            'url' => self::resumeUrl($token, self::intentByToken($token, true)),
            'resume_url' => self::resumeUrl($token, self::intentByToken($token, true)),
            'expires_at' => wp_date(DATE_ATOM, $expires),
        ];
    }

    public static function intentByToken(string $token, bool $allowExpired = false): ?array {
        global $wpdb;
        $token = self::cleanToken($token);
        if ($token === '' || !self::tableReady()) return null;
        $row = $wpdb->get_row($wpdb->prepare('SELECT i.*,p.name plan_name,p.slug plan_slug,p.monthly_price,p.annual_price,p.currency FROM '.self::table().' i LEFT JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=i.plan_id WHERE i.token_hash=%s LIMIT 1', hash('sha256', $token)), ARRAY_A);
        if (!$row) return null;
        if (!$allowExpired && !empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
            self::updateIntent((int)$row['id'], ['status'=>'expired']);
            return null;
        }
        return $row;
    }

    public static function currentToken(): string {
        $query = self::cleanToken((string)($_GET['ff_signup'] ?? ''));
        if ($query !== '') { $intent=self::intentByToken($query); if ($intent && !in_array((string)$intent['status'], ['completed','expired','cancelled'], true)) return $query; }
        $cookie = self::cleanToken((string)($_COOKIE[self::COOKIE] ?? ''));
        if ($cookie !== '') { $intent=self::intentByToken($cookie); if ($intent && !in_array((string)$intent['status'], ['completed','expired','cancelled'], true)) return $cookie; }
        if (is_user_logged_in()) {
            $saved = self::cleanToken((string)get_user_meta(get_current_user_id(), self::USER_TOKEN_META, true));
            if ($saved !== '') { $intent=self::intentByToken($saved); if ($intent && !in_array((string)$intent['status'], ['completed','expired','cancelled'], true)) return $saved; }
        }
        return '';
    }

    public static function captureIntentFromRequest(): void {
        $token = self::cleanToken((string)($_GET['ff_signup'] ?? ''));
        if ($token === '') return;
        $intent = self::intentByToken($token);
        if (!$intent || in_array((string)($intent['status'] ?? ''), ['completed','expired','cancelled'], true)) {
            self::clearCookie();
            return;
        }
        self::setCookie($token, strtotime((string)$intent['expires_at']) ?: time() + DAY_IN_SECONDS);
        self::rememberResumeUrl((int)$intent['id'], self::currentRequestUrl());
        if (is_user_logged_in()) self::attachUser($token, get_current_user_id(), 'Intent retomado por URL.');
    }

    public static function onUltimateMemberRegister(int $userId, array $args = []): void {
        $token = self::cleanToken((string)($args['fieldflow_signup_token'] ?? ($_COOKIE[self::COOKIE] ?? '')));
        if ($token === '' || !self::intentByToken($token)) return;
        self::applyPendingRole($userId, true);
        self::attachUser($token, $userId, 'Registo concluído no Ultimate Member.');
    }


    public static function afterAutoLoginRegistration(int $userId): void {
        $token = self::cleanToken((string)get_user_meta($userId, self::USER_TOKEN_META, true));
        if ($token === '' || !self::intentByToken($token)) return;
        wp_safe_redirect(self::resumeUrl($token, null, true));
        exit;
    }

    public static function afterEmailConfirmationRedirect(string $redirect, int $userId, $login = false): string {
        $token = self::cleanToken((string)get_user_meta($userId, self::USER_TOKEN_META, true));
        if ($token === '' || !self::intentByToken($token)) return $redirect;
        return self::resumeUrl($token, null, true);
    }

    public static function onLogin(string $userLogin, $user): void {
        if (!$user instanceof \WP_User) return;
        $token = self::cleanToken((string)($_COOKIE[self::COOKIE] ?? get_user_meta($user->ID, self::USER_TOKEN_META, true)));
        if ($token !== '') self::attachUser($token, (int)$user->ID, 'Intent retomado após login.');
    }

    public static function attachUser(string $token, int $userId, string $reason = '') {
        $intent = self::intentByToken($token);
        $user = get_userdata($userId);
        if (!$intent || !$user) return new WP_Error('fieldflow_signup_user', 'Não foi possível associar o registo à subscrição.');
        $existingUser = absint($intent['user_id'] ?? 0);
        if ($existingUser > 0 && $existingUser !== $userId) return new WP_Error('fieldflow_signup_claimed', 'Esta seleção de subscrição já está associada a outra conta.');
        update_user_meta($userId, self::USER_TOKEN_META, $token);
        self::applyPendingRole($userId);
        $clientId = self::ensureClientForUser($userId, $intent);
        if (is_wp_error($clientId)) return $clientId;
        self::updateIntent((int)$intent['id'], [
            'user_id' => $userId,
            'client_id' => (int)$clientId,
            'email' => sanitize_email((string)$user->user_email),
            'status' => 'registered',
        ]);
        if (class_exists(ProductionHardening::class)) ProductionHardening::bindConsent((int)$intent['id'], $userId, (int)$clientId);
        self::setCookie($token, strtotime((string)$intent['expires_at']) ?: time() + DAY_IN_SECONDS);
        self::audit('billing.signup_user_attached', (int)$intent['id'], ['user_id'=>$userId, 'client_id'=>(int)$clientId, 'reason'=>$reason]);
        return ['user_id'=>$userId, 'client_id'=>(int)$clientId];
    }

    public static function checkoutForUser(string $token, int $userId, string $returnUrl = '') {
        $intent = self::intentByToken($token);
        if (!$intent) return new WP_Error('fieldflow_signup_intent', 'A seleção de subscrição expirou. Escolhe novamente o plano.', ['status'=>410]);
        if (in_array((string)$intent['status'], ['completed','expired','cancelled'], true)) return new WP_Error('fieldflow_signup_closed', 'Este processo de subscrição já foi concluído ou expirou.', ['status'=>409]);
        if (class_exists(ProductionHardening::class) && !ProductionHardening::hasRequiredConsent((int)$intent['id'])) return new WP_Error('fieldflow_signup_consent_missing', 'Os consentimentos legais deste processo estão incompletos. Escolhe novamente o plano e aceita os documentos obrigatórios.', ['status'=>409]);
        $attached = self::attachUser($token, $userId, 'Checkout solicitado.');
        if (is_wp_error($attached)) return $attached;
        $clientId = (int)$attached['client_id'];
        self::updateIntent((int)$intent['id'], ['status'=>'checkout_pending']);
        $result = BillingService::createCheckout($clientId, (int)$intent['plan_id'], (string)$intent['billing_interval'], $userId, $userId, $returnUrl);
        if (is_wp_error($result)) {
            self::updateIntent((int)$intent['id'], ['status'=>'registered']);
            return $result;
        }
        self::updateIntent((int)$intent['id'], [
            'status' => 'checkout_open',
            'checkout_session_id' => absint($result['id'] ?? 0),
        ]);
        self::audit('billing.signup_checkout_opened', (int)$intent['id'], ['user_id'=>$userId, 'client_id'=>$clientId, 'checkout_session_id'=>absint($result['id'] ?? 0)]);
        return $result;
    }

    public static function markCompleted(int $clientId, int $userId = 0, int $planId = 0, string $reason = ''): void {
        global $wpdb;
        if ($clientId <= 0 || !self::tableReady()) return;
        $where = 'client_id=%d';
        $args = [$clientId];
        if ($userId > 0) { $where .= ' AND user_id=%d'; $args[] = $userId; }
        if ($planId > 0) { $where .= ' AND plan_id=%d'; $args[] = $planId; }
        $ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM '.self::table().' WHERE '.$where." AND status NOT IN ('completed','expired','cancelled')", ...$args)) ?: [];
        if (!$ids) return;
        foreach ($ids as $id) self::updateIntent((int)$id, ['status'=>'completed', 'completed_at'=>current_time('mysql')]);
        if ($userId > 0) delete_user_meta($userId, self::USER_TOKEN_META);
        self::clearCookie();
        self::audit('billing.signup_completed', (int)reset($ids), ['client_id'=>$clientId, 'user_id'=>$userId, 'plan_id'=>$planId, 'reason'=>$reason, 'intent_ids'=>array_map('intval',$ids)]);
    }

    public static function createIntentRest(WP_REST_Request $request) {
        $rateKey = 'fieldflow_signup_intent_' . self::ipHash();
        $hits = (int)get_transient($rateKey);
        if ($hits >= 20) return new WP_Error('fieldflow_signup_rate', 'Foram iniciados demasiados pedidos. Tenta novamente dentro de alguns minutos.', ['status'=>429]);
        set_transient($rateKey, $hits + 1, 10 * MINUTE_IN_SECONDS);
        return self::createIntent(absint($request->get_param('plan_id')), sanitize_key((string)$request->get_param('interval')), sanitize_key((string)($request->get_param('source') ?: 'web')), (string)$request->get_param('return_url'), [
            'terms_accepted'=>rest_sanitize_boolean($request->get_param('terms_accepted')),
            'privacy_accepted'=>rest_sanitize_boolean($request->get_param('privacy_accepted')),
            'marketing_consent'=>rest_sanitize_boolean($request->get_param('marketing_consent')),
            'source'=>sanitize_key((string)($request->get_param('source') ?: 'web')),
        ]);
    }

    public static function checkoutRest(WP_REST_Request $request) {
        if (!is_user_logged_in()) return new WP_Error('fieldflow_signup_login', 'Inicia sessão para continuar o pagamento.', ['status'=>401]);
        return self::checkoutForUser(self::cleanToken((string)$request->get_param('token')), get_current_user_id(), (string)$request->get_param('return_url'));
    }

    public static function recentIntents(int $limit = 100): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        return $wpdb->get_results('SELECT i.*,p.name plan_name,u.display_name user_name,u.user_email FROM '.self::table().' i LEFT JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=i.plan_id LEFT JOIN '.$wpdb->users.' u ON u.ID=i.user_id ORDER BY i.id DESC LIMIT '.max(1,min(500,$limit)), ARRAY_A) ?: [];
    }

    public static function maintenance(): array {
        global $wpdb;
        if (!self::tableReady()) return ['expired'=>0];
        $count = (int)$wpdb->query($wpdb->prepare("UPDATE ".self::table()." SET status='expired',updated_at=%s WHERE expires_at<%s AND status NOT IN ('completed','expired','cancelled')", current_time('mysql'), current_time('mysql')));
        return ['expired'=>max(0,$count)];
    }


    public static function resumeUrl(string $token, ?array $intent = null, bool $resume = false): string {
        $token = self::cleanToken($token);
        if ($intent === null && $token !== '') $intent = self::intentByToken($token, true);
        $base = self::intentResumeBase(is_array($intent) ? $intent : []);
        $args = [];
        if ($token !== '') $args['ff_signup'] = $token;
        if ($resume) $args['resume'] = '1';
        return $args ? add_query_arg($args, $base) : $base;
    }

    private static function intentResumeBase(array $intent): string {
        $meta = json_decode((string)($intent['meta_json'] ?? ''), true);
        $stored = is_array($meta) ? (string)($meta['resume_url'] ?? '') : '';
        $safe = self::sanitizeResumeUrl($stored);
        return $safe !== '' ? $safe : Settings::signupUrl();
    }

    private static function rememberResumeUrl(int $intentId, string $url): void {
        global $wpdb;
        if ($intentId <= 0 || !self::tableReady()) return;
        $safe = self::sanitizeResumeUrl($url);
        if ($safe === '') return;
        $raw = $wpdb->get_var($wpdb->prepare('SELECT meta_json FROM '.self::table().' WHERE id=%d LIMIT 1', $intentId));
        $meta = json_decode((string)$raw, true);
        if (!is_array($meta)) $meta = [];
        if ((string)($meta['resume_url'] ?? '') === $safe) return;
        $meta['resume_url'] = $safe;
        $wpdb->update(self::table(), ['meta_json'=>wp_json_encode($meta), 'updated_at'=>current_time('mysql')], ['id'=>$intentId]);
    }

    private static function currentRequestUrl(): string {
        $host = sanitize_text_field((string)($_SERVER['HTTP_HOST'] ?? ''));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($host === '' || $uri === '') return '';
        return (is_ssl() ? 'https://' : 'http://') . $host . $uri;
    }

    private static function sanitizeResumeUrl(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        $safe = wp_validate_redirect(esc_url_raw($url), '');
        if ($safe === '') return '';
        $homeHost = strtolower((string)wp_parse_url(home_url('/'), PHP_URL_HOST));
        $targetHost = strtolower((string)wp_parse_url($safe, PHP_URL_HOST));
        if ($homeHost === '' || $targetHost === '' || $homeHost !== $targetHost) return '';
        $safe = remove_query_arg(['ff_signup','resume'], $safe);
        $fragment = strpos($safe, '#');
        if ($fragment !== false) $safe = substr($safe, 0, $fragment);
        return esc_url_raw($safe);
    }

    private static function ensureClientForUser(int $userId, array $intent) {
        global $wpdb;
        $clientId = absint($intent['client_id'] ?? 0);
        if ($clientId > 0 && (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d', $clientId)) > 0) {
            RoleMappingService::ensureClientAssociation($clientId, $userId);
            return $clientId;
        }
        $preferred = BillingRepository::preferredSubscriptionForUser($userId);
        if ($preferred && !empty($preferred['client_id'])) return (int)$preferred['client_id'];
        $savedClient = absint(get_user_meta($userId, self::USER_CLIENT_META, true));
        if ($savedClient > 0 && (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d', $savedClient)) > 0) return $savedClient;
        $user = get_userdata($userId);
        if (!$user) return new WP_Error('fieldflow_signup_user_missing', 'Utilizador não encontrado.');
        $settings = Settings::get();
        $companyKey = sanitize_key((string)($settings['um_company_meta_key'] ?? 'company_name'));
        $taxKey = sanitize_key((string)($settings['um_tax_meta_key'] ?? 'tax_id'));
        $phoneKey = sanitize_key((string)($settings['um_phone_meta_key'] ?? 'mobile_number'));
        $company = sanitize_text_field((string)get_user_meta($userId, $companyKey, true));
        if ($company === '') $company = sanitize_text_field((string)($user->display_name ?: $user->user_login));
        $taxId = sanitize_text_field((string)get_user_meta($userId, $taxKey, true));
        $phone = sanitize_text_field((string)get_user_meta($userId, $phoneKey, true));
        $meta = [
            'associated_user_ids' => [$userId],
            'user_ids' => [$userId],
            'onboarding' => [
                'source' => 'ultimate_member',
                'status' => 'pending_billing',
                'signup_intent_id' => absint($intent['id'] ?? 0),
                'created_by_user_id' => $userId,
            ],
        ];
        $ok = $wpdb->insert($wpdb->prefix.'routespro_clients', [
            'name' => $company,
            'taxid' => $taxId,
            'email' => sanitize_email((string)$user->user_email),
            'phone' => $phone,
            'meta_json' => wp_json_encode($meta),
        ], ['%s','%s','%s','%s','%s']);
        if (!$ok) return new WP_Error('fieldflow_signup_client_create', 'Não foi possível criar a organização provisória.');
        $clientId = (int)$wpdb->insert_id;
        update_user_meta($userId, self::USER_CLIENT_META, $clientId);
        RoleMappingService::ensureClientAssociation($clientId, $userId);
        PlanRepository::assign($clientId, (int)$intent['plan_id'], [
            'status' => 'suspended',
            'starts_at' => current_time('mysql'),
            'status_reason' => 'Aguardar confirmação do pagamento.',
            'notes' => 'Organização criada automaticamente pelo onboarding Ultimate Member.',
        ], $userId, true);
        self::audit('billing.signup_client_created', absint($intent['id'] ?? 0), ['client_id'=>$clientId, 'user_id'=>$userId, 'company'=>$company]);
        return $clientId;
    }

    private static function applyPendingRole(int $userId, bool $replace = false): void {
        $role = sanitize_key((string)(Settings::get()['pending_role'] ?? ''));
        if ($role === '' || !isset(RoleMappingService::availableRoles()[$role])) return;
        $user = get_userdata($userId);
        if (!$user) return;
        if ($replace) {
            $user->set_role($role);
            update_user_meta($userId, 'fieldflow_signup_pending_role', $role);
            return;
        }
        foreach (RoleMappingService::managedRoles() as $managed) {
            if ($managed !== $role && in_array($managed, (array)$user->roles, true)) $user->remove_role($managed);
        }
        if (!in_array($role, (array)$user->roles, true)) $user->add_role($role);
        update_user_meta($userId, 'fieldflow_signup_pending_role', $role);
    }

    private static function checkoutReady(int $planId, string $interval): bool {
        return BillingService::checkoutAvailable($planId, $interval);
    }

    private static function updateIntent(int $id, array $data): void {
        global $wpdb;
        if ($id <= 0 || !self::tableReady()) return;
        $allowed = ['user_id','client_id','status','email','checkout_session_id','completed_at'];
        $row = [];
        foreach ($allowed as $key) if (array_key_exists($key, $data)) $row[$key] = $data[$key];
        if (!$row) return;
        $row['updated_at'] = current_time('mysql');
        $wpdb->update(self::table(), $row, ['id'=>$id]);
    }

    private static function tableReady(): bool {
        global $wpdb;
        $table = self::table();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function cleanToken(string $token): string {
        $token = preg_replace('/[^A-Za-z0-9]/', '', $token);
        return is_string($token) && strlen($token) >= 32 && strlen($token) <= 128 ? $token : '';
    }

    private static function setCookie(string $token, int $expires): void {
        if (headers_sent()) return;
        setcookie(self::COOKIE, $token, [
            'expires' => $expires,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $token;
    }

    private static function clearCookie(): void {
        if (!headers_sent()) setcookie(self::COOKIE, '', ['expires'=>time()-3600,'path'=>COOKIEPATH ?: '/','domain'=>COOKIE_DOMAIN ?: '','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        unset($_COOKIE[self::COOKIE]);
    }

    private static function ipHash(): string {
        return substr(hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('auth')), 0, 32);
    }

    private static function audit(string $event, int $intentId, array $context): void {
        if (class_exists(AuditLog::class)) AuditLog::record($event, 'billing_signup_intent', $intentId, null, $context, ['actor_user_id'=>get_current_user_id()], $context);
        if (class_exists(EventBus::class)) EventBus::emit($event, $context, ['entity_type'=>'billing_signup_intent','entity_id'=>$intentId,'actor_user_id'=>get_current_user_id()], 'billing');
    }
}
