<?php
namespace RoutesPro\Mobile;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use RoutesPro\Repositories\RouteAccessRepository;
use RoutesPro\Rest\EventsController;
use RoutesPro\Support\Permissions;
use RoutesPro\Support\StabilityFlags;

if (!defined('ABSPATH')) exit;

class Controller {
    const NS = 'fieldflow-mobile/v1';

    public function register_routes(): void {
        register_rest_route(self::NS, '/auth/login', ['methods' => 'POST', 'callback' => [$this, 'login'], 'permission_callback' => '__return_true']);
        register_rest_route(self::NS, '/auth/refresh', ['methods' => 'POST', 'callback' => [$this, 'refresh'], 'permission_callback' => '__return_true']);
        register_rest_route(self::NS, '/auth/logout', ['methods' => 'POST', 'callback' => [$this, 'logout'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/auth/logout-all', ['methods' => 'POST', 'callback' => [$this, 'logout_all'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/auth/web-session', ['methods' => 'POST', 'callback' => [$this, 'web_session'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/auth/web-session/heartbeat', ['methods' => 'POST', 'callback' => [$this, 'web_session_heartbeat'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/auth/web-session/revoke', ['methods' => 'POST', 'callback' => [$this, 'web_session_revoke'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/me', ['methods' => 'GET', 'callback' => [$this, 'me'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/billing/experience', ['methods' => 'GET', 'callback' => [$this, 'billing_experience'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/app-config', ['methods' => 'GET', 'callback' => [$this, 'app_config'], 'permission_callback' => '__return_true']);
        register_rest_route(self::NS, '/my-routes', ['methods' => 'GET', 'callback' => [$this, 'my_routes'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/assigned-stores', ['methods' => 'GET', 'callback' => [$this, 'assigned_stores'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/self-route-plans', ['methods' => 'POST', 'callback' => [$this, 'create_self_route_plan'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/route-optimization/preview', ['methods' => 'POST', 'callback' => [$this, 'preview_route_optimization'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/routes/(?P<id>\d+)/optimize', ['methods' => 'POST', 'callback' => [$this, 'apply_route_optimization'], 'permission_callback' => [$this, 'route_access']]);
        register_rest_route(self::NS, '/routes/(?P<id>\d+)/workspace', ['methods' => 'POST', 'callback' => [$this, 'save_route_workspace'], 'permission_callback' => [$this, 'route_access']]);
        register_rest_route(self::NS, '/routes/(?P<id>\d+)/workspace/restore', ['methods' => 'POST', 'callback' => [$this, 'restore_route_workspace'], 'permission_callback' => [$this, 'route_access']]);
        register_rest_route(self::NS, '/routes/(?P<id>\d+)/stops/bulk-remove', ['methods' => 'POST', 'callback' => [$this, 'bulk_remove_route_stops'], 'permission_callback' => [$this, 'route_access']]);
        register_rest_route(self::NS, '/offline/ping', ['methods' => 'GET', 'callback' => [$this, 'offline_ping'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/offline/bootstrap', ['methods' => 'GET', 'callback' => [$this, 'offline_bootstrap'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/offline/delta', ['methods' => 'GET', 'callback' => [$this, 'offline_delta'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/offline/sync', ['methods' => 'POST', 'callback' => [$this, 'offline_sync'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/offline/status', ['methods' => ['GET','POST'], 'callback' => [$this, 'offline_status'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/offline/trust-lease', ['methods' => 'GET', 'callback' => [$this, 'offline_trust_lease'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/offline/media/start', ['methods' => 'POST', 'callback' => ['\RoutesPro\Mobile\ResumableMediaService', 'start'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/offline/media/(?P<upload_id>[a-f0-9]{48})/chunk', ['methods' => 'POST', 'callback' => ['\RoutesPro\Mobile\ResumableMediaService', 'chunk'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/offline/media/(?P<upload_id>[a-f0-9]{48})/complete', ['methods' => 'POST', 'callback' => ['\RoutesPro\Mobile\ResumableMediaService', 'complete'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/routes/(?P<id>\d+)', ['methods' => 'GET', 'callback' => [$this, 'route'], 'permission_callback' => [$this, 'route_access']]);
        register_rest_route(self::NS, '/routes/(?P<id>\d+)/stops', ['methods' => 'GET', 'callback' => [$this, 'route_stops'], 'permission_callback' => [$this, 'route_access']]);
        register_rest_route(self::NS, '/stops/(?P<id>\d+)', ['methods' => 'GET', 'callback' => [$this, 'stop'], 'permission_callback' => [$this, 'stop_access']]);
        register_rest_route(self::NS, '/stops/(?P<id>\d+)/form', ['methods' => 'GET', 'callback' => [$this, 'stop_form'], 'permission_callback' => [$this, 'stop_access']]);
        foreach (['checkin', 'checkout', 'failure'] as $event) {
            register_rest_route(self::NS, '/stops/(?P<id>\d+)/' . $event, ['methods' => 'POST', 'callback' => function(WP_REST_Request $request) use ($event) { return $this->stop_event($request, $event); }, 'permission_callback' => [$this, 'stop_access']]);
        }
        register_rest_route(self::NS, '/form-submissions', ['methods' => 'POST', 'callback' => [$this, 'submit_form'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/form-submissions/(?P<id>\d+)', ['methods' => 'GET', 'callback' => [$this, 'submission'], 'permission_callback' => [$this, 'submission_access']]);
        register_rest_route(self::NS, '/media', ['methods' => 'POST', 'callback' => [$this, 'media'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/briefings', ['methods' => 'GET', 'callback' => [$this, 'briefings'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/briefings/(?P<id>\d+)/view', ['methods' => 'POST', 'callback' => [$this, 'briefing_view'], 'permission_callback' => [$this, 'briefing_access']]);
        register_rest_route(self::NS, '/briefings/(?P<id>\d+)/acknowledge', ['methods' => 'POST', 'callback' => [$this, 'briefing_ack'], 'permission_callback' => [$this, 'briefing_access']]);
        register_rest_route(self::NS, '/implementations', ['methods' => 'GET', 'callback' => [$this, 'implementations'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/stocks', ['methods' => 'GET', 'callback' => [$this, 'stocks'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/stocks/viewed', ['methods' => 'POST', 'callback' => [$this, 'stocks_viewed'], 'permission_callback' => [$this, 'authenticated']]);
        register_rest_route(self::NS, '/expenses', [
            ['methods' => 'GET', 'callback' => [$this, 'expenses'], 'permission_callback' => [$this, 'authenticated']],
            ['methods' => 'POST', 'callback' => [$this, 'expense_create'], 'permission_callback' => [$this, 'authenticated']],
        ]);
        register_rest_route(self::NS, '/health', ['methods' => 'GET', 'callback' => [$this, 'health'], 'permission_callback' => '__return_true']);
    }

    public function save_route_workspace(WP_REST_Request $request) {
        return (new \RoutesPro\Rest\RoutesController())->save_route_workspace($request);
    }

    public function restore_route_workspace(WP_REST_Request $request) {
        return (new \RoutesPro\Rest\RoutesController())->restore_route_workspace($request);
    }

    public function bulk_remove_route_stops(WP_REST_Request $request) {
        return (new \RoutesPro\Rest\RoutesController())->bulk_remove_stops($request);
    }

    public function authenticated() {
        return is_user_logged_in() ? true : new WP_Error('fieldflow_mobile_auth_required', 'Authentication required.', ['status' => 401]);
    }

    public function route_access(WP_REST_Request $request) {
        return Permissions::can_access_route(absint($request['id']), get_current_user_id())
            ? true : new WP_Error('fieldflow_mobile_forbidden', 'Route access denied.', ['status' => 403]);
    }

    public function stop_access(WP_REST_Request $request) {
        global $wpdb;
        $route_id = (int)$wpdb->get_var($wpdb->prepare('SELECT route_id FROM ' . $wpdb->prefix . 'routespro_route_stops WHERE id=%d', absint($request['id'])));
        if (!$route_id) return new WP_Error('fieldflow_mobile_stop_not_found', 'Stop not found.', ['status' => 404]);
        return Permissions::can_access_route($route_id, get_current_user_id())
            ? true : new WP_Error('fieldflow_mobile_forbidden', 'Stop access denied.', ['status' => 403]);
    }

    public function submission_access(WP_REST_Request $request) {
        if (!is_user_logged_in()) return new WP_Error('fieldflow_mobile_auth_required', 'Authentication required.', ['status' => 401]);
        global $wpdb;
        $id = absint($request['id']);
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT user_id,client_id,project_id,route_id FROM ' . $wpdb->prefix . 'routespro_form_submissions WHERE id=%d LIMIT 1',
            $id
        ), ARRAY_A);
        if (!$row) return new WP_Error('fieldflow_mobile_submission_not_found', 'Submission not found.', ['status' => 404]);
        $scope = Permissions::assert_scope_or_error((int)($row['client_id'] ?? 0), (int)($row['project_id'] ?? 0));
        $allowed = (int)($row['user_id'] ?? 0) === get_current_user_id()
            || Permissions::is_manager()
            || (!empty($row['route_id']) && Permissions::can_access_route((int)$row['route_id']))
            || !is_wp_error($scope);
        return $allowed ? true : new WP_Error('fieldflow_mobile_forbidden', 'Submission access denied.', ['status' => 403]);
    }

    public function briefing_access(WP_REST_Request $request) {
        if (!is_user_logged_in()) return new WP_Error('fieldflow_mobile_auth_required', 'Authentication required.', ['status' => 401]);
        $briefingId = absint($request['id']);
        $briefing = \RoutesPro\Briefings\BriefingService::get($briefingId);
        if (!$briefing || (string)($briefing['status'] ?? '') !== 'published') {
            return new WP_Error('fieldflow_mobile_briefing_not_found', 'Briefing not found.', ['status' => 404]);
        }
        if (Permissions::is_manager()) return true;
        global $wpdb;
        $recipient = (int)$wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . \RoutesPro\Briefings\BriefingService::table('briefing_recipients') . ' WHERE briefing_id=%d AND user_id=%d AND version_no=%d LIMIT 1',
            $briefingId,
            get_current_user_id(),
            (int)($briefing['version_no'] ?? 0)
        ));
        return $recipient > 0
            ? true
            : new WP_Error('fieldflow_mobile_forbidden', 'Briefing access denied.', ['status' => 403]);
    }

    public function login(WP_REST_Request $request) {
        $body = $request->get_json_params() ?: [];
        $username = sanitize_text_field((string)($body['username'] ?? $body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $limit = Auth::login_rate_limit($username);
        if (is_wp_error($limit)) return $limit;
        if ($username === '' || $password === '') return new WP_Error('fieldflow_mobile_credentials_required', 'Username and password are required.', ['status' => 400]);
        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) return new WP_Error('fieldflow_mobile_invalid_credentials', 'Invalid credentials.', ['status' => 401]);
        $mfa = \RoutesPro\Security\Mfa::verifyMobileLogin($user, $body);
        if (is_wp_error($mfa)) return $mfa;
        if (!Permissions::can_access_front((int)$user->ID)) return new WP_Error('fieldflow_mobile_access_denied', 'This user cannot access FieldFlow.', ['status' => 403]);
        Auth::clear_login_rate_limit($username);
        $tokens = Auth::issue((int)$user->ID, $body);
        if (is_wp_error($tokens)) return $tokens;
        wp_set_current_user((int)$user->ID);
        return new WP_REST_Response(['tokens' => $tokens, 'user' => self::build_user_payload((int)$user->ID)], 200);
    }

    public function refresh(WP_REST_Request $request) {
        $body = $request->get_json_params() ?: [];
        $tokens = Auth::refresh((string)($body['refresh_token'] ?? ''), $body);
        if (is_wp_error($tokens)) return $tokens;
        $user_id = absint($tokens['user_id'] ?? 0);
        if ($user_id > 0) wp_set_current_user($user_id);
        return new WP_REST_Response([
            'tokens' => $tokens,
            'user' => $user_id > 0 ? self::build_user_payload($user_id) : null,
        ], 200);
    }

    public function logout() {
        $web_ok = WebViewBridge::revoke_current_mobile_session();
        $mobile_ok = Auth::revoke_current();
        return new WP_REST_Response(['ok' => $web_ok && $mobile_ok, 'web_sessions_revoked' => $web_ok], 200);
    }

    public function logout_all() {
        $user_id = get_current_user_id();
        $web_ok = WebViewBridge::revoke_all_for_user($user_id);
        $mobile_ok = Auth::revoke_all($user_id);
        return new WP_REST_Response(['ok' => $web_ok && $mobile_ok, 'web_sessions_revoked' => $web_ok], 200);
    }

    public function web_session(WP_REST_Request $request) {
        $body = $request->get_json_params() ?: [];
        $result = WebViewBridge::issue(get_current_user_id(), $body);
        if (is_wp_error($result)) return $result;
        return new WP_REST_Response($result, 201);
    }

    public function web_session_heartbeat(WP_REST_Request $request) {
        $target = sanitize_key((string)$request->get_param('target'));
        $result = WebViewBridge::heartbeat($target);
        if (is_wp_error($result)) return $result;
        return new WP_REST_Response($result, 200);
    }

    public function web_session_revoke(WP_REST_Request $request) {
        $body = $request->get_json_params() ?: [];
        $target = sanitize_key((string)($body['target'] ?? ''));
        if ($target !== '' && !in_array($target, ['operational', 'client', 'billing', 'subscriptions'], true)) {
            return new WP_Error('fieldflow_mobile_invalid_portal_target', 'Invalid portal target.', ['status' => 400]);
        }
        $ok = WebViewBridge::revoke_current_mobile_session($target ?: null);
        return new WP_REST_Response(['ok' => $ok, 'target' => $target ?: 'all'], $ok ? 200 : 500);
    }

    public function me() {
        return new WP_REST_Response(['user' => self::build_user_payload(get_current_user_id()), 'session' => $this->session_payload()], 200);
    }

    public function app_config(WP_REST_Request $request) {
        $configUserId = get_current_user_id();
        $configClientId = 0;
        $configProjectId = 0;
        if ($configUserId > 0) {
            [$configClientId, $configProjectId] = Permissions::resolve_front_context(0, 0, $configUserId);
        }
        $theme = class_exists('\\RoutesPro\\Support\\Theme')
            ? \RoutesPro\Support\Theme::resolve($configClientId, $configProjectId)
            : [];
        $settings = Settings::get();
        $requested_platform = Settings::platform((string)$request->get_param('platform'));
        $platform = in_array($requested_platform, ['android','ios'], true) ? $requested_platform : 'android';
        $app_version = sanitize_text_field((string)$request->get_param('app_version'));
        $platform_config = Settings::platform_config($platform);
        $version_status = $app_version !== ''
            ? Settings::version_status($platform, $app_version)
            : [
                'current_version' => '',
                'minimum_version' => (string)$platform_config['minimum_version'],
                'latest_version' => (string)$platform_config['latest_version'],
                'update_required' => false,
                'update_available' => false,
            ];
        $maintenance_status = Settings::maintenance_status($platform);
        $maintenance = (bool)$maintenance_status['enabled'];
        $bridge = (array)$settings['bridge'];
        $business_features = array_map('boolval', (array)$settings['features']);
        $portal_features = [
            'operational' => Settings::features_for_target('operational'),
            'client' => Settings::features_for_target('client'),
        ];

        return new WP_REST_Response([
            'api_version' => ContractRegistry::API_VERSION,
            'plugin_version' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            'environment' => (string)$settings['general']['environment'],
            'platform' => $platform,
            'contract' => ContractRegistry::summary(),
            'stability' => StabilityFlags::publicState(),
            // Backwards-compatible fields used by the current FlutterFlow app.
            'minimum_app_version' => (string)$platform_config['minimum_version'],
            'latest_app_version' => (string)$platform_config['latest_version'],
            'maintenance_mode' => $maintenance,
            'maintenance_title' => (string)$settings['maintenance']['title'],
            'maintenance_message' => (string)$settings['maintenance']['message'],
            'maintenance_starts_at' => (string)$settings['maintenance']['starts_at'],
            'maintenance_expected_at' => (string)$settings['maintenance']['expected_at'],
            'maintenance_retry_after_seconds' => (int)$settings['maintenance']['retry_after_seconds'],
            'update_required' => (bool)$version_status['update_required'],
            'update_available' => (bool)$version_status['update_available'],
            'store_url' => (string)$platform_config['store_url'],
            'release_notes' => (string)$platform_config['release_notes'],
            'platform_enabled' => Settings::platform_enabled($platform),
            'platforms' => [
                'android' => self::public_platform_config('android', $settings['platforms']['android']),
                'ios' => self::public_platform_config('ios', $settings['platforms']['ios']),
            ],
            'maintenance' => [
                'enabled' => $maintenance,
                'global_enabled' => (bool)$settings['maintenance']['enabled'],
                'configured' => (bool)$maintenance_status['configured'],
                'scheduled' => (bool)$maintenance_status['scheduled'],
                'expired' => (bool)$maintenance_status['expired'],
                'title' => (string)$settings['maintenance']['title'],
                'message' => (string)$settings['maintenance']['message'],
                'starts_at' => (string)$settings['maintenance']['starts_at'],
                'expected_at' => (string)$settings['maintenance']['expected_at'],
                'auto_disable' => (bool)$settings['maintenance']['auto_disable'],
                'retry_after_seconds' => (int)$settings['maintenance']['retry_after_seconds'],
                'platforms' => [
                    'android' => (bool)$settings['maintenance']['android'],
                    'ios' => (bool)$settings['maintenance']['ios'],
                    'web' => (bool)$settings['maintenance']['web'],
                ],
            ],
            'features' => array_merge([
                'dynamic_forms' => $business_features['reporting'] ?? true,
                'media_uploads' => $business_features['media_upload'] ?? true,
                'idempotent_mutations' => true,
                'offline_queue_contract' => true,
                'hybrid_webview_portals' => true,
                'webview_sso' => true,
                'native_webview_experience' => true,
                'native_pwa_prompt_suppression' => true,
                'resume_safe_webview' => true,
                'multiple_webview_uploads' => (bool)$bridge['allow_multiple_uploads'],
                'native_image_viewer' => true,
                'role_context_sync' => true,
                'canonical_state_contracts' => true,
                'audit_correlation' => true,
                'offline_operational_cache' => true,
                'offline_form_drafts' => true,
                'offline_sync_queue' => true,
                'offline_batch_sync' => true,
                'offline_incremental_delta' => true,
                'offline_conflict_management' => true,
                'offline_sync_center' => true,
                'offline_cross_tab_lock' => true,
                'device_policy_management' => true,
                'remote_cache_reset' => true,
                'remote_session_revocation' => true,
                'mobile_subscription_experience' => true,
                'billing_status_screens' => true,
                'billing_return_bridge' => true,
                'billing_context_refresh' => true,
                'self_route_planning' => true,
                'live_route_optimization' => true,
                'route_optimization_version' => \RoutesPro\Services\RouteOptimizationService::VERSION,
                'route_planning_workspace' => true,
                'live_route_adjustments' => true,
                'protected_operational_stops' => true,
                'route_workspace_version' => \RoutesPro\Services\RouteWorkspaceService::VERSION,
                'route_workspace_history_limit' => \RoutesPro\Services\RouteWorkspaceService::HISTORY_LIMIT,
                'route_google_maps_links' => true,
                'fresh_media_per_report' => true,
            ], $business_features),
            'portal_features' => $portal_features,
            'supported_question_types' => ['text','textarea','number','currency','percent','date','datetime','checkbox','select','radio','multi_select','checkbox_group','visual_choice','image_upload','categorized_image_upload','file_upload','product_matrix','order_suggestion','share_space_matrix','briefing_comparison','briefing_block','briefing_intro'],
            'theme' => $theme,
            'theme_context' => ['client_id' => $configClientId, 'project_id' => $configProjectId],
            'support' => [
                'email' => (string)$settings['general']['support_email'],
                'support_url' => (string)$settings['general']['support_url'],
                'privacy_url' => (string)$settings['general']['privacy_url'],
                'terms_url' => (string)$settings['general']['terms_url'],
            ],
            'authentication' => [
                'access_header' => 'X-FieldFlow-Mobile-Token',
                'legacy_bearer_supported' => true,
                'access_ttl_seconds' => Settings::access_ttl(),
                'refresh_ttl_seconds' => Settings::refresh_ttl(),
            ],
            'device_management' => [
                'policy_endpoint' => rest_url(self::NS . '/device/policy'),
                'supports_trusted_device' => true,
                'supports_offline_disable' => true,
                'supports_cache_generation' => true,
            ],
            'webview' => [
                'enabled' => Settings::bridge_enabled(),
                'ticket_endpoint' => rest_url(self::NS . '/auth/web-session'),
                'revoke_endpoint' => rest_url(self::NS . '/auth/web-session/revoke'),
                'ticket_ttl_seconds' => Settings::ticket_ttl(),
                'web_session_ttl_seconds' => Settings::web_ttl(),
                'native_shell_version' => WebViewBridge::NATIVE_SHELL_VERSION,
                'pwa_prompt_suppressed' => true,
                'resume_safe' => true,
                'role_context_sync_seconds' => Settings::context_sync_seconds(),
                'javascript_enabled' => (bool)$bridge['javascript_enabled'],
                'auto_renew' => (bool)$bridge['auto_renew'],
                'renew_window_seconds' => Settings::renew_window(),
                'heartbeat_seconds' => Settings::heartbeat_seconds(),
                'allow_downloads' => (bool)$bridge['allow_downloads'],
                'allow_external_links' => (bool)$bridge['allow_external_links'],
                'open_external_in_system_browser' => (bool)$bridge['open_external_in_system_browser'],
                'allow_file_uploads' => (bool)$bridge['allow_file_uploads'],
                'allow_multiple_uploads' => (bool)$bridge['allow_multiple_uploads'],
                'allow_camera' => (bool)$bridge['allow_camera'],
                'allow_gallery' => (bool)$bridge['allow_gallery'],
                'allow_location' => (bool)$bridge['allow_location'],
                'network_status_banner' => (bool)$bridge['network_status_banner'],
                'native_image_viewer' => (bool)$bridge['native_image_viewer'],
                'enable_zoom' => (bool)$bridge['enable_zoom'],
                'show_portal_header' => (bool)$bridge['show_portal_header'],
                'show_logout' => (bool)$bridge['show_logout'],
                'allowed_domains' => Settings::allowed_domains(),
                'portal_paths' => [
                    'operational' => home_url('/fieldflow-mobile/portal/operational/'),
                    'client' => home_url('/fieldflow-mobile/portal/client/'),
                    'billing' => home_url('/fieldflow-mobile/portal/billing/'),
                    'subscriptions' => home_url('/fieldflow-mobile/portal/subscriptions/'),
                ],
            ],
            'endpoints' => [
                'namespace' => self::NS,
                'core_state_namespace' => 'fieldflow/v1',
                'route_state_template' => rest_url('fieldflow/v1/core/state/route/{id}'),
                'visit_state_template' => rest_url('fieldflow/v1/core/state/visit/{id}'),
                'submission_state_template' => rest_url('fieldflow/v1/core/state/submission/{id}'),
                'offline_ping' => rest_url(self::NS . '/offline/ping'),
                'offline_bootstrap' => rest_url(self::NS . '/offline/bootstrap'),
                'offline_delta' => rest_url(self::NS . '/offline/delta'),
                'offline_sync' => rest_url(self::NS . '/offline/sync'),
                'offline_status' => rest_url(self::NS . '/offline/status'),
                'billing_experience' => rest_url(self::NS . '/billing/experience'),
                'assigned_stores' => rest_url(self::NS . '/assigned-stores'),
                'self_route_plans' => rest_url(self::NS . '/self-route-plans'),
                'route_optimization_preview' => rest_url(self::NS . '/route-optimization/preview'),
                'route_optimization_apply_template' => rest_url(self::NS . '/routes/{id}/optimize'),
                'route_workspace_save_template' => rest_url(self::NS . '/routes/{id}/workspace'),
                'route_workspace_restore_template' => rest_url(self::NS . '/routes/{id}/workspace/restore'),
                'route_stops_bulk_remove_template' => rest_url(self::NS . '/routes/{id}/stops/bulk-remove'),
                'contract' => rest_url(self::NS . '/contract'),
                'client_diagnostics' => rest_url(self::NS . '/diagnostics/client-event'),
            ],
        ], 200);
    }

    private static function public_platform_config(string $platform, array $config): array {
        return [
            'enabled' => Settings::platform_enabled($platform),
            'minimum_app_version' => (string)$config['minimum_version'],
            'latest_app_version' => (string)$config['latest_version'],
            'build_number' => (string)$config['build_number'],
            'store_url' => (string)$config['store_url'],
            'force_update' => (bool)$config['force_update'],
            'package_id' => (string)$config['package_id'],
            'release_notes' => (string)$config['release_notes'],
        ];
    }

    public function offline_ping(WP_REST_Request $request) {
        return OfflineSyncService::ping($request);
    }

    public function offline_bootstrap(WP_REST_Request $request) {
        return OfflineSyncService::bootstrap($request);
    }

    public function offline_delta(WP_REST_Request $request) {
        return OfflineSyncService::delta($request);
    }

    public function offline_sync(WP_REST_Request $request) {
        return OfflineSyncService::sync($request);
    }

    public function offline_status(WP_REST_Request $request) {
        return OfflineSyncService::status($request);
    }

    public function offline_trust_lease(WP_REST_Request $request) {
        return OfflineTrustLease::restIssue($request);
    }

    public function billing_experience(WP_REST_Request $request) {
        $userId = get_current_user_id();
        $clientId = absint($request->get_param('client_id'));
        $user = self::build_user_payload($userId);
        $experience = $clientId > 0
            ? SubscriptionExperience::build($userId, $clientId)
            : (array)($user['subscription_experience'] ?? []);
        return new WP_REST_Response([
            'experience' => $experience,
            'user' => $user,
        ], 200);
    }

    public function my_routes(WP_REST_Request $request) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;
        $filters = [
            'mine' => true,
            'client_id' => absint($request->get_param('client_id') ?: 0),
            'project_id' => absint($request->get_param('project_id') ?: 0),
            'location_id' => absint($request->get_param('location_id') ?: 0),
            'limit' => min(500, max(1, absint($request->get_param('limit') ?: 200))),
        ];
        $from = sanitize_text_field((string)($request->get_param('date_from') ?: ''));
        $to = sanitize_text_field((string)($request->get_param('date_to') ?: ''));
        $date = sanitize_text_field((string)($request->get_param('date') ?: ''));
        if ($from || $to) { $filters['date_from'] = $from; $filters['date_to'] = $to; }
        else $filters['date'] = $date ?: current_time('Y-m-d');
        $routes = RouteAccessRepository::listRoutes($filters);
        if (is_wp_error($routes)) return $routes;
        return new WP_REST_Response(['routes' => $routes], 200);
    }

    public function assigned_stores(WP_REST_Request $request) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;
        $proxy = new WP_REST_Request('GET', '/routespro/v1/campaign-pdvs');
        foreach (['client_id','project_id','q','page','per_page'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') $proxy->set_param($key, $value);
        }
        $proxy->set_param('owner_user_id', get_current_user_id());
        $proxy->set_param('planner_assigned', 1);
        return (new \RoutesPro\Rest\RoutesController())->list_campaign_pdvs($proxy);
    }

    public function preview_route_optimization(WP_REST_Request $request) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;
        $result = \RoutesPro\Services\RouteOptimizationService::preview($request->get_json_params() ?: [], get_current_user_id());
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function apply_route_optimization(WP_REST_Request $request) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;
        $result = \RoutesPro\Services\RouteOptimizationService::applyToRoute(absint($request['id']), $request->get_json_params() ?: [], get_current_user_id());
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function create_self_route_plan(WP_REST_Request $request) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;

        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $body = $request->get_json_params() ?: [];
        $client_id = absint($body['client_id'] ?? 0);
        $project_id = absint($body['project_id'] ?? 0);
        $period = sanitize_key((string)($body['period'] ?? 'day'));
        $days_input = is_array($body['days'] ?? null) ? array_values((array)$body['days']) : [];
        $user_id = get_current_user_id();

        if (!$client_id || !$project_id || !$days_input) {
            return new WP_Error('fieldflow_mobile_plan_invalid', 'client_id, project_id e days são obrigatórios.', ['status' => 400]);
        }
        if (!in_array($period, ['day', 'week', 'month'], true)) {
            return new WP_Error('fieldflow_mobile_plan_period_invalid', 'O período do plano não é válido.', ['status' => 400]);
        }

        $scope = Permissions::assert_scope_or_error($client_id, $project_id);
        if (is_wp_error($scope)) return $scope;
        $project_client = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM {$px}projects WHERE id=%d LIMIT 1",
            $project_id
        ));
        if ($project_client !== $client_id) {
            return new WP_Error('fieldflow_mobile_plan_scope_mismatch', 'A campanha não pertence ao cliente selecionado.', ['status' => 400]);
        }

        $legacy_start = is_array($body['start_point'] ?? null) ? (array)$body['start_point'] : [];
        $legacy_end = is_array($body['end_point'] ?? null) ? (array)$body['end_point'] : [];
        $seen_dates = [];
        $all_location_ids_lookup = [];
        $days = [];

        foreach ($days_input as $raw_day) {
            if (!is_array($raw_day)) continue;
            $date = sanitize_text_field((string)($raw_day['date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return new WP_Error('fieldflow_mobile_route_date_invalid', 'Uma das datas do plano não é válida.', ['status' => 400, 'date' => $date]);
            }
            [$year, $month, $month_day] = array_map('intval', explode('-', $date));
            if (!checkdate($month, $month_day, $year)) {
                return new WP_Error('fieldflow_mobile_route_date_invalid', 'Uma das datas do plano não é válida.', ['status' => 400, 'date' => $date]);
            }
            if (isset($seen_dates[$date])) {
                return new WP_Error('fieldflow_mobile_route_date_duplicate', 'O plano contém a mesma data mais do que uma vez.', ['status' => 400, 'date' => $date]);
            }
            $seen_dates[$date] = true;

            $location_ids = array_values(array_unique(array_filter(array_map('absint', (array)($raw_day['location_ids'] ?? [])))));
            if (count($location_ids) > 8) {
                return new WP_Error('fieldflow_mobile_route_daily_limit', 'Cada rota pode ter no máximo 8 lojas.', ['status' => 400, 'date' => $date, 'limit' => 8]);
            }
            foreach ($location_ids as $location_id) {
                $all_location_ids_lookup[$location_id] = true;
            }

            $days[] = [
                'date' => $date,
                'route_id' => absint($raw_day['route_id'] ?? 0),
                'location_ids' => $location_ids,
                'start_point' => is_array($raw_day['start_point'] ?? null) ? (array)$raw_day['start_point'] : $legacy_start,
                'end_point' => is_array($raw_day['end_point'] ?? null) ? (array)$raw_day['end_point'] : $legacy_end,
                'return_to_start' => !empty($raw_day['return_to_start']),
                'auto_optimize' => !array_key_exists('auto_optimize', $raw_day) || !empty($raw_day['auto_optimize']),
                'manual_order_locked' => !empty($raw_day['manual_order_locked']),
            ];
        }

        if (!$days) return new WP_Error('fieldflow_mobile_plan_empty', 'Não existem dias válidos para guardar.', ['status' => 400]);
        if (count($days) > 31) {
            return new WP_Error('fieldflow_mobile_plan_too_large', 'O plano não pode ultrapassar 31 dias.', ['status' => 400, 'limit' => 31]);
        }

        $all_location_ids = array_values(array_map('intval', array_keys($all_location_ids_lookup)));
        // 9BB predecessor marker: assigned_to=%d AND is_active=1 AND status='active'
        // 9BC cross-day repeat contract: a location may appear on different planning dates; uniqueness remains per day only.
        $assigned_lookup = [];
        if ($all_location_ids) {
            $placeholders = implode(',', array_fill(0, count($all_location_ids), '%d'));
            $assigned_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT location_id
                 FROM {$px}campaign_locations
                 WHERE project_id=%d AND assigned_to=%d
                   AND location_id IN ({$placeholders})",
                $project_id,
                $user_id,
                ...$all_location_ids
            )) ?: [];
            $assigned_lookup = array_fill_keys(array_map('intval', $assigned_ids), true);
        }

        $prepared = [];
        $new_route_count = 0;
        $planning_warnings = [];
        foreach ($days as $day) {
            $route = [];
            if ($day['route_id'] > 0) {
                $route = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$px}routes
                     WHERE id=%d AND owner_user_id=%d AND project_id=%d
                     LIMIT 1",
                    $day['route_id'],
                    $user_id,
                    $project_id
                ), ARRAY_A) ?: [];
                if (!$route) {
                    return new WP_Error('fieldflow_mobile_route_not_editable', 'A rota indicada não existe ou não pertence ao utilizador.', ['status' => 403, 'route_id' => $day['route_id']]);
                }
                if ((string)($route['date'] ?? '') !== $day['date']) {
                    return new WP_Error('fieldflow_mobile_route_date_mismatch', 'A rota indicada não pertence ao dia selecionado.', ['status' => 400, 'route_id' => $day['route_id'], 'date' => $day['date']]);
                }
            } else {
                $route = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$px}routes
                     WHERE owner_user_id=%d AND project_id=%d AND date=%s
                       AND status NOT IN ('canceled','cancelled')
                     ORDER BY id ASC LIMIT 1",
                    $user_id,
                    $project_id,
                    $day['date']
                ), ARRAY_A) ?: [];
            }

            $route_id = absint($route['id'] ?? 0);
            if ($route_id > 0) {
                $safety = \RoutesPro\Services\RouteOptimizationService::routeSafety($route_id, $route);
                if (empty($safety['can_plan'])) {
                    return new WP_Error('fieldflow_mobile_route_locked', 'A rota já não permite ajustes de planeamento.', ['status' => 409, 'route_id' => $route_id, 'reason' => $safety['reason'] ?? 'route_locked']);
                }
            }
            $existing_stops = $route_id > 0 ? ($wpdb->get_results($wpdb->prepare(
                "SELECT id,location_id,seq FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC,id ASC",
                $route_id
            ), ARRAY_A) ?: []) : [];
            $existing_location_lookup = [];
            $existing_stop_by_location = [];
            foreach ($existing_stops as $stop) {
                $location_id = absint($stop['location_id'] ?? 0);
                if (!$location_id) continue;
                $existing_location_lookup[$location_id] = true;
                if (!isset($existing_stop_by_location[$location_id])) {
                    $existing_stop_by_location[$location_id] = absint($stop['id'] ?? 0);
                }
            }

            $invalid_locations = [];
            foreach ($day['location_ids'] as $location_id) {
                if (empty($assigned_lookup[$location_id]) && empty($existing_location_lookup[$location_id])) {
                    $invalid_locations[] = $location_id;
                }
            }
            if ($invalid_locations) {
                return new WP_Error('fieldflow_mobile_store_not_assigned', 'O plano contém lojas que não estão atribuídas ao utilizador nem já incluídas nesta rota.', ['status' => 403, 'location_ids' => $invalid_locations, 'date' => $day['date']]);
            }

            $meta = [];
            if (!empty($route['meta_json'])) {
                $decoded = json_decode((string)$route['meta_json'], true);
                if (is_array($decoded)) $meta = $decoded;
            }

            $start = is_array($day['start_point'] ?? null) ? (array)$day['start_point'] : [];
            $end = is_array($day['end_point'] ?? null) ? (array)$day['end_point'] : [];
            $first_stop = [];
            $last_stop = [];
            if ($day['location_ids']) {
                $edge_ids = array_values(array_unique([
                    (int)$day['location_ids'][0],
                    (int)$day['location_ids'][count($day['location_ids']) - 1],
                ]));
                $edge_placeholders = implode(',', array_fill(0, count($edge_ids), '%d'));
                $edge_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id,name,address,lat,lng FROM {$px}locations WHERE id IN ({$edge_placeholders})",
                    ...$edge_ids
                ), ARRAY_A) ?: [];
                $edge_lookup = [];
                foreach ($edge_rows as $edge) $edge_lookup[(int)$edge['id']] = $edge;
                $first_stop = (array)($edge_lookup[(int)$day['location_ids'][0]] ?? []);
                $last_location_id = (int)$day['location_ids'][count($day['location_ids']) - 1];
                $last_stop = (array)($edge_lookup[$last_location_id] ?? []);
            }

            if (class_exists('\\RoutesPro\\Services\\RouteEndpointProfileService')) {
                $resolved_endpoints = \RoutesPro\Services\RouteEndpointProfileService::resolveForRoute(
                    $client_id,
                    $project_id,
                    $user_id,
                    $meta,
                    $start,
                    $end,
                    $first_stop,
                    $last_stop,
                    ['return_to_start'=>!empty($day['return_to_start'])]
                );
                $start = (array)($resolved_endpoints['start_point'] ?? []);
                $end = (array)($resolved_endpoints['end_point'] ?? []);
                $day['return_to_start'] = !empty($resolved_endpoints['return_to_start']);
                $meta['route_endpoint_profile_contract'] = \RoutesPro\Services\RouteEndpointProfileService::CONTRACT;
                $meta['route_endpoint_start_source'] = sanitize_key((string)($resolved_endpoints['start_source'] ?? 'missing'));
                $meta['route_endpoint_end_source'] = sanitize_key((string)($resolved_endpoints['end_source'] ?? 'missing'));
            } else {
                if (\RoutesPro\Services\GoogleMapsUrlService::pointValue($start) === '' && is_array($meta['start_point'] ?? null)) $start = (array)$meta['start_point'];
                if (\RoutesPro\Services\GoogleMapsUrlService::pointValue($end) === '' && is_array($meta['end_point'] ?? null)) $end = (array)$meta['end_point'];
                if ($day['return_to_start'] && \RoutesPro\Services\GoogleMapsUrlService::pointValue($start) !== '') $end = $start;
            }

            if ($day['location_ids'] && (\RoutesPro\Services\GoogleMapsUrlService::pointValue($start) === '' || \RoutesPro\Services\GoogleMapsUrlService::pointValue($end) === '')) {
                return new WP_Error('fieldflow_mobile_route_endpoints_required', 'Define a base de rota do operativo/campanha ou indica explicitamente partida e chegada.', ['status' => 400, 'date' => $day['date']]);
            }
            if (!$route_id && !$day['location_ids']) continue;

            $optimization = null;
            if ($day['location_ids'] && $day['auto_optimize'] && !$day['manual_order_locked']) {
                $optimization = \RoutesPro\Services\RouteOptimizationService::preview([
                    'route_id' => $route_id,
                    'client_id' => $client_id,
                    'project_id' => $project_id,
                    'location_ids' => $day['location_ids'],
                    'start_point' => $start,
                    'end_point' => $end,
                    'return_to_start' => $day['return_to_start'] ? 1 : 0,
                    'manual_order_locked' => 0,
                    'mode' => 'hybrid',
                    'planner_mode' => 1,
                    'planning_source' => 'self_planner',
                    'date' => $day['date'],
                ], $user_id);
                if (is_wp_error($optimization)) return $optimization;
                if (empty($optimization['locked']) && !empty($optimization['ordered_location_ids'])) {
                    $day['location_ids'] = array_values(array_map('absint', (array)$optimization['ordered_location_ids']));
                }
            } elseif ($day['location_ids']) {
                $optimization = \RoutesPro\Services\RouteOptimizationService::preview([
                    'route_id' => $route_id,
                    'client_id' => $client_id,
                    'project_id' => $project_id,
                    'location_ids' => $day['location_ids'],
                    'start_point' => $start,
                    'end_point' => $end,
                    'return_to_start' => $day['return_to_start'] ? 1 : 0,
                    'manual_order_locked' => 1,
                    'mode' => 'internal',
                    'planner_mode' => 1,
                    'planning_source' => 'self_planner',
                    'date' => $day['date'],
                ], $user_id);
                if (is_wp_error($optimization)) return $optimization;
            }
            if (is_array($optimization) && !empty($optimization['planning_warnings'])) {
                foreach ((array)$optimization['planning_warnings'] as $warning) {
                    $warning = sanitize_text_field((string)$warning);
                    if ($warning !== '') $planning_warnings[$warning] = $warning;
                }
            }

            $planned_stops = [];
            foreach ($day['location_ids'] as $seq => $location_id) {
                $planned_stops[] = [
                    'route_stop_id' => absint($existing_stop_by_location[$location_id] ?? 0),
                    'location_id' => $location_id,
                    'seq' => $seq,
                ];
            }

            if (!$route_id) $new_route_count++;
            $prepared[] = [
                'date' => $day['date'],
                'route' => $route,
                'route_id' => $route_id,
                'location_ids' => $day['location_ids'],
                'planned_stops' => $planned_stops,
                'start_point' => $start,
                'end_point' => $end,
                'return_to_start' => $day['return_to_start'],
                'auto_optimize' => $day['auto_optimize'],
                'manual_order_locked' => $day['manual_order_locked'],
                'optimization' => is_array($optimization) ? $optimization : [],
                'meta' => $meta,
            ];
        }

        if (!$prepared) return new WP_Error('fieldflow_mobile_plan_empty', 'Não existem rotas com lojas para guardar.', ['status' => 400]);
        if ($new_route_count > 0 && class_exists('\\RoutesPro\\SaaS\\UsageService')) {
            $quota = \RoutesPro\SaaS\UsageService::canConsume($client_id, 'routes_monthly', $new_route_count);
            if (empty($quota['allowed'])) return \RoutesPro\SaaS\UsageService::quotaError($client_id, 'routes_monthly');
        }

        $controller = new \RoutesPro\Rest\RoutesController();
        $created = [];
        $updated = [];
        $transaction_started = $wpdb->query('START TRANSACTION') !== false;
        if (!$transaction_started) {
            return new WP_Error('fieldflow_mobile_plan_transaction_failed', 'Não foi possível iniciar a gravação segura do plano.', ['status' => 500]);
        }

        try {
        foreach ($prepared as $item) {
            $route_id = (int)$item['route_id'];
            $is_new = $route_id <= 0;

            if ($is_new) {
                $route_request = new WP_REST_Request('POST', '/routespro/v1/routes');
                $route_request->set_header('content-type', 'application/json');
                $route_request->set_body(wp_json_encode([
                    'client_id' => $client_id,
                    'project_id' => $project_id,
                    'date' => $item['date'],
                    'status' => 'planned',
                    'meta' => [
                        'self_planned' => 1,
                        'planning_period' => $period,
                        'planning_source' => 'flutterflow_mobile',
                        'start_point' => $item['start_point'],
                        'end_point' => $item['end_point'],
                        'return_to_start' => $item['return_to_start'] ? 1 : 0,
                        'store_count' => count($item['location_ids']),
                        'manual_order_locked' => $item['manual_order_locked'] ? 1 : 0,
                        'route_metrics_stale' => 1,
                        'routing_refresh_required' => 1,
                    ],
                ]));
                $route_response = $controller->create_route($route_request);
                if (is_wp_error($route_response)) {
                    $wpdb->query('ROLLBACK');
                    return $route_response;
                }
                $route_data = $route_response instanceof WP_REST_Response ? $route_response->get_data() : (array)$route_response;
                $route_id = absint($route_data['id'] ?? 0);
                if (!$route_id) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('fieldflow_mobile_route_create_failed', 'A rota foi criada sem identificador.', ['status' => 500, 'created' => $created, 'updated' => $updated]);
                }
            } else {
                $meta = $item['meta'];
                $meta['planning_period'] = $period;
                $meta['planning_source'] = 'merchandiser_front_edit';
                $meta['last_self_planned_edit_at'] = current_time('mysql');
                $meta['last_self_planned_edit_by'] = $user_id;
                $meta['start_point'] = $item['start_point'];
                $meta['end_point'] = $item['end_point'];
                $meta['return_to_start'] = $item['return_to_start'] ? 1 : 0;
                $meta['store_count'] = count($item['location_ids']);
                $meta['manual_order_locked'] = $item['manual_order_locked'] ? 1 : 0;
                if (!empty($item['optimization'])) {
                    $meta = \RoutesPro\Services\RouteOptimizationService::mergeOptimizationMeta($meta, $item['optimization'], $item['manual_order_locked']);
                } else {
                    $meta['route_metrics_stale'] = 1;
                    $meta['routing_refresh_required'] = 1;
                    unset($meta['route_metrics']);
                }
                $ok = $wpdb->update(
                    $px . 'routes',
                    [
                        'status' => count($item['location_ids']) ? 'planned' : 'draft',
                        'meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                    ['id' => $route_id],
                    ['%s', '%s'],
                    ['%d']
                );
                if ($ok === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('fieldflow_mobile_route_update_failed', 'Não foi possível atualizar a rota existente.', ['status' => 500, 'route_id' => $route_id]);
                }
            }

            if ($is_new && !empty($item['optimization'])) {
                $created_meta = [
                    'self_planned' => 1,
                    'planning_period' => $period,
                    'planning_source' => 'flutterflow_mobile',
                    'start_point' => $item['start_point'],
                    'end_point' => $item['end_point'],
                    'return_to_start' => $item['return_to_start'] ? 1 : 0,
                    'store_count' => count($item['location_ids']),
                ];
                $created_meta = \RoutesPro\Services\RouteOptimizationService::mergeOptimizationMeta($created_meta, $item['optimization'], $item['manual_order_locked']);
                $meta_ok = $wpdb->update($px . 'routes', [
                    'meta_json' => wp_json_encode($created_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], ['id' => $route_id], ['%s'], ['%d']);
                if ($meta_ok === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('fieldflow_mobile_route_optimization_meta_failed', 'A rota foi criada, mas não foi possível guardar a otimização.', ['status' => 500, 'route_id' => $route_id]);
                }
            }

            $sync = \RoutesPro\Services\RoutePlanSyncService::sync($route_id, $item['planned_stops']);
            if (is_wp_error($sync)) {
                $wpdb->query('ROLLBACK');
                return $sync;
            }
            $progress = \RoutesPro\Services\RouteProgressService::sync($route_id, true);
            if (is_wp_error($progress)) {
                $wpdb->query('ROLLBACK');
                return $progress;
            }
            $payload = [
                'route_id' => $route_id,
                'date' => $item['date'],
                'stops_count' => count($item['location_ids']),
                'sync' => $sync,
                'optimization' => $item['optimization'],
                'google_maps_url' => \RoutesPro\Services\GoogleMapsUrlService::forRouteId($route_id),
            ];
            if ($is_new) $created[] = $payload;
            else $updated[] = $payload;
        }
        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('fieldflow_mobile_plan_commit_failed', 'O plano não pôde ser confirmado. Nenhuma alteração foi aplicada.', ['status' => 500]);
        }
        \RoutesPro\Services\RouteOptimizationService::invalidateUserRouteCache($user_id);
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Falha na gravação atómica do plano de rotas.', $error, ['context_key' => 'self_route_plan']);
            }
            return new WP_Error('fieldflow_mobile_plan_atomic_failure', 'O plano não foi guardado. Nenhuma alteração ficou aplicada.', ['status' => 500]);
        }

        $count = count($created) + count($updated);
        return new WP_REST_Response([
            'created' => $created,
            'updated' => $updated,
            'created_count' => count($created),
            'updated_count' => count($updated),
            'count' => $count,
            'warnings' => array_values($planning_warnings),
        ], $updated ? 200 : 201);
    }

    public function route(WP_REST_Request $request) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;
        $route_id = absint($request['id']);
        if (class_exists('\\RoutesPro\\Services\\RouteProgressService')) {
            $progress = \RoutesPro\Services\RouteProgressService::sync($route_id);
            if (is_wp_error($progress)) return $progress;
        }
        $route = $this->route_row($route_id);
        if (!$route) return new WP_Error('fieldflow_mobile_route_not_found', 'Route not found.', ['status' => 404]);
        $route['stops'] = $this->stop_rows((int)$route['id']);
        $meta = !empty($route['meta_json']) ? json_decode((string)$route['meta_json'], true) : [];
        if (!is_array($meta)) $meta = [];
        $route = array_merge($route, \RoutesPro\Services\GoogleMapsUrlService::routePayload($meta, $route['stops']), ['meta' => $meta]);
        return new WP_REST_Response(['route' => $route], 200);
    }

    public function route_stops(WP_REST_Request $request) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;
        return new WP_REST_Response(['stops' => $this->stop_rows(absint($request['id']))], 200);
    }

    public function stop(WP_REST_Request $request) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;
        $row = $this->stop_row(absint($request['id']));
        return $row ? new WP_REST_Response(['stop' => $row], 200) : new WP_Error('fieldflow_mobile_stop_not_found', 'Stop not found.', ['status' => 404]);
    }

    public function stop_form(WP_REST_Request $request) {
        if (($gate = $this->feature_required('reporting')) !== true) return $gate;
        $payload = FormPayloadService::for_stop(absint($request['id']));
        return is_wp_error($payload) ? $payload : new WP_REST_Response($payload, 200);
    }

    public function stop_event(WP_REST_Request $request, string $event) {
        if (($gate = $this->feature_required('routes')) !== true) return $gate;
        $body = $request->get_json_params() ?: [];
        $mutation_id = (string)($body['mutation_id'] ?? $request->get_header('x-fieldflow-mutation-id'));
        return MutationStore::execute($mutation_id, 'stop_' . $event, function() use ($request, $body, $event) {
            $proxy = new WP_REST_Request('POST', '/routespro/v1/events');
            $proxy->set_body_params([]);
            $proxy->set_header('content-type', 'application/json');
            $proxy->set_body(wp_json_encode([
                'route_stop_id' => absint($request['id']),
                'event_type' => $event,
                'payload' => is_array($body['payload'] ?? null) ? $body['payload'] : $body,
            ]));
            return (new EventsController())->create($proxy);
        });
    }

    public function submit_form(WP_REST_Request $request) {
        if (($gate = $this->feature_required('reporting')) !== true) return $gate;
        $body = $request->get_json_params() ?: [];
        $mutation_id = (string)($body['mutation_id'] ?? $request->get_header('x-fieldflow-mutation-id'));
        $body['mutation_id'] = $mutation_id;
        return MutationStore::execute($mutation_id, 'form_submission', function() use ($body) {
            $result = SubmissionService::submit($body);
            return is_wp_error($result) ? $result : new WP_REST_Response($result, !empty($result['updated_existing']) ? 200 : 201);
        });
    }

    public function submission(WP_REST_Request $request) {
        if (($gate = $this->feature_required('reporting')) !== true) return $gate;
        global $wpdb;
        $id = absint($request['id']);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'routespro_form_submissions WHERE id=%d LIMIT 1', $id), ARRAY_A);
        if (!$row) return new WP_Error('fieldflow_mobile_submission_not_found', 'Submission not found.', ['status' => 404]);
        $scope_check = Permissions::assert_scope_or_error((int)($row['client_id'] ?? 0), (int)($row['project_id'] ?? 0));
        $can = (int)$row['user_id'] === get_current_user_id()
            || Permissions::is_manager()
            || (!empty($row['route_id']) && Permissions::can_access_route((int)$row['route_id']))
            || !is_wp_error($scope_check);
        if (!$can) return new WP_Error('fieldflow_mobile_forbidden', 'Submission access denied.', ['status' => 403]);
        $answers = $wpdb->get_results($wpdb->prepare('SELECT question_key,question_label,value_text,value_number,value_json FROM ' . $wpdb->prefix . 'routespro_form_submission_answers WHERE submission_id=%d ORDER BY id ASC', $id), ARRAY_A) ?: [];
        foreach ($answers as &$answer) {
            if ((string)$answer['value_json'] !== '') $answer['value'] = json_decode((string)$answer['value_json'], true);
            elseif ($answer['value_number'] !== null && $answer['value_number'] !== '') $answer['value'] = (float)$answer['value_number'];
            else $answer['value'] = (string)$answer['value_text'];
        }
        unset($answer);
        return new WP_REST_Response(['submission' => $row, 'answers' => $answers], 200);
    }

    public function media(WP_REST_Request $request) {
        if (($gate = $this->feature_required('media_upload')) !== true) return $gate;
        $mutation_id = (string)($request->get_param('mutation_id') ?: $request->get_header('x-fieldflow-mutation-id'));
        return MutationStore::execute($mutation_id, 'media_upload', function() use ($request, $mutation_id) {
            if (empty($_FILES['file']['tmp_name'])) return new WP_Error('fieldflow_mobile_file_required', 'A multipart file field named file is required.', ['status' => 400]);
            if (!empty($_FILES['file']['size']) && (int)$_FILES['file']['size'] > 20 * MB_IN_BYTES) {
                return new WP_Error('fieldflow_mobile_file_too_large', 'The file exceeds the 20 MB mobile upload limit.', ['status' => 413]);
            }
            $attachment_id = \RoutesPro\Security\ProtectedMedia::upload('file', [
                'route_id'=>absint($request->get_param('route_id')),
                'route_stop_id'=>absint($request->get_param('route_stop_id')),
                'client_id'=>absint($request->get_param('client_id')),
                'project_id'=>absint($request->get_param('project_id')),
            ]);
            if (is_wp_error($attachment_id)) return $attachment_id;
            update_post_meta((int)$attachment_id, '_fieldflow_mobile_user_id', get_current_user_id());
            update_post_meta((int)$attachment_id, '_fieldflow_mobile_mutation_id', sanitize_text_field($mutation_id));
            update_post_meta((int)$attachment_id, '_fieldflow_mobile_question_key', sanitize_key((string)$request->get_param('question_key')));
            update_post_meta((int)$attachment_id, '_fieldflow_mobile_category', sanitize_text_field((string)$request->get_param('category')));
            if (class_exists('\\RoutesPro\\Security\\MediaRegistry')) {
                \RoutesPro\Security\MediaRegistry::registerAttachment((int)$attachment_id, [
                    'owner_user_id' => get_current_user_id(),
                    'question_key' => sanitize_key((string)$request->get_param('question_key')),
                    'category' => sanitize_text_field((string)$request->get_param('category')),
                    'source' => 'mobile_upload',
                ]);
            }
            return new WP_REST_Response([
                'attachment_id' => (int)$attachment_id,
                'url' => wp_get_attachment_url((int)$attachment_id),
                'mime_type' => get_post_mime_type((int)$attachment_id),
                'filename' => sanitize_file_name((string)($_FILES['file']['name'] ?? 'evidence')),
            ], 201);
        });
    }

    public function briefings(WP_REST_Request $request) {
        if (($gate = $this->feature_required('briefings')) !== true) return $gate;
        return (new \RoutesPro\Briefings\BriefingsController())->mine($request);
    }

    public function briefing_view(WP_REST_Request $request) {
        if (($gate = $this->feature_required('briefings')) !== true) return $gate;
        $body = $request->get_json_params() ?: [];
        $mutation_id = (string)($body['mutation_id'] ?? $request->get_header('x-fieldflow-mutation-id'));
        return MutationStore::execute($mutation_id, 'briefing_view', function() use ($request) {
            return (new \RoutesPro\Briefings\BriefingsController())->view($request);
        });
    }

    public function briefing_ack(WP_REST_Request $request) {
        if (($gate = $this->feature_required('briefings')) !== true) return $gate;
        $body = $request->get_json_params() ?: [];
        $mutation_id = (string)($body['mutation_id'] ?? $request->get_header('x-fieldflow-mutation-id'));
        return MutationStore::execute($mutation_id, 'briefing_ack', function() use ($request) {
            return (new \RoutesPro\Briefings\BriefingsController())->ack($request);
        });
    }

    public function implementations(WP_REST_Request $request) {
        if (($gate = $this->feature_required('implementations')) !== true) return $gate;
        return (new \RoutesPro\Implementations\ImplementationsController())->mine($request);
    }

    public function stocks(WP_REST_Request $request) {
        if (($gate = $this->feature_required('stocks')) !== true) return $gate;
        return (new \RoutesPro\Stocks\StocksController())->team($request);
    }

    public function stocks_viewed(WP_REST_Request $request) {
        if (($gate = $this->feature_required('stocks')) !== true) return $gate;
        $body = $request->get_json_params() ?: [];
        $mutation_id = (string)($body['mutation_id'] ?? $request->get_header('x-fieldflow-mutation-id'));
        return MutationStore::execute($mutation_id, 'stocks_viewed', function() use ($request) {
            return (new \RoutesPro\Stocks\StocksController())->viewed($request);
        });
    }

    public function expenses(WP_REST_Request $request) {
        if (($gate = $this->feature_required('expenses')) !== true) return $gate;
        return (new \RoutesPro\Expenses\ExpensesController())->index($request);
    }

    public function expense_create(WP_REST_Request $request) {
        if (($gate = $this->feature_required('expenses')) !== true) return $gate;
        $mutation_id = (string)($request->get_param('mutation_id') ?: $request->get_header('x-fieldflow-mutation-id'));
        return MutationStore::execute($mutation_id, 'expense_create', function() use ($request) {
            return (new \RoutesPro\Expenses\ExpensesController())->create($request);
        });
    }

    private function feature_required(string $feature) {
        return Settings::feature($feature)
            ? true
            : new WP_Error('fieldflow_mobile_feature_disabled', 'This mobile feature is currently disabled.', ['status' => 403, 'feature' => $feature]);
    }

    public function health() {
        return new WP_REST_Response([
            'ok' => true,
            'api_version' => ContractRegistry::API_VERSION,
            'plugin_version' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            'contract' => ContractRegistry::summary(),
            'stability' => StabilityFlags::publicState(),
            'mobile_enabled' => !empty(Settings::get()['general']['enabled']),
            'webview_bridge' => Settings::bridge_enabled(),
            'android_enabled' => Settings::platform_enabled('android'),
            'ios_enabled' => Settings::platform_enabled('ios'),
            'native_webview_experience' => true,
            'pwa_prompt_suppressed' => true,
            'resume_safe_webview' => true,
            'role_context_sync' => true,
            'offline_operational_cache' => true,
            'offline_form_drafts' => true,
            'offline_sync_queue' => true,
            'offline_incremental_delta' => true,
            'offline_conflict_management' => true,
            'offline_sync_center' => true,
        ], 200);
    }

    private function route_row(int $id): ?array {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT r.*, c.name AS client_name, p.name AS project_name, COALESCE(NULLIF(u.display_name,''),u.user_login) AS owner_name FROM {$px}routes r LEFT JOIN {$px}clients c ON c.id=r.client_id LEFT JOIN {$px}projects p ON p.id=r.project_id LEFT JOIN {$wpdb->users} u ON u.ID=r.owner_user_id WHERE r.id=%d LIMIT 1", $id), ARRAY_A);
        return $row ?: null;
    }

    private function stop_rows(int $route_id): array {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        return $wpdb->get_results($wpdb->prepare("SELECT rs.*, l.name AS location_name, l.address, l.city, l.postal_code, l.phone, l.lat, l.lng FROM {$px}route_stops rs INNER JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC", $route_id), ARRAY_A) ?: [];
    }

    private function stop_row(int $stop_id): ?array {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT rs.*, r.client_id, r.project_id, r.date AS route_date, r.owner_user_id, l.name AS location_name, l.address, l.city, l.postal_code, l.phone, l.lat, l.lng FROM {$px}route_stops rs INNER JOIN {$px}routes r ON r.id=rs.route_id INNER JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.id=%d LIMIT 1", $stop_id), ARRAY_A);
        return $row ?: null;
    }

    public static function build_user_payload(int $user_id): array {
        $user = get_userdata($user_id);
        $scope = Permissions::get_scope($user_id);
        $portals = WebViewBridge::portal_access($user_id);
        $roles = $user ? array_values((array)$user->roles) : [];
        $role_labels = [];
        $wp_roles = function_exists('wp_roles') ? wp_roles() : null;
        foreach ($roles as $role) {
            $role_labels[$role] = $wp_roles && isset($wp_roles->roles[$role]['name'])
                ? translate_user_role((string)$wp_roles->roles[$role]['name'])
                : (string)$role;
        }

        $manage = Permissions::is_manager($user_id);
        $execute = $user ? user_can($user, 'routespro_execute') : false;
        $feature = static function(string $key): bool { return Settings::feature($key); };
        $portal_feature = static function(string $key, string $target): bool { return Settings::feature_for_target($key, $target); };
        $portal_features = [
            'operational' => Settings::features_for_target('operational'),
            'client' => Settings::features_for_target('client'),
        ];
        $entitled = static function(string $key) use ($user_id): bool {
            return !class_exists('\RoutesPro\SaaS\EntitlementService') || \RoutesPro\SaaS\EntitlementService::userHasAny($key, $user_id);
        };
        $front_access = Permissions::can_access_front($user_id);
        $operational = !empty($portals['operational']);
        $client = !empty($portals['client']);
        $messaging = !class_exists('\RoutesPro\Support\Features') || \RoutesPro\Support\Features::enabled('team_messaging');
        $growth = class_exists('\RoutesPro\Performance\Module');

        $capabilities = [
            'manage' => $manage,
            'execute' => $execute,
            'front_access' => $front_access,
            'operational_portal' => $operational,
            'client_portal' => $client,
            'routes' => $entitled('routes.view') && (($operational && $portal_feature('routes','operational')) || ($client && $portal_feature('routes','client'))),
            'route_execute' => $portal_feature('routes','operational') && $entitled('routes.view') && $operational && ($execute || $manage || !empty($scope['route_ids'])),
            'reporting' => $entitled('reporting.view') && (($operational && $portal_feature('reporting','operational')) || ($client && $portal_feature('reporting','client'))),
            'report_submit' => $portal_feature('reporting','operational') && $entitled('reporting.submit') && $operational,
            'media_upload' => $portal_feature('media_upload','operational') && $entitled('media.upload') && $operational,
            'briefings' => $entitled('briefings.view') && (($operational && $portal_feature('briefings','operational')) || ($client && $portal_feature('briefings','client'))),
            'implementations' => $entitled('implementations.view') && (($operational && $portal_feature('implementations','operational')) || ($client && $portal_feature('implementations','client'))),
            'stocks' => $entitled('stocks.view') && (($operational && $portal_feature('stocks','operational')) || ($client && $portal_feature('stocks','client'))),
            'expenses' => $portal_feature('expenses','operational') && $entitled('expenses.use') && $operational,
            'analytics' => $entitled('analytics.basic') && $front_access && (($operational && $portal_feature('analytics','operational')) || ($client && $portal_feature('analytics','client'))),
            'commercial_base' => $entitled('commercial.view') && (($operational && $portal_feature('commercial_base','operational')) || ($client && $portal_feature('commercial_base','client'))),
            'self_route_planning' => $portal_feature('routes','operational') && $entitled('routes.create') && $operational,
            'campaign_pdvs' => $portal_feature('campaign_pdvs','client') && $entitled('campaign_pdvs.manage') && $client,
            'team' => $portal_feature('team','client') && $entitled('team.view') && $client,
            'growth_hub' => $portal_feature('growth_hub','client') && $entitled('growth_hub.view') && $client && $growth,
            'messages' => $entitled('messages.use') && $messaging && (($operational && $portal_feature('messages','operational')) || ($client && $portal_feature('messages','client'))),
            'academy' => $portal_feature('academy','operational') && $entitled('academy.view') && $operational && $growth,
            'webview' => Settings::bridge_enabled() && $entitled('mobile.webview') && ($operational || $client),
            'billing' => $client && $entitled('billing.view') && Settings::target_enabled('billing'),
            'billing_subscribe' => Settings::target_enabled('subscriptions') && class_exists('\RoutesPro\Billing\SignupService') && \RoutesPro\Billing\SignupService::isEnabled(),
        ];

        $navigation = [
            'operational' => $operational ? array_values(array_filter([
                ($portal_feature('routes','operational') && $capabilities['routes']) ? 'route' : null,
                ($portal_feature('briefings','operational') && $capabilities['briefings']) ? 'briefings' : null,
                ($portal_feature('implementations','operational') && $capabilities['implementations']) ? 'implementations' : null,
                ($portal_feature('stocks','operational') && $capabilities['stocks']) ? 'stocks' : null,
                (($portal_feature('routes','operational') && $capabilities['self_route_planning']) || ($portal_feature('commercial_base','operational') && $capabilities['commercial_base'])) ? 'discovery' : null,
                ($portal_feature('reporting','operational') && $capabilities['reporting']) ? 'report' : null,
                ($portal_feature('academy','operational') && $capabilities['academy']) ? 'academy' : null,
                ($portal_feature('expenses','operational') && $capabilities['expenses']) ? 'expenses' : null,
                ($portal_feature('messages','operational') && $capabilities['messages']) ? 'messages' : null,
                ($portal_feature('analytics','operational') && $capabilities['analytics']) ? 'analytics' : null,
            ])) : [],
            'client' => $client ? array_values(array_filter([
                'overview',
                ($portal_feature('routes','client') && $capabilities['routes']) ? 'routes' : null,
                ($portal_feature('campaign_pdvs','client') && $capabilities['campaign_pdvs']) ? 'campaign_pdvs' : null,
                ($portal_feature('reporting','client') && $capabilities['reporting']) ? 'reports' : null,
                ($portal_feature('commercial_base','client') && $capabilities['commercial_base']) ? 'commercial' : null,
                ($portal_feature('briefings','client') && $capabilities['briefings']) ? 'briefings' : null,
                ($portal_feature('implementations','client') && $capabilities['implementations']) ? 'implementations' : null,
                ($portal_feature('stocks','client') && $capabilities['stocks']) ? 'stocks' : null,
                ($portal_feature('team','client') && $capabilities['team']) ? 'team' : null,
                ($portal_feature('growth_hub','client') && $capabilities['growth_hub']) ? 'performance' : null,
                ($portal_feature('analytics','client') && $capabilities['analytics']) ? 'analytics' : null,
                ($capabilities['billing'] || $capabilities['billing_subscribe']) ? 'billing' : null,
            ])) : [],
        ];

        [$resolvedClientId, $resolvedProjectId] = Permissions::resolve_front_context(0, 0, $user_id);
        $resolvedTheme = class_exists('\\RoutesPro\\Support\\Theme')
            ? \RoutesPro\Support\Theme::resolve($resolvedClientId, $resolvedProjectId)
            : [];
        $resolvedThemePayload = class_exists('\\RoutesPro\\Support\\Theme')
            ? \RoutesPro\Support\Theme::js_payload($resolvedTheme)
            : [];
        $subscriptionExperience = SubscriptionExperience::build($user_id);
        $payload = [
            'id' => $user_id,
            'display_name' => $user ? (string)$user->display_name : '',
            'email' => $user ? (string)$user->user_email : '',
            'roles' => $roles,
            'role_labels' => $role_labels,
            'capabilities' => $capabilities,
            'scope' => $scope,
            'portals' => $portals,
            'portal_features' => $portal_features,
            'navigation' => $navigation,
            'billing_portal' => [
                'enabled' => !empty($capabilities['billing']) || !empty($capabilities['billing_subscribe']),
                'manage_enabled' => !empty($capabilities['billing']),
                'subscribe_enabled' => !empty($capabilities['billing_subscribe']),
                'target' => !empty($subscriptionExperience['primary_action']['target']) ? (string)$subscriptionExperience['primary_action']['target'] : 'subscriptions',
                'manage_target' => 'billing',
                'subscribe_target' => 'subscriptions',
                'page_url' => class_exists('\\RoutesPro\\Billing\\Settings') ? \RoutesPro\Billing\Settings::portalUrl() : '',
                'signup_page_url' => class_exists('\\RoutesPro\\Billing\\Settings') ? \RoutesPro\Billing\Settings::signupUrl() : '',
                'web_session_endpoint' => rest_url('fieldflow-mobile/v1/auth/web-session'),
                'experience_endpoint' => rest_url('fieldflow-mobile/v1/billing/experience'),
            ],
            'subscription_experience' => $subscriptionExperience,
            'recommended_target' => (string)($portals['default_target'] ?? ''),
            'context' => [
                'client_id' => $resolvedClientId,
                'project_id' => $resolvedProjectId,
            ],
            'theme' => $resolvedThemePayload,
            'theme_context_version' => hash('sha256', wp_json_encode([
                'client_id' => $resolvedClientId,
                'project_id' => $resolvedProjectId,
                'theme' => $resolvedThemePayload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'mobile_config_version' => hash('sha256', wp_json_encode(Settings::get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'subscription_capabilities' => class_exists('\RoutesPro\SaaS\CapabilityCatalog') ? array_reduce(
                \RoutesPro\SaaS\CapabilityCatalog::keys(),
                static function(array $carry, string $key) use ($entitled): array { $carry[$key] = $entitled($key); return $carry; },
                []
            ) : [],
        ];
        // The mobile context signature must only contain durable access state.
        // subscription_experience.generated_at changes on every request and was
        // causing the native WebView to reload periodically even when roles,
        // permissions and subscriptions had not changed.
        $versionPayload = $payload;
        if (isset($versionPayload['subscription_experience']['generated_at'])) {
            unset($versionPayload['subscription_experience']['generated_at']);
        }
        $payload['context_version'] = hash('sha256', wp_json_encode($versionPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $payload;
    }

    public static function context_version_for_user(int $user_id): string {
        $payload = self::build_user_payload($user_id);
        return (string)($payload['context_version'] ?? '');
    }

    private function session_payload(): array {
        $session = Auth::current_session() ?: [];
        return [
            'id' => (int)($session['id'] ?? 0),
            'device_id' => (string)($session['device_id'] ?? ''),
            'device_name' => (string)($session['device_name'] ?? ''),
            'platform' => (string)($session['platform'] ?? ''),
            'app_version' => (string)($session['app_version'] ?? ''),
            'access_expires_at' => (string)($session['access_expires_at'] ?? ''),
            'refresh_expires_at' => (string)($session['refresh_expires_at'] ?? ''),
        ];
    }
}
