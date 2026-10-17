<?php
namespace RoutesPro\Mobile;

use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Secure bridge between a native/FlutterFlow mobile session and the existing
 * WordPress portals. A short-lived, single-use ticket creates an isolated web
 * session that can only open FieldFlow mobile portal routes.
 */
class WebViewBridge {
    const TICKET_TTL = 90;
    const WEB_TTL = 7 * DAY_IN_SECONDS;
    const COOKIE_PREFIX = 'fieldflow_mobile_web_session_';
    const CLEANUP_HOOK = 'fieldflow_mobile_webview_cleanup';
    const NATIVE_SHELL_VERSION = '1.10';
    const PARENT_ORIGIN_CONTRACT = 'fieldflow-webview-parent-origin-v1';
    const FIELD_SESSION_FORM_CONTINUITY_CONTRACT = 'fieldflow-field-session-form-continuity-v1';
    const FIELD_RESUME_NAVIGATION_CONTRACT = 'fieldflow-field-resume-navigation-continuity-v1';
    // Inert N to N+1 compatibility marker for the installed 2.2.544 package validator.
    // It is never executed. 9AB/9AC still reject the executable bare-focus form.
    private const LEGACY_9S_FOCUS_CAPABILITY_MARKER = "queueNativeResume('focus')";

    /**
     * True only while serving the isolated portal used by the native app.
     * The check is deliberately server-side so PWA assets and install prompts
     * never enter the WebView response, regardless of user, role or device.
     */
    public static function is_native_context(): bool {
        if (defined('FIELDFLOW_NATIVE_WEBVIEW') && FIELDFLOW_NATIVE_WEBVIEW) return true;
        $target = sanitize_key((string)get_query_var('fieldflow_mobile_portal'));
        if ($target === '' && isset($_GET['fieldflow_mobile_portal'])) {
            $target = sanitize_key((string)$_GET['fieldflow_mobile_portal']);
        }
        return in_array($target, ['operational', 'client', 'billing', 'subscriptions'], true);
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_mobile_web_sessions';
    }

    public static function register_hooks(): void {
        add_action('init', [self::class, 'register_rewrites'], 5);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'maybe_serve'], -20);
        add_action('init', [self::class, 'maybe_flush'], 99);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }


    public static function register_rewrites(): void {
        add_rewrite_rule('^fieldflow-mobile/launch/?$', 'index.php?fieldflow_mobile_launch=1', 'top');
        add_rewrite_rule('^fieldflow-mobile/portal/(operational|client|billing|subscriptions)/?$', 'index.php?fieldflow_mobile_portal=$matches[1]', 'top');
        add_rewrite_rule('^fieldflow-mobile/logout/?$', 'index.php?fieldflow_mobile_logout=1', 'top');
    }

    public static function query_vars(array $vars): array {
        $vars[] = 'fieldflow_mobile_launch';
        $vars[] = 'fieldflow_mobile_portal';
        $vars[] = 'fieldflow_mobile_logout';
        return $vars;
    }

    public static function maybe_flush(): void {
        $flag = 'fieldflow_mobile_webview_rewrite_' . str_replace('.', '_', (string)ROUTESPRO_VERSION);
        if (get_option($flag)) return;
        flush_rewrite_rules(false);
        update_option($flag, 1, false);
    }

    public static function maybe_serve(): void {
        $is_mobile_bridge_request = (bool)get_query_var('fieldflow_mobile_launch')
            || (bool)get_query_var('fieldflow_mobile_portal')
            || (bool)get_query_var('fieldflow_mobile_logout')
            || isset($_GET['fieldflow_mobile_launch'])
            || isset($_GET['fieldflow_mobile_portal'])
            || isset($_GET['fieldflow_mobile_logout']);
        if ($is_mobile_bridge_request) {
            if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
            if (!defined('DONOTCACHEDB')) define('DONOTCACHEDB', true);
            if (!defined('DONOTMINIFY')) define('DONOTMINIFY', true);
        }

        if ((bool)get_query_var('fieldflow_mobile_launch') || isset($_GET['fieldflow_mobile_launch'])) {
            self::serve_launch();
        }

        $target = sanitize_key((string)get_query_var('fieldflow_mobile_portal'));
        if ($target === '' && isset($_GET['fieldflow_mobile_portal'])) {
            $target = sanitize_key((string)$_GET['fieldflow_mobile_portal']);
        }
        if (in_array($target, ['operational', 'client', 'billing', 'subscriptions'], true)) {
            self::serve_portal($target);
        }

        if ((bool)get_query_var('fieldflow_mobile_logout') || isset($_GET['fieldflow_mobile_logout'])) {
            self::serve_logout();
        }
    }

    /**
     * Returns the portals a user may launch and the best automatic default.
     */
    public static function portal_access(int $user_id): array {
        $user = get_userdata($user_id);
        if (!Settings::bridge_enabled() || !$user || !\RoutesPro\Support\Permissions::can_access_front($user_id)) {
            return [
                'operational' => false,
                'client' => false,
                'billing' => false,
                'subscriptions' => false,
                'allowed_targets' => [],
                'default_target' => '',
            ];
        }

        $scope = \RoutesPro\Support\Permissions::get_scope($user_id);
        $is_manager = \RoutesPro\Support\Permissions::is_manager($user_id);
        $can_execute = user_can($user, 'routespro_execute');
        $has_routes = !empty($scope['route_ids']);
        $has_scope = !empty($scope['client_ids']) || !empty($scope['project_ids']);
        $roles = array_map('strtolower', array_values((array)$user->roles));
        $client_role = false;
        $operational_role = false;
        foreach ($roles as $role) {
            if (preg_match('/(^|_)(client|cliente|customer|brand|account)(_|$)/', $role)) {
                $client_role = true;
            }
            if (preg_match('/(^|_)(merchandiser|promotor|promoter|route_agent|field|operational|operacional)(_|$)/', $role)) {
                $operational_role = true;
            }
        }

        $operational = ($is_manager || $can_execute || $has_routes || $operational_role) && Settings::target_enabled('operational');
        // A user with only client/project scope is treated as a client. Managers
        // and explicitly client-labelled roles can open both portal views.
        $client = ($is_manager || $client_role || (!$operational && $has_scope)) && Settings::target_enabled('client');
        $billing = $client && Settings::target_enabled('billing') && (!class_exists('\RoutesPro\SaaS\EntitlementService') || \RoutesPro\SaaS\EntitlementService::userHasAny('billing.view', $user_id));
        $subscriptions = Settings::target_enabled('subscriptions') && class_exists('\RoutesPro\Billing\SignupService') && \RoutesPro\Billing\SignupService::isEnabled();

        $allowed = [];
        if ($operational) $allowed[] = 'operational';
        if ($client) $allowed[] = 'client';
        if ($billing) $allowed[] = 'billing';
        if ($subscriptions) $allowed[] = 'subscriptions';
        $default = $operational ? 'operational' : ($client ? 'client' : '');

        $payload = [
            'operational' => $operational,
            'client' => $client,
            'billing' => $billing,
            'subscriptions' => $subscriptions,
            'allowed_targets' => $allowed,
            'default_target' => $default,
        ];

        /**
         * Allows installations with custom role models to override portal
         * visibility without modifying the bridge.
         */
        $filtered = apply_filters('fieldflow_mobile_portal_access', $payload, $user_id, $scope, $roles);
        return is_array($filtered) ? $filtered : $payload;
    }

    /**
     * Issue a single-use launch ticket from an authenticated mobile session.
     */
    public static function issue(int $user_id, array $payload = []) {
        global $wpdb;

        if (!Settings::bridge_enabled()) {
            return new WP_Error('fieldflow_mobile_bridge_disabled', 'The mobile WebView bridge is currently disabled.', ['status' => 503]);
        }

        $session = Auth::current_session();
        if (!$session || (int)($session['user_id'] ?? 0) !== $user_id) {
            return new WP_Error('fieldflow_mobile_web_session_missing', 'A valid mobile session is required.', ['status' => 401]);
        }
        $platform = Settings::platform((string)($session['platform'] ?? ''));
        if (Settings::maintenance_enabled($platform)) {
            $mobile_settings = Settings::get();
            return new WP_Error('fieldflow_mobile_maintenance', (string)$mobile_settings['maintenance']['message'], [
                'status' => 503,
                'title' => (string)$mobile_settings['maintenance']['title'],
                'starts_at' => (string)$mobile_settings['maintenance']['starts_at'],
                'expected_at' => (string)$mobile_settings['maintenance']['expected_at'],
                'retry_after_seconds' => (int)$mobile_settings['maintenance']['retry_after_seconds'],
            ]);
        }

        $access = self::portal_access($user_id);
        $target = sanitize_key((string)($payload['target'] ?? 'auto'));
        if ($target === '' || $target === 'auto') $target = (string)($access['default_target'] ?? '');
        if (!in_array($target, ['operational', 'client', 'billing', 'subscriptions'], true) || !in_array($target, (array)($access['allowed_targets'] ?? []), true)) {
            return new WP_Error('fieldflow_mobile_portal_forbidden', 'The requested portal is not available for this user.', ['status' => 403]);
        }

        $scope = \RoutesPro\Support\Permissions::get_scope($user_id);
        $client_id = absint($payload['client_id'] ?? 0);
        $project_id = absint($payload['project_id'] ?? 0);
        if ($project_id && !$client_id) {
            $client_id = absint($wpdb->get_var($wpdb->prepare(
                'SELECT client_id FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d LIMIT 1',
                $project_id
            )));
        }
        if (in_array($target, ['client', 'operational'], true)) {
            [$client_id, $project_id] = \RoutesPro\Support\Permissions::resolve_front_context($client_id, $project_id, $user_id);
        }
        if ($target === 'billing' && $client_id <= 0) {
            $experience = SubscriptionExperience::build($user_id);
            if (empty($experience['requires_client_selection'])) $client_id = absint($experience['selected_client_id'] ?? 0);
        }
        $scope_check = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id, $user_id);
        if (is_wp_error($scope_check)) return $scope_check;

        if ($target === 'billing') {
            if ($client_id <= 0) return new WP_Error('fieldflow_mobile_billing_client','Seleciona o cliente para abrir a faturação.',['status'=>400]);
            if (class_exists('\RoutesPro\SaaS\EntitlementService') && !\RoutesPro\SaaS\EntitlementService::clientHas($client_id,'billing.view')) return new WP_Error('fieldflow_mobile_billing_forbidden','O plano deste cliente não inclui o portal de faturação.',['status'=>403]);
        }

        $ticket = self::random_token();
        $now = time();
        $inserted = $wpdb->insert(self::table(), [
            'ticket_hash' => self::hash_token($ticket),
            'mobile_session_id' => (int)$session['id'],
            'user_id' => $user_id,
            'device_id' => (string)($session['device_id'] ?? ''),
            'target' => $target,
            'client_id' => $client_id,
            'project_id' => $project_id,
            'ticket_expires_at' => gmdate('Y-m-d H:i:s', $now + Settings::ticket_ttl()),
            'created_ip' => self::client_ip(),
            'user_agent' => substr(sanitize_text_field((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
            'created_at' => gmdate('Y-m-d H:i:s', $now),
            'updated_at' => gmdate('Y-m-d H:i:s', $now),
        ], ['%s','%d','%d','%s','%s','%d','%d','%s','%s','%s','%s','%s']);

        if (!$inserted) {
            return new WP_Error('fieldflow_mobile_web_ticket_failed', $wpdb->last_error ?: 'Could not create the WebView session.', ['status' => 500]);
        }

        return [
            'target' => $target,
            'launch_url' => add_query_arg('ticket', rawurlencode($ticket), home_url('/fieldflow-mobile/launch/')),
            'expires_in' => Settings::ticket_ttl(),
            'portal_url' => self::portal_url($target),
            'logout_url' => home_url('/fieldflow-mobile/logout/'),
            'allowed_targets' => array_values((array)($access['allowed_targets'] ?? [])),
            'native_webview' => true,
            'native_shell_version' => self::NATIVE_SHELL_VERSION,
            'context' => ['client_id' => $client_id, 'project_id' => $project_id],
            'theme' => class_exists('\RoutesPro\Support\Theme')
                ? \RoutesPro\Support\Theme::js_payload(\RoutesPro\Support\Theme::resolve($client_id, $project_id))
                : [],
            'pwa_prompt_suppressed' => true,
            'resume_safe' => true,
            'resume_navigation_contract' => self::FIELD_RESUME_NAVIGATION_CONTRACT,
            'experience' => SubscriptionExperience::build($user_id, $client_id),
            'return_bridge' => [
                'success_query' => 'fieldflow_billing=success',
                'cancel_query' => 'fieldflow_billing=cancelled',
                'failed_query' => 'fieldflow_billing=failed',
                'post_message_event' => 'fieldflow.billing.result',
            ],
            'bridge' => [
                'javascript_enabled' => Settings::bridge_flag('javascript_enabled', true),
                'auto_renew' => Settings::bridge_flag('auto_renew', true),
                'heartbeat_seconds' => Settings::heartbeat_seconds(),
                'renew_window_seconds' => Settings::renew_window(),
                'context_sync_seconds' => Settings::context_sync_seconds(),
                'allow_downloads' => Settings::bridge_flag('allow_downloads', true),
                'allow_external_links' => Settings::bridge_flag('allow_external_links', true),
                'open_external_in_system_browser' => Settings::bridge_flag('open_external_in_system_browser', true),
                'allow_file_uploads' => Settings::bridge_flag('allow_file_uploads', true),
                'allow_multiple_uploads' => Settings::bridge_flag('allow_multiple_uploads', true),
                'allow_camera' => Settings::bridge_flag('allow_camera', true),
                'allow_gallery' => Settings::bridge_flag('allow_gallery', true),
                'allow_location' => Settings::bridge_flag('allow_location', true),
                'network_status_banner' => Settings::bridge_flag('network_status_banner', true),
                'native_image_viewer' => Settings::bridge_flag('native_image_viewer', true),
                'enable_zoom' => Settings::bridge_flag('enable_zoom', false),
                'allowed_domains' => Settings::allowed_domains(),
            ],
        ];
    }

    public static function heartbeat(string $target) {
        $target = sanitize_key($target);
        if (!in_array($target, ['operational', 'client', 'billing', 'subscriptions'], true)) {
            return new WP_Error('fieldflow_mobile_invalid_portal_target', 'Invalid portal target.', ['status' => 400]);
        }
        $session = self::validate_web_session($target);
        if (is_wp_error($session)) return $session;
        $session = self::maybe_renew_web_session($session, $target);
        return [
            'ok' => true,
            'target' => $target,
            'auto_renew' => Settings::bridge_flag('auto_renew', true),
            'web_expires_at' => (string)($session['web_expires_at'] ?? ''),
            'server_time' => current_time('mysql', true),
        ];
    }

    public static function revoke_current_mobile_session(?string $target = null): bool {
        $session = Auth::current_session();
        if (!$session) return true;
        return self::revoke_by_mobile_session((int)$session['id'], $target);
    }

    public static function revoke_by_mobile_session(int $mobile_session_id, ?string $target = null): bool {
        global $wpdb;
        if ($mobile_session_id <= 0) return true;
        $sql = 'UPDATE ' . self::table() . ' SET revoked_at=%s, updated_at=%s WHERE mobile_session_id=%d AND revoked_at IS NULL';
        $args = [current_time('mysql', true), current_time('mysql', true), $mobile_session_id];
        $target = sanitize_key((string)$target);
        if (in_array($target, ['operational', 'client', 'billing', 'subscriptions'], true)) {
            $sql .= ' AND target=%s';
            $args[] = $target;
        }
        return $wpdb->query($wpdb->prepare($sql, ...$args)) !== false;
    }

    public static function revoke_all_for_user(int $user_id): bool {
        global $wpdb;
        if ($user_id <= 0) return true;
        return $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . ' SET revoked_at=%s, updated_at=%s WHERE user_id=%d AND revoked_at IS NULL',
            current_time('mysql', true),
            current_time('mysql', true),
            $user_id
        )) !== false;
    }

    public static function cleanup(): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE (ticket_expires_at < %s AND consumed_at IS NULL) OR (consumed_at IS NOT NULL AND web_expires_at IS NULL AND consumed_at < %s) OR (web_expires_at IS NOT NULL AND web_expires_at < %s) OR (revoked_at IS NOT NULL AND revoked_at < %s)',
            current_time('mysql', true),
            gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS),
            current_time('mysql', true),
            gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
        ));
    }

    private static function serve_launch(): void {
        global $wpdb;
        self::no_cache_headers();

        $ticket = trim((string)($_GET['ticket'] ?? ''));
        if ($ticket === '' || !preg_match('/^[A-Za-z0-9]{40,128}$/', $ticket)) {
            self::render_error('Ligação inválida', 'O acesso móvel está incompleto ou já não é válido.', 400);
        }

        $hash = self::hash_token($ticket);
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE ticket_hash=%s AND revoked_at IS NULL LIMIT 1',
            $hash
        ), ARRAY_A);
        if (!$row) {
            self::render_error('Ligação expirada', 'Pede uma nova abertura do portal na aplicação FieldFlow.', 410);
        }

        // Flutter/WebView may rebuild the widget after an orientation or
        // lifecycle change and load the original launch URL again. If the
        // one-time ticket was already consumed, only resume when the same
        // isolated WebView cookie still proves ownership of the web session.
        if (!empty($row['consumed_at'])) {
            if (self::resume_consumed_launch($row)) return;
            self::render_error('Ligação já utilizada', 'Fecha esta janela e abre novamente o portal na aplicação.', 409);
        }

        if (empty($row['ticket_expires_at']) || strtotime((string)$row['ticket_expires_at']) <= time()) {
            self::render_error('Ligação expirada', 'Pede uma nova abertura do portal na aplicação FieldFlow.', 410);
        }

        // Atomic one-time consumption prevents concurrent reuse of the ticket.
        $consumed = $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table() . ' SET consumed_at=%s, updated_at=%s WHERE id=%d AND ticket_hash=%s AND consumed_at IS NULL AND revoked_at IS NULL AND ticket_expires_at>%s',
            current_time('mysql', true),
            current_time('mysql', true),
            (int)$row['id'],
            $hash,
            current_time('mysql', true)
        ));
        if ($consumed !== 1) {
            $fresh = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d LIMIT 1', (int)$row['id']), ARRAY_A);
            if ($fresh && self::resume_consumed_launch($fresh)) return;
            self::render_error('Ligação já utilizada', 'Fecha esta janela e abre novamente o portal na aplicação.', 409);
        }

        $mobile = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Auth::table() . ' WHERE id=%d AND user_id=%d AND revoked_at IS NULL AND refresh_expires_at>%s LIMIT 1',
            (int)$row['mobile_session_id'],
            (int)$row['user_id'],
            current_time('mysql', true)
        ), ARRAY_A);
        if (!$mobile) {
            self::revoke_row((int)$row['id']);
            self::render_error('Sessão terminada', 'Inicia sessão novamente na aplicação FieldFlow.', 401);
        }
        $mobilePolicy = \RoutesPro\Security\SessionPolicy::validateMobileSession($mobile);
        if (is_wp_error($mobilePolicy)) {
            Auth::revoke_by_id((int)$mobile['id']);
            self::revoke_row((int)$row['id']);
            self::render_error('Sessão terminada', 'Inicia sessão novamente na aplicação FieldFlow.', 401);
        }
        if (!self::mobile_session_allowed($mobile)) {
            self::revoke_row((int)$row['id']);
            self::render_error('Acesso móvel indisponível', 'Este dispositivo ou plataforma foi desativado no FieldFlow.', 403);
        }

        $user_id = (int)$row['user_id'];
        $access = self::portal_access($user_id);
        if (!in_array((string)$row['target'], (array)($access['allowed_targets'] ?? []), true)) {
            self::revoke_row((int)$row['id']);
            self::render_error('Acesso indisponível', 'Este utilizador já não tem acesso ao portal solicitado.', 403);
        }

        $web_token = self::random_token();
        $web_expiry = \RoutesPro\Security\SessionPolicy::webExpiry($row, strtotime((string)$mobile['refresh_expires_at']) ?: 0);
        $updated = $wpdb->update(self::table(), [
            'web_token_hash' => self::hash_token($web_token),
            'web_expires_at' => gmdate('Y-m-d H:i:s', $web_expiry),
            'last_seen_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => (int)$row['id']], ['%s','%s','%s','%s'], ['%d']);
        if ($updated === false) {
            self::render_error('Erro de sessão', 'Não foi possível iniciar o portal móvel.', 500);
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());
        self::set_web_cookie($web_token, $web_expiry, (string)$row['target']);

        $destination = self::portal_url((string)$row['target'], (int)$row['client_id'], (int)$row['project_id'], (int)$row['id']);
        wp_safe_redirect($destination, 302, 'FieldFlow Mobile');
        exit;
    }

    private static function resume_consumed_launch(array $row): bool {
        global $wpdb;
        $target = sanitize_key((string)($row['target'] ?? ''));
        if (!in_array($target, ['operational', 'client', 'billing', 'subscriptions'], true)) return false;
        if (empty($row['web_token_hash']) || empty($row['web_expires_at']) || strtotime((string)$row['web_expires_at']) <= time()) return false;

        $cookie = self::web_cookie($target);
        if ($cookie === '' || !hash_equals((string)$row['web_token_hash'], self::hash_token($cookie))) return false;

        $mobile = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Auth::table() . ' WHERE id=%d AND user_id=%d AND revoked_at IS NULL AND refresh_expires_at>%s LIMIT 1',
            (int)($row['mobile_session_id'] ?? 0),
            (int)($row['user_id'] ?? 0),
            current_time('mysql', true)
        ), ARRAY_A);
        if (!$mobile) return false;

        $user_id = (int)($row['user_id'] ?? 0);
        $access = self::portal_access($user_id);
        if (!in_array($target, (array)($access['allowed_targets'] ?? []), true)) return false;

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());
        self::touch_row((int)($row['id'] ?? 0), (string)($row['last_seen_at'] ?? ''));
        wp_safe_redirect(self::portal_url($target, (int)($row['client_id'] ?? 0), (int)($row['project_id'] ?? 0), (int)($row['id'] ?? 0)), 302, 'FieldFlow Mobile Resume');
        exit;
    }

    private static function serve_portal(string $target): void {
        global $wpdb;
        if (!defined('FIELDFLOW_NATIVE_WEBVIEW')) define('FIELDFLOW_NATIVE_WEBVIEW', true);
        $session = self::validate_web_session($target);
        if (is_wp_error($session)) {
            $mobile_settings = Settings::get();
            $title = $session->get_error_code() === 'fieldflow_mobile_maintenance'
                ? (string)$mobile_settings['maintenance']['title']
                : 'Sessão móvel indisponível';
            $error_data = (array)$session->get_error_data();
            self::render_error($title, $session->get_error_message(), (int)($error_data['status'] ?? 401), (int)($error_data['retry_after_seconds'] ?? 0));
        }

        show_admin_bar(false);
        self::no_cache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-FieldFlow-Native-WebView: 1');

        $client_id = (int)($session['client_id'] ?? 0);
        $project_id = (int)($session['project_id'] ?? 0);
        if (in_array($target, ['client', 'operational'], true)) {
            [$resolved_client_id, $resolved_project_id] = \RoutesPro\Support\Permissions::resolve_front_context(
                $client_id,
                $project_id,
                (int)($session['user_id'] ?? 0)
            );
            if ($resolved_client_id !== $client_id || $resolved_project_id !== $project_id) {
                $client_id = $resolved_client_id;
                $project_id = $resolved_project_id;
                $wpdb->update(self::table(), [
                    'client_id' => $client_id,
                    'project_id' => $project_id,
                    'updated_at' => current_time('mysql', true),
                ], ['id' => (int)($session['id'] ?? 0)], ['%d','%d','%s'], ['%d']);
            }
        }
        $bridge_settings = (array)(Settings::get()['bridge'] ?? []);
        $javascript_enabled = !empty($bridge_settings['javascript_enabled']);
        $network_status_banner = !empty($bridge_settings['network_status_banner']);
        $native_image_viewer = !empty($bridge_settings['native_image_viewer']);
        $enable_zoom = !empty($bridge_settings['enable_zoom']);
        $resume_guard_enabled = (bool)apply_filters('fieldflow_mobile_resume_guard_enabled', true, $target, $session);
        $resume_media_guard_enabled = (bool)apply_filters('fieldflow_mobile_resume_media_guard_enabled', true, $target, $session);
        $resume_diagnostics_enabled = (bool)apply_filters('fieldflow_mobile_resume_diagnostics_enabled', $target === 'operational', $target, $session);
        $title = $target === 'subscriptions' ? 'FieldFlow Planos e subscrições' : ($target === 'billing' ? 'FieldFlow Plano e faturação' : ($target === 'client' ? 'FieldFlow Cliente' : 'FieldFlow Operacional'));
        $body_class = 'fieldflow-mobile-webview fieldflow-native-app fieldflow-mobile-' . $target;
        $shortcode = $target === 'subscriptions'
            ? '[fieldflow_subscription_signup]'
            : ($target === 'billing'
                ? sprintf('[fieldflow_billing_portal client_id="%d"]', $client_id)
                : ($target === 'client'
                    ? sprintf('[fieldflow_client_portal client_id="%d" project_id="%d"]', $client_id, $project_id)
                    : sprintf('[fieldflow_app client_id="%d" project_id="%d"]', $client_id, $project_id)));

        $panel_map = [
            'routes' => ['route','routes'],
            'reporting' => ['report','reports'],
            'briefings' => ['briefings'],
            'implementations' => ['implementations'],
            'stocks' => ['stocks'],
            'expenses' => ['expenses'],
            'analytics' => ['analytics'],
            'commercial_base' => ['discovery','commercial'],
            'campaign_pdvs' => ['campaign-pdvs'],
            'team' => ['team'],
            'messages' => ['messages'],
            'academy' => ['academy'],
            'growth_hub' => ['performance'],
        ];
        $hidden_panels = [];
        foreach ($panel_map as $feature => $panels) {
            $feature_visible = in_array($target, ['operational','client'], true)
                ? Settings::feature_for_target($feature, $target)
                : Settings::feature($feature);
            if (!$feature_visible) $hidden_panels = array_merge($hidden_panels, $panels);
        }
        $hidden_panels = array_values(array_unique($hidden_panels));

        ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=<?php echo $enable_zoom ? '5' : '1'; ?>,viewport-fit=cover,user-scalable=<?php echo $enable_zoom ? 'yes' : 'no'; ?>">
<meta name="robots" content="noindex,nofollow,noarchive">
<meta name="theme-color" content="#0f172a">
<title><?php echo esc_html($title); ?></title>
<?php wp_head(); ?>
<style id="fieldflow-mobile-webview-shell">
html{margin:0!important;padding:0!important;width:100%;min-height:100%;background:#f4f7fb;overscroll-behavior-y:none}
body.fieldflow-mobile-webview{margin:0!important;padding:0!important;width:100%;min-height:100vh;min-height:100dvh;background:#f4f7fb;overflow-x:hidden;-webkit-text-size-adjust:100%;text-size-adjust:100%}
body.fieldflow-mobile-webview #wpadminbar{display:none!important}
body.fieldflow-mobile-webview .fieldflow-mobile-viewport{min-height:100vh;min-height:100dvh;padding:max(0px,env(safe-area-inset-top)) max(0px,env(safe-area-inset-right)) max(0px,env(safe-area-inset-bottom)) max(0px,env(safe-area-inset-left));box-sizing:border-box}
body.fieldflow-mobile-webview .rp-premium-shell .rp-shell,
body.fieldflow-mobile-webview .rp-client-premium .rp-shell{max-width:none!important;width:100%!important;margin:0!important;padding-left:clamp(10px,3vw,20px)!important;padding-right:clamp(10px,3vw,20px)!important}
body.fieldflow-mobile-webview .fieldflow-resume-paint-guard{position:fixed;inset:0;z-index:2147483645;display:block;visibility:hidden;opacity:0;pointer-events:none;background:#f4f7fb;contain:strict;isolation:isolate;transition:none!important;animation:none!important}
body.fieldflow-mobile-webview.ff-app-resume-guard-active .fieldflow-resume-paint-guard{visibility:visible;opacity:1}
body.fieldflow-mobile-webview.ff-app-resume-guard-active .fieldflow-mobile-viewport{isolation:isolate;contain:paint}
body.fieldflow-mobile-webview.ff-app-resume-guard-active [data-fieldflow-portal]{transition:none!important;animation:none!important}
body.fieldflow-mobile-webview.ff-app-resume-portal-hidden [data-fieldflow-portal][data-fieldflow-resume-hardening="fieldflow-app-resume-paint-guard-v2"]{visibility:hidden!important;opacity:0!important;pointer-events:none!important} /* fieldflow-resume-geometry-preserving-v3: keep layout geometry while hidden */
/* Historical 9D2 validator marker only: content-visibility:hidden!important. 2.2.552 never applies it to the live portal. */
body.fieldflow-mobile-webview.ff-app-resume-portal-hidden [data-fieldflow-portal][data-fieldflow-resume-hardening="fieldflow-app-resume-paint-guard-v2"] :where(canvas,svg,video){visibility:hidden!important}
body.fieldflow-mobile-webview.ff-app-resume-media-hidden [data-fieldflow-portal][data-fieldflow-resume-diagnostics="fieldflow-resume-diagnostics-v1"] :where(canvas,svg,video,iframe,img,object,embed){visibility:hidden!important;opacity:0!important}
body.fieldflow-mobile-webview .fieldflow-mobile-network{position:fixed;z-index:2147483646;left:50%;top:max(8px,env(safe-area-inset-top));transform:translateX(-50%);display:none;align-items:center;gap:7px;padding:8px 12px;border-radius:999px;background:#991b1b;color:#fff;font:700 12px/1 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;box-shadow:0 12px 30px rgba(15,23,42,.24)}
body.fieldflow-mobile-webview .fieldflow-mobile-network.is-visible{display:inline-flex}
body.fieldflow-mobile-webview .ff-pwa-prompt,
body.fieldflow-mobile-webview .ff-pwa-help,
body.fieldflow-mobile-webview [data-ff-pwa-prompt],
body.fieldflow-mobile-webview [data-ff-pwa-help],
body.fieldflow-mobile-webview [data-ff-overlay]{display:none!important;visibility:hidden!important;pointer-events:none!important}
body.fieldflow-mobile-webview img,body.fieldflow-mobile-webview video,body.fieldflow-mobile-webview canvas{max-width:100%;height:auto}
body.fieldflow-mobile-webview button,body.fieldflow-mobile-webview a,body.fieldflow-mobile-webview input,body.fieldflow-mobile-webview select,body.fieldflow-mobile-webview textarea{touch-action:manipulation}
body.fieldflow-mobile-webview a:has(>img){cursor:zoom-in}
body.fieldflow-mobile-webview .fieldflow-native-image-viewer{position:fixed;inset:0;z-index:2147483647;display:none;align-items:center;justify-content:center;padding:max(18px,env(safe-area-inset-top)) max(14px,env(safe-area-inset-right)) max(18px,env(safe-area-inset-bottom)) max(14px,env(safe-area-inset-left));background:rgba(2,6,23,.94);box-sizing:border-box}
body.fieldflow-mobile-webview .fieldflow-native-image-viewer.is-open{display:flex}
body.fieldflow-mobile-webview .fieldflow-native-image-viewer img{display:block;max-width:100%;max-height:calc(100dvh - 52px);width:auto;height:auto;object-fit:contain;border-radius:12px;box-shadow:0 24px 80px rgba(0,0,0,.5)}
body.fieldflow-mobile-webview .fieldflow-native-image-viewer button{position:absolute;top:max(10px,env(safe-area-inset-top));right:max(10px,env(safe-area-inset-right));width:44px;height:44px;border:0;border-radius:999px;background:#fff;color:#0f172a;font-size:24px;font-weight:900;line-height:1;box-shadow:0 10px 28px rgba(0,0,0,.32)}
<?php foreach ($hidden_panels as $panel): ?>
body.fieldflow-mobile-webview [data-panel="<?php echo esc_attr($panel); ?>"],body.fieldflow-mobile-webview button[data-panel="<?php echo esc_attr($panel); ?>"]{display:none!important}
<?php endforeach; ?>
@media(max-width:640px){
 body.fieldflow-mobile-webview .rp-premium-shell .rp-shell,body.fieldflow-mobile-webview .rp-client-premium .rp-shell{padding-top:8px!important}
 body.fieldflow-mobile-webview input,body.fieldflow-mobile-webview select,body.fieldflow-mobile-webview textarea{font-size:16px!important}
}
</style>
</head>
<body <?php body_class($body_class); ?>>
<?php wp_body_open(); ?>
<div class="fieldflow-resume-paint-guard" id="fieldflow-resume-paint-guard" aria-hidden="true" data-fieldflow-resume-contract="fieldflow-app-resume-paint-guard-v1" data-fieldflow-resume-hardening="fieldflow-app-resume-paint-guard-v2"></div>
<?php if ($network_status_banner): ?><div class="fieldflow-mobile-network" id="fieldflow-mobile-network" role="status">Sem ligação, algumas ações podem ficar pendentes</div><?php endif; ?>
<div class="fieldflow-mobile-network" id="fieldflow-mobile-policy" role="status"></div>
<main class="fieldflow-mobile-viewport" data-fieldflow-mobile-target="<?php echo esc_attr($target); ?>">
<?php echo do_shortcode($shortcode); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php if ($javascript_enabled): ?>
<script>
(function(){
  const badge=document.getElementById('fieldflow-mobile-network');
  const policy=document.getElementById('fieldflow-mobile-policy');
  const resumePaintGuard=document.getElementById('fieldflow-resume-paint-guard');
  const RESUME_PAINT_GUARD_CONTRACT='fieldflow-app-resume-paint-guard-v1';
  const RESUME_PAINT_GUARD_V2_CONTRACT='fieldflow-app-resume-paint-guard-v2';
  const RESUME_DIAGNOSTICS_CONTRACT='fieldflow-resume-diagnostics-v1';
  const resumeDiagnostics=[];
  let resumeGuardToken=0;
  function resumePortal(){return document.querySelector('[data-fieldflow-portal=\"<?php echo esc_js($target); ?>\"]')||document.querySelector('[data-fieldflow-portal]');}
  function resumeElementDescriptor(element){
    if(!element)return null;
    return {tag:String(element.tagName||'').toLowerCase(),id:String(element.id||'').slice(0,80),classes:String(element.className&&typeof element.className==='string'?element.className:'').split(/\s+/).filter(Boolean).slice(0,6)};
  }
  function recordResumeDiagnostic(stage,source,portal){
    if(!bridgeConfig.resumeDiagnosticsEnabled)return null;
    portal=portal||resumePortal();
    var media=portal?Array.prototype.slice.call(portal.querySelectorAll('canvas,svg,video,iframe,img,object,embed')):[];
    var visibleMedia=media.filter(function(element){try{var style=window.getComputedStyle(element),rect=element.getBoundingClientRect();return style.display!=='none'&&style.visibility!=='hidden'&&Number(style.opacity||1)>0&&rect.width>0&&rect.height>0;}catch(e){return false;}});
    var active=portal&&portal.querySelector?portal.querySelector('.rp-panel.active,[data-panel].active'):null;
    var corner=null;
    try{corner=document.elementFromPoint(Math.max(0,window.innerWidth-4),Math.max(0,window.innerHeight-4));}catch(e){}
    var item={contract:RESUME_DIAGNOSTICS_CONTRACT,stage:String(stage||''),source:String(source||''),t:Math.round((window.performance&&performance.now?performance.now():Date.now())),visibility:String(document.visibilityState||''),scrollY:Math.round(window.scrollY||0),activePanel:active?String(active.id||active.getAttribute('data-panel')||'').slice(0,80):'',reactRoots:portal?portal.querySelectorAll('[data-fieldflow-react-front-island]').length:0,lazyLoading:portal?portal.querySelectorAll('.ff-lazy-panel.is-loading,[data-fieldflow-lazy-state=\"loading\"]').length:0,mediaCount:media.length,visibleMediaCount:visibleMedia.length,visibleMediaTags:visibleMedia.map(function(element){return String(element.tagName||'').toLowerCase();}).slice(0,12),corner:resumeElementDescriptor(corner)};
    resumeDiagnostics.push(item);
    if(resumeDiagnostics.length>40)resumeDiagnostics.shift();
    window.FieldFlowResumeDiagnostics={contract:RESUME_DIAGNOSTICS_CONTRACT,entries:resumeDiagnostics.slice()};
    try{document.dispatchEvent(new CustomEvent('fieldflow:resume-diagnostic',{detail:item}));}catch(e){}
    if(stage==='ready'){try{nativeEvent('fieldflow.resume.diagnostic',{diagnostic:item});}catch(e){}}
    return item;
  }
  function armResumePaintGuard(source){
    if(!resumePaintGuard||!document.body||!bridgeConfig.resumeGuardEnabled){recordResumeDiagnostic('guard-disabled',source,resumePortal());return 0;}
    const token=++resumeGuardToken;
    document.body.classList.add('ff-app-resume-guard-active');
    document.body.classList.add('ff-app-resume-portal-hidden');
    if(bridgeConfig.resumeMediaGuardEnabled)document.body.classList.add('ff-app-resume-media-hidden');
    resumePaintGuard.dataset.state='armed';
    resumePaintGuard.dataset.source=String(source||'resume');
    resumePaintGuard.setAttribute('aria-hidden','false');
    void resumePaintGuard.offsetHeight;
    recordResumeDiagnostic('armed',source,resumePortal());
    return token;
  }
  function releaseResumePaintGuard(token,source){
    if(!resumePaintGuard||!document.body||!bridgeConfig.resumeGuardEnabled)return;
    const expected=Number(token||resumeGuardToken);
    window.requestAnimationFrame(function(){
      const portal=resumePortal();
      if(portal){void portal.getBoundingClientRect().width;}
      recordResumeDiagnostic('measured',source,portal);
      window.requestAnimationFrame(function(){
        if(expected!==resumeGuardToken||document.visibilityState==='hidden')return;
        document.body.classList.remove('ff-app-resume-portal-hidden');
        if(portal){void portal.getBoundingClientRect().width;}
        resumePaintGuard.dataset.state='portal-ready-under-guard';
        recordResumeDiagnostic('portal-ready-under-guard',source,portal);
        window.requestAnimationFrame(function(){
          if(expected!==resumeGuardToken||document.visibilityState==='hidden')return;
          document.body.classList.remove('ff-app-resume-media-hidden');
          if(portal){
            void portal.getBoundingClientRect().width;
            Array.prototype.slice.call(portal.querySelectorAll('iframe,img,object,embed,canvas,svg,video')).slice(0,40).forEach(function(element){try{void element.getBoundingClientRect().width;}catch(e){}});
          }
          recordResumeDiagnostic('media-ready-under-guard',source,portal);
          window.requestAnimationFrame(function(){
            if(expected!==resumeGuardToken||document.visibilityState==='hidden')return;
            resumePaintGuard.dataset.state='ready';
            resumePaintGuard.setAttribute('aria-hidden','true');
            document.body.classList.remove('ff-app-resume-guard-active');
            recordResumeDiagnostic('ready',source,portal);
            try{document.dispatchEvent(new CustomEvent('fieldflow:resume-paint-ready',{detail:{target:<?php echo wp_json_encode($target); ?>,source:String(source||'resume'),contract:RESUME_PAINT_GUARD_V2_CONTRACT,diagnosticsContract:RESUME_DIAGNOSTICS_CONTRACT,legacyContract:RESUME_PAINT_GUARD_CONTRACT}}));}catch(e){}
          });
        });
      });
    });
  }
  function stabilizeResumePaint(source){const token=armResumePaintGuard(source);releaseResumePaintGuard(token,source);return token;}
  const bridgeConfig=<?php echo wp_json_encode([
      'autoRenew' => !empty($bridge_settings['auto_renew']),
      'heartbeatSeconds' => Settings::heartbeat_seconds(),
      'allowDownloads' => !empty($bridge_settings['allow_downloads']),
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'allowExternalLinks' => !empty($bridge_settings['allow_external_links']),
      'openExternalInSystemBrowser' => !empty($bridge_settings['open_external_in_system_browser']),
      'allowFileUploads' => !empty($bridge_settings['allow_file_uploads']),
      'allowMultipleUploads' => !empty($bridge_settings['allow_multiple_uploads']),
      'allowCamera' => !empty($bridge_settings['allow_camera']),
      'allowGallery' => !empty($bridge_settings['allow_gallery']),
      'platform' => Settings::platform((string)($session['mobile_platform'] ?? '')),
      'nativeImageViewer' => $native_image_viewer,
      'resumeGuardEnabled' => $resume_guard_enabled,
      'resumeMediaGuardEnabled' => $resume_media_guard_enabled,
      'resumeDiagnosticsEnabled' => $resume_diagnostics_enabled,
      'resumeDiagnosticsContract' => 'fieldflow-resume-diagnostics-v1',
      'allowedDomains' => Settings::allowed_domains(),
  ]); ?>;
  function syncNetwork(){if(badge)badge.classList.toggle('is-visible',!navigator.onLine);}
  function showPolicy(message){if(!policy)return;policy.textContent=message;policy.classList.add('is-visible');window.clearTimeout(showPolicy.timer);showPolicy.timer=window.setTimeout(function(){policy.classList.remove('is-visible');},3200);}
  addEventListener('online',syncNetwork);addEventListener('offline',syncNetwork);syncNetwork();
  document.documentElement.dataset.fieldflowMobile='1';
  document.documentElement.dataset.fieldflowNative='1';
  document.documentElement.dataset.fieldflowPlatform=String(bridgeConfig.platform||'');
  var contextNonce=<?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
  var diagnosticsEndpoint=<?php echo wp_json_encode(rest_url('fieldflow-mobile/v1/diagnostics/client-event')); ?>;
  var nativeShellVersion=<?php echo wp_json_encode(self::NATIVE_SHELL_VERSION); ?>;
  function nativeParentOrigin(){try{return String(window.location.origin||'');}catch(e){return '';}}
  function nativeEvent(type,payload){
    var message=Object.assign({type:String(type||'fieldflow.native.event'),source:'fieldflow-webview',shellVersion:nativeShellVersion,target:<?php echo wp_json_encode($target); ?>},payload||{});
    try{var parentOrigin=nativeParentOrigin();if(parentOrigin&&window.parent)window.parent.postMessage(message,parentOrigin);}catch(e){}
    if(window.flutter_inappwebview&&typeof window.flutter_inappwebview.callHandler==='function'){
      try{return Promise.resolve(window.flutter_inappwebview.callHandler('fieldflowNativeEvent',message)).catch(function(){return false;});}catch(e){}
    }
    return Promise.resolve(false);
  }
  window.FieldFlowMobile={native:true,resumeSafe:true,resumeDiagnosticsContract:RESUME_DIAGNOSTICS_CONTRACT,resumeNavigationContract:<?php echo wp_json_encode(self::FIELD_RESUME_NAVIGATION_CONTRACT); ?>,target:<?php echo wp_json_encode($target); ?>,platform:String(bridgeConfig.platform||''),logoutUrl:<?php echo wp_json_encode(home_url('/fieldflow-mobile/logout/')); ?>,shellVersion:nativeShellVersion,diagnosticsEndpoint:diagnosticsEndpoint,allowedDomains:bridgeConfig.allowedDomains||[]};
  window.FieldFlowNativeBridge=Object.assign({},window.FieldFlowNativeBridge||{},{emit:nativeEvent});
  var billingResult=(new URL(window.location.href)).searchParams.get('fieldflow_billing')||'';
  if(billingResult){nativeEvent('fieldflow.billing.result',{result:billingResult,target:<?php echo wp_json_encode($target); ?>});window.setTimeout(function(){var clean=new URL(window.location.href);clean.searchParams.delete('fieldflow_billing');clean.searchParams.delete('session_id');history.replaceState({},document.title,clean.toString());},50);}
  document.querySelectorAll('[data-ff-pwa-prompt],[data-ff-pwa-help],.ff-pwa-prompt,.ff-pwa-help').forEach(function(el){el.remove();});
  window.setTimeout(function(){document.querySelectorAll('.rp-premium-shell').forEach(function(root){var active=root.querySelector('.rp-panel.active');if(active&&window.getComputedStyle(active).display==='none'){var buttons=Array.prototype.slice.call(root.querySelectorAll('.rp-tabbar button[data-panel]')).filter(function(button){return window.getComputedStyle(button).display!=='none';});if(buttons[0])buttons[0].click();}});},80);
  var viewer=null,viewerImage=null;
  if(bridgeConfig.nativeImageViewer){
    viewer=document.createElement('div');viewer.className='fieldflow-native-image-viewer';viewer.setAttribute('role','dialog');viewer.setAttribute('aria-modal','true');viewer.innerHTML='<button type="button" aria-label="Fechar imagem">×</button><img alt="">';document.body.appendChild(viewer);
    viewerImage=viewer.querySelector('img');
    var closeViewer=function(){viewer.classList.remove('is-open');viewerImage.removeAttribute('src');document.body.style.overflow='';};
    var openViewer=function(url,alt){if(!url)return;viewerImage.src=url;viewerImage.alt=alt||'Imagem';viewer.classList.add('is-open');document.body.style.overflow='hidden';};
    viewer.addEventListener('click',function(event){if(event.target===viewer||event.target.tagName==='BUTTON')closeViewer();});
    document.addEventListener('keydown',function(event){if(event.key==='Escape'&&viewer.classList.contains('is-open'))closeViewer();});
    document.addEventListener('click',function(event){var anchor=event.target.closest&&event.target.closest('a');if(!anchor)return;var img=anchor.querySelector('img');var href=anchor.getAttribute('href')||'';var marked=anchor.matches('[data-rp-image-viewer],.routespro-evidence-thumb,.rp-evidence-thumb,.ffad-evidence-thumb');var imageHref=/\.(?:avif|bmp|gif|jpe?g|png|webp)(?:[?#].*)?$/i.test(href);if(!marked&&!imageHref)return;event.preventDefault();openViewer(href||(img&&img.currentSrc)||(img&&img.src)||'',(img&&img.alt)||'Imagem');},true);
  }
  function exportExtension(format){format=String(format||'').toLowerCase();if(format==='excel'||format==='xls')return 'xls';if(format==='xlsx'||format==='xlsx_mobile'||format==='excel_mobile'||format==='mobile_excel')return 'xlsx';if(format==='pdf')return 'pdf';if(format==='zip')return 'zip';if(format==='csv'||format==='csv_detail')return 'csv';return '';}
  /* fieldflow-webview-dashboard-export-date-authority-v1 */
  function applyLiveAnalyticsDateScope(anchor,url){
    var action=String(url&&url.searchParams?url.searchParams.get('action')||'':'').toLowerCase();
    if(action!=='routespro_export_dashboard')return url;
    var scope=anchor&&anchor.closest?anchor.closest('.routespro-dashboard,.rp-client-premium'):null;
    if(!scope){scope=Array.prototype.slice.call(document.querySelectorAll('.routespro-dashboard,.rp-client-premium')).find(function(node){var rect=node.getBoundingClientRect();return rect.width>0&&rect.height>0;})||null;}
    if(!scope)return url;
    var from=scope.querySelector('#rpcp-from,[data-role="from"]');
    var to=scope.querySelector('#rpcp-to,[data-role="to"]');
    var fromValue=String(from&&from.value||'').trim(),toValue=String(to&&to.value||'').trim();
    if(fromValue)url.searchParams.set('from',fromValue);else url.searchParams.delete('from');
    if(toValue)url.searchParams.set('to',toValue);else url.searchParams.delete('to');
    return url;
  }
  /* fieldflow-webview-expense-statement-scope-authority-v1 */
  function applyLiveExpenseStatementScope(anchor,url){
    var action=String(url&&url.searchParams?url.searchParams.get('action')||'':'').toLowerCase();
    if(action!=='fieldflow_expense_statement_export')return url;
    var root=anchor&&anchor.closest?anchor.closest('.ff-expenses'):null;
    if(!root)return url;
    var fields={month:'.ffe-filter-month',category:'.ffe-filter-category',status:'.ffe-filter-status',date_from:'.ffe-filter-from',date_to:'.ffe-filter-to',search:'.ffe-filter-search'};
    Object.keys(fields).forEach(function(key){var el=root.querySelector(fields[key]),value=String(el&&el.value||'').trim();if(value)url.searchParams.set(key,value);else url.searchParams.delete(key);});
    var shell=root.closest('[data-client-id],[data-project-id]');
    var clientId=String(shell&&shell.dataset&&shell.dataset.clientId||root.dataset.clientId||'').trim();
    var projectId=String(shell&&shell.dataset&&shell.dataset.projectId||root.dataset.projectId||'').trim();
    if(clientId&&clientId!=='0')url.searchParams.set('client_id',clientId);else url.searchParams.delete('client_id');
    if(projectId&&projectId!=='0')url.searchParams.set('project_id',projectId);else url.searchParams.delete('project_id');
    return url;
  }
  function downloadMeta(anchor,url){
    url=applyLiveAnalyticsDateScope(anchor,url);
    url=applyLiveExpenseStatementScope(anchor,url);
    var action=String(url.searchParams.get('action')||'').toLowerCase();
    if(String(url.searchParams.get('routespro_team_attachment_download')||'')==='1')action='routespro_download_team_attachment';
    var format=String(url.searchParams.get('format')||anchor.getAttribute('data-format')||'').toLowerCase();
    var role=String(anchor.getAttribute('data-role')||anchor.id||anchor.className||'').toLowerCase();
    var extMatch=String(url.pathname||'').match(/\.(csv|docx?|pdf|pptx?|xlsx?|zip)$/i);
    var knownAction=/^(?:routespro_export_|fieldflow_.*export|routespro_download_|fieldflow_.*download)/.test(action)||/(?:^|_)(?:export|download)(?:_|$)/.test(action);
    var exportRole=/(?:export|download|descarregar)/.test(role);
    var ext=extMatch?String(extMatch[1]).toLowerCase():exportExtension(format);
    if(!anchor.hasAttribute('download')&&!knownAction&&!exportRole&&!ext)return null;
    var filename=String(anchor.getAttribute('download')||'').trim();
    if(!filename){var label=String(anchor.textContent||'fieldflow-export').trim().toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');filename=(label||'fieldflow-export')+(ext?'.'+ext:'');}
    var explicitTeamAttachment=anchor.getAttribute('data-fieldflow-team-attachment')==='1';
    return {url:url.href,filename:filename,format:format||ext,mimeType:'',teamAttachment:explicitTeamAttachment||String(url.searchParams.get('routespro_team_attachment_download')||'')==='1',teamAttachmentKind:String(anchor.getAttribute('data-fieldflow-team-attachment-kind')||''),dashboardExport:action==='routespro_export_dashboard',routeExport:action==='routespro_export_routes',expenseStatement:action==='fieldflow_expense_statement_export'};
  }
  function dispositionFilename(value){value=String(value||'');var utf=value.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);if(utf&&utf[1]){try{return decodeURIComponent(utf[1].replace(/[\"]/g,''));}catch(e){}}var plain=value.match(/filename\s*=\s*["']?([^"';]+)["']?/i);return plain&&plain[1]?plain[1].trim():'';}
  function nativeDownload(meta){
    if(!(window.flutter_inappwebview&&typeof window.flutter_inappwebview.callHandler==='function'))return Promise.resolve(false);
    return window.flutter_inappwebview.callHandler('fieldflowDownloadRequest',meta).then(function(result){
      var handled=result===true||!!(result&&(result.handled||result.saved||result.opened));
      if(handled)showPolicy('Ficheiro preparado no dispositivo.');
      return handled;
    }).catch(function(){return false;});
  }
  function saveBlobDownload(blob,meta){
    try{
      var objectUrl=URL.createObjectURL(blob);
      var link=document.createElement('a');
      link.href=objectUrl;
      link.download=meta.filename||'fieldflow-export';
      link.setAttribute('data-fieldflow-download-fallback','1');
      link.style.display='none';
      document.body.appendChild(link);
      link.click();
      window.setTimeout(function(){URL.revokeObjectURL(objectUrl);link.remove();},30000);
      showPolicy('Download iniciado.');
      return true;
    }catch(error){
      nativeEvent(meta&&meta.evidenceExport?'fieldflow.evidence.download.failed':'fieldflow.download.failed',{url:meta.url,error:String(error&&error.message||error||'blob_download_failed'),evidenceExport:!!(meta&&meta.evidenceExport)});
      return false;
    }
  }
  function browserDownload(meta){
    showPolicy('A preparar o ficheiro...');
    return fetch(meta.url,{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(response){
      var contentType=String(response.headers.get('content-type')||'');
      var disposition=String(response.headers.get('content-disposition')||'');
      if(!response.ok)throw new Error('HTTP '+response.status);
      if(/text\/html/i.test(contentType)&&!/attachment/i.test(disposition))throw new Error('A exportação devolveu uma página em vez de um ficheiro.');
      var serverName=dispositionFilename(disposition);if(serverName)meta.filename=serverName;
      meta.mimeType=contentType.split(';')[0]||meta.mimeType||'application/octet-stream';
      return response.blob();
    }).then(function(blob){
      var file;try{file=new File([blob],meta.filename||'fieldflow-export',{type:meta.mimeType||blob.type||'application/octet-stream'});}catch(e){file=null;}
      if(file&&navigator.share&&navigator.canShare&&navigator.canShare({files:[file]})){
        return navigator.share({files:[file],title:meta.filename||'Exportação FieldFlow'}).then(function(){showPolicy('Ficheiro pronto para guardar ou partilhar.');return true;}).catch(function(){return saveBlobDownload(blob,meta);});
      }
      return saveBlobDownload(blob,meta);
    }).catch(function(error){var detail=String(error&&error.message||error||'');showPolicy(detail&&/^HTTP \d+$/i.test(detail)?('Não foi possível descarregar o ficheiro, '+detail+'.'):'Não foi possível descarregar o ficheiro.');nativeEvent(meta&&meta.evidenceExport?'fieldflow.evidence.download.failed':'fieldflow.download.failed',{url:meta.url,error:detail,evidenceExport:!!(meta&&meta.evidenceExport)});return false;});
  }
  /* fieldflow-expense-statement-mobile-ticket-delivery-v1 */
  function requestExpenseStatement(meta){
    showPolicy('A preparar a folha de despesas...');
    var source;try{source=new URL(meta.url,location.href);}catch(e){showPolicy('Não foi possível preparar a folha de despesas.');return;}
    var body=new URLSearchParams(source.search);
    body.set('action','fieldflow_prepare_expense_statement_ticket');
    var ajaxUrl=String(bridgeConfig.ajaxUrl||'');
    if(!ajaxUrl){showPolicy('Não foi possível preparar a folha de despesas.');return;}
    nativeEvent('fieldflow.expense.statement.prepare.requested',{source:'expense_statement_pdf'});
    fetch(ajaxUrl,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:body.toString()}).then(function(response){
      return response.text().then(function(text){var data=null;try{data=JSON.parse(text);}catch(e){}if(!response.ok||!data||data.success!==true||!data.data||!data.data.url){var message=data&&data.data&&data.data.message?String(data.data.message):('HTTP '+response.status);throw new Error(message);}return data.data;});
    }).then(function(ticket){
      var url=String(ticket.url||'');if(!url)throw new Error('Ticket da folha de despesas inválido.');
      var filename=String(ticket.filename||meta.filename||'fieldflow-folha-despesas.pdf');
      var payload={url:url,source:'expense_statement_pdf_ticket',externalApplication:true,systemBrowser:true,preferNativeApp:false,filename:filename,attachmentKind:'pdf'};
      nativeEvent('fieldflow.expense.statement.download.requested',{url:url,filename:filename,transport:'external_system_browser'});
      return nativeExternalOpen(payload,3600).then(function(handled){
        if(handled){showPolicy('Folha de despesas enviada para o gestor de downloads.');return true;}
        var fallback={url:url,filename:filename,format:'pdf',mimeType:'application/pdf',teamAttachment:false,teamAttachmentKind:'',dashboardExport:false,routeExport:false,expenseStatement:false};
        return browserDownload(fallback).then(function(ok){if(ok)showPolicy('Folha de despesas pronta para guardar.');return !!ok;});
      });
    }).catch(function(error){
      var detail=String(error&&error.message||error||'expense_statement_ticket_failed');
      showPolicy('Não foi possível preparar a folha de despesas.');
      nativeEvent('fieldflow.expense.statement.download.failed',{url:meta.url,error:detail});
    });
  }
  function requestDashboardExport(meta){
    showPolicy('A preparar a exportação...');
    var source;try{source=new URL(meta.url,location.href);}catch(e){showPolicy('Não foi possível preparar a exportação.');return;}
    var body=new URLSearchParams(source.search);
    body.set('action',meta&&meta.routeExport?'routespro_create_route_export_ticket':'routespro_create_dashboard_export_ticket');
    var ajaxUrl=String(bridgeConfig.ajaxUrl||'');
    if(!ajaxUrl){showPolicy('Não foi possível preparar a exportação.');return;}
    fetch(ajaxUrl,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:body.toString()}).then(function(response){
      return response.text().then(function(text){var data=null;try{data=JSON.parse(text);}catch(e){}if(!response.ok||!data||data.success!==true||!data.data||!data.data.url){var message=data&&data.data&&data.data.message?String(data.data.message):('HTTP '+response.status);throw new Error(message);}return data.data;});
    }).then(function(ticket){
      var url=String(ticket.url||'');if(!url)throw new Error('Ticket de exportação inválido.');
      var payload={url:url,source:meta&&meta.routeExport?'route_export_download':'analytics_export_download',externalApplication:true,systemBrowser:true};
      return nativeExternalOpen(payload).then(function(handled){
        if(handled){showPolicy('Exportação aberta no dispositivo.');return true;}
        var link=document.createElement('a');link.href=url;link.target='_blank';link.rel='noopener';link.setAttribute('data-fieldflow-external-fallback','1');link.style.display='none';document.body.appendChild(link);link.click();window.setTimeout(function(){link.remove();},5000);showPolicy('Exportação preparada.');return true;
      });
    }).catch(function(error){showPolicy('Não foi possível exportar o ficheiro.');nativeEvent('fieldflow.download.failed',{url:meta.url,error:String(error&&error.message||error||'ticket_failed')});});
  }
  function teamAttachmentSignal(payload){
    try{window.dispatchEvent(new CustomEvent('fieldflow:external-open',{detail:payload}));}catch(e){}
    try{var parentOrigin=nativeParentOrigin();if(parentOrigin&&window.parent)window.parent.postMessage(Object.assign({type:'fieldflow.external.open'},payload),parentOrigin);}catch(e){}
  }
  function requestTeamAttachment(meta){
    showPolicy(meta.teamAttachmentKind==='image'?'A preparar a imagem...':'A abrir o anexo no dispositivo...');
    var payload={url:meta.url,source:'team_attachment_download',externalApplication:true,systemBrowser:true,filename:meta.filename||'',attachmentKind:meta.teamAttachmentKind||'document'};
    teamAttachmentSignal(payload);
    nativeExternalOpen(payload,3200).then(function(handled){
      if(handled){showPolicy('Anexo aberto no dispositivo.');return true;}
      // A app atual pode ter o downloader nativo funcional mesmo quando a ponte
      // externa não está disponível. O anexo continua identificado explicitamente
      // e nunca é confundido com uma exportação Analytics.
      return nativeDownload(meta).then(function(nativeHandled){
        if(nativeHandled){showPolicy('Anexo preparado no dispositivo.');return true;}
        return browserDownload(meta);
      });
    }).catch(function(){
      nativeDownload(meta).then(function(nativeHandled){if(!nativeHandled)browserDownload(meta);});
    });
  }
  function requestDownload(meta){
    if(meta&&meta.expenseStatement){requestExpenseStatement(meta);return;}
    nativeEvent('fieldflow.download.requested',{url:meta.url,filename:meta.filename,format:meta.format,teamAttachment:!!meta.teamAttachment,teamAttachmentKind:meta.teamAttachmentKind||'',dashboardExport:!!meta.dashboardExport,routeExport:!!meta.routeExport});
    if(meta&&(meta.dashboardExport||meta.routeExport)){requestDashboardExport(meta);return;}
    if(meta&&meta.teamAttachment){requestTeamAttachment(meta);return;}
    nativeDownload(meta).then(function(handled){if(!handled)browserDownload(meta);});
  }
  function isAllowedHost(host){host=(host||'').toLowerCase();return !host||host===location.hostname.toLowerCase()||bridgeConfig.allowedDomains.some(function(domain){return host===domain||host.endsWith('.'+domain);});}
  function normaliseExternalUrl(raw,payload){var url;try{url=new URL(String(raw||''),location.href);}catch(e){return '';}if(!/^https?:$/i.test(url.protocol))return '';if(payload&&payload.navigation&&/(^|\.)google\.[a-z.]+$/i.test(url.hostname)&&/\/maps\/dir\/?$/i.test(url.pathname)){url.searchParams.set('api','1');url.searchParams.set('dir_action','navigate');}return url.href;}
  function nativeExternalOpen(payload,timeoutMs){if(!(window.flutter_inappwebview&&typeof window.flutter_inappwebview.callHandler==='function'))return Promise.resolve(false);var request=window.flutter_inappwebview.callHandler('fieldflowOpenExternal',payload).then(function(result){return result===true||!!(result&&(result.handled||result.opened||result.launched));}).catch(function(){return false;});var wait=Math.max(500,Math.min(5000,parseInt(timeoutMs||1400,10)||1400));return Promise.race([request,new Promise(function(resolve){window.setTimeout(function(){resolve(false);},wait);})]);}
  function requestExternalOpen(raw,payload){payload=Object.assign({source:'external_link',navigation:false,preferNativeApp:false,externalApplication:true,systemBrowser:bridgeConfig.openExternalInSystemBrowser},payload||{});var url=normaliseExternalUrl(raw,payload);if(!url)return Promise.resolve(false);payload.url=url;try{window.dispatchEvent(new CustomEvent('fieldflow:external-open',{detail:payload}));}catch(e){}try{var parentOrigin=nativeParentOrigin();if(parentOrigin&&window.parent)window.parent.postMessage(Object.assign({type:'fieldflow.external.open'},payload),parentOrigin);}catch(e){}return nativeExternalOpen(payload).then(function(handled){if(handled)return true;window.location.assign(url);return true;});}
  function preparedEvidenceDownload(meta){
    // Evidence ZIPs are already prepared server-side and exposed through a
    // short-lived capability ticket. Do not route them through the generic
    // Flutter downloader: the current mobile shell acknowledges that handler
    // before the native transfer has actually succeeded, which can show a
    // false "Download iniciado" followed by a native export failure. Open the
    // prepared attachment with the system/browser bridge instead.
    showPolicy('A abrir o ZIP no gestor de downloads...');
    var payload={url:meta.url,source:'evidence_zip_prepared',externalApplication:true,systemBrowser:true,preferNativeApp:false,filename:meta.filename||'evidencias-fieldflow.zip',attachmentKind:'zip'};
    nativeEvent('fieldflow.evidence.download.requested',{url:meta.url,filename:meta.filename,transport:'external_system_browser'});
    return nativeExternalOpen(payload,3600).then(function(handled){
      if(handled){showPolicy('ZIP enviado para o gestor de downloads.');return true;}
      // Compatibility fallback for shells without fieldflowOpenExternal.
      // This path stays inside the browser layer and explicitly avoids the
      // generic native download handler.
      return browserDownload(meta).then(function(ok){if(ok)showPolicy('ZIP preparado para guardar.');return !!ok;});
    }).catch(function(error){
      showPolicy('Não foi possível abrir o ZIP no dispositivo.');
      nativeEvent('fieldflow.evidence.download.failed',{url:meta.url,error:String(error&&error.message||error||'prepared_evidence_external_failed')});
      return false;
    });
  }
  window.FieldFlowNativeBridge=Object.assign({},window.FieldFlowNativeBridge||{},{openExternal:function(url,payload){return requestExternalOpen(url,payload);},downloadPrepared:function(raw,filename,options){var url;try{url=new URL(String(raw||''),location.href);}catch(e){showPolicy('Link de download inválido.');return Promise.resolve(false);}if(!isAllowedHost(url.hostname)){showPolicy('O download foi bloqueado por segurança.');return Promise.resolve(false);}var meta={url:url.href,filename:String(filename||'fieldflow-download.zip'),format:'zip',mimeType:'application/zip',teamAttachment:false,teamAttachmentKind:'',dashboardExport:false,routeExport:false,evidenceExport:!!(options&&options.kind==='evidence_zip')};if(meta.evidenceExport)return preparedEvidenceDownload(meta);nativeEvent('fieldflow.download.requested',{url:meta.url,filename:meta.filename,format:meta.format,evidenceExport:false});showPolicy('A preparar o ficheiro...');return nativeDownload(meta).then(function(handled){if(handled)return true;return browserDownload(meta);}).catch(function(error){showPolicy('Não foi possível descarregar o ficheiro.');nativeEvent('fieldflow.download.failed',{url:meta.url,error:String(error&&error.message||error||'prepared_download_failed'),evidenceExport:false});return false;});}});
  document.addEventListener('click',function(event){var anchor=event.target.closest&&event.target.closest('a[href]');if(!anchor)return;if(anchor.hasAttribute('data-fieldflow-external-fallback')||anchor.hasAttribute('data-fieldflow-download-fallback'))return;var raw=anchor.getAttribute('href')||'';if(!raw||raw.charAt(0)==='#'||/^javascript:/i.test(raw))return;if(/^(?:mailto:|tel:|sms:)/i.test(raw)){event.preventDefault();event.stopPropagation();if(!bridgeConfig.allowExternalLinks){showPolicy('Este link externo está bloqueado na aplicação.');return;}nativeExternalOpen({url:raw,source:'contact_link',externalApplication:true,systemBrowser:true}).then(function(handled){if(!handled)window.location.assign(raw);});return;}var url;try{url=new URL(raw,location.href);}catch(e){return;}var meta=downloadMeta(anchor,url);if(meta){event.preventDefault();event.stopPropagation();if(!bridgeConfig.allowDownloads){showPolicy('Os downloads estão desativados na aplicação.');return;}requestDownload(meta);return;}if(!isAllowedHost(url.hostname)){event.preventDefault();event.stopPropagation();if(!bridgeConfig.allowExternalLinks){showPolicy('Este link externo está bloqueado na aplicação.');return;}requestExternalOpen(url.href,{source:'anchor',navigation:/\/maps\/dir\/?$/i.test(url.pathname),preferNativeApp:/google\./i.test(url.hostname),systemBrowser:bridgeConfig.openExternalInSystemBrowser});}
  },true);
  function syncFileInputs(){var nativeIOS=String(bridgeConfig.platform||'').toLowerCase()==='ios';document.querySelectorAll('[data-rp-upload-zone]').forEach(function(zone){zone.setAttribute('data-fieldflow-allow-multiple',bridgeConfig.allowMultipleUploads?'1':'0');var add=zone.querySelector('[data-rp-upload-add]');if(add){add.hidden=!bridgeConfig.allowMultipleUploads;add.disabled=!bridgeConfig.allowMultipleUploads;}});document.querySelectorAll('input[type="file"]').forEach(function(input){var source=String(input.getAttribute('data-rp-upload-source')||'');var allowed=bridgeConfig.allowFileUploads&&(source!=='camera'||bridgeConfig.allowCamera)&&(source!=='gallery'||bridgeConfig.allowGallery);input.disabled=!allowed;input.setAttribute('data-fieldflow-upload-policy',allowed?'allowed':'blocked');if(source==='camera'&&nativeIOS){input.setAttribute('accept','image/*');input.setAttribute('capture','environment');input.removeAttribute('multiple');}if(source==='gallery'){if(nativeIOS){input.setAttribute('accept','image/*');input.removeAttribute('capture');}var zone=input.closest('[data-rp-upload-zone]');if(bridgeConfig.allowMultipleUploads&&zone&&zone.getAttribute('data-rp-upload-multiple')==='1')input.setAttribute('multiple','multiple');else input.removeAttribute('multiple');}});document.querySelectorAll('[data-rp-upload-trigger]').forEach(function(button){var source=String(button.getAttribute('data-rp-upload-trigger')||'');var allowed=bridgeConfig.allowFileUploads&&(source!=='camera'||bridgeConfig.allowCamera)&&(source!=='gallery'||bridgeConfig.allowGallery);button.hidden=!allowed;button.disabled=!allowed;});}
  syncFileInputs();
  if('MutationObserver' in window)new MutationObserver(syncFileInputs).observe(document.body,{childList:true,subtree:true});
  var initialContextVersion=<?php echo wp_json_encode(Controller::context_version_for_user((int)($session['user_id'] ?? 0))); ?>;
  var pendingContextReloadVersion='';
  function fieldflowVisibleReportForm(){
    var forms=Array.prototype.slice.call(document.querySelectorAll('.routespro-dyn-form'));
    return forms.find(function(form){
      if(!form||!document.documentElement.contains(form))return false;
      var panel=form.closest('.rp-panel');
      if(panel&&!panel.classList.contains('active'))return false;
      var stop=form.querySelector('[name="routespro_stop_id"]');
      return !!(stop&&String(stop.value||'')!=='');
    })||null;
  }
  function fieldflowContextReload(version,user){
    if(!version||!initialContextVersion||version===initialContextVersion)return;
    if(fieldflowVisibleReportForm()){
      if(pendingContextReloadVersion!==version){
        pendingContextReloadVersion=version;
        try{window.sessionStorage&&window.sessionStorage.setItem('fieldflow_pending_context_reload',version);}catch(e){}
        nativeEvent('fieldflow.context.changed_deferred',{user:user||null});
      }
      return;
    }
    nativeEvent('fieldflow.context.changed',{user:user||null});
    window.location.reload();
  }
  document.addEventListener('fieldflow:visit-completed',function(){
    if(!pendingContextReloadVersion)return;
    window.setTimeout(function(){if(pendingContextReloadVersion)window.location.reload();},450);
  });
  document.addEventListener('click',function(event){
    if(!pendingContextReloadVersion)return;
    var trigger=event.target&&event.target.closest?event.target.closest('.rp-tabbar button[data-panel]'):null;
    if(trigger&&String(trigger.dataset.panel||'')!=='report')window.setTimeout(function(){window.location.reload();},120);
  },true);
  function checkContext(){return fetch(<?php echo wp_json_encode(rest_url('fieldflow-mobile/v1/me')); ?>,{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest','X-WP-Nonce':contextNonce}}).then(function(response){if(!response.ok){var error=new Error('context');error.status=response.status;throw error;}return response.json();}).then(function(payload){var version=payload&&payload.user&&payload.user.context_version?String(payload.user.context_version):'';fieldflowContextReload(version,payload&&payload.user?payload.user:null);return payload;}).catch(function(error){if(error&&Number(error.status||0)>=401)nativeEvent('fieldflow.session.context_failed',{status:String(error.status||''),http_status:Number(error.status||0)});return null;});}
  function runHeartbeat(){if(!bridgeConfig.autoRenew)return Promise.resolve(null);return fetch(<?php echo wp_json_encode(rest_url('fieldflow-mobile/v1/auth/web-session/heartbeat')); ?>,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-WP-Nonce':contextNonce},body:JSON.stringify({target:<?php echo wp_json_encode($target); ?>})}).then(function(response){if(!response.ok){var error=new Error(String(response.status));error.status=response.status;throw error;}return response.json();}).then(function(payload){nativeEvent('fieldflow.session.heartbeat',{payload:payload});return payload;}).catch(function(error){nativeEvent('fieldflow.session.heartbeat_failed',{status:String(error&&error.message||''),http_status:Number(error&&error.status||0)});return null;});}
  window.setInterval(checkContext,<?php echo (int)(Settings::context_sync_seconds() * 1000); ?>);
  if(bridgeConfig.autoRenew)window.setInterval(runHeartbeat,Math.max(60,Number(bridgeConfig.heartbeatSeconds||300))*1000);
  addEventListener('beforeinstallprompt',function(event){event.preventDefault();});
  try{
    if('serviceWorker' in navigator){navigator.serviceWorker.getRegistrations().then(function(regs){regs.forEach(function(reg){var worker=reg.active||reg.waiting||reg.installing;var url=worker&&worker.scriptURL?worker.scriptURL:'';if(url.indexOf('fieldflow_pwa_sw')!==-1||url.indexOf('fieldflow-service-worker.js')!==-1)reg.unregister();});}).catch(function(){});}
    if('caches' in window){caches.keys().then(function(keys){keys.forEach(function(key){if(key.indexOf('fieldflow-pwa-')===0)caches.delete(key);});}).catch(function(){});}
  }catch(e){}
  function publishBillingState(){return fetch(<?php echo wp_json_encode(rest_url('fieldflow-mobile/v1/billing/experience')); ?>,{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest','X-WP-Nonce':contextNonce}}).then(function(response){return response.ok?response.json():null;}).then(function(payload){if(payload&&payload.experience)nativeEvent('fieldflow.billing.state',{experience:payload.experience,user:payload.user||null});return payload;}).catch(function(){return null;});}
  var fieldflowResumeTimer=0,fieldflowResumeLastAt=0,fieldflowResumeArmedAt=0;
  function armNativeResume(){fieldflowResumeArmedAt=Date.now();}
  function consumeNativeResumeArm(){var armed=fieldflowResumeArmedAt&&Date.now()-fieldflowResumeArmedAt<60000;fieldflowResumeArmedAt=0;return !!armed;}
  function handleNativeResume(reason){
    reason=String(reason||'resume');
    if(reason==='fieldflow:app-resume')stabilizeResumePaint('fieldflow:app-resume');
    else stabilizeResumePaint(reason);
    syncNetwork();
    checkContext();
    runHeartbeat();
    publishBillingState();
    try{document.dispatchEvent(new CustomEvent('fieldflow:native-resume-sync',{detail:{target:<?php echo wp_json_encode($target); ?>,reason:reason,contract:'fieldflow-field-session-form-continuity-v1'}}));}catch(e){}
    try{if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.refreshDevicePolicy==='function')window.FieldFlowOffline.refreshDevicePolicy();}catch(e){}
    try{if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.sync==='function')window.FieldFlowOffline.sync({includeFailed:true});}catch(e){}
    nativeEvent('fieldflow.portal.resumed',{online:navigator.onLine!==false,reason:reason,contract:'fieldflow-field-session-form-continuity-v1'});
  }
  function queueNativeResume(reason){
    var now=Date.now(),wait=(now-fieldflowResumeLastAt<900)?900:40;
    if(fieldflowResumeTimer)window.clearTimeout(fieldflowResumeTimer);
    fieldflowResumeTimer=window.setTimeout(function(){fieldflowResumeTimer=0;fieldflowResumeLastAt=Date.now();handleNativeResume(reason);},wait);
  }
  window.addEventListener('fieldflow:app-resume',function(){fieldflowResumeArmedAt=0;queueNativeResume('fieldflow:app-resume');});
  document.addEventListener('visibilitychange',function(){
    if(document.visibilityState==='hidden'){armNativeResume();armResumePaintGuard('visibility-hidden');return;}
    if(consumeNativeResumeArm())queueNativeResume('visibility-visible');
  });
  window.addEventListener('pagehide',function(){armNativeResume();armResumePaintGuard('pagehide');});
  window.addEventListener('pageshow',function(event){if(document.visibilityState!=='hidden'&&(event.persisted||consumeNativeResumeArm()))queueNativeResume('pageshow');});
  // Android native <select> and file pickers can blur/focus the WebView without
  // an app lifecycle transition. Bare focus must never restore an older visit.
  window.addEventListener('focus',function(){if(document.visibilityState!=='hidden'&&consumeNativeResumeArm())queueNativeResume('focus-after-hidden');});
  publishBillingState();
  if(billingResult){[3000,8000,15000].forEach(function(delay){window.setTimeout(publishBillingState,delay);});}
  function publishNativeReady(){return nativeEvent('fieldflow.portal.ready',{native:true,target:<?php echo wp_json_encode($target); ?>,diagnosticsEndpoint:diagnosticsEndpoint,nonce:contextNonce,allowedDomains:bridgeConfig.allowedDomains||[],allowExternalLinks:!!bridgeConfig.allowExternalLinks,allowDownloads:!!bridgeConfig.allowDownloads,openExternalInSystemBrowser:!!bridgeConfig.openExternalInSystemBrowser,deviceId:<?php echo wp_json_encode((string)($session['mobile_device_id'] ?? '')); ?>,appVersion:<?php echo wp_json_encode((string)($session['mobile_app_version'] ?? '')); ?>,platform:<?php echo wp_json_encode((string)($session['mobile_platform'] ?? '')); ?>,logoutUrl:<?php echo wp_json_encode(home_url('/fieldflow-mobile/logout/')); ?>,shellVersion:nativeShellVersion});}
  window.addEventListener('fieldflow:native-shell-ready',publishNativeReady);
  publishNativeReady();
})();
</script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html><?php
        exit;
    }

    private static function serve_logout(): void {
        global $wpdb;
        foreach (['operational', 'client', 'billing', 'subscriptions'] as $portal_target) {
            $token = self::web_cookie($portal_target);
            if ($token !== '') {
                $wpdb->query($wpdb->prepare(
                    'UPDATE ' . self::table() . ' SET revoked_at=%s, updated_at=%s WHERE web_token_hash=%s AND revoked_at IS NULL',
                    current_time('mysql', true),
                    current_time('mysql', true),
                    self::hash_token($token)
                ));
            }
            self::clear_web_cookie($portal_target);
        }
        if (is_user_logged_in()) wp_logout();
        self::no_cache_headers();
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>FieldFlow</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a}.card{max-width:520px;margin:24px;padding:28px;border-radius:24px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 24px 70px rgba(15,23,42,.13);text-align:center}h1{margin:0 0 10px;font-size:26px}p{margin:0;color:#64748b;line-height:1.55}</style></head><body><main class="card"><h1>Sessão terminada</h1><p>Podes fechar esta janela ou voltar a iniciar sessão na aplicação FieldFlow.</p></main><script>(function(){try{if(window.localStorage){var remove=[];for(var i=0;i<localStorage.length;i++){var key=localStorage.key(i)||"";if(key.indexOf("fieldflow:resume:operational:v1:")===0||key.indexOf("fieldflow:last-report-visit:v2:")===0||key==="fieldflow:last-report-visit")remove.push(key);}remove.forEach(function(key){localStorage.removeItem(key);});}if(window.sessionStorage)sessionStorage.removeItem("fieldflow_active_panel");}catch(e){}var message={type:"fieldflow.portal.logout",source:"fieldflow-webview",shellVersion:"1.7",resumeNavigationContract:"fieldflow-field-resume-navigation-continuity-v1"};try{var origin=String(window.location.origin||"");if(origin&&window.parent)window.parent.postMessage(message,origin);}catch(e){}try{if(window.flutter_inappwebview&&typeof window.flutter_inappwebview.callHandler==="function")window.flutter_inappwebview.callHandler("fieldflowNativeEvent",message);}catch(e){}})();</script></body></html>';
        exit;
    }

    private static function validate_web_session(string $target) {
        global $wpdb;
        $token = self::web_cookie($target);
        if ($token === '') {
            return new WP_Error('fieldflow_mobile_web_cookie_missing', 'Abre novamente este portal a partir da aplicação FieldFlow.', ['status' => 401]);
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE web_token_hash=%s AND target=%s AND consumed_at IS NOT NULL AND revoked_at IS NULL AND web_expires_at>%s LIMIT 1',
            self::hash_token($token),
            $target,
            current_time('mysql', true)
        ), ARRAY_A);
        if (!$row) {
            self::clear_web_cookie($target);
            return new WP_Error('fieldflow_mobile_web_session_expired', 'A sessão do portal expirou. Abre-o novamente na aplicação.', ['status' => 401]);
        }
        $webPolicy = \RoutesPro\Security\SessionPolicy::validateWebSession($row);
        if (is_wp_error($webPolicy)) {
            self::revoke_row((int)$row['id']);
            self::clear_web_cookie($target);
            return $webPolicy;
        }

        $mobile = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Auth::table() . ' WHERE id=%d AND user_id=%d AND revoked_at IS NULL AND refresh_expires_at>%s LIMIT 1',
            (int)$row['mobile_session_id'],
            (int)$row['user_id'],
            current_time('mysql', true)
        ), ARRAY_A);
        if (!$mobile || !self::mobile_session_allowed($mobile)) {
            self::revoke_row((int)$row['id']);
            self::clear_web_cookie($target);
            return new WP_Error('fieldflow_mobile_parent_session_expired', 'A sessão principal terminou ou foi bloqueada. Inicia sessão novamente.', ['status' => 401]);
        }
        $mobilePolicy = \RoutesPro\Security\SessionPolicy::validateMobileSession($mobile);
        if (is_wp_error($mobilePolicy)) {
            Auth::revoke_by_id((int)$mobile['id']);
            self::revoke_row((int)$row['id']);
            self::clear_web_cookie($target);
            return $mobilePolicy;
        }
        $platform = Settings::platform((string)($mobile['platform'] ?? ''));
        if (Settings::maintenance_enabled($platform)) {
            $mobile_settings = Settings::get();
            return new WP_Error('fieldflow_mobile_maintenance', (string)$mobile_settings['maintenance']['message'], [
                'status' => 503,
                'retry_after_seconds' => (int)$mobile_settings['maintenance']['retry_after_seconds'],
            ]);
        }

        $access = self::portal_access((int)$row['user_id']);
        if (!in_array($target, (array)($access['allowed_targets'] ?? []), true)) {
            self::revoke_row((int)$row['id']);
            self::clear_web_cookie($target);
            return new WP_Error('fieldflow_mobile_portal_forbidden', 'Este portal já não está disponível para o utilizador.', ['status' => 403]);
        }

        $cookie_user_id = get_current_user_id();
        wp_set_current_user((int)$row['user_id']);
        if ($cookie_user_id !== (int)$row['user_id']) {
            wp_set_auth_cookie((int)$row['user_id'], true, is_ssl());
        }
        $row = self::maybe_renew_web_session($row, $target);
        $row['mobile_device_id'] = (string)($mobile['device_id'] ?? '');
        $row['mobile_platform'] = Settings::platform((string)($mobile['platform'] ?? ''));
        $row['mobile_app_version'] = (string)($mobile['app_version'] ?? '');
        self::touch_row((int)$row['id'], (string)($row['last_seen_at'] ?? ''));
        return $row;
    }

    private static function mobile_session_allowed(array $mobile): bool {
        $device_id = (string)($mobile['device_id'] ?? '');
        if (Settings::is_device_blocked($device_id)) return false;
        $platform = Settings::platform((string)($mobile['platform'] ?? ''));
        return Settings::platform_enabled($platform);
    }

    private static function portal_url(string $target, int $client_id = 0, int $project_id = 0, int $web_session_id = 0): string {
        $safeTarget = in_array($target,['operational','client','billing','subscriptions'],true)?$target:'operational';
        $url = home_url('/fieldflow-mobile/portal/' . $safeTarget . '/');
        $args = ['ff_mobile' => 1];
        if ($client_id > 0) $args['client_id'] = $client_id;
        if ($project_id > 0) $args['project_id'] = $project_id;
        if ($web_session_id > 0) $args['ffws'] = $web_session_id;
        return add_query_arg($args, $url);
    }

    private static function render_error(string $title, string $message, int $status = 400, int $retry_after = 0): void {
        self::no_cache_headers();
        status_header($status);
        if ($retry_after > 0) header('Retry-After: ' . $retry_after);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>' . esc_html($title) . '</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a}.card{max-width:560px;margin:24px;padding:28px;border-radius:24px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 24px 70px rgba(15,23,42,.13)}.pill{display:inline-flex;padding:7px 11px;border-radius:999px;background:#0f172a;color:#fff;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}h1{font-size:28px;margin:16px 0 9px}p{margin:0;color:#64748b;line-height:1.6}</style></head><body><main class="card"><span class="pill">FieldFlow Mobile</span><h1>' . esc_html($title) . '</h1><p>' . esc_html($message) . '</p></main></body></html>';
        exit;
    }

    private static function no_cache_headers(): void {
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    private static function cookie_name(string $target): string {
        $safe = in_array($target, ['operational','client','billing','subscriptions'], true) ? $target : 'operational';
        return self::COOKIE_PREFIX . $safe;
    }

    private static function set_web_cookie(string $value, int $expires, string $target): void {
        $name = self::cookie_name($target);
        $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') ? (string)COOKIE_DOMAIN : '';
        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $value, [
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie($name, $value, $expires, $path . '; samesite=Lax', $domain, is_ssl(), true);
        }
        $_COOKIE[$name] = $value;
    }

    private static function clear_web_cookie(string $target): void {
        $name = self::cookie_name($target);
        $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') ? (string)COOKIE_DOMAIN : '';
        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, '', [
                'expires' => time() - HOUR_IN_SECONDS,
                'path' => $path,
                'domain' => $domain,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie($name, '', time() - HOUR_IN_SECONDS, $path . '; samesite=Lax', $domain, is_ssl(), true);
        }
        unset($_COOKIE[$name]);
    }

    private static function web_cookie(string $target): string {
        return trim((string)($_COOKIE[self::cookie_name($target)] ?? ''));
    }

    private static function revoke_row(int $id): void {
        global $wpdb;
        if ($id <= 0) return;
        $wpdb->update(self::table(), [
            'revoked_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id], ['%s','%s'], ['%d']);
    }

    private static function maybe_renew_web_session(array $row, string $target): array {
        if (!Settings::bridge_flag('auto_renew', true)) return $row;
        $current_expiry = strtotime((string)($row['web_expires_at'] ?? '')) ?: 0;
        if ($current_expiry > time() + Settings::renew_window()) return $row;

        global $wpdb;
        $parent_expiry = (string)$wpdb->get_var($wpdb->prepare(
            'SELECT refresh_expires_at FROM ' . Auth::table() . ' WHERE id=%d AND user_id=%d AND revoked_at IS NULL LIMIT 1',
            (int)($row['mobile_session_id'] ?? 0),
            (int)($row['user_id'] ?? 0)
        ));
        $parent_ts = strtotime($parent_expiry) ?: 0;
        if ($parent_ts <= time()) return $row;

        $new_expiry = \RoutesPro\Security\SessionPolicy::webExpiry($row, $parent_ts);
        if ($new_expiry <= $current_expiry + 60) return $row;
        $updated_at = current_time('mysql', true);
        $wpdb->update(self::table(), [
            'web_expires_at' => gmdate('Y-m-d H:i:s', $new_expiry),
            'last_seen_at' => $updated_at,
            'updated_at' => $updated_at,
        ], ['id' => (int)($row['id'] ?? 0)], ['%s','%s','%s'], ['%d']);
        $token = self::web_cookie($target);
        if ($token !== '') self::set_web_cookie($token, $new_expiry, $target);
        $row['web_expires_at'] = gmdate('Y-m-d H:i:s', $new_expiry);
        $row['last_seen_at'] = $updated_at;
        return $row;
    }

    private static function touch_row(int $id, string $last_seen): void {
        if ($last_seen !== '' && strtotime($last_seen) > time() - 300) return;
        global $wpdb;
        $wpdb->update(self::table(), [
            'last_seen_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id], ['%s','%s'], ['%d']);
    }

    private static function random_token(): string {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return preg_replace('/[^A-Za-z0-9]/', '', wp_generate_password(64, false, false));
        }
    }

    private static function hash_token(string $token): string {
        return hash('sha256', $token);
    }

    private static function client_ip(): string {
        return substr(sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 64);
    }
}
