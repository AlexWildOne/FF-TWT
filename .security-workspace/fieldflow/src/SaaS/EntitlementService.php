<?php
namespace RoutesPro\SaaS;

use RoutesPro\Support\Permissions;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

class EntitlementService {
    private static bool $registered = false;
    private static array $cache = [];

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        UsageService::registerHooks();
        add_filter('rest_pre_dispatch', [self::class, 'guardRest'], 8, 3);
        add_action('admin_init', [self::class, 'guardAdminPage'], 1);
        add_action('admin_menu', [self::class, 'filterAdminMenu'], 999);
    }

    public static function clientHas(int $clientId, string $capability): bool {
        $capability = sanitize_text_field($capability);
        if ($clientId <= 0 || $capability === '') return true;
        $key = $clientId . '|' . $capability;
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        $sub = PlanRepository::subscription($clientId);
        if (!$sub) return self::$cache[$key] = true;
        $status = self::effectiveStatus($sub);
        $allowed = !empty($sub['capabilities'][$capability]);
        if (array_key_exists($capability, (array)$sub['overrides'])) $allowed = !empty($sub['overrides'][$capability]);
        if (in_array($status, ['suspended', 'expired', 'cancelled'], true)) {
            $readOnly = preg_match('/\.(view|basic)$/', $capability)
                || in_array($capability, ['portal.client', 'portal.operational', 'exports.csv'], true);
            $allowed = $allowed && (bool)$readOnly;
        }
        return self::$cache[$key] = (bool)$allowed;
    }

    public static function userHasAny(string $capability, int $userId = 0): bool {
        $scope = Permissions::get_scope($userId ?: null);
        if (!empty($scope['is_manager'])) {
            $ids = self::allClientIds();
            if (!$ids) return true;
        } else {
            $ids = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
        }
        if (!$ids) return true;
        foreach ($ids as $id) if (self::clientHas($id, $capability)) return true;
        return false;
    }

    public static function clientStatus(int $clientId): string {
        $sub = PlanRepository::subscription($clientId);
        return $sub ? self::effectiveStatus($sub) : 'legacy';
    }

    public static function effectiveForClient(int $clientId): array {
        $sub = PlanRepository::subscription($clientId);
        $caps = [];
        foreach (CapabilityCatalog::keys() as $key) $caps[$key] = self::clientHas($clientId, $key);
        $publicSubscription = null;
        if ($sub) {
            $publicSubscription = [
                'plan_slug'=>(string)($sub['plan_slug'] ?? ''),
                'plan_name'=>(string)($sub['plan_name'] ?? ''),
                'status'=>(string)($sub['status'] ?? 'active'),
                'starts_at'=>$sub['starts_at'] ?? null,
                'trial_ends_at'=>$sub['trial_ends_at'] ?? null,
                'renews_at'=>$sub['renews_at'] ?? null,
                'expires_at'=>$sub['expires_at'] ?? null,
                'currency'=>(string)($sub['currency'] ?? 'EUR'),
            ];
        }
        return [
            'client_id' => $clientId,
            'subscription' => $publicSubscription,
            'status' => $sub ? self::effectiveStatus($sub) : 'legacy',
            'capabilities' => $caps,
            'usage' => UsageService::snapshot($clientId),
        ];
    }

    public static function capabilityForShortcode(string $method): string {
        return (string)(CapabilityCatalog::shortcodeMap()[$method] ?? '');
    }

    public static function guardShortcode(string $method, array $atts = []): string {
        $cap = self::capabilityForShortcode($method);
        if ($cap === '') return '';
        $clientId = self::resolveClientId($atts);
        if ($clientId > 0 && !self::clientHas($clientId, $cap)) return self::blockedHtml($cap, $clientId);
        if ($clientId <= 0 && !self::userHasAny($cap)) return self::blockedHtml($cap, 0);
        return '';
    }

    public static function exportCapability(string $format): string {
        $format = sanitize_key($format);
        if (in_array($format, ['xls','xlsx','xlsx_mobile','excel','excel_mobile','mobile_excel'], true)) return 'exports.excel';
        if ($format === 'pdf') return 'exports.pdf';
        return 'exports.csv';
    }

    public static function assertExportOrDie(string $format, int $clientId = 0, bool $consume = true): void {
        $capability = self::exportCapability($format);
        $allowed = $clientId > 0 ? self::clientHas($clientId, $capability) : self::userHasAny($capability);
        if (!$allowed) wp_die('Este formato de exportação não está incluído no plano deste cliente.', 'FieldFlow', ['response'=>403,'back_link'=>true]);
        if ($consume && $clientId > 0) {
            $quota = UsageService::canConsume($clientId, 'exports_monthly', 1);
            if (empty($quota['allowed'])) wp_die('O limite mensal de exportações foi atingido.', 'FieldFlow', ['response'=>429,'back_link'=>true]);
            UsageService::increment($clientId, 'exports_monthly', 1);
        }
    }

    public static function blockedHtml(string $capability, int $clientId = 0): string {
        $item = CapabilityCatalog::all()[$capability] ?? ['label' => $capability];
        $plan = $clientId ? PlanRepository::subscription($clientId) : null;
        $planName = (string)($plan['plan_name'] ?? 'plano atual');
        $recommended = PlanRepository::firstPlanWithCapability($capability);
        $upgrade = $recommended ? ' Disponível a partir do ' . (string)$recommended['name'] . '.' : '';
        return '<div class="ff-entitlement-lock" style="padding:20px;border:1px solid #dbe3ee;border-radius:18px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.06);font-family:inherit"><strong style="display:block;font-size:18px;color:#0f172a">Funcionalidade não incluída</strong><p style="margin:8px 0;color:#64748b">' . esc_html((string)$item['label']) . ' não está disponível no ' . esc_html($planName) . '.' . esc_html($upgrade) . ' Os dados existentes permanecem guardados. Contacta a equipa responsável para alterar o plano.</p></div>';
    }

    public static function resolveClientId(array $values = []): int {
        global $wpdb;
        $clientId = absint($values['client_id'] ?? ($_REQUEST['client_id'] ?? 0));
        if ($clientId) return $clientId;
        $projectId = absint($values['project_id'] ?? ($_REQUEST['project_id'] ?? 0));
        if ($projectId) return absint($wpdb->get_var($wpdb->prepare('SELECT client_id FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', $projectId)));
        $routeId = absint($values['route_id'] ?? ($_REQUEST['route_id'] ?? 0));
        if ($routeId) return absint($wpdb->get_var($wpdb->prepare('SELECT client_id FROM ' . $wpdb->prefix . 'routespro_routes WHERE id=%d', $routeId)));
        $stopId = absint($values['route_stop_id'] ?? ($values['stop_id'] ?? ($_REQUEST['route_stop_id'] ?? ($_REQUEST['stop_id'] ?? 0))));
        if ($stopId) return absint($wpdb->get_var($wpdb->prepare('SELECT r.client_id FROM ' . $wpdb->prefix . 'routespro_route_stops s INNER JOIN ' . $wpdb->prefix . 'routespro_routes r ON r.id=s.route_id WHERE s.id=%d', $stopId)));
        $scope = Permissions::get_scope();
        $ids = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
        return count($ids) === 1 ? (int)$ids[0] : 0;
    }

    public static function guardRest($result, $server, WP_REST_Request $request) {
        if (is_wp_error($result)) return $result;
        $route = (string)$request->get_route();
        $method = strtoupper((string)$request->get_method());
        $params = array_merge((array)$request->get_params(), (array)$request->get_json_params());
        $cap = self::restCapability($route, $method);
        if ($cap === '') return $result;
        $clientId = self::resolveRestClientId($route, $params);
        if ($clientId > 0 && !self::clientHas($clientId, $cap)) return self::restDenied($cap, $clientId);
        if ($clientId <= 0 && !self::userHasAny($cap)) return self::restDenied($cap, 0);

        $quotaMetric = self::restQuotaMetric($route, $method);
        if ($clientId > 0 && $quotaMetric !== '') {
            $quota = UsageService::canConsume($clientId, $quotaMetric, 1);
            if (empty($quota['allowed'])) return UsageService::quotaError($clientId, $quotaMetric);
        }
        return $result;
    }

    public static function guardAdminPage(): void {
        if (!is_admin() || wp_doing_ajax() || !current_user_can('routespro_manage')) return;
        $page = sanitize_key((string)($_REQUEST['page'] ?? ''));
        $cap = (string)(CapabilityCatalog::adminPageMap()[$page] ?? '');
        if ($cap === '') return;
        $clientId = self::resolveClientId();
        $allowed = $clientId > 0 ? self::clientHas($clientId, $cap) : self::userHasAny($cap);
        if (!$allowed) {
            wp_die(esc_html('Esta funcionalidade não está incluída no plano do cliente selecionado.'), 'FieldFlow', ['response' => 403, 'back_link' => true]);
        }
    }

    public static function filterAdminMenu(): void {
        if (!current_user_can('routespro_manage')) return;
        foreach (CapabilityCatalog::adminPageMap() as $page => $capability) {
            if (!self::userHasAny((string)$capability)) remove_submenu_page('routespro', (string)$page);
        }
    }

    public static function clearCache(): void { self::$cache = []; }

    private static function effectiveStatus(array $sub): string {
        $status = (string)($sub['status'] ?? 'active');
        $now = time();
        if (!empty($sub['expires_at']) && strtotime((string)$sub['expires_at']) <= $now) $status = 'expired';
        if ($status === 'trial' && !empty($sub['trial_ends_at']) && strtotime((string)$sub['trial_ends_at']) <= $now) $status = 'expired';
        return $status;
    }

    private static function restCapability(string $route, string $method): string {
        $isRead = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);

        if (preg_match('#^/routespro/v1/routes(?:/\d+)?$#', $route)) return $isRead ? 'routes.view' : 'routes.create';
        if (preg_match('#^/routespro/v1/routes/\d+/stops$#', $route)) return $isRead ? 'routes.view' : 'routes.create';
        if (preg_match('#^/routespro/v1/stops(?:/\d+)?$#', $route)) return $isRead ? 'routes.view' : 'routes.create';
        if (preg_match('#^/routespro/v1/events$#', $route)) return 'routes.create';
        if (preg_match('#^/routespro/v1/form-submissions$#', $route)) return 'reporting.view';
        if (preg_match('#^/routespro/v1/(?:optimi|optimize)#', $route)) return 'routes.optimise';
        if (preg_match('#^/routespro/v1/campaign-pdvs(?:/|$)#', $route)) return 'campaign_pdvs.manage';

        if (preg_match('#^/routespro/v1/stats$#', $route)) return 'analytics.basic';
        if (preg_match('#^/routespro/v1/analytics/ai-insights$#', $route)) return 'ai.use';
        if (preg_match('#^/routespro/v1/analytics/evidence-archive$#', $route)) return 'exports.csv';
        if (preg_match('#^/routespro/v1/heatmap$#', $route)) return 'analytics.advanced';

        if (preg_match('#^/routespro/v1/briefings(?:/|$)#', $route)) return 'briefings.view';
        if (preg_match('#^/routespro/v1/implementations(?:/|$)#', $route)) return 'implementations.view';
        if (preg_match('#^/routespro/v1/stocks(?:/|$)#', $route)) return 'stocks.view';
        if (preg_match('#^/routespro/v1/expenses(?:/|$)#', $route)) return 'expenses.use';

        if (preg_match('#^/routespro/v1/locations(?:/|$)#', $route)) return $isRead ? 'commercial.view' : 'commercial.manage';
        if (preg_match('#^/routespro/v1/categories(?:/|$)#', $route)) return 'commercial.view';
        if (preg_match('#^/routespro/v1/(?:commercial-search|commercial-filters|locations/lookup)$#', $route)) return 'commercial.view';
        if (preg_match('#^/routespro/v1/locations/import-template$#', $route)) return 'commercial.manage';
        if (preg_match('#^/routespro/v1/places/search$#', $route)) return 'commercial.manage';

        if (preg_match('#^/routespro/v1/geocode$#', $route)) return 'routes.create';
        if (preg_match('#^/routespro/v1/matrix$#', $route)) return 'routes.optimise';
        if (preg_match('#^/routespro/v1/ai/suggest$#', $route)) return 'ai.use';
        if (preg_match('#^/routespro/v1/integrations(?:/|$)#', $route)) return 'api.access';

        if (preg_match('#^/fieldflow/v1/briefings(?:/|$)#', $route)) return $isRead ? 'briefings.view' : 'briefings.manage';
        if (preg_match('#^/fieldflow/v1/implementations(?:/|$)#', $route)) return $isRead ? 'implementations.view' : 'implementations.manage';
        if (preg_match('#^/fieldflow/v1/stocks(?:/|$)#', $route)) return $isRead ? 'stocks.view' : 'stocks.manage';
        if (preg_match('#^/fieldflow/v1/commercial(?:/|$)#', $route)) return $isRead ? 'commercial.view' : 'commercial.manage';
        if (preg_match('#^/fieldflow/v1/mapping(?:/|$)#', $route)) return 'mapping.manage';

        if (preg_match('#^/fieldflow-mobile/v1/form-submissions$#', $route) && $method === 'POST') return 'reporting.submit';
        if (preg_match('#^/fieldflow-mobile/v1/media$#', $route) && $method === 'POST') return 'media.upload';
        if (preg_match('#^/fieldflow-mobile/v1/offline(?:/|$)#', $route)) return 'mobile.offline';
        if (preg_match('#^/fieldflow-mobile/v1/(?:routes|my-routes|stops)(?:/|$)#', $route)) return 'routes.view';
        if (preg_match('#^/fieldflow-mobile/v1/briefings(?:/|$)#', $route)) return 'briefings.view';
        if (preg_match('#^/fieldflow-mobile/v1/implementations(?:/|$)#', $route)) return 'implementations.view';
        if (preg_match('#^/fieldflow-mobile/v1/stocks(?:/|$)#', $route)) return 'stocks.view';

        foreach (CapabilityCatalog::restPatterns() as $pattern => $candidate) if (preg_match($pattern, $route)) return $candidate;
        return '';
    }

    private static function restQuotaMetric(string $route, string $method): string {
        if ($method === 'POST' && preg_match('#^/routespro/v1/routes$#', $route)) return 'routes_monthly';
        if ($method === 'POST' && preg_match('#^/fieldflow-mobile/v1/form-submissions$#', $route)) return 'submissions_monthly';
        if ($method === 'POST' && preg_match('#^/routespro/v1/analytics/ai-insights$#', $route)) return 'ai_requests_monthly';
        if ($method === 'POST' && preg_match('#^/routespro/v1/analytics/evidence-archive$#', $route)) return 'exports_monthly';
        return '';
    }

    private static function resolveRestClientId(string $route, array $params): int {
        global $wpdb;
        $clientId = self::resolveClientId($params);
        if ($clientId > 0) return $clientId;
        $id = absint($params['id'] ?? 0);
        if (!$id) return 0;
        if (preg_match('#^/routespro/v1/routes/|^/fieldflow-mobile/v1/routes/#', $route)) {
            return absint($wpdb->get_var($wpdb->prepare('SELECT client_id FROM ' . $wpdb->prefix . 'routespro_routes WHERE id=%d', $id)));
        }
        if (preg_match('#^/routespro/v1/stops/|^/fieldflow-mobile/v1/stops/#', $route)) {
            return absint($wpdb->get_var($wpdb->prepare('SELECT r.client_id FROM ' . $wpdb->prefix . 'routespro_route_stops s INNER JOIN ' . $wpdb->prefix . 'routespro_routes r ON r.id=s.route_id WHERE s.id=%d', $id)));
        }
        return 0;
    }

    private static function restDenied(string $capability, int $clientId): WP_Error {
        $recommended = PlanRepository::firstPlanWithCapability($capability);
        return new WP_Error('fieldflow_plan_required', 'Funcionalidade não incluída no plano deste cliente.', [
            'status' => 403,
            'capability' => $capability,
            'client_id' => $clientId,
            'recommended_plan' => $recommended ? ['slug'=>(string)$recommended['slug'],'name'=>(string)$recommended['name']] : null,
        ]);
    }

    private static function allClientIds(): array {
        global $wpdb;
        return array_map('intval', $wpdb->get_col('SELECT id FROM ' . $wpdb->prefix . 'routespro_clients') ?: []);
    }
}
