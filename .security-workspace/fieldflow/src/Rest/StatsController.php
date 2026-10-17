<?php
namespace RoutesPro\Rest;

use WP_REST_Request;
use WP_REST_Response;
use RoutesPro\Support\Permissions;
use RoutesPro\Analytics\AnalyticsAIService;
use RoutesPro\Analytics\FieldFlowAskService;
use RoutesPro\Analytics\QuestionAnalyticsService;
use RoutesPro\Analytics\WidgetOrchestrator;
use RoutesPro\Analytics\RouteMobilityWidgetService;
use RoutesPro\Analytics\MonthlyExecutiveAnalyticsService;
use RoutesPro\Forms\EvidenceFlow;
use RoutesPro\Services\SubmissionStatusPolicy;
use RoutesPro\Services\OperationalReportDayPolicy;
use RoutesPro\Reporting\CanonicalOperationalReportService;

class StatsController {
    const NS = 'routespro/v1';

    public function register_routes() {
        register_rest_route(self::NS, '/stats', [
            'methods'  => 'GET',
            'callback' => [$this, 'stats'],
            'permission_callback' => function(){ return current_user_can('routespro_manage') || Permissions::can_access_front(); }
        ]);
        register_rest_route(self::NS, '/analytics/ai-insights', [
            'methods'  => 'POST',
            'callback' => [$this, 'aiInsights'],
            'permission_callback' => function(){ return current_user_can('routespro_manage') || Permissions::can_access_front(); }
        ]);
        register_rest_route(self::NS, '/analytics/ask', [
            'methods'  => 'POST',
            'callback' => [$this, 'askFieldFlow'],
            'permission_callback' => function(){ return current_user_can('routespro_manage') || Permissions::can_access_front(); }
        ]);
        register_rest_route(self::NS, '/analytics/store-intelligence', [
            'methods'  => 'POST',
            'callback' => [$this, 'storeIntelligence'],
            'permission_callback' => function(){ return current_user_can('routespro_manage') || Permissions::can_access_front(); }
        ]);
        register_rest_route(self::NS, '/analytics/evidence-archive', [
            'methods'  => 'POST',
            'callback' => [$this, 'evidenceArchive'],
            'permission_callback' => function(){ return current_user_can('routespro_manage') || Permissions::can_access_front(); }
        ]);
        register_rest_route(self::NS, '/heatmap', [
            'methods'  => 'GET',
            'callback' => [$this, 'heatmap'],
            'permission_callback' => function(){ return current_user_can('routespro_manage') || Permissions::can_access_front(); }
        ]);
    }

    private function build_where(WP_REST_Request $req, array &$args, string $routeAlias = 'r'): string {
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $from = sanitize_text_field($req->get_param('from') ?: $req->get_param('date_from')) ?: date('Y-m-d', strtotime('-7 days'));
        $to   = sanitize_text_field($req->get_param('to') ?: $req->get_param('date_to')) ?: current_time('Y-m-d');
        $client_id  = absint($req->get_param('client_id') ?: 0);
        $project_id = absint($req->get_param('project_id') ?: 0);
        $route_id   = absint($req->get_param('route_id') ?: 0);
        $user_param = $req->get_param('user_id');
        $user_id    = ($user_param === 'me' || $user_param === 'current') ? get_current_user_id() : absint($user_param ?: 0);
        $role       = sanitize_text_field($req->get_param('role') ?: '');
        $location_id = absint($req->get_param('location_id') ?: 0);

        $scopeCheck = Permissions::assert_scope_or_error($client_id, $project_id);
        if (is_wp_error($scopeCheck)) return '';

        $where = ["{$routeAlias}.date BETWEEN %s AND %s"];
        $args  = [$from, $to];
        if ($client_id)  { $where[] = "{$routeAlias}.client_id = %d";  $args[] = $client_id; }
        if ($project_id) { $where[] = "{$routeAlias}.project_id = %d"; $args[] = $project_id; }
        if ($route_id)   { $where[] = "{$routeAlias}.id = %d"; $args[] = $route_id; }
        list($scopeSql, $scopeArgs) = Permissions::scope_sql($routeAlias);
        if ($scopeSql !== '1=1') { $where[] = $scopeSql; $args = array_merge($args, $scopeArgs); }

        if ($location_id) {
            $where[] = "EXISTS (SELECT 1 FROM {$px}route_stops s_loc WHERE s_loc.route_id = {$routeAlias}.id AND s_loc.location_id = %d)";
            $args[] = $location_id;
        }

        if ($user_id && $role) {
            $where[] = "({$routeAlias}.owner_user_id = %d OR EXISTS (SELECT 1 FROM {$px}assignments ax WHERE ax.route_id = {$routeAlias}.id AND ax.user_id = %d AND ax.role = %s))";
            $args[] = $user_id; $args[] = $user_id; $args[] = $role;
        } elseif ($user_id) {
            $where[] = "({$routeAlias}.owner_user_id = %d OR EXISTS (SELECT 1 FROM {$px}assignments ax WHERE ax.route_id = {$routeAlias}.id AND ax.user_id = %d))";
            $args[] = $user_id; $args[] = $user_id;
        } elseif ($role) {
            $where[] = "EXISTS (SELECT 1 FROM {$px}assignments ax WHERE ax.route_id = {$routeAlias}.id AND ax.role = %s)";
            $args[] = $role;
        }
        return $wpdb->prepare(implode(' AND ', $where), ...$args);
    }

    public function stats(WP_REST_Request $req) {
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $args = [];
        $sqlWhere = $this->build_where($req, $args, 'r');
        if ($sqlWhere === '') return new \WP_Error('forbidden', 'Sem acesso ao âmbito pedido.', ['status' => 403]);

        $from = sanitize_text_field($req->get_param('from') ?: $req->get_param('date_from')) ?: date('Y-m-d', strtotime('-7 days'));
        $to   = sanitize_text_field($req->get_param('to') ?: $req->get_param('date_to')) ?: current_time('Y-m-d');
        $export_full = rest_sanitize_boolean($req->get_param('export_full'));
        $report_export = rest_sanitize_boolean($req->get_param('report_export'));

        if (!$report_export) $this->reconcile_routes_for_where($sqlWhere);

        // 2.2.420: stats() runs 7+ aggregate queries plus a correlated-subquery
        // detail query, a full repeat for the previous-period comparison, and
        // form analytics — expensive, and previously recomputed on every call
        // even when nothing relevant had changed (this is also what made
        // /analytics/ai-insights slow: it calls stats() first, unconditionally,
        // before its own AI-response cache is ever checked). Cached here,
        // scoped to the exact resolved WHERE clause (which already encodes
        // date range, client/project/location/user/role AND the caller's own
        // permission scope) plus the extra params form_analytics reads and the
        // current user, so two people with different access never share a
        // cache entry. Skipped entirely for exports: those are deliberate,
        // infrequent "give me everything" requests where a large, one-off
        // payload is a poor fit for the transient cache and staleness matters
        // more than speed.
        $statsCacheable = !$export_full && !$report_export;
        $statsCacheKey = '';
        if ($statsCacheable) {
            $statsCacheKey = 'ff_stats_' . substr(hash('sha256', wp_json_encode([
                'where' => $sqlWhere,
                'from' => $from,
                'to' => $to,
                'category' => (string)($req->get_param('category') ?: ''),
                'product' => (string)($req->get_param('product') ?: ''),
                'audience' => (string)($req->get_param('analytics_audience') ?: ''),
                'runtime' => defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '',
                'user' => get_current_user_id(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 40);
            $cachedRes = get_transient($statsCacheKey);
            if (is_array($cachedRes)) {
                $res = $cachedRes;
                $rows = (array)($res['by_day'] ?? []);
            }
        }

        if (!isset($res)) {
        $location_scope_id = absint($req->get_param('location_id') ?: 0);
        $stop_location_sql = $location_scope_id ? $wpdb->prepare(' AND s.location_id = %d', $location_scope_id) : '';
        $total_routes = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT r.id FROM {$px}routes r WHERE $sqlWhere GROUP BY r.id) x");
        $total_stops = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT DISTINCT s.id FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id = s.route_id WHERE $sqlWhere{$stop_location_sql}) x");
        $done_stops = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT DISTINCT s.id FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id = s.route_id WHERE $sqlWhere{$stop_location_sql} AND s.status IN ('done','completed')) x");
        if ($location_scope_id) {
            $completed_routes = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM (SELECT r.id FROM {$px}routes r WHERE $sqlWhere AND NOT EXISTS (SELECT 1 FROM {$px}route_stops s_done WHERE s_done.route_id = r.id AND s_done.location_id = %d AND (s_done.status NOT IN ('done','completed') AND NOT (s_done.status='failed' AND TRIM(COALESCE(s_done.fail_reason,''))<>''))) GROUP BY r.id) x", $location_scope_id));
        } else {
            $completed_routes = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT r.id FROM {$px}routes r WHERE $sqlWhere AND NOT EXISTS (SELECT 1 FROM {$px}route_stops s WHERE s.route_id = r.id AND (s.status NOT IN ('done','completed') AND NOT (s.status='failed' AND TRIM(COALESCE(s.fail_reason,''))<>''))) GROUP BY r.id) x");
        }
        $observedArrival = "s.arrived_at IS NOT NULL AND s.arrived_at > '1000-01-01' AND l.window_start IS NOT NULL AND l.window_end IS NOT NULL";
        $on_time = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id=s.route_id INNER JOIN {$px}locations l ON l.id=s.location_id WHERE $sqlWhere{$stop_location_sql} AND {$observedArrival} AND ((l.window_start<=l.window_end AND TIME(s.arrived_at) BETWEEN l.window_start AND l.window_end) OR (l.window_start>l.window_end AND (TIME(s.arrived_at)>=l.window_start OR TIME(s.arrived_at)<=l.window_end)))");
        $with_windows = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id=s.route_id INNER JOIN {$px}locations l ON l.id=s.location_id WHERE $sqlWhere{$stop_location_sql} AND {$observedArrival}");
        $avg_stop_secs = $wpdb->get_var("SELECT AVG(TIMESTAMPDIFF(SECOND,s.arrived_at,s.departed_at)) FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id=s.route_id WHERE $sqlWhere{$stop_location_sql} AND s.arrived_at > '1000-01-01' AND s.departed_at>=s.arrived_at");

        $res = [
            'from' => $from,
            'to'   => $to,
            'total_routes'        => $total_routes,
            'completed_routes'    => $completed_routes,
            'completion_rate'     => $total_routes ? round($completed_routes/$total_routes*100,1) : 0,
            'total_stops'         => $total_stops,
            'done_stops'          => $done_stops,
            'done_rate'           => $total_stops ? round($done_stops/$total_stops*100,1) : 0,
            'avg_stops_per_route' => $total_routes ? round($total_stops/$total_routes,2) : 0,
            'on_time_rate'        => $with_windows ? round($on_time/$with_windows*100,1) : null,
            'avg_stop_minutes'    => $avg_stop_secs !== null ? round((float)$avg_stop_secs/60,1) : null,
            'location_scope_id'   => $location_scope_id,
            'timing_policy'       => 'one_validated_stop_arrival_and_departure_per_observation',
        ];

        $route_limit_sql = '';
        $rows = $wpdb->get_results("SELECT r.id as route_id, r.date, r.status as route_status, r.client_id, r.project_id, r.owner_user_id, r.meta_json, p.name as project_name, c.name as client_name, COALESCE((SELECT COALESCE(NULLIF(uo.display_name,''), uo.user_login) FROM {$wpdb->users} uo WHERE uo.ID = r.owner_user_id LIMIT 1),(SELECT COALESCE(NULLIF(ua.display_name,''), ua.user_login) FROM {$px}assignments a2 JOIN {$wpdb->users} ua ON ua.ID = a2.user_id WHERE a2.route_id = r.id ORDER BY a2.id ASC LIMIT 1)) AS user_name, COUNT(s.id) as stops, SUM(CASE WHEN s.status IN ('done','completed') THEN 1 ELSE 0 END) as stops_done, SUM(CASE WHEN s.status='failed' AND TRIM(COALESCE(s.fail_reason,''))<>'' THEN 1 ELSE 0 END) as stops_justified_failed, SUM(CASE WHEN s.status IN ('done','completed') OR (s.status='failed' AND TRIM(COALESCE(s.fail_reason,''))<>'') THEN 1 ELSE 0 END) as stops_terminal, SUM(CASE WHEN s.id IS NOT NULL AND (s.status IS NULL OR (s.status NOT IN ('done','completed') AND NOT (s.status='failed' AND TRIM(COALESCE(s.fail_reason,''))<>''))) THEN 1 ELSE 0 END) as stops_remaining, ROUND(100.0 * SUM(CASE WHEN s.status IN ('done','completed') THEN 1 ELSE 0 END)/NULLIF(COUNT(s.id),0),1) as done_rate FROM {$px}routes r LEFT JOIN {$px}route_stops s ON s.route_id = r.id LEFT JOIN {$px}projects p ON p.id = r.project_id LEFT JOIN {$px}clients c ON c.id = r.client_id WHERE $sqlWhere{$stop_location_sql} GROUP BY r.id ORDER BY r.date DESC, r.id DESC{$route_limit_sql}", ARRAY_A);
        $mobilitySummary = ['configured_routes'=>0,'costed_routes'=>0,'incomplete_distance_routes'=>0,'energy_cost_eur'=>0.0,'operational_cost_eur'=>0.0,'toll_cost_eur'=>0.0,'total_mobility_cost_eur'=>0.0,'estimated_litres'=>0.0,'estimated_kwh'=>0.0];
        if (class_exists('\RoutesPro\Services\MobilityCostService')) {
            foreach ($rows as &$routeRow) {
                $mobility = \RoutesPro\Services\MobilityCostService::calculateForRouteArray($routeRow);
                $routeRow['mobility_cost'] = $mobility;
                if (!empty($mobility['enabled'])) $mobilitySummary['configured_routes']++;
                if (!empty($mobility['costs_available'])) {
                    $mobilitySummary['costed_routes']++;
                    foreach (['energy_cost_eur','operational_cost_eur','toll_cost_eur','total_mobility_cost_eur','estimated_litres','estimated_kwh'] as $key) {
                        $mobilitySummary[$key] += (float)($mobility[$key] ?? 0);
                    }
                } elseif (!empty($mobility['enabled'])) {
                    $mobilitySummary['incomplete_distance_routes']++;
                }
                unset($routeRow['meta_json']);
            }
            unset($routeRow);
            foreach (['energy_cost_eur','operational_cost_eur','toll_cost_eur','total_mobility_cost_eur'] as $key) $mobilitySummary[$key] = round((float)$mobilitySummary[$key], 2);
            foreach (['estimated_litres','estimated_kwh'] as $key) $mobilitySummary[$key] = round((float)$mobilitySummary[$key], 3);
        } else {
            foreach ($rows as &$routeRow) unset($routeRow['meta_json']);
            unset($routeRow);
        }
        $res['mobility_summary'] = $mobilitySummary;
        $res['by_day'] = $rows ?: [];
        $location_rows = $wpdb->get_results("SELECT l.id as location_id, l.name as location_name, l.city, COUNT(s.id) as stops, SUM(CASE WHEN s.status IN ('done','completed') THEN 1 ELSE 0 END) as stops_done, ROUND(100.0 * SUM(CASE WHEN s.status IN ('done','completed') THEN 1 ELSE 0 END)/NULLIF(COUNT(s.id),0),1) as done_rate FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id = s.route_id INNER JOIN {$px}locations l ON l.id = s.location_id WHERE $sqlWhere{$stop_location_sql} GROUP BY l.id HAVING COUNT(s.id) > 0 ORDER BY done_rate ASC, stops DESC LIMIT 300", ARRAY_A);
        $res['by_location'] = $location_rows ?: [];
        $periodDays = max(1, (int) floor((strtotime($to) - strtotime($from)) / DAY_IN_SECONDS) + 1);
        $previousTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $previousFrom = date('Y-m-d', strtotime($previousTo . ' -' . max(0, $periodDays - 1) . ' days'));
        $res['previous_period'] = ['from' => $previousFrom, 'to' => $previousTo];
        $res['previous_summary'] = $this->range_summary($req, $previousFrom, $previousTo);

        // 2.2.467 Temporal Intelligence keeps the raw dashboard envelope intact,
        // but adds a time-aware operational context for scores and AI. Closed
        // past days can be evaluated, today is work in progress and future routes
        // are planning, never overdue work.
        if (class_exists('\RoutesPro\Analytics\TemporalAnalyticsService')) {
            $closedLocationRows = [];
            $today = current_time('Y-m-d');
            if ($to < $today) {
                $closedLocationRows = $location_rows ?: [];
            } else {
                $closedTo = wp_date('Y-m-d', strtotime($today . ' -1 day'), wp_timezone());
                if ($from <= $closedTo) {
                    try { $closedLocationRows = $this->location_rows_for_range($req, $from, min($to, $closedTo)); }
                    catch (\Throwable $e) { $closedLocationRows = []; }
                }
            }
            $res = \RoutesPro\Analytics\TemporalAnalyticsService::enrich($res, $closedLocationRows);
        }

        $res['rankings'] = $this->top_rankings($rows ?: []);
        $res['team_performance'] = $this->team_performance($rows ?: [], $req, $from, $to);
        $res['form_analytics'] = $this->build_form_analytics($req, $from, $to, $res);

        // 2.2.431 second-pass orchestration. Briefing/question relevance is
        // calculated while metrics are built. Once route/store facts exist we
        // can also boost widgets that correspond to active alerts, without
        // persisting a new alert merely because a dashboard was opened.
        $fa = is_array($res['form_analytics'] ?? null) ? $res['form_analytics'] : [];
        $allMetrics = array_values(array_filter((array)($fa['_orchestration_all_metrics'] ?? $fa['metrics'] ?? []), 'is_array'));
        $dashboardsForOrchestration = array_values(array_filter((array)($fa['dashboards'] ?? []), 'is_array'));
        if ($allMetrics && $dashboardsForOrchestration && class_exists('\RoutesPro\Analytics\OperationalIntelligenceService')) {
            $audience = sanitize_key((string)($req->get_param('analytics_audience') ?: 'client'));
            $userParamForIntelligence = $req->get_param('user_id');
            $resolvedUserForIntelligence = ($userParamForIntelligence === 'me' || $userParamForIntelligence === 'current') ? get_current_user_id() : absint($userParamForIntelligence ?: 0);
            $intelligenceContext = [
                'audience'=>$audience,
                'client_id'=>absint($req->get_param('client_id') ?: 0),
                'project_id'=>absint($req->get_param('project_id') ?: 0),
                'route_id'=>absint($req->get_param('route_id') ?: 0),
                'user_id'=>$resolvedUserForIntelligence,
                'location_id'=>absint($req->get_param('location_id') ?: 0),
                'from'=>$from,
                'to'=>$to,
            ];
            $stockContext = [];
            $narrowStockScope = !empty($intelligenceContext['route_id']) || !empty($intelligenceContext['user_id']) || !empty($intelligenceContext['location_id']);
            try {
                // Critical-path policy: keep /stats on the proven 2.2.436 cost
                // envelope. Narrow store/route stock enrichment belongs to the
                // isolated Store Intelligence endpoint, never to base Analytics.
                if (!$narrowStockScope && class_exists('\RoutesPro\Stocks\StockService')) {
                    $stockContext = \RoutesPro\Stocks\StockService::analyticsContext($intelligenceContext);
                }
            } catch (\Throwable $e) {
                $stockContext = [];
            }
            $statsForIntelligence = class_exists('\RoutesPro\Analytics\TemporalAnalyticsService')
                ? \RoutesPro\Analytics\TemporalAnalyticsService::forEvaluation($res)
                : $res;
            $statsForIntelligence['form_analytics']['metrics'] = $allMetrics;
            unset($statsForIntelligence['form_analytics']['_orchestration_all_metrics']);
            try {
                // Widget orchestration only needs deterministic alerts/scores. The
                // per-store engine is intentionally skipped here because /ai-insights
                // calculates it once for presentation. This avoids duplicate stock,
                // learning and per-store work on every Command View refresh.
                $widgetIntelligence = \RoutesPro\Analytics\OperationalIntelligenceService::analyse($statsForIntelligence, $intelligenceContext, [], $stockContext, false, false);
                $finalOrchestration = WidgetOrchestrator::orchestrate(
                    $allMetrics,
                    $dashboardsForOrchestration,
                    $audience,
                    $intelligenceContext,
                    $export_full || $report_export,
                    $widgetIntelligence
                );
                $res['form_analytics']['metrics'] = (array)($finalOrchestration['metrics'] ?? $res['form_analytics']['metrics']);
                $res['form_analytics']['orchestration'] = $finalOrchestration;
            } catch (\Throwable $e) {
                // Dashboard rendering must never fail because intelligence is
                // unavailable. The deterministic briefing-first pass remains valid.
            }
        }
        $canonicalData = $res;
        $canonicalData['form_analytics']['metrics'] = $allMetrics ?: (array)($res['form_analytics']['metrics'] ?? []);
        unset($res['form_analytics']['_orchestration_all_metrics']);
        try {
            $canonicalContext = [
                'client_name' => '', 'project_name' => '', 'user_name' => '', 'location_name' => '',
            ];
            if (!empty($res['by_day'][0]) && is_array($res['by_day'][0])) {
                $canonicalContext['client_name'] = (string)($res['by_day'][0]['client_name'] ?? '');
                $canonicalContext['project_name'] = (string)($res['by_day'][0]['project_name'] ?? '');
            }
            $res['canonical_operational_report'] = CanonicalOperationalReportService::build($canonicalData, [
                'from'=>$from, 'to'=>$to,
                'client_id'=>absint($req->get_param('client_id') ?: 0),
                'project_id'=>absint($req->get_param('project_id') ?: 0),
                'user_id'=>(($req->get_param('user_id') === 'me' || $req->get_param('user_id') === 'current') ? get_current_user_id() : absint($req->get_param('user_id') ?: 0)),
                'location_id'=>absint($req->get_param('location_id') ?: 0),
                'route_id'=>absint($req->get_param('route_id') ?: 0),
                'category'=>sanitize_text_field((string)($req->get_param('category') ?: '')),
                'product'=>sanitize_text_field((string)($req->get_param('product') ?: '')),
                'role'=>sanitize_key((string)($req->get_param('role') ?: '')),
            ], $canonicalContext);
        } catch (\Throwable $e) {
            $res['canonical_operational_report'] = [];
        }
        try {
            $res['monthly_executive'] = MonthlyExecutiveAnalyticsService::build($res, [
                'from'=>$from,
                'to'=>$to,
                'client_id'=>absint($req->get_param('client_id') ?: 0),
                'project_id'=>absint($req->get_param('project_id') ?: 0),
                'user_id'=>(($req->get_param('user_id') === 'me' || $req->get_param('user_id') === 'current') ? get_current_user_id() : absint($req->get_param('user_id') ?: 0)),
                'location_id'=>absint($req->get_param('location_id') ?: 0),
            ]);
        } catch (\Throwable $e) {
            $res['monthly_executive'] = [];
        }
        if ($statsCacheable) set_transient($statsCacheKey, $res, 300);
        }
        if (!$export_full && class_exists('\RoutesPro\Admin\FormSubmissions')) {
            $userParam = $req->get_param('user_id');
            $resolvedUserId = ($userParam === 'me' || $userParam === 'current') ? get_current_user_id() : absint($userParam ?: 0);
            \RoutesPro\Admin\FormSubmissions::storeDashboardSnapshot([
                'from' => $from,
                'to' => $to,
                'client_id' => absint($req->get_param('client_id') ?: 0),
                'project_id' => absint($req->get_param('project_id') ?: 0),
                'user_id' => $resolvedUserId,
                'location_id' => absint($req->get_param('location_id') ?: 0),
                'category' => sanitize_text_field((string)($req->get_param('category') ?: '')),
                'product' => sanitize_text_field((string)($req->get_param('product') ?: '')),
                'role' => sanitize_key((string)($req->get_param('role') ?: '')),
                'audience' => sanitize_key((string)($req->get_param('analytics_audience') ?: 'manager')),
                'self_mode' => false,
            ], $res);
        }
        if (!$export_full && !$report_export) {
            $res['pagination'] = ['routes_total'=>count((array)($res['by_day'] ?? [])), 'routes_limit'=>500, 'partial'=>count((array)($res['by_day'] ?? [])) > 500];
            $res['by_day'] = array_slice((array)($res['by_day'] ?? []), 0, 500);
            foreach ($res['form_analytics']['metrics'] as &$metric) {
                $metric['rows_total'] = count((array)($metric['rows'] ?? []));
                $metric['rows_partial'] = $metric['rows_total'] > 80;
                $metric['rows'] = array_slice((array)($metric['rows'] ?? []), 0, 80);
            }
            unset($metric);
        }
        return new WP_REST_Response($res, 200);
    }

    public function aiInsights(WP_REST_Request $req) {
        $audience = sanitize_key((string)($req->get_param('audience') ?: 'client'));
        if (!in_array($audience, ['client','merchandiser','manager'], true)) $audience = 'client';

        // O copiloto pessoal é sempre limitado ao utilizador autenticado no servidor.
        if ($audience === 'merchandiser') {
            $req->set_param('user_id', 'me');
            $req->set_param('analytics_audience', 'merchandiser');
        } elseif ($audience === 'client') {
            $req->set_param('analytics_audience', 'client');
        } elseif (!current_user_can('routespro_manage')) {
            $audience = 'client';
            $req->set_param('analytics_audience', 'client');
        }

        $statsResponse = $this->stats($req);
        if (is_wp_error($statsResponse)) return $statsResponse;
        $stats = $statsResponse instanceof WP_REST_Response ? $statsResponse->get_data() : $statsResponse;
        if (!is_array($stats)) return new \WP_Error('analytics_ai_invalid_stats', 'Não foi possível preparar os dados para análise.', ['status' => 500]);
        $refresh = rest_sanitize_boolean($req->get_param('refresh'));
        $user_param = $req->get_param('user_id');
        $context_user_id = ($user_param === 'me' || $user_param === 'current') ? get_current_user_id() : absint($user_param ?: 0);
        $aiContext = [
            'audience' => $audience,
            'client_id' => absint($req->get_param('client_id') ?: 0),
            'project_id' => absint($req->get_param('project_id') ?: 0),
            'route_id' => absint($req->get_param('route_id') ?: 0),
            'user_id' => $context_user_id,
            'location_id' => absint($req->get_param('location_id') ?: 0),
            'from' => (string)($stats['from'] ?? ''),
            'to' => (string)($stats['to'] ?? ''),
        ];
        return new WP_REST_Response(AnalyticsAIService::generateInsights($stats, $aiContext, $refresh), 200);
    }

    public function askFieldFlow(WP_REST_Request $req) {
        $question = sanitize_textarea_field((string)($req->get_param('question') ?: ''));
        if ($question === '') return new \WP_Error('ask_fieldflow_empty_question', 'Escreve uma pergunta sobre o contexto atual.', ['status'=>400]);

        $audience = sanitize_key((string)($req->get_param('audience') ?: 'client'));
        if (!in_array($audience, ['client','merchandiser','manager'], true)) $audience = 'client';
        if ($audience === 'merchandiser') {
            $req->set_param('user_id', 'me');
            $req->set_param('analytics_audience', 'merchandiser');
        } elseif ($audience === 'client') {
            $req->set_param('analytics_audience', 'client');
        } elseif (!current_user_can('routespro_manage')) {
            $audience = 'client';
            $req->set_param('analytics_audience', 'client');
        } else {
            $req->set_param('analytics_audience', 'manager');
        }

        // 2.2.467: functional help is deterministic and does not require a
        // metrics query or an external AI call. This also makes onboarding
        // available when the selected period has no submissions yet.
        if (class_exists(FieldFlowAskService::class) && FieldFlowAskService::isHelpQuestion($question)) {
            $userParam = $req->get_param('user_id');
            $resolvedUser = ($userParam === 'me' || $userParam === 'current') ? get_current_user_id() : absint($userParam ?: 0);
            $helpContext = [
                'audience'=>$audience,
                'client_id'=>absint($req->get_param('client_id') ?: 0),
                'project_id'=>absint($req->get_param('project_id') ?: 0),
                'route_id'=>absint($req->get_param('route_id') ?: 0),
                'user_id'=>$resolvedUser,
                'location_id'=>absint($req->get_param('location_id') ?: 0),
                'category'=>sanitize_text_field((string)($req->get_param('category') ?: '')),
                'product'=>sanitize_text_field((string)($req->get_param('product') ?: '')),
                'from'=>sanitize_text_field((string)($req->get_param('from') ?: '')),
                'to'=>sanitize_text_field((string)($req->get_param('to') ?: '')),
            ];
            return new WP_REST_Response(FieldFlowAskService::helpAnswer($question, $helpContext), 200);
        }

        try {
            $statsResponse = $this->stats($req);
            if (is_wp_error($statsResponse)) return $statsResponse;
            $stats = $statsResponse instanceof WP_REST_Response ? $statsResponse->get_data() : $statsResponse;
            if (!is_array($stats)) return new \WP_Error('ask_fieldflow_invalid_stats', 'Não foi possível preparar os dados para a pergunta.', ['status'=>500]);

            $userParam = $req->get_param('user_id');
            $resolvedUser = ($userParam === 'me' || $userParam === 'current') ? get_current_user_id() : absint($userParam ?: 0);
            $context = [
                'audience'=>$audience,
                'client_id'=>absint($req->get_param('client_id') ?: 0),
                'project_id'=>absint($req->get_param('project_id') ?: 0),
                'route_id'=>absint($req->get_param('route_id') ?: 0),
                'user_id'=>$resolvedUser,
                'location_id'=>absint($req->get_param('location_id') ?: 0),
                'category'=>sanitize_text_field((string)($req->get_param('category') ?: '')),
                'product'=>sanitize_text_field((string)($req->get_param('product') ?: '')),
                'from'=>(string)($stats['from'] ?? ''),
                'to'=>(string)($stats['to'] ?? ''),
            ];

            // Reuses the already proven Analytics 2.0 envelope. generateInsights()
            // receives the stats array directly and therefore does not rerun the
            // base SQL workload here. Its own response cache remains effective.
            $insights = AnalyticsAIService::generateInsights($stats, $context, false);
            if (!is_array($insights)) $insights = [];

            $store = [];
            if (class_exists(FieldFlowAskService::class) && FieldFlowAskService::needsStoreIntelligence($question)) {
                try {
                    // Store Intelligence remains a second fail-open path. Repeated
                    // follow-up questions in the same exact scope reuse a short,
                    // user-bound cache so the conversational UI does not recompute
                    // hundreds of PDV profiles on every message.
                    $cachePayload = [
                        'version'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : 'dev',
                        'viewer'=>get_current_user_id(),
                        'audience'=>$audience,
                        'client_id'=>$context['client_id'],
                        'project_id'=>$context['project_id'],
                        'route_id'=>$context['route_id'],
                        'user_id'=>$context['user_id'],
                        'location_id'=>$context['location_id'],
                        'category'=>$context['category'],
                        'product'=>$context['product'],
                        'from'=>$context['from'],
                        'to'=>$context['to'],
                    ];
                    $storeCacheKey = 'ff_ask_store_' . substr(hash('sha256', wp_json_encode($cachePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 36);
                    $cachedStore = get_transient($storeCacheKey);
                    if (is_array($cachedStore) && !empty($cachedStore)) {
                        $store = $cachedStore;
                    } else {
                        $storeResponse = $this->storeIntelligence($req);
                        if (!is_wp_error($storeResponse)) {
                            $storeData = $storeResponse instanceof WP_REST_Response ? $storeResponse->get_data() : $storeResponse;
                            if (is_array($storeData)) {
                                $store = $storeData;
                                if (!empty($store)) set_transient($storeCacheKey, $store, 120);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $store = [];
                }
            }

            $history = $req->get_param('history');
            if (!is_array($history)) $history = [];
            $answer = FieldFlowAskService::answer($question, $stats, $insights, $store, $context, $history);
            return new WP_REST_Response($answer, 200);
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('ask_fieldflow_invalid_question', $e->getMessage(), ['status'=>400]);
        } catch (\Throwable $e) {
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Perguntar ao FieldFlow falhou sem afetar Analytics base.', $e, ['context_key'=>'ask_fieldflow_endpoint']);
            }
            return new \WP_Error('ask_fieldflow_failed', 'Não foi possível responder neste momento. O Analytics base continua operacional.', ['status'=>503]);
        }
    }

    public function storeIntelligence(WP_REST_Request $req) {
        // Store Intelligence is deliberately isolated from /stats and
        // /analytics/ai-insights. A slow PDV enrichment must never blank the
        // Analytics 2.0 Command View again.
        $audience = sanitize_key((string)($req->get_param('audience') ?: 'manager'));
        if (!in_array($audience, ['client','merchandiser','manager'], true)) $audience = 'manager';
        if ($audience === 'merchandiser') {
            $req->set_param('user_id', 'me');
            $req->set_param('analytics_audience', 'merchandiser');
        } elseif ($audience === 'client') {
            $req->set_param('analytics_audience', 'client');
        } else {
            $req->set_param('analytics_audience', 'manager');
        }

        try {
            $statsResponse = $this->stats($req);
            if (is_wp_error($statsResponse)) return $statsResponse;
            $stats = $statsResponse instanceof WP_REST_Response ? $statsResponse->get_data() : $statsResponse;
            if (!is_array($stats)) return new \WP_Error('store_intelligence_invalid_stats', 'Não foi possível preparar os dados por loja.', ['status'=>500]);

            $from = sanitize_text_field((string)($stats['from'] ?? $req->get_param('from') ?? ''));
            $to = sanitize_text_field((string)($stats['to'] ?? $req->get_param('to') ?? ''));
            $stats['previous_by_location'] = [];
            if (absint($req->get_param('route_id') ?: 0) === 0 && !empty($stats['by_location']) && $from !== '' && $to !== '') {
                $periodDays = max(1, (int)floor((strtotime($to) - strtotime($from)) / DAY_IN_SECONDS) + 1);
                $previousTo = date('Y-m-d', strtotime($from . ' -1 day'));
                $previousFrom = date('Y-m-d', strtotime($previousTo . ' -' . max(0, $periodDays - 1) . ' days'));
                try { $stats['previous_by_location'] = $this->location_rows_for_range($req, $previousFrom, $previousTo); }
                catch (\Throwable $e) { $stats['previous_by_location'] = []; }
            }

            $userParam = $req->get_param('user_id');
            $resolvedUser = ($userParam === 'me' || $userParam === 'current') ? get_current_user_id() : absint($userParam ?: 0);
            $context = [
                'audience'=>$audience,
                'client_id'=>absint($req->get_param('client_id') ?: 0),
                'project_id'=>absint($req->get_param('project_id') ?: 0),
                'route_id'=>absint($req->get_param('route_id') ?: 0),
                'user_id'=>$resolvedUser,
                'location_id'=>absint($req->get_param('location_id') ?: 0),
                'from'=>$from,
                'to'=>$to,
            ];

            // Keep Store Intelligence on the same temporal truth model as
            // operational scores. Future planned stops must not look like weak
            // execution simply because their visit date has not arrived yet.
            if (class_exists('\RoutesPro\Analytics\TemporalAnalyticsService')) {
                $stats = \RoutesPro\Analytics\TemporalAnalyticsService::forEvaluation($stats);
            }

            $confidence = [];
            if (class_exists('\RoutesPro\Analytics\OperationalIntelligenceService')) {
                try {
                    $base = \RoutesPro\Analytics\OperationalIntelligenceService::analyse($stats, $context, [], [], false, false);
                    $confidence = is_array($base['confidence'] ?? null) ? $base['confidence'] : [];
                } catch (\Throwable $e) { $confidence = []; }
            }

            if (!class_exists('\RoutesPro\Analytics\StoreOpportunityRiskService')) {
                return new WP_REST_Response(['available'=>false,'reason'=>'store_engine_unavailable','mode'=>'isolated_core'], 200);
            }

            // 2.2.439 intentionally starts with a resilient core profile. Exact
            // per-store stock and learning enrichment are deferred to a later
            // progressive pass, so large projects cannot take the base UI down.
            $result = \RoutesPro\Analytics\StoreOpportunityRiskService::build(
                $stats,
                $context,
                $confidence,
                [],
                ['include_stock'=>false,'include_learning'=>false]
            );
            if (!is_array($result)) $result = [];

            // 2.2.441 reintroduces learning as a single grouped, read-only pass.
            // It can only adjust presentation confidence and Store ranking. It
            // never changes Health, Risk, Opportunity or the base priority score.
            if (!empty($result['available']) && class_exists('\RoutesPro\Analytics\AdaptiveIntelligenceService')) {
                try {
                    $result = \RoutesPro\Analytics\AdaptiveIntelligenceService::enrichStoreIntelligence($result, $context);
                } catch (\Throwable $e) {
                    if (class_exists('\RoutesPro\Support\Logger')) {
                        \RoutesPro\Support\Logger::warning('Adaptive Store ranking indisponível; mantido ranking determinístico.', ['context_key'=>'adaptive_store_ranking'], ['error'=>$e->getMessage()]);
                    }
                }
            }
            if (!empty($result['available']) && class_exists('\RoutesPro\Analytics\PredictiveIntelligenceService')) {
                try {
                    $result = \RoutesPro\Analytics\PredictiveIntelligenceService::enrichStoreIntelligence($result, $context);
                } catch (\Throwable $e) {
                    if (class_exists('\RoutesPro\Support\Logger')) {
                        \RoutesPro\Support\Logger::warning('Predictive Store Intelligence indisponível; mantida leitura atual.', ['context_key'=>'predictive_store_ranking'], ['error'=>$e->getMessage()]);
                    }
                }
            }
            if (!empty($result['available']) && class_exists('\RoutesPro\Analytics\ContextualPredictiveIntelligenceService')) {
                try {
                    $result = \RoutesPro\Analytics\ContextualPredictiveIntelligenceService::enrichStoreIntelligence($result, $context, []);
                } catch (\Throwable $e) {
                    if (class_exists('\RoutesPro\Support\Logger')) {
                        \RoutesPro\Support\Logger::warning('Contextual Next Best Action indisponível; mantida Store Intelligence.', ['context_key'=>'contextual_store_actions'], ['error'=>$e->getMessage()]);
                    }
                }
            }
            $result['mode'] = 'isolated_adaptive_predictive_contextual';
            $result['enrichment_deferred'] = ['stock'];
            $result['base_analytics_independent'] = true;
            return new WP_REST_Response($result, 200);
        } catch (\Throwable $e) {
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Store Intelligence endpoint falhou sem afetar Analytics base.', $e, ['context_key'=>'analytics_store_endpoint']);
            }
            return new \WP_Error('store_intelligence_failed', 'Store Intelligence temporariamente indisponível. O Analytics base continua operacional.', ['status'=>503]);
        }
    }

    private function build_form_analytics(WP_REST_Request $req, string $from, string $to, array $statsBase = []): array {
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $bindings_table = $px . 'form_analytics_bindings';
        $dashboards_table = $px . 'analytics_dashboards';
        $groups_table = $px . 'analytics_store_groups';
        $items_table = $px . 'analytics_store_group_items';
        $records_table = $px . 'form_records';
        $versions_table = $px . 'form_record_versions';
        $values_table = $px . 'form_record_values';
        $submissions_table = $px . 'form_submissions';
        $client_id  = absint($req->get_param('client_id') ?: 0);
        $project_id = absint($req->get_param('project_id') ?: 0);
        $route_id   = absint($req->get_param('route_id') ?: 0);
        $location_filter = absint($req->get_param('location_id') ?: 0);
        $product_filter = sanitize_text_field((string)($req->get_param('product') ?: ''));
        $category_filter = sanitize_text_field((string)($req->get_param('category') ?: ''));
        $user_param = $req->get_param('user_id');
        $user_filter = ($user_param === 'me' || $user_param === 'current') ? get_current_user_id() : absint($user_param ?: 0);
        $analytics_audience = sanitize_key((string)($req->get_param('analytics_audience') ?: ''));
        // Facts are never paginated; presentation is limited only after canonical aggregation.
        $export_full = true;
        $report_export = rest_sanitize_boolean($req->get_param('report_export'));

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bindings_table));
        if ($exists !== $bindings_table) return ['dashboards' => [], 'metrics' => []];

        $dashboard_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $dashboards_table)) === $dashboards_table;
        $group_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $groups_table)) === $groups_table;
        $item_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $items_table)) === $items_table;

        $dashboards = [];
        if ($dashboard_exists) {
            $dash_where = ['is_active=1'];
            $dash_args = [];
            if ($client_id) { $dash_where[] = '(client_id IS NULL OR client_id=0 OR client_id=%d)'; $dash_args[] = $client_id; }
            if ($project_id) { $dash_where[] = '(project_id IS NULL OR project_id=0 OR project_id=%d)'; $dash_args[] = $project_id; }
            if ($route_id) { $dash_where[] = '(route_id IS NULL OR route_id=0 OR route_id=%d)'; $dash_args[] = $route_id; }
            if ($analytics_audience === 'client') {
                $dash_where[] = "visibility IN ('client_portal','both','all','')";
            } elseif ($analytics_audience === 'merchandiser') {
                $dash_where[] = "visibility IN ('fieldflow_app','both','all')";
            }
            $dash_sql = "SELECT * FROM {$dashboards_table} WHERE " . implode(' AND ', $dash_where) . " ORDER BY sort_order ASC, id ASC" . ($export_full ? '' : ' LIMIT 100');
            $dashboards = $dash_args ? ($wpdb->get_results($wpdb->prepare($dash_sql, ...$dash_args), ARRAY_A) ?: []) : ($wpdb->get_results($dash_sql, ARRAY_A) ?: []);
        }
        $dashboard_map = [];
        foreach ($dashboards as $d) {
            $dashboard_map[(int)$d['id']] = [
                'id' => (int)$d['id'],
                'title' => (string)$d['title'],
                'description' => (string)($d['description'] ?? ''),
                'layout_type' => (string)($d['layout_type'] ?? 'mixed'),
                'visibility' => (string)($d['visibility'] ?? 'client_portal'),
                'ai_enabled' => (int)($d['ai_enabled'] ?? 0),
                'ai_objective' => (string)($d['ai_objective'] ?? ''),
                'ai_settings' => (($decodedAiSettings = json_decode((string)($d['ai_settings_json'] ?? ''), true)) && is_array($decodedAiSettings)) ? $decodedAiSettings : [],
                'sort_order' => (int)($d['sort_order'] ?? 10),
            ];
        }

        $bindings = $wpdb->get_results("SELECT * FROM {$bindings_table} WHERE is_active = 1 ORDER BY id ASC" . ($export_full ? '' : ' LIMIT 500'), ARRAY_A) ?: [];
        if (!$bindings && !$dashboards) return ['dashboards' => [], 'metrics' => []];
        $form_question_types = $this->analytics_form_question_types(array_values(array_unique(array_map(static function(array $binding): int { return (int)($binding['form_id'] ?? 0); }, $bindings))));

        $client_names = []; $project_names = []; $location_names = [];
        foreach (($wpdb->get_results("SELECT id,name FROM {$px}clients", ARRAY_A) ?: []) as $r) { $client_names[(int)$r['id']] = (string)$r['name']; }
        foreach (($wpdb->get_results("SELECT id,name FROM {$px}projects", ARRAY_A) ?: []) as $r) { $project_names[(int)$r['id']] = (string)$r['name']; }
        foreach (($wpdb->get_results("SELECT id,name,city,address FROM {$px}locations", ARRAY_A) ?: []) as $r) {
            $id = (int)($r['id'] ?? 0);
            $name = trim((string)($r['name'] ?? ''));
            $city = trim((string)($r['city'] ?? ''));
            $address = trim((string)($r['address'] ?? ''));
            if ($name === '') $name = $address !== '' ? $address : ($city !== '' ? $city : ('Loja #' . $id));
            elseif ($city !== '' && stripos($name, $city) === false) $name .= ' · ' . $city;
            $location_names[$id] = $name;
        }

        // Mantém a prioridade do briefing também em dashboards criados antes da 2.2.144.
        $briefing_question_keys = [];
        $context_table = class_exists('\RoutesPro\Forms\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::table() : $px . 'report_context_questions';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $context_table)) === $context_table) {
            $context_where = ["is_active=1", "(source_type='briefing' OR briefing_id>0)"];
            $context_args = [];
            if ($client_id) { $context_where[] = '(client_id=0 OR client_id=%d)'; $context_args[] = $client_id; }
            if ($project_id) { $context_where[] = '(project_id=0 OR project_id=%d)'; $context_args[] = $project_id; }
            if ($location_filter) { $context_where[] = '(location_id=0 OR location_id=%d)'; $context_args[] = $location_filter; }
            $context_sql = 'SELECT DISTINCT form_id,question_key FROM ' . $context_table . ' WHERE ' . implode(' AND ', $context_where);
            $context_rows = $context_args ? ($wpdb->get_results($wpdb->prepare($context_sql, ...$context_args), ARRAY_A) ?: []) : ($wpdb->get_results($context_sql, ARRAY_A) ?: []);
            foreach ($context_rows as $context_row) {
                $briefing_question_keys[(int)($context_row['form_id'] ?? 0) . ':' . sanitize_key((string)($context_row['question_key'] ?? ''))] = true;
            }
        }

        $metrics = [];
        $legacy_dashboard_added = false;
        foreach ($bindings as $binding) {
            $settings = json_decode((string)($binding['settings_json'] ?? ''), true);
            if (!is_array($settings)) $settings = [];
            $bClient = absint($settings['client_id'] ?? 0);
            $bProject = absint($settings['project_id'] ?? 0);
            $bRoute = absint($settings['route_id'] ?? 0);
            $dashboard_id = absint($settings['dashboard_id'] ?? 0);
            $store_group_id = absint($settings['store_group_id'] ?? 0);
            $show_empty = array_key_exists('show_empty', $settings) ? (bool)$settings['show_empty'] : true;
            $show_table = array_key_exists('show_table', $settings) ? (bool)$settings['show_table'] : true;
            $show_kpi = array_key_exists('show_kpi', $settings) ? (bool)$settings['show_kpi'] : ((string)$binding['chart_type'] === 'kpi');
            $sort_order = (int)($settings['sort_order'] ?? 10);
            $managedQuestionKeys = array_values(array_unique(array_filter(array_map('sanitize_key', (array)($settings['managed_question_keys'] ?? [])))));
            $representativeQuestionKey = sanitize_key((string)($binding['question_key'] ?? ''));
            if ($representativeQuestionKey !== '' && !in_array($representativeQuestionKey, $managedQuestionKeys, true)) $managedQuestionKeys[] = $representativeQuestionKey;
            $questionKeys = $managedQuestionKeys ?: [$representativeQuestionKey];
            $questionKeys = array_values(array_filter($questionKeys));
            if (!$questionKeys) continue;
            $question_type_key = (int)($binding['form_id'] ?? 0) . ':' . $representativeQuestionKey;
            $configured_question_type = sanitize_key((string)($settings['question_type'] ?? ($form_question_types[$question_type_key] ?? '')));

            if ($client_id && $bClient && $client_id !== $bClient) continue;
            if ($project_id && $bProject && $project_id !== $bProject) continue;
            if ($route_id && $bRoute && $route_id !== $bRoute) continue;
            if ($dashboard_id && !isset($dashboard_map[$dashboard_id])) continue;
            if (!$dashboard_id) {
                $dashboard_id = 0;
                if (!$legacy_dashboard_added) {
                    $dashboard_map[0] = ['id'=>0,'title'=>'Analytics operacional','description'=>'Métricas analíticas configuradas no Intelligence Center.','layout_type'=>'mixed','visibility'=>'both','ai_enabled'=>0,'ai_objective'=>'','ai_settings'=>[],'sort_order'=>999];
                    $legacy_dashboard_added = true;
                }
            }

            $store_ids = [];
            if ($store_group_id) {
                if (!$group_exists || !$item_exists) continue;
                $store_ids = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT location_id FROM {$items_table} WHERE group_id=%d", $store_group_id)) ?: []);
                if (!$store_ids) continue;
            }
            if ($location_filter) {
                if ($store_group_id && !in_array($location_filter, $store_ids, true)) continue;
                $store_ids = [$location_filter];
            }

            if (RouteMobilityWidgetService::isSystemSettings($settings)) {
                $systemMetric = RouteMobilityWidgetService::buildMetric(
                    $binding,
                    $settings,
                    $dashboard_map[$dashboard_id] ?? ['id'=>$dashboard_id,'title'=>'Analytics operacional','sort_order'=>999],
                    $statsBase
                );
                if (is_array($systemMetric) && $systemMetric) $metrics[] = $systemMetric;
                continue;
            }

            if (QuestionAnalyticsService::isSmartRollupBinding($binding, $settings)) {
                $smart = $this->build_smart_briefing_metric(
                    $binding, $settings, $dashboard_map, $from, $to,
                    $client_id, $project_id, $route_id, $location_filter, $user_filter,
                    $bClient, $bProject, $bRoute, $store_ids, $location_names, $export_full
                );
                if (is_array($smart) && $smart) $metrics[] = $smart;
                continue;
            }

            $questionKeyPlaceholders = implode(',', array_fill(0, count($questionKeys), '%s'));
            $where = [
                "r.form_id = %d",
                "rv.question_key IN ({$questionKeyPlaceholders})",
                $this->analytics_report_day_sql() . ' BETWEEN %s AND %s',
                SubmissionStatusPolicy::operationalSql('s'),
                "NOT EXISTS (SELECT 1 FROM {$versions_table} v_new WHERE v_new.submission_id=v.submission_id AND v_new.record_id=v.record_id AND (v_new.submitted_at>v.submitted_at OR (v_new.submitted_at=v.submitted_at AND v_new.id>v.id)))"
            ];
            $args = array_merge([(int)$binding['form_id']], $questionKeys, [$from, $to]);
            if ($client_id) { $where[] = "v.client_id = %d"; $args[] = $client_id; }
            if ($project_id) { $where[] = "v.project_id = %d"; $args[] = $project_id; }
            if ($route_id) { $where[] = "v.route_id = %d"; $args[] = $route_id; }
            if ($user_filter) {
                $where[] = "(v.user_id = %d OR v.owner_user_id = %d)";
                $args[] = $user_filter;
                $args[] = $user_filter;
            }
            list($scopeSql, $scopeArgs) = Permissions::scope_sql('', 'v.client_id', 'v.project_id');
            if ($scopeSql !== '1=1') { $where[] = $scopeSql; $args = array_merge($args, $scopeArgs); }
            if ($bClient) { $where[] = "v.client_id = %d"; $args[] = $bClient; }
            if ($bProject) { $where[] = "v.project_id = %d"; $args[] = $bProject; }
            if ($bRoute) { $where[] = "v.route_id = %d"; $args[] = $bRoute; }
            if ($store_ids) {
                $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
                $where[] = "(v.location_id IN ({$placeholders}) OR EXISTS (SELECT 1 FROM {$px}route_stops rs_scope WHERE rs_scope.id=v.route_stop_id AND rs_scope.location_id IN ({$placeholders})))";
                $args = array_merge($args, $store_ids, $store_ids);
            }
            $sql = "SELECT v.id AS version_id, v.submission_id, r.id AS record_id, rv.id AS value_id,
                           " . $this->analytics_report_day_sql() . " AS report_day,
                           v.submitted_at, v.route_id, v.route_stop_id, v.project_id, v.client_id, v.location_id, v.binding_id AS version_binding_id, v.meta_json AS version_meta_json,
                           s.route_id AS submission_route_id, s.route_stop_id AS submission_route_stop_id, s.location_id AS submission_location_id, s.binding_id AS submission_binding_id, s.meta_json AS submission_meta_json,
                           r.latest_route_id AS record_route_id, r.latest_route_stop_id AS record_route_stop_id, r.location_id AS record_location_id, r.latest_binding_id AS record_binding_id, r.meta_json AS record_meta_json,
                           rv.question_key AS source_question_key, rv.question_label, rv.value_type, rv.value_text, rv.value_number, rv.value_json
                    FROM {$values_table} rv
                    INNER JOIN {$versions_table} v ON v.id = rv.version_id AND v.record_id = rv.record_id
                    INNER JOIN {$records_table} r ON r.id = rv.record_id
                    INNER JOIN {$submissions_table} s ON s.id = v.submission_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY v.submitted_at ASC, rv.id ASC" . ($export_full ? '' : "
                    LIMIT 5000");
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
            $this->hydrate_analytics_location_names($rows, $location_names);
            if ($store_ids) $rows = array_values(array_filter($rows, static fn(array $row): bool => in_array((int)($row['_analytics_location_id'] ?? $row['location_id'] ?? 0), $store_ids, true)));
            $source_rows = $rows;
            $product_options = [];
            $category_options = [];
            $expanded_rows = [];
            $has_fronts_payload = false;
            $has_order_payload = false;
            $has_share_payload = false;
            foreach ($rows as $row) {
                $decoded = !empty($row['value_json']) ? json_decode((string)$row['value_json'], true) : null;
                $matrix_rows = $this->analytics_matrix_rows($decoded);
                $is_matrix = !empty($matrix_rows);
                if (!$is_matrix) {
                    if ($category_filter !== '' || $product_filter !== '') continue;
                    $expanded_rows[] = $row;
                    continue;
                }
                foreach ($matrix_rows as $matrix_item_index => $mrow) {
                    if (!is_array($mrow)) continue;
                    $row_is_share = $configured_question_type === 'share_space_matrix'
                        || array_key_exists('pr_fronts', $mrow)
                        || array_key_exists('competitor_fronts', $mrow)
                        || array_key_exists('pr_share', $mrow);
                    $row_is_order = !$row_is_share && ($configured_question_type === 'order_suggestion'
                        || array_key_exists('order_qty', $mrow)
                        || array_key_exists('boxes', $mrow)
                        || array_key_exists('units_total', $mrow));
                    $row_is_fronts = !$row_is_share && !$row_is_order && (
                        $configured_question_type === 'product_matrix'
                        || array_key_exists('before', $mrow)
                        || array_key_exists('after', $mrow)
                    );
                    if ($row_is_share) $has_share_payload = true;
                    if ($row_is_fronts) $has_fronts_payload = true;
                    if ($row_is_order) $has_order_payload = true;
                    $category = $row_is_share ? 'Share Space' : trim((string)($mrow['category'] ?? $mrow['categoria'] ?? ''));
                    $ref = trim((string)($mrow['ref'] ?? $mrow['reference'] ?? ''));
                    $name = $row_is_share ? trim((string)($mrow['metric'] ?? 'Share Space')) : trim((string)($mrow['name'] ?? $mrow['product'] ?? ''));
                    $product_label = $row_is_share ? $name : trim(($ref ? $ref . ' · ' : '') . ($name ?: 'Produto'));
                    $label = trim(($category ? $category . ' | ' : '') . $product_label);
                    $qty = $mrow['order_qty'] ?? ($mrow['boxes'] ?? ($mrow['qty'] ?? ($mrow['after'] ?? ($mrow['quantity'] ?? ''))));
                    if ($product_label === '') continue;
                    if ($category !== '') $category_options[$category] = $category;
                    $product_options[$label] = $label;
                    if ($category_filter !== '' && $category_filter !== $category) continue;
                    if ($product_filter !== '' && $product_filter !== $label && $product_filter !== $product_label) continue;
                    $r2 = $row;
                    $r2['source_item_index'] = $matrix_item_index;
                    $r2['product_category'] = $category;
                    $r2['product_label'] = $product_label;
                    $r2['product_filter_label'] = $label;
                    $r2['matrix_row'] = 1;
                    $r2['matrix_version_id'] = (int)($row['version_id'] ?? 0);
                    $r2['matrix_record_id'] = (int)($row['record_id'] ?? 0);
                    $r2['value_text'] = $label;
                    $r2['item_type'] = sanitize_text_field((string)($mrow['item_type'] ?? $mrow['type'] ?? ''));
                    $r2['capacity_cl'] = is_numeric($mrow['capacity_cl'] ?? null) ? (float)$mrow['capacity_cl'] : null;
                    $r2['units_per_case'] = is_numeric($mrow['units_per_case'] ?? null) ? (float)$mrow['units_per_case'] : null;
                    if ($row_is_share) {
                        $r2['analytics_row_type'] = 'share_space_matrix';
                        $r2['share_metric_key'] = sanitize_key((string)($mrow['metric_key'] ?? ''));
                        $r2['share_metric'] = sanitize_text_field((string)($mrow['metric'] ?? $product_label));
                        $r2['pr_label'] = sanitize_text_field((string)($mrow['pr_label'] ?? 'Produto Pernod Ricard'));
                        $r2['competitor_label'] = sanitize_text_field((string)($mrow['competitor_label'] ?? 'Concorrência'));
                        $r2['target'] = is_numeric($mrow['target'] ?? null) ? (float)$mrow['target'] : 0.0;
                        $r2['pr_fronts'] = is_numeric($mrow['pr_fronts'] ?? null) ? (float)$mrow['pr_fronts'] : 0.0;
                        $r2['competitor_fronts'] = is_numeric($mrow['competitor_fronts'] ?? null) ? (float)$mrow['competitor_fronts'] : 0.0;
                        $r2['total_fronts'] = is_numeric($mrow['total_fronts'] ?? null) ? (float)$mrow['total_fronts'] : ($r2['pr_fronts'] + $r2['competitor_fronts']);
                        $r2['pr_share'] = is_numeric($mrow['pr_share'] ?? null) ? (float)$mrow['pr_share'] : ($r2['total_fronts'] > 0 ? round(($r2['pr_fronts'] / $r2['total_fronts']) * 100, 2) : 0.0);
                        $r2['competitor_share'] = is_numeric($mrow['competitor_share'] ?? null) ? (float)$mrow['competitor_share'] : max(0.0, 100.0 - $r2['pr_share']);
                        $r2['share_gap'] = is_numeric($mrow['gap'] ?? null) ? (float)$mrow['gap'] : round($r2['pr_share'] - $r2['target'], 2);
                        $r2['share_status'] = sanitize_key((string)($mrow['status'] ?? ($r2['total_fronts'] <= 0 ? 'no_fronts' : ($r2['share_gap'] < 0 ? 'below' : 'on_or_above'))));
                        $r2['value_number'] = $r2['pr_share'];
                        $r2['value_text'] = $r2['share_metric'];
                    } elseif ($row_is_order) {
                        $r2['order_qty'] = is_numeric($qty) ? (float)$qty : null;
                        $r2['units_total'] = is_numeric($mrow['units_total'] ?? null)
                            ? (float)$mrow['units_total']
                            : (($r2['order_qty'] !== null && $r2['units_per_case'] !== null) ? round($r2['order_qty'] * $r2['units_per_case'], 2) : null);
                        $r2['notes'] = sanitize_textarea_field((string)($mrow['notes'] ?? $mrow['observations'] ?? ''));
                        $r2['value_number'] = $r2['order_qty'];
                        $r2['analytics_row_type'] = 'order_suggestion';
                    } else {
                        $before = $mrow['before'] ?? null;
                        $after = $mrow['after'] ?? $qty;
                        $r2['value_before'] = is_numeric($before) ? (float)$before : null;
                        $r2['value_after'] = is_numeric($after) ? (float)$after : (is_numeric($qty) ? (float)$qty : null);
                        $normalized_before = $r2['value_before'] !== null ? (float)$r2['value_before'] : 0.0;
                        $normalized_after = $r2['value_after'] !== null ? (float)$r2['value_after'] : 0.0;
                        $r2['value_difference'] = round($normalized_after - $normalized_before, 2);
                        $r2['value_growth'] = $normalized_before > 0
                            ? round((($normalized_after - $normalized_before) / $normalized_before) * 100, 1)
                            : null;
                        $r2['value_growth_label'] = $normalized_before <= 0 && $normalized_after > 0
                            ? 'Novo'
                            : ($normalized_before > 0 && $normalized_after <= 0
                                ? 'Removido'
                                : (($r2['value_growth'] !== null ? ($r2['value_growth'] > 0 ? '+' : '') . number_format((float)$r2['value_growth'], 1, ',', '.') . '%' : '0,0%')));
                        $r2['value_growth_kind'] = $normalized_after > $normalized_before ? 'up' : ($normalized_after < $normalized_before ? 'down' : 'same');
                        $r2['value_number'] = $r2['value_after'];
                        $r2['analytics_row_type'] = 'product_matrix';
                    }
                    $expanded_rows[] = $r2;
                }
            }
            $rows = $expanded_rows;
            $detected_question_type = $configured_question_type;
            if ($detected_question_type === '' && !empty($source_rows[0]['value_type'])) $detected_question_type = sanitize_key((string)$source_rows[0]['value_type']);
            if ($has_share_payload) $detected_question_type = 'share_space_matrix';
            elseif ($has_order_payload) $detected_question_type = 'order_suggestion';
            elseif ($has_fronts_payload) $detected_question_type = 'product_matrix';
            if ((string)$binding['chart_type'] === 'image_gallery' && !in_array($detected_question_type, ['image_upload', 'file_upload', 'categorized_image_upload'], true)) $detected_question_type = 'image_upload';
            $is_share_binding = $detected_question_type === 'share_space_matrix' || $has_share_payload;
            $is_fronts_binding = $detected_question_type === 'product_matrix' && !$has_order_payload;
            $is_evidence_binding = in_array($detected_question_type, ['image_upload','file_upload','categorized_image_upload'], true) || (string)$binding['chart_type'] === 'image_gallery';
            $managedEvidenceKeys = array_values(array_unique(array_filter(array_map('sanitize_key', (array)($settings['managed_evidence_question_keys'] ?? [])))));
            $extraEvidenceKeys = array_values(array_diff($managedEvidenceKeys, $questionKeys));
            $relatedEvidenceRows = [];
            if ($extraEvidenceKeys) {
                $relatedEvidenceRows = $this->smart_briefing_value_rows(
                    (int)$binding['form_id'], $extraEvidenceKeys, $from, $to,
                    $client_id ?: $bClient, $project_id ?: $bProject, $route_id ?: $bRoute,
                    $user_filter, $store_ids, $location_names, $export_full
                );
            }
            $has_related_evidence = $is_evidence_binding || !empty($managedEvidenceKeys);
            $evidence_source_rows = $has_related_evidence ? array_merge($source_rows, $relatedEvidenceRows) : [];

            $group_name = '';
            if ($store_group_id && $group_exists) $group_name = (string)($wpdb->get_var($wpdb->prepare("SELECT name FROM {$groups_table} WHERE id=%d", $store_group_id)) ?: '');
            $is_briefing_priority = !empty($settings['briefing_priority']);
            foreach ($questionKeys as $sourceQuestionKey) {
                $briefingMetricKey = (int)$binding['form_id'] . ':' . $sourceQuestionKey;
                if (!empty($briefing_question_keys[$briefingMetricKey]) || !empty($briefing_question_keys['0:' . $sourceQuestionKey])) { $is_briefing_priority = true; break; }
            }
            $sourceQuestionLabels = [];
            foreach ($source_rows as $sourceRow) {
                $sourceLabel = sanitize_text_field((string)($sourceRow['question_label'] ?? ''));
                if ($sourceLabel !== '') $sourceQuestionLabels[$sourceLabel] = $sourceLabel;
            }
            foreach ((array)($settings['managed_question_labels'] ?? []) as $sourceLabel) {
                $sourceLabel = sanitize_text_field((string)$sourceLabel);
                if ($sourceLabel !== '') $sourceQuestionLabels[$sourceLabel] = $sourceLabel;
            }
            $sourceQuestionLabels = array_values($sourceQuestionLabels);
            $latestSourceRow = $source_rows ? $source_rows[count($source_rows) - 1] : [];
            $evergreenBriefing = !empty($settings['evergreen_briefing']);
            $metric = [
                'binding_id' => (int)($binding['id'] ?? 0),
                'dashboard_id' => $dashboard_id,
                'dashboard_title' => (string)($dashboard_map[$dashboard_id]['title'] ?? 'Analytics operacional'),
                'dashboard_sort_order' => (int)($dashboard_map[$dashboard_id]['sort_order'] ?? 999),
                'question_key' => (string)$binding['question_key'],
                'question_keys' => $questionKeys,
                'source_question_count' => count($questionKeys),
                'question_labels' => $sourceQuestionLabels,
                'question_label' => (string)($latestSourceRow['question_label'] ?? $binding['metric_label']),
                'question_type' => $detected_question_type,
                'is_fronts_metric' => $is_fronts_binding ? 1 : 0,
                'is_share_space_metric' => $is_share_binding ? 1 : 0,
                'metric_key' => (string)$binding['metric_key'],
                'metric_label' => (string)$binding['metric_label'],
                'chart_type' => (string)$binding['chart_type'],
                'aggregation' => (string)$binding['aggregation'],
                'aggregation_label' => $this->analytics_aggregation_name((string)$binding['aggregation']),
                'dimension' => (string)$binding['dimension'],
                'dimension_label' => $this->analytics_dimension_name((string)$binding['dimension']),
                'secondary_dimension' => (string)($settings['secondary_dimension'] ?? ''),
                'secondary_dimension_label' => $this->analytics_dimension_name((string)($settings['secondary_dimension'] ?? '')),
                'evidence_grouping' => sanitize_key((string)($settings['evidence_grouping'] ?? 'location_date')),
                'evidence_show_thumbnails' => array_key_exists('evidence_show_thumbnails', $settings) ? (int)!empty($settings['evidence_show_thumbnails']) : 1,
                'store_group_id' => $store_group_id,
                'store_group_name' => $group_name,
                'show_empty' => $show_empty ? 1 : 0,
                'show_table' => $show_table ? 1 : 0,
                'show_kpi' => $show_kpi ? 1 : 0,
                'sort_order' => $sort_order,
                'series' => [],
                'rows' => [],
                'value' => null,
                'note' => '',
                'record_count' => count($rows),
                'submission_integrity' => 'active_latest_only',
                'ai_reason' => sanitize_text_field((string)($settings['ai_reason'] ?? ($is_briefing_priority ? 'Prioridade definida no briefing operacional e cruzada apenas com reportes válidos.' : ''))),
                'briefing_priority' => $is_briefing_priority ? 1 : 0,
                'source_type' => sanitize_key((string)($settings['source_type'] ?? ($is_briefing_priority ? 'briefing' : 'analytics'))),
                'briefing_id' => absint($settings['briefing_id'] ?? 0),
                'briefing_ids' => array_values(array_unique(array_filter(array_map('absint', (array)($settings['briefing_ids'] ?? []))))),
                'evergreen_briefing' => $evergreenBriefing ? 1 : 0,
                'canonical_metric_key' => sanitize_key((string)($settings['canonical_metric_key'] ?? $binding['metric_key'] ?? '')),
                'orchestration_pinned' => !empty($settings['orchestration_pinned']) ? 1 : 0,
                'category_options' => array_values(array_unique($category_options)),
                'category_filter' => $category_filter,
                'product_options' => array_values(array_unique($product_options)),
                'product_filter' => $product_filter,
                'files' => $has_related_evidence ? $this->analytics_files_from_rows($evidence_source_rows, $export_full ? 0 : 250) : [],
                'evidence_groups' => $has_related_evidence ? $this->analytics_evidence_groups($evidence_source_rows, $location_names, $export_full) : [],
                'question_breakdown' => count($questionKeys) > 1 ? $this->analytics_question_breakdown($source_rows, $location_names) : [],
            ];

            $chart = (string)$binding['chart_type'];
            $aggregation = (string)$binding['aggregation'];
            $dimension = (string)$binding['dimension'];

            if ($is_evidence_binding) {
                $evidence_groups = (array)$metric['evidence_groups'];
                $total_images = 0;
                $dates_seen = [];
                $series_by_date = [];
                foreach ($evidence_groups as $group) {
                    $group_count = (int)($group['count'] ?? 0);
                    $total_images += $group_count;
                    foreach ((array)($group['dates'] ?? []) as $date_group) {
                        $date_key = (string)($date_group['date'] ?? '');
                        if ($date_key !== '') {
                            $dates_seen[$date_key] = true;
                            if (!isset($series_by_date[$date_key])) $series_by_date[$date_key] = 0;
                            $series_by_date[$date_key] += (int)($date_group['count'] ?? 0);
                        }
                        $image_count = (int)($date_group['count'] ?? 0);
                        $metric['rows'][] = [
                            'date' => $date_key,
                            'date_label' => (string)($date_group['date_label'] ?? $date_key),
                            'value' => $image_count . ' ' . ($image_count === 1 ? 'imagem' : 'imagens'),
                            'image_count' => $image_count,
                            'location' => (string)($group['location'] ?? 'Sem loja'),
                            'location_id' => (int)($group['location_id'] ?? 0),
                            'route' => (int)($date_group['route_id'] ?? 0),
                            'status' => $image_count > 0 ? 'Com imagens' : 'Sem imagens',
                        ];
                    }
                    if (empty($group['dates'])) {
                        $metric['rows'][] = [
                            'date' => '',
                            'date_label' => 'Sem data',
                            'value' => $group_count . ' ' . ($group_count === 1 ? 'imagem' : 'imagens'),
                            'image_count' => $group_count,
                            'location' => (string)($group['location'] ?? 'Sem loja'),
                            'location_id' => (int)($group['location_id'] ?? 0),
                            'route' => 0,
                            'status' => $group_count > 0 ? 'Com imagens' : 'Sem imagens',
                        ];
                    }
                }
                $metric['value'] = $total_images;
                $metric['record_count'] = $total_images;
                $metric['submission_count'] = count($source_rows);
                $metric['evidence_location_count'] = count($evidence_groups);
                $metric['evidence_date_count'] = count($dates_seen);
                $metric['note'] = $total_images > 0
                    ? ($total_images . ' ' . ($total_images === 1 ? 'imagem' : 'imagens') . ' · ' . count($evidence_groups) . ' ' . (count($evidence_groups) === 1 ? 'loja' : 'lojas') . ' · ' . count($dates_seen) . ' ' . (count($dates_seen) === 1 ? 'data' : 'datas'))
                    : 'Sem imagens no período selecionado';
                if ($dimension === 'submitted_at') {
                    krsort($series_by_date);
                    foreach ($series_by_date as $date => $count) $metric['series'][] = ['label' => $date, 'value' => $count];
                } else {
                    foreach ($evidence_groups as $group) $metric['series'][] = ['label' => (string)($group['location'] ?? 'Sem loja'), 'value' => (int)($group['count'] ?? 0)];
                }
                if (!$metric['rows'] && $show_empty) {
                    $scope_locations = $this->analytics_scope_locations($client_id ?: $bClient, $project_id ?: $bProject, $store_ids, $export_full, $user_filter);
                    $scope_locations = $export_full ? $scope_locations : array_slice($scope_locations, 0, 1000);
                    foreach ($scope_locations as $loc) {
                        $metric['rows'][] = [
                            'date' => '', 'date_label' => '', 'value' => '0 imagens', 'image_count' => 0,
                            'location' => (string)($loc['name'] ?? ('#' . (int)($loc['id'] ?? 0))),
                            'location_id' => (int)($loc['id'] ?? 0), 'route' => 0, 'status' => 'Sem imagens',
                        ];
                    }
                }
                if (!$show_empty && $total_images === 0) continue;
                $metrics[] = $metric;
                continue;
            }

            if ($is_share_binding) {
                $metricKeys = []; $locationKeys = []; $series = []; $below = 0; $onTarget = 0;
                $shareTotal = 0.0; $gapTotal = 0.0; $prFrontsTotal = 0.0; $competitorFrontsTotal = 0.0;
                foreach ($rows as $row) {
                    $metricName = (string)($row['share_metric'] ?? $row['product_label'] ?? 'Share Space');
                    $metricKeys[(string)($row['share_metric_key'] ?? sanitize_key($metricName))] = true;
                    $locationKeys[(int)($row['_analytics_location_id'] ?? $row['location_id'] ?? 0)] = true;
                    $share = (float)($row['pr_share'] ?? 0); $gap = (float)($row['share_gap'] ?? 0);
                    $shareTotal += $share; $gapTotal += $gap;
                    $prFrontsTotal += (float)($row['pr_fronts'] ?? 0); $competitorFrontsTotal += (float)($row['competitor_fronts'] ?? 0);
                    if ($gap < 0) $below++; else $onTarget++;
                    if (!isset($series[$metricName])) $series[$metricName] = ['total'=>0.0,'target_total'=>0.0,'gap_total'=>0.0,'count'=>0];
                    $series[$metricName]['total'] += $share;
                    $series[$metricName]['target_total'] += (float)($row['target'] ?? 0);
                    $series[$metricName]['gap_total'] += $gap;
                    $series[$metricName]['count']++;
                    $metric['rows'][] = [
                        'date'=>substr((string)$row['submitted_at'],0,10),
                        'value'=>round($share,1).'%',
                        'location'=>(string)($row['_analytics_location_name'] ?? ($location_names[(int)($row['location_id'] ?? 0)] ?? ('#'.(int)($row['location_id'] ?? 0)))),
                        'location_id'=>(int)($row['_analytics_location_id'] ?? $row['location_id'] ?? 0),
                        'route'=>(int)($row['route_id'] ?? 0),
                        'metric'=>$metricName,
                        'product'=>$metricName,
                        'pr_label'=>(string)($row['pr_label'] ?? ''),
                        'competitor_label'=>(string)($row['competitor_label'] ?? ''),
                        'target'=>round((float)($row['target'] ?? 0),1),
                        'pr_fronts'=>round((float)($row['pr_fronts'] ?? 0),2),
                        'competitor_fronts'=>round((float)($row['competitor_fronts'] ?? 0),2),
                        'total_fronts'=>round((float)($row['total_fronts'] ?? 0),2),
                        'pr_share'=>round($share,1),
                        'competitor_share'=>round((float)($row['competitor_share'] ?? 0),1),
                        'gap'=>round($gap,1),
                        'share_status'=>(string)($row['share_status'] ?? ''),
                        'status'=>$gap < 0 ? 'Abaixo do objetivo' : 'Objetivo atingido',
                    ];
                }
                $count = count($rows);
                foreach ($series as $label => $values) {
                    $seriesCount = max(1, (int)$values['count']);
                    $metric['series'][] = [
                        'label'=>$label,
                        'value'=>round($values['total']/$seriesCount,1),
                        'target'=>round($values['target_total']/$seriesCount,1),
                        'gap'=>round($values['gap_total']/$seriesCount,1),
                    ];
                }
                $metric['value'] = $count ? round($shareTotal/$count,1) : null;
                $metric['share_space_summary'] = [
                    'metric_count'=>count($metricKeys),'stores_count'=>count(array_filter(array_keys($locationKeys))),
                    'below_target'=>$below,'on_target'=>$onTarget,'avg_share'=>$metric['value'],
                    'avg_gap'=>$count ? round($gapTotal/$count,1) : null,'pr_fronts_total'=>round($prFrontsTotal,2),
                    'competitor_fronts_total'=>round($competitorFrontsTotal,2),
                ];
                $metric['record_count'] = $count;
                $metric['note'] = $count ? (count($metricKeys).' comparações · '.count(array_filter(array_keys($locationKeys))).' lojas · '.$below.' abaixo do objetivo') : 'Sem dados Share Space no período selecionado';
                if (!$show_empty && !$rows) continue;
                $metrics[] = $metric;
                continue;
            }

            $metric['value'] = $rows ? $this->aggregate_metric_value($rows, $aggregation) : null;
            if ($rows && $aggregation === 'latest' && $this->has_matrix_rows($rows)) {
                $currentScopes = $this->latest_matrix_scope_count($rows);
                $metric['note'] = 'Estado atual · ' . $currentScopes . ' ' . ($currentScopes === 1 ? 'loja' : 'lojas') . ' · ' . count($rows) . ' referências analisadas';
            } else {
                $metric['note'] = $rows ? (ucfirst($aggregation) . ' · ' . count($rows) . ' registos') : 'Sem dados no período selecionado';
            }

            if ($chart === 'pie') {
                $buckets = [];
                foreach ($rows as $row) {
                    $label = trim((string)($row['value_text'] ?? ''));
                    if ($label === '') $label = $row['value_number'] !== null ? (string)$row['value_number'] : 'Sem valor';
                    if (!isset($buckets[$label])) $buckets[$label] = 0;
                    $buckets[$label] += ($aggregation === 'sum' && $row['value_number'] !== null) ? (float)$row['value_number'] : 1;
                }
                foreach ($buckets as $label => $value) $metric['series'][] = ['label' => (string)$label, 'value' => round((float)$value, 2)];
            } elseif ($chart !== 'kpi' && $chart !== 'table') {
                $buckets = [];
                foreach ($rows as $row) {
                    $label = $this->dimension_label($row, $dimension, $client_names, $project_names, $location_names);
                    if (!isset($buckets[$label])) $buckets[$label] = [];
                    $buckets[$label][] = $row;
                }
                foreach ($buckets as $label => $groupRows) $metric['series'][] = ['label' => (string)$label, 'value' => $this->aggregate_metric_value($groupRows, $aggregation)];
            }

            if ($is_fronts_binding) {
                $metric['fronts_summary'] = ['total'=>0,'up'=>0,'same'=>0,'down'=>0,'new'=>0,'removed'=>0,'before_total'=>0.0,'after_total'=>0.0,'difference_total'=>0.0,'growth_percent'=>null,'growth_label'=>'0,0%'];
            }
            $comparisonRows = array_values(array_filter($rows, static function(array $row): bool {
                if (!array_key_exists('value_before', $row) && !array_key_exists('value_after', $row)) return true;
                $before = $row['value_before'] !== null && is_numeric($row['value_before']) ? (float)$row['value_before'] : 0.0;
                $after = $row['value_after'] !== null && is_numeric($row['value_after']) ? (float)$row['value_after'] : 0.0;
                return abs($before) > 0.00001 || abs($after) > 0.00001;
            }));
            $hasComparisonRows = false;
            foreach ($comparisonRows as $comparisonRow) {
                if (array_key_exists('value_before', $comparisonRow) || array_key_exists('value_after', $comparisonRow)) { $hasComparisonRows = true; break; }
            }
            if ($is_fronts_binding && $hasComparisonRows) {
                usort($comparisonRows, static function(array $a, array $b): int {
                    $score = static function(array $row): float {
                        $before = $row['value_before'] !== null && is_numeric($row['value_before']) ? (float)$row['value_before'] : 0.0;
                        $after = $row['value_after'] !== null && is_numeric($row['value_after']) ? (float)$row['value_after'] : 0.0;
                        if ($before <= 0 && $after > 0) return 1000000.0 + $after;
                        if ($before > 0 && $after <= 0) return 900000.0 + $before;
                        return abs((float)($row['value_growth'] ?? 0));
                    };
                    $cmp = $score($b) <=> $score($a);
                    if ($cmp !== 0) return $cmp;
                    return strcmp((string)($a['product_label'] ?? ''), (string)($b['product_label'] ?? ''));
                });
                $summary = ['total' => count($comparisonRows), 'up' => 0, 'same' => 0, 'down' => 0, 'new' => 0, 'removed' => 0, 'before_total' => 0.0, 'after_total' => 0.0, 'difference_total' => 0.0, 'growth_percent' => null, 'growth_label' => '0,0%'];
                foreach ($comparisonRows as $comparisonRow) {
                    $before = $comparisonRow['value_before'] !== null && is_numeric($comparisonRow['value_before']) ? (float)$comparisonRow['value_before'] : 0.0;
                    $after = $comparisonRow['value_after'] !== null && is_numeric($comparisonRow['value_after']) ? (float)$comparisonRow['value_after'] : 0.0;
                    $summary['before_total'] += $before; $summary['after_total'] += $after;
                    if ($before <= 0 && $after > 0) { $summary['new']++; $summary['up']++; }
                    elseif ($before > 0 && $after <= 0) { $summary['removed']++; $summary['down']++; }
                    elseif ($after > $before) $summary['up']++;
                    elseif ($after < $before) $summary['down']++;
                    else $summary['same']++;
                }
                $summary['before_total'] = round($summary['before_total'], 2);
                $summary['after_total'] = round($summary['after_total'], 2);
                $summary['difference_total'] = round($summary['after_total'] - $summary['before_total'], 2);
                if ($summary['before_total'] > 0) {
                    $summary['growth_percent'] = round(($summary['difference_total'] / $summary['before_total']) * 100, 1);
                    $summary['growth_label'] = ($summary['growth_percent'] > 0 ? '+' : '') . number_format((float)$summary['growth_percent'], 1, ',', '.') . '%';
                } elseif ($summary['after_total'] > 0) $summary['growth_label'] = 'Novo crescimento';
                $metric['fronts_summary'] = $summary;
                $metric['is_fronts_metric'] = 1;
                $metric['record_count'] = count($comparisonRows);
                $metric['note'] = count($comparisonRows) . ' referências com frentes · ' . $summary['growth_label'] . ' de evolução total';
            }
            $recent = $hasComparisonRows ? $comparisonRows : array_reverse($rows);
            if (!$export_full) $recent = array_slice($recent, 0, 80);
            foreach ($recent as $row) {
                $metric['rows'][] = [
                    'date' => (string)($row['report_day'] ?? substr((string)$row['submitted_at'], 0, 10)),
                    'submitted_at' => (string)($row['submitted_at'] ?? ''),
                    'submission_id' => (int)($row['submission_id'] ?? 0),
                    'version_id' => (int)($row['version_id'] ?? 0),
                    'record_id' => (int)($row['record_id'] ?? 0),
                    'value_id' => (int)($row['value_id'] ?? 0),
                    'source_question_key' => (string)($row['source_question_key'] ?? ''),
                    'source_item_index' => $row['source_item_index'] ?? null,
                    'value' => $this->analytics_row_value_label($row),
                    'before' => array_key_exists('value_before', $row) && $row['value_before'] !== null ? round((float)$row['value_before'], 2) : '',
                    'after' => array_key_exists('value_after', $row) && $row['value_after'] !== null ? round((float)$row['value_after'], 2) : '',
                    'difference' => array_key_exists('value_difference', $row) && $row['value_difference'] !== null ? round((float)$row['value_difference'], 2) : '',
                    'growth' => (string)($row['value_growth_label'] ?? (array_key_exists('value_growth', $row) && $row['value_growth'] !== null ? (($row['value_growth'] > 0 ? '+' : '') . number_format((float)$row['value_growth'], 1, ',', '.') . '%') : '')),
                    'growth_percent' => array_key_exists('value_growth', $row) && $row['value_growth'] !== null ? round((float)$row['value_growth'], 1) : '',
                    'growth_kind' => (string)($row['value_growth_kind'] ?? ''),
                    'location' => (string)($row['_analytics_location_name'] ?? ($location_names[(int)($row['location_id'] ?? 0)] ?? ('#' . (int)($row['location_id'] ?? 0)))),
                    'location_id' => (int)($row['_analytics_location_id'] ?? $row['location_id'] ?? 0),
                    'route' => (int)($row['route_id'] ?? 0),
                    'category' => (string)($row['product_category'] ?? ''),
                    'product' => (string)($row['product_label'] ?? ''),
                    'order_qty' => array_key_exists('order_qty', $row) && $row['order_qty'] !== null ? round((float)$row['order_qty'], 2) : '',
                    'units_per_case' => array_key_exists('units_per_case', $row) && $row['units_per_case'] !== null ? round((float)$row['units_per_case'], 2) : '',
                    'units_total' => array_key_exists('units_total', $row) && $row['units_total'] !== null ? round((float)$row['units_total'], 2) : '',
                    'notes' => (string)($row['notes'] ?? ''),
                    'item_type' => (string)($row['item_type'] ?? ''),
                    'capacity_cl' => array_key_exists('capacity_cl', $row) && $row['capacity_cl'] !== null ? round((float)$row['capacity_cl'], 2) : '',
                    'status' => !empty($row['product_label']) ? trim(((string)($row['product_category'] ?? '') ? 'Categoria: ' . (string)$row['product_category'] . ' · ' : '') . 'Produto: ' . (string)$row['product_label']) : 'Com dados',
                ];
            }
            if (!$metric['rows'] && $show_empty) {
                $scope_locations = $this->analytics_scope_locations($client_id ?: $bClient, $project_id ?: $bProject, $store_ids, $export_full, $user_filter);
                $scope_locations = $export_full ? $scope_locations : array_slice($scope_locations, 0, 1000);
                foreach ($scope_locations as $loc) {
                    $metric['rows'][] = [
                        'date' => '',
                        'value' => '',
                        'location' => (string)($loc['name'] ?? ('#' . (int)($loc['id'] ?? 0))),
                        'location_id' => (int)($loc['id'] ?? 0),
                        'route' => 0,
                        'status' => 'Sem dados',
                    ];
                }
            }
            if (!$show_empty && !$rows) continue;
            $metrics[] = $metric;
        }
        usort($metrics, function($a, $b) use ($dashboard_map){
            $ad = (int)($a['dashboard_id'] ?? 0); $bd = (int)($b['dashboard_id'] ?? 0);
            $ado = (int)($dashboard_map[$ad]['sort_order'] ?? 999);
            $bdo = (int)($dashboard_map[$bd]['sort_order'] ?? 999);
            if ($ado !== $bdo) return $ado <=> $bdo;
            if ($ad !== $bd) return $ad <=> $bd;
            $ao = (int)($a['sort_order'] ?? 10); $bo = (int)($b['sort_order'] ?? 10);
            if ($ao !== $bo) return $ao <=> $bo;
            return ((int)($a['binding_id'] ?? 0)) <=> ((int)($b['binding_id'] ?? 0));
        });
        $dashboards_out = array_values($dashboard_map);
        usort($dashboards_out, function($a,$b){
            $ao = (int)($a['sort_order'] ?? 10); $bo = (int)($b['sort_order'] ?? 10);
            return $ao === $bo ? ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)) : ($ao <=> $bo);
        });

        // 2.2.431 Widget Intelligence. The same dashboard registry now decides
        // which widgets are relevant to each audience. Full/report exports keep
        // every metric and receive recommendation metadata only, so no source
        // data is lost when the front is intentionally simplified.
        // Keep the full, annotated registry internally for a second pass after
        // route/store intelligence is available. The public payload is still
        // compact in automatic mode, while reports preserve every metric.
        $preserveAll = $export_full || $report_export;
        $orchestration = WidgetOrchestrator::orchestrate(
            $metrics,
            $dashboards_out,
            $analytics_audience,
            [
                'client_id'=>$client_id,
                'project_id'=>$project_id,
                'route_id'=>$route_id,
                'user_id'=>$user_filter,
                'location_id'=>$location_filter,
                'from'=>$from,
                'to'=>$to,
            ],
            true
        );
        $allMetrics = (array)($orchestration['metrics'] ?? $metrics);
        $publicMetrics = $allMetrics;
        if (!$preserveAll) {
            $publicMetrics = array_values(array_filter($allMetrics, static function($metric): bool {
                if (!is_array($metric)) return false;
                return (string)($metric['orchestration_mode'] ?? 'automatic') !== 'automatic' || !empty($metric['orchestration_recommended']);
            }));
        }
        return [
            'dashboards' => $dashboards_out,
            'metrics' => $publicMetrics,
            'orchestration' => $orchestration,
            '_orchestration_all_metrics' => $allMetrics,
        ];
    }


    private function build_smart_briefing_metric(array $binding, array $settings, array $dashboard_map, string $from, string $to, int $client_id, int $project_id, int $route_id, int $location_filter, int $user_filter, int $bClient, int $bProject, int $bRoute, array $store_ids, array &$location_names, bool $export_full): array {
        global $wpdb;
        $contextTable = class_exists('\\RoutesPro\\Forms\\ContextQuestions') ? \RoutesPro\Forms\ContextQuestions::table() : $wpdb->prefix . 'routespro_report_context_questions';
        $formId = absint($binding['form_id'] ?? 0);
        $dashboardId = absint($settings['dashboard_id'] ?? 0);
        $kind = sanitize_key((string)($settings['briefing_rollup_kind'] ?? 'operational'));
        $effectiveClient = $client_id ?: $bClient;
        $effectiveProject = $project_id ?: $bProject;
        $effectiveRoute = $route_id ?: $bRoute;
        $dashboard = $dashboard_map[$dashboardId] ?? ['id'=>$dashboardId,'title'=>'Briefing semanal','sort_order'=>10];
        if ($kind === 'operational_evidence') {
            return $this->build_operational_evidence_metric($binding,$settings,$dashboard,$from,$to,$effectiveClient,$effectiveProject,$effectiveRoute,$user_filter,$store_ids,$location_names,$export_full);
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $contextTable)) !== $contextTable) return [];

        $where = ["analytics_enabled=1", "is_active=1", "(source_type='briefing' OR briefing_id>0)", "valid_from<=%s", "valid_to>=%s"];
        $args = [$to, $from];
        if ($formId) { $where[] = '(form_id=0 OR form_id=%d)'; $args[] = $formId; }
        if ($effectiveClient) { $where[] = '(client_id=0 OR client_id=%d)'; $args[] = $effectiveClient; }
        if ($effectiveProject) { $where[] = '(project_id=0 OR project_id=%d)'; $args[] = $effectiveProject; }
        if ($dashboardId) { $where[] = '(analytics_dashboard_id=0 OR analytics_dashboard_id=%d)'; $args[] = $dashboardId; }
        $sql = 'SELECT * FROM ' . $contextTable . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY priority ASC,briefing_id ASC,id ASC';
        $contextRows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];

        $scopeLocations = $this->analytics_scope_locations($effectiveClient, $effectiveProject, $store_ids, $export_full, $user_filter);
        $allowed = [];
        foreach ($scopeLocations as $loc) $allowed[(int)($loc['id'] ?? 0)] = true;
        if ($location_filter) $allowed = [$location_filter => true];

        $groups = [];
        foreach ($contextRows as $row) {
            if (QuestionAnalyticsService::analyticsBehaviorFromRow($row, true) !== 'rollup') continue;
            $locationId = absint($row['location_id'] ?? 0);
            if ($allowed && $locationId && empty($allowed[$locationId])) continue;
            $qKey = sanitize_key((string)($row['question_key'] ?? ''));
            if ($qKey === '') continue;
            $type = sanitize_key((string)($row['question_type'] ?? 'text')) ?: 'text';
            $label = sanitize_text_field((string)($row['question_label'] ?? $qKey));
            $groupKey = QuestionAnalyticsService::canonicalMetricKey($label, $type);
            if (!isset($groups[$groupKey])) $groups[$groupKey] = [
                'label'=>$label, 'type'=>$type, 'question_keys'=>[], 'briefing_ids'=>[], 'target_store_ids'=>[], 'target_assignment_keys'=>[], 'priority'=>absint($row['priority'] ?? 9999),
            ];
            $groups[$groupKey]['question_keys'][$qKey] = $qKey;
            $bid = absint($row['briefing_id'] ?? 0); if ($bid) $groups[$groupKey]['briefing_ids'][$bid] = $bid;
            if ($locationId) {
                $groups[$groupKey]['target_store_ids'][$locationId] = $locationId;
                $groups[$groupKey]['target_assignment_keys'][$qKey . ':' . $locationId] = $qKey . ':' . $locationId;
            }
            $groups[$groupKey]['priority'] = min((int)$groups[$groupKey]['priority'], absint($row['priority'] ?? 9999));
        }
        if (!$groups) return [];
        uasort($groups, static fn(array $a,array $b):int => ((int)$a['priority'] <=> (int)$b['priority']) ?: strcasecmp((string)$a['label'],(string)$b['label']));

        if ($kind === 'evidence') return $this->build_smart_briefing_evidence_metric($binding,$settings,$dashboard,$groups,$from,$to,$effectiveClient,$effectiveProject,$effectiveRoute,$user_filter,$location_names,$export_full);

        $questions=[];$targetAssignments=0;$answeredAssignments=0;$allTargetStores=[];$allAnsweredStores=[];$totalRecords=0;
        foreach ($groups as $groupKey=>$group) {
            $keys=array_values($group['question_keys']);$targetIds=array_values($group['target_store_ids']);
            $rows=$this->smart_briefing_value_rows($formId,$keys,$from,$to,$effectiveClient,$effectiveProject,$effectiveRoute,$user_filter,$targetIds,$location_names,$export_full);
            $summary=$this->smart_briefing_response_summary($rows,(string)$group['type']);
            $answeredStores=[];$answeredAssignmentKeys=[];
            foreach($rows as $row){
                $lid=absint($row['_analytics_location_id']??$row['location_id']??0);
                $sourceKey=sanitize_key((string)($row['source_question_key']??''));
                if($lid){$answeredStores[$lid]=$lid;if($sourceKey!=='')$answeredAssignmentKeys[$sourceKey.':'.$lid]=true;}
            }
            $targetStoreCount=count($targetIds);$answeredStoreCount=count($answeredStores);$pendingStores=max(0,$targetStoreCount-$answeredStoreCount);
            $targetAssignmentCount=count((array)($group['target_assignment_keys']??[]));
            $answeredAssignmentCount=count($answeredAssignmentKeys);$pendingAssignmentCount=max(0,$targetAssignmentCount-$answeredAssignmentCount);
            $targetAssignments+=$targetAssignmentCount;$answeredAssignments+=$answeredAssignmentCount;$totalRecords+=count($rows);
            foreach($targetIds as $id)$allTargetStores[$id]=$id;foreach($answeredStores as $id)$allAnsweredStores[$id]=$id;
            $questions[]=[
                'key'=>$groupKey,'label'=>$group['label'],'type'=>$group['type'],'source_question_keys'=>$keys,'briefing_ids'=>array_values($group['briefing_ids']),
                'target_stores'=>$targetStoreCount,'answered_stores'=>$answeredStoreCount,'pending_stores'=>$pendingStores,
                'target_store_ids'=>array_values(array_map('intval',$targetIds)),'answered_store_ids'=>array_values(array_map('intval',array_values($answeredStores))),
                'target_assignments'=>$targetAssignmentCount,'answered_assignments'=>$answeredAssignmentCount,'pending_assignments'=>$pendingAssignmentCount,
                'target_assignment_keys'=>array_values(array_keys((array)($group['target_assignment_keys']??[]))),'answered_assignment_keys'=>array_values(array_keys($answeredAssignmentKeys)),
                'coverage_percent'=>$targetAssignmentCount?round(($answeredAssignmentCount/$targetAssignmentCount)*100,1):0,
                'response_count'=>count($rows),'yes'=>$summary['yes'],'no'=>$summary['no'],'na'=>$summary['na'],
                'conformity_percent'=>$summary['yes']+$summary['no']>0?round(($summary['yes']/($summary['yes']+$summary['no']))*100,1):null,
                'distribution'=>$summary['distribution'],'samples'=>$summary['samples'],'latest_date'=>$summary['latest_date'],
            ];
        }
        $pendingAssignments=max(0,$targetAssignments-$answeredAssignments);
        $series=[];foreach($questions as $q)$series[]=['label'=>(string)$q['label'],'value'=>(float)$q['coverage_percent']];
        return [
            'binding_id'=>(int)($binding['id']??0),'dashboard_id'=>(int)($dashboard['id']??0),'dashboard_title'=>(string)($dashboard['title']??'Briefing semanal'),'dashboard_sort_order'=>(int)($dashboard['sort_order']??10),
            'form_id'=>$formId,'question_key'=>'__briefing_operational_rollup','question_label'=>'Briefing operacional','metric_key'=>'briefing_operacional','metric_label'=>'Briefing operacional',
            'chart_type'=>'briefing_rollup','aggregation'=>'count','aggregation_label'=>'Cobertura','dimension'=>'location_id','dimension_label'=>'Loja','secondary_dimension'=>'','secondary_dimension_label'=>'',
            'scope_mode'=>(string)($binding['scope_mode']??''),'sort_order'=>(int)($settings['sort_order']??15),'show_kpi'=>0,'show_table'=>0,'show_empty'=>1,
            'series'=>$series,'rows'=>[],'value'=>count($questions).' pergunta(s)','note'=>'Perguntas variáveis reunidas automaticamente no dashboard permanente.','record_count'=>$totalRecords,
            'briefing_priority'=>1,'source_type'=>'briefing','evergreen_briefing'=>1,'canonical_metric_key'=>'briefing_operacional','smart_briefing_rollup'=>1,'orchestration_pinned'=>!empty($settings['orchestration_pinned'])?1:0,
            'briefing_questions'=>$questions,'question_count'=>count($questions),'target_assignments'=>$targetAssignments,'answered_assignments'=>$answeredAssignments,'pending_assignments'=>$pendingAssignments,
            'coverage_percent'=>$targetAssignments?round(($answeredAssignments/$targetAssignments)*100,1):0,'target_store_count'=>count($allTargetStores),'answered_store_count'=>count($allAnsweredStores),
            'files'=>[],'evidence_groups'=>[],
        ];
    }

    private function build_operational_evidence_metric(array $binding,array $settings,array $dashboard,string $from,string $to,int $clientId,int $projectId,int $routeId,int $userId,array $storeIds,array &$locationNames,bool $exportFull): array {
        $formId=absint($binding['form_id']??0);
        $keys=array_values(array_unique(array_filter(array_map('sanitize_key',(array)($settings['managed_question_keys']??[])))));
        $keys=array_values(array_filter($keys,static fn(string $key):bool=>$key!==''&&strpos($key,'__')!==0));
        if(!$formId||!$keys)return[];
        $rows=$this->smart_briefing_value_rows($formId,$keys,$from,$to,$clientId,$projectId,$routeId,$userId,$storeIds,$locationNames,$exportFull);
        $labels=(array)($settings['managed_question_labels_by_key']??[]);
        $byKey=[];
        foreach($keys as $key)$byKey[$key]=[];
        foreach($rows as $row){$key=sanitize_key((string)($row['source_question_key']??''));if($key!==''&&isset($byKey[$key]))$byKey[$key][]=$row;}
        $questions=[];$allFiles=[];$allRows=[];$total=0;
        foreach($byKey as $key=>$questionRows){
            $label='';foreach($questionRows as $row){$candidate=sanitize_text_field((string)($row['question_label']??''));if($candidate!==''){$label=$candidate;break;}}
            if($label==='')$label=sanitize_text_field((string)($labels[$key]??$key));
            $filesAll=$this->analytics_files_from_rows($questionRows,0);$files=$exportFull?$filesAll:array_slice($filesAll,0,250);$groups=$this->analytics_evidence_groups($questionRows,$locationNames,$exportFull);$locations=[];
            foreach($questionRows as $row){$lid=absint($row['_analytics_location_id']??$row['location_id']??0);if($lid)$locations[$lid]=$lid;}
            $questions[]=['key'=>$key,'label'=>$label,'type'=>'image_upload','source_question_keys'=>[$key],'evidence_question_keys'=>[$key],'briefing_ids'=>[],'target_stores'=>0,'location_count'=>count($locations),'image_count'=>count($filesAll),'files'=>$files,'evidence_groups'=>$groups];
            $total+=count($filesAll);$allFiles=array_merge($allFiles,$files);$allRows=array_merge($allRows,$questionRows);
        }
        $scopeLocations=$this->analytics_scope_locations($clientId,$projectId,$storeIds,$exportFull,$userId);
        $allGroups=$this->analytics_evidence_groups($allRows,$locationNames,$exportFull);if(!$exportFull)$allFiles=array_slice($allFiles,0,250);
        return [
            'binding_id'=>(int)($binding['id']??0),'dashboard_id'=>(int)($dashboard['id']??0),'dashboard_title'=>(string)($dashboard['title']??'Briefing semanal'),'dashboard_sort_order'=>(int)($dashboard['sort_order']??10),
            'form_id'=>$formId,'question_key'=>'__operational_evidence_rollup','question_label'=>'Evidências operacionais','metric_key'=>'evidencias_operacionais','metric_label'=>'Evidências operacionais',
            'chart_type'=>'briefing_evidence_rollup','aggregation'=>'evidence','aggregation_label'=>'Evidências','dimension'=>'location_id','dimension_label'=>'Loja','secondary_dimension'=>'submitted_at','secondary_dimension_label'=>'Data',
            'scope_mode'=>(string)($binding['scope_mode']??''),'sort_order'=>(int)($settings['sort_order']??130),'show_kpi'=>0,'show_table'=>0,'show_empty'=>1,
            'series'=>[],'rows'=>[],'value'=>$total,'note'=>'Fotografias operacionais permanentes reunidas por tipo e loja.','record_count'=>$total,
            'briefing_priority'=>0,'source_type'=>'analytics','evergreen_briefing'=>0,'canonical_metric_key'=>'evidencias_operacionais','smart_briefing_rollup'=>1,'operational_evidence_rollup'=>1,'orchestration_pinned'=>!empty($settings['orchestration_pinned'])?1:0,
            'briefing_evidence_questions'=>$questions,'question_count'=>count($questions),'target_store_count'=>count($scopeLocations),'files'=>$allFiles,'evidence_groups'=>$allGroups,
        ];
    }

    private function build_smart_briefing_evidence_metric(array $binding,array $settings,array $dashboard,array $groups,string $from,string $to,int $clientId,int $projectId,int $routeId,int $userId,array &$locationNames,bool $exportFull): array {
        $formId=absint($binding['form_id']??0);$questions=[];$allEvidenceRows=[];$allFiles=[];$allGroups=[];$totalImages=0;$targetStores=[];
        foreach($groups as $groupKey=>$group){
            $type=sanitize_key((string)$group['type']);
            if(!in_array($type,['evidence_check','image_upload','categorized_image_upload','file_upload'],true))continue;
            $sourceKeys=array_values($group['question_keys']);$evidenceKeys=$sourceKeys;
            if($type==='evidence_check')$evidenceKeys=array_values(array_map(static fn(string $key):string=>EvidenceFlow::photo_key_for_parent($key),$sourceKeys));
            $targetIds=array_values($group['target_store_ids']);foreach($targetIds as $id)$targetStores[$id]=$id;
            $rows=$this->smart_briefing_value_rows($formId,$evidenceKeys,$from,$to,$clientId,$projectId,$routeId,$userId,$targetIds,$locationNames,$exportFull);
            $filesAll=$this->analytics_files_from_rows($rows,0);$files=$exportFull?$filesAll:array_slice($filesAll,0,250);$evidenceGroups=$this->analytics_evidence_groups($rows,$locationNames,$exportFull);
            $imageCount=count($filesAll);$locationIds=[];foreach($rows as $row){$lid=absint($row['_analytics_location_id']??$row['location_id']??0);if($lid)$locationIds[$lid]=$lid;}
            $questions[]=['key'=>$groupKey,'label'=>$group['label'],'type'=>$type,'source_question_keys'=>$sourceKeys,'evidence_question_keys'=>$evidenceKeys,'briefing_ids'=>array_values($group['briefing_ids']),'target_stores'=>count($targetIds),'location_count'=>count($locationIds),'image_count'=>$imageCount,'files'=>$files,'evidence_groups'=>$evidenceGroups];
            $totalImages+=$imageCount;$allEvidenceRows=array_merge($allEvidenceRows,$rows);$allFiles=array_merge($allFiles,$files);
        }
        if(!$questions)return [];
        $allGroups=$this->analytics_evidence_groups($allEvidenceRows,$locationNames,$exportFull);
        if(!$exportFull)$allFiles=array_slice($allFiles,0,250);
        return [
            'binding_id'=>(int)($binding['id']??0),'dashboard_id'=>(int)($dashboard['id']??0),'dashboard_title'=>(string)($dashboard['title']??'Briefing semanal'),'dashboard_sort_order'=>(int)($dashboard['sort_order']??10),
            'form_id'=>$formId,'question_key'=>'__briefing_evidence_rollup','question_label'=>'Evidências do briefing','metric_key'=>'briefing_evidencias','metric_label'=>'Evidências do briefing',
            'chart_type'=>'briefing_evidence_rollup','aggregation'=>'evidence','aggregation_label'=>'Evidências','dimension'=>'location_id','dimension_label'=>'Loja','secondary_dimension'=>'submitted_at','secondary_dimension_label'=>'Data',
            'scope_mode'=>(string)($binding['scope_mode']??''),'sort_order'=>(int)($settings['sort_order']??25),'show_kpi'=>0,'show_table'=>0,'show_empty'=>1,
            'series'=>[],'rows'=>[],'value'=>$totalImages,'note'=>'Fotografias e ficheiros das perguntas variáveis do briefing, agrupados por pergunta.','record_count'=>$totalImages,
            'briefing_priority'=>1,'source_type'=>'briefing','evergreen_briefing'=>1,'canonical_metric_key'=>'briefing_evidencias','smart_briefing_rollup'=>1,'orchestration_pinned'=>!empty($settings['orchestration_pinned'])?1:0,
            'briefing_evidence_questions'=>$questions,'question_count'=>count($questions),'target_store_count'=>count($targetStores),'files'=>$allFiles,'evidence_groups'=>$allGroups,
        ];
    }

    private function analytics_report_day_sql(): string {
        global $wpdb;
        return "COALESCE(NULLIF(s.report_day,''),(SELECT NULLIF(day_route.date,'') FROM {$wpdb->prefix}routespro_routes day_route WHERE day_route.id=COALESCE(NULLIF(v.route_id,0),NULLIF(s.route_id,0)) LIMIT 1),DATE(v.submitted_at))";
    }

    private function smart_briefing_value_rows(int $formId,array $questionKeys,string $from,string $to,int $clientId,int $projectId,int $routeId,int $userId,array $locationIds,array &$locationNames,bool $exportFull): array {
        global $wpdb;$px=$wpdb->prefix.'routespro_';$questionKeys=array_values(array_unique(array_filter(array_map('sanitize_key',$questionKeys))));if(!$formId||!$questionKeys)return[];
        $values=$px.'form_record_values';$versions=$px.'form_record_versions';$records=$px.'form_records';$submissions=$px.'form_submissions';
        $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$values));if($exists!==$values)return[];
        $ph=implode(',',array_fill(0,count($questionKeys),'%s'));
        $where=["r.form_id=%d","rv.question_key IN ({$ph})",$this->analytics_report_day_sql() . ' BETWEEN %s AND %s',SubmissionStatusPolicy::operationalSql('s'),"NOT EXISTS (SELECT 1 FROM {$versions} v_new WHERE v_new.submission_id=v.submission_id AND v_new.record_id=v.record_id AND (v_new.submitted_at>v.submitted_at OR (v_new.submitted_at=v.submitted_at AND v_new.id>v.id)))"];
        $args=array_merge([$formId],$questionKeys,[$from,$to]);
        if($clientId){$where[]='v.client_id=%d';$args[]=$clientId;}if($projectId){$where[]='v.project_id=%d';$args[]=$projectId;}if($routeId){$where[]='v.route_id=%d';$args[]=$routeId;}if($userId){$where[]='(v.user_id=%d OR v.owner_user_id=%d)';$args[]=$userId;$args[]=$userId;}
        list($scopeSql,$scopeArgs)=Permissions::scope_sql('','v.client_id','v.project_id');if($scopeSql!=='1=1'){$where[]=$scopeSql;$args=array_merge($args,$scopeArgs);}
        $locationIds=array_values(array_unique(array_filter(array_map('intval',$locationIds))));if($locationIds){$lp=implode(',',array_fill(0,count($locationIds),'%d'));$where[]="(v.location_id IN ({$lp}) OR EXISTS (SELECT 1 FROM {$px}route_stops rs_scope WHERE rs_scope.id=v.route_stop_id AND rs_scope.location_id IN ({$lp}))";$args=array_merge($args,$locationIds,$locationIds);}
        $sql="SELECT v.id AS version_id,r.id AS record_id,rv.id AS value_id,v.submitted_at,v.route_id,v.route_stop_id,v.project_id,v.client_id,v.location_id,v.binding_id AS version_binding_id,v.meta_json AS version_meta_json,s.route_id AS submission_route_id,s.route_stop_id AS submission_route_stop_id,s.location_id AS submission_location_id,s.binding_id AS submission_binding_id,s.meta_json AS submission_meta_json,r.latest_route_id AS record_route_id,r.latest_route_stop_id AS record_route_stop_id,r.location_id AS record_location_id,r.latest_binding_id AS record_binding_id,r.meta_json AS record_meta_json,rv.question_key AS source_question_key,rv.question_label,rv.value_type,rv.value_text,rv.value_number,rv.value_json FROM {$values} rv INNER JOIN {$versions} v ON v.id=rv.version_id AND v.record_id=rv.record_id INNER JOIN {$records} r ON r.id=rv.record_id INNER JOIN {$submissions} s ON s.id=v.submission_id WHERE ".implode(' AND ',$where).' ORDER BY v.submitted_at ASC,rv.id ASC'.($exportFull?'':' LIMIT 5000');
        $rows=$wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A)?:[];$this->hydrate_analytics_location_names($rows,$locationNames);return$rows;
    }

    private function smart_briefing_response_summary(array $rows,string $type): array {
        $distribution=[];$yes=0;$no=0;$na=0;$samples=[];$latest='';
        foreach($rows as $row){$label=$this->smart_briefing_value_label($row);if($label==='')continue;$token=$this->smart_briefing_answer_token($label);if(in_array($token,['sim','yes','conforme','implementado'],true))$yes++;elseif(in_array($token,['nao','no','não','nao conforme','não conforme'],true))$no++;elseif(strpos($token,'aplic')!==false)$na++;$distribution[$label]=($distribution[$label]??0)+1;$date=(string)($row['submitted_at']??'');if($date>$latest)$latest=$date;if(in_array(sanitize_key($type),['text','textarea'],true)&&count($samples)<6)$samples[]=['value'=>$label,'location'=>(string)($row['_analytics_location_name']??''),'date'=>$date];}
        arsort($distribution);$dist=[];foreach(array_slice($distribution,0,20,true)as $label=>$count)$dist[]=['label'=>$label,'value'=>$count];return['yes'=>$yes,'no'=>$no,'na'=>$na,'distribution'=>$dist,'samples'=>$samples,'latest_date'=>$latest];
    }

    private function smart_briefing_value_label(array $row): string {
        if($row['value_number']!==null&&$row['value_number']!=='')return(string)round((float)$row['value_number'],2);$text=trim((string)($row['value_text']??''));if($text!=='')return$this->smart_briefing_clean_label($text);$json=json_decode((string)($row['value_json']??''),true);if(is_scalar($json))return$this->smart_briefing_clean_label((string)$json);if(is_array($json)){if(isset($json['value'])&&is_scalar($json['value']))return$this->smart_briefing_clean_label((string)$json['value']);$flat=[];array_walk_recursive($json,static function($v)use(&$flat){if(is_scalar($v)&&trim((string)$v)!=='')$flat[]=(string)$v;});return$this->smart_briefing_clean_label(implode(', ',array_slice($flat,0,5)));}return'';
    }

    private function smart_briefing_answer_token(string $value): string { if(function_exists('remove_accents'))$value=remove_accents($value);return strtolower(trim(preg_replace('/\\s+/',' ',$value)?:$value)); }
    private function smart_briefing_clean_label(string $value): string { $value=wp_strip_all_tags($value);$value=function_exists('mb_substr')?mb_substr($value,0,300):substr($value,0,300);return trim((string)$value); }

    private function analytics_question_breakdown(array $rows,array $locationNames): array {
        $groups=[];foreach($rows as $row){$key=sanitize_key((string)($row['source_question_key']??''));if($key==='')continue;if(!isset($groups[$key]))$groups[$key]=['question_key'=>$key,'label'=>sanitize_text_field((string)($row['question_label']??$key)),'responses'=>0,'locations'=>[],'distribution'=>[],'latest_date'=>''];$groups[$key]['responses']++;$lid=absint($row['_analytics_location_id']??$row['location_id']??0);if($lid)$groups[$key]['locations'][$lid]=$lid;$label=$this->smart_briefing_value_label($row);if($label!=='')$groups[$key]['distribution'][$label]=($groups[$key]['distribution'][$label]??0)+1;$date=(string)($row['submitted_at']??'');if($date>$groups[$key]['latest_date'])$groups[$key]['latest_date']=$date;}
        $out=[];foreach($groups as $group){arsort($group['distribution']);$dist=[];foreach(array_slice($group['distribution'],0,12,true)as $label=>$count)$dist[]=['label'=>$label,'value'=>$count];$out[]=['question_key'=>$group['question_key'],'label'=>$group['label'],'responses'=>$group['responses'],'location_count'=>count($group['locations']),'distribution'=>$dist,'latest_date'=>$group['latest_date']];}return$out;
    }

    private function analytics_form_question_types(array $form_ids): array {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', $form_ids))));
        if (!$ids) return [];
        $table = $wpdb->prefix . 'routespro_forms';
        $in = implode(',', array_fill(0, count($ids), '%d'));
        $forms = $wpdb->get_results($wpdb->prepare("SELECT id,schema_json FROM {$table} WHERE id IN ({$in})", ...$ids), ARRAY_A) ?: [];
        $out = [];
        foreach ($forms as $form) {
            $form_id = (int)($form['id'] ?? 0);
            $schema = json_decode((string)($form['schema_json'] ?? ''), true);
            if ($form_id <= 0 || !is_array($schema)) continue;
            $walk = static function($node) use (&$walk, &$out, $form_id): void {
                if (!is_array($node)) return;
                $key = sanitize_key((string)($node['key'] ?? $node['name'] ?? $node['slug'] ?? ''));
                $type = sanitize_key((string)($node['type'] ?? $node['question_type'] ?? ''));
                if ($key !== '' && $type !== '') $out[$form_id . ':' . $key] = $type;
                foreach ($node as $childKey => $child) {
                    if (in_array((string)$childKey, ['options','products','meta','theme'], true)) continue;
                    if (is_array($child)) $walk($child);
                }
            };
            $walk($schema);
        }
        return $out;
    }

    private function analytics_matrix_rows($decoded): array {
        if (!is_array($decoded) || !$decoded) return [];
        if (isset($decoded['rows']) && is_array($decoded['rows'])) $decoded = $decoded['rows'];
        $keys = array_keys($decoded);
        $is_list = $keys === range(0, count($decoded) - 1);
        if (!$is_list) {
            $matrixKeys = ['qty','quantity','before','after','ref','reference','category','categoria','name','product','item_id','order_qty','boxes','metric_key','metric','pr_fronts','competitor_fronts','pr_share'];
            $isSingleRow = false;
            foreach ($matrixKeys as $key) {
                if (array_key_exists($key, $decoded)) { $isSingleRow = true; break; }
            }
            if ($isSingleRow) $decoded = [$decoded];
            else {
                $candidateRows = [];
                foreach ($decoded as $candidate) if (is_array($candidate)) $candidateRows[] = $candidate;
                $decoded = $candidateRows;
            }
        }
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;
            $matrixKeys = ['qty','quantity','before','after','ref','reference','category','categoria','name','product','item_id','order_qty','boxes','metric_key','metric','pr_fronts','competitor_fronts','pr_share'];
            $matches = false;
            foreach ($matrixKeys as $key) {
                if (array_key_exists($key, $row)) { $matches = true; break; }
            }
            if ($matches) $rows[] = $row;
        }
        return $rows;
    }

    private function analytics_row_value_label(array $row): string {
        if (array_key_exists('value_before', $row) || array_key_exists('value_after', $row)) {
            $before = array_key_exists('value_before', $row) && $row['value_before'] !== null ? round((float)$row['value_before'], 2) : '';
            $after = array_key_exists('value_after', $row) && $row['value_after'] !== null ? round((float)$row['value_after'], 2) : '';
            $growth = (string)($row['value_growth_label'] ?? '');
            $suffix = ($growth !== '' ? ' (' . $growth . ')' : '');
            if ($before !== '' || $after !== '') return (string)$before . ' > ' . (string)$after . $suffix;
        }
        return $row['value_number'] !== null ? (string)round((float)$row['value_number'], 2) : (string)($row['value_text'] ?? '');
    }

    private function analytics_scope_locations(int $client_id = 0, int $project_id = 0, array $location_ids = [], bool $export_full = false, int $user_id = 0): array {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $ids = array_values(array_unique(array_filter(array_map('intval', $location_ids))));
        $where = ['1=1'];
        $args = [];
        if ($ids) {
            $where[] = 'l.id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';
            $args = array_merge($args, $ids);
        }
        if ($client_id && !$project_id) { $where[] = 'l.client_id=%d'; $args[] = $client_id; }
        if ($project_id) {
            $where[] = "EXISTS (SELECT 1 FROM {$px}campaign_locations cl_scope WHERE cl_scope.location_id=l.id AND cl_scope.project_id=%d)";
            $args[] = $project_id;
        }
        if ($user_id) {
            $userParts = [];
            if ($project_id) {
                $userParts[] = "EXISTS (SELECT 1 FROM {$px}campaign_locations cl_user WHERE cl_user.location_id=l.id AND cl_user.project_id=%d AND cl_user.assigned_to=%d)";
                $args[] = $project_id;
                $args[] = $user_id;
            } else {
                $userParts[] = "EXISTS (SELECT 1 FROM {$px}campaign_locations cl_user WHERE cl_user.location_id=l.id AND cl_user.assigned_to=%d)";
                $args[] = $user_id;
            }
            $routeConditions = ['rs_user.location_id=l.id', '(r_user.owner_user_id=%d OR EXISTS (SELECT 1 FROM ' . $px . 'assignments a_user WHERE a_user.route_id=r_user.id AND a_user.user_id=%d AND a_user.is_active=1))'];
            $args[] = $user_id;
            $args[] = $user_id;
            if ($project_id) { $routeConditions[] = 'r_user.project_id=%d'; $args[] = $project_id; }
            elseif ($client_id) { $routeConditions[] = 'r_user.client_id=%d'; $args[] = $client_id; }
            $userParts[] = "EXISTS (SELECT 1 FROM {$px}route_stops rs_user INNER JOIN {$px}routes r_user ON r_user.id=rs_user.route_id WHERE " . implode(' AND ', $routeConditions) . ')';
            $where[] = '(' . implode(' OR ', $userParts) . ')';
        }
        if (!$ids && !$project_id && !$client_id) return [];
        $sql = "SELECT DISTINCT l.id,l.name FROM {$px}locations l WHERE " . implode(' AND ', $where) . ' ORDER BY l.name ASC' . ($export_full ? '' : ' LIMIT 500');
        return $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
    }

    private function analytics_dimension_name(string $dimension): string {
        return [
            'submitted_at' => 'Data da visita',
            'location_id' => 'Local',
            'route_id' => 'Rota',
            'project_id' => 'Campanha',
            'client_id' => 'Cliente',
            'product' => 'Produto',
            'product_label' => 'Produto',
        ][$dimension] ?? ($dimension !== '' ? str_replace('_', ' ', $dimension) : '');
    }

    private function analytics_aggregation_name(string $aggregation): string {
        return [
            'latest' => 'Último valor',
            'sum' => 'Soma',
            'avg' => 'Média',
            'count' => 'Contagem',
            'evidence' => 'Todas as evidências',
            'growth' => 'Evolução',
        ][$aggregation] ?? ($aggregation !== '' ? str_replace('_', ' ', $aggregation) : '');
    }

    private function dimension_label(array $row, string $dimension, array $client_names, array $project_names, array $location_names): string {
        switch ($dimension) {
            case 'client_id':
                $id = (int)($row['client_id'] ?? 0); return $client_names[$id] ?? ('Cliente #' . $id);
            case 'project_id':
                $id = (int)($row['project_id'] ?? 0); return $project_names[$id] ?? ('Campanha #' . $id);
            case 'location_id':
                $id = (int)($row['_analytics_location_id'] ?? $row['location_id'] ?? 0);
                return (string)($row['_analytics_location_name'] ?? ($location_names[$id] ?? ($id ? ('Loja não identificada #' . $id) : 'Sem loja')));
            case 'route_id':
                return 'Rota #' . (int)($row['route_id'] ?? 0);
            case 'product':
            case 'product_label':
                return (string)($row['product_label'] ?? 'Produto');
            case 'submitted_at':
            default:
                return substr((string)($row['submitted_at'] ?? ''), 0, 10);
        }
    }

    private function aggregate_metric_value(array $rows, string $aggregation) {
        $count = count($rows);
        if ($aggregation === 'count' || $aggregation === 'evidence') return $count;
        $numeric = [];
        foreach ($rows as $row) {
            if (isset($row['value_number']) && $row['value_number'] !== null && $row['value_number'] !== '') $numeric[] = (float)$row['value_number'];
        }
        if ($aggregation === 'latest') {
            // Numa matriz de produtos, "Último valor" não pode significar a última
            // referência da lista. Essa referência pode ter 0 frentes e fazia o KPI
            // inteiro aparecer a zero. O estado atual é a soma da matriz mais recente
            // de cada loja dentro do âmbito selecionado.
            if ($this->has_matrix_rows($rows)) {
                return $this->aggregate_latest_matrix_value($rows);
            }
            $last = end($rows);
            if (!$last) return 0;
            if (isset($last['value_number']) && $last['value_number'] !== null && $last['value_number'] !== '') return round((float)$last['value_number'], 2);
            return (string)($last['value_text'] ?? '');
        }
        if ($aggregation === 'growth') {
            $growth = [];
            foreach ($rows as $row) if (isset($row['value_growth']) && $row['value_growth'] !== null && $row['value_growth'] !== '') $growth[] = (float)$row['value_growth'];
            return $growth ? round(array_sum($growth), 2) : 0;
        }
        if (!$numeric) return $count;
        if ($aggregation === 'avg') return round(array_sum($numeric) / max(1, count($numeric)), 2);
        return round(array_sum($numeric), 2);
    }


    private function has_matrix_rows(array $rows): bool {
        foreach ($rows as $row) {
            if (!empty($row['matrix_row']) || !empty($row['product_label'])) return true;
        }
        return false;
    }

    private function latest_matrix_scope_count(array $rows): int {
        return count($this->latest_matrix_versions_by_scope($rows));
    }

    private function aggregate_latest_matrix_value(array $rows) {
        $latestByScope = $this->latest_matrix_versions_by_scope($rows);
        if (!$latestByScope) return 0;

        $total = 0.0;
        foreach ($rows as $row) {
            if (empty($row['matrix_row']) && empty($row['product_label'])) continue;
            $scope = $this->matrix_scope_key($row);
            if (!isset($latestByScope[$scope])) continue;
            $versionId = (int)($row['matrix_version_id'] ?? $row['version_id'] ?? 0);
            $recordId = (int)($row['matrix_record_id'] ?? $row['record_id'] ?? 0);
            $signature = $this->matrix_version_signature($row, $versionId, $recordId);
            if ($signature !== $latestByScope[$scope]['signature']) continue;
            if (isset($row['value_number']) && $row['value_number'] !== null && $row['value_number'] !== '' && is_numeric($row['value_number'])) {
                $total += (float)$row['value_number'];
            }
        }
        return round($total, 2);
    }

    private function latest_matrix_versions_by_scope(array $rows): array {
        $latest = [];
        foreach ($rows as $row) {
            if (empty($row['matrix_row']) && empty($row['product_label'])) continue;
            $scope = $this->matrix_scope_key($row);
            $versionId = (int)($row['matrix_version_id'] ?? $row['version_id'] ?? 0);
            $recordId = (int)($row['matrix_record_id'] ?? $row['record_id'] ?? 0);
            $submittedAt = (string)($row['submitted_at'] ?? '');
            $signature = $this->matrix_version_signature($row, $versionId, $recordId);
            $sortKey = $submittedAt . '|' . str_pad((string)$versionId, 20, '0', STR_PAD_LEFT) . '|' . str_pad((string)$recordId, 20, '0', STR_PAD_LEFT);
            if (!isset($latest[$scope]) || strcmp($sortKey, $latest[$scope]['sort_key']) > 0) {
                $latest[$scope] = ['signature' => $signature, 'sort_key' => $sortKey];
            }
        }
        return $latest;
    }

    private function matrix_scope_key(array $row): string {
        $locationId = (int)($row['_analytics_location_id'] ?? $row['location_id'] ?? 0);
        if ($locationId > 0) return 'location:' . $locationId;
        $recordId = (int)($row['matrix_record_id'] ?? $row['record_id'] ?? 0);
        if ($recordId > 0) return 'record:' . $recordId;
        $routeId = (int)($row['route_id'] ?? 0);
        if ($routeId > 0) return 'route:' . $routeId;
        return 'global';
    }

    private function matrix_version_signature(array $row, int $versionId, int $recordId): string {
        if ($versionId > 0) return 'version:' . $versionId;
        if ($recordId > 0) return 'record:' . $recordId . '@' . (string)($row['submitted_at'] ?? '');
        return 'submitted:' . (string)($row['submitted_at'] ?? '');
    }



    private function hydrate_analytics_location_names(array &$rows, array &$location_names): void {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        if (!$rows) return;

        $snapshot_table = $px . 'route_location_snapshot';
        $has_snapshot = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $snapshot_table)) === $snapshot_table;
        $contexts = [];
        $all_location_ids = [];
        $all_stop_ids = [];
        $all_route_ids = [];
        $all_binding_ids = [];

        foreach ($rows as $index => $row) {
            $location_ids = [];
            foreach (['location_id','submission_location_id','record_location_id'] as $field) {
                $id = (int)($row[$field] ?? 0);
                if ($id > 0) $location_ids[$id] = $id;
            }
            $stop_ids = [];
            foreach (['route_stop_id','submission_route_stop_id','record_route_stop_id'] as $field) {
                $id = (int)($row[$field] ?? 0);
                if ($id > 0) $stop_ids[$id] = $id;
            }
            $route_ids = [];
            foreach (['route_id','submission_route_id','record_route_id'] as $field) {
                $id = (int)($row[$field] ?? 0);
                if ($id > 0) $route_ids[$id] = $id;
            }
            $binding_ids = [];
            foreach (['version_binding_id','submission_binding_id','record_binding_id'] as $field) {
                $id = (int)($row[$field] ?? 0);
                if ($id > 0) $binding_ids[$id] = $id;
            }

            $meta_name = '';
            foreach (['version_meta_json','submission_meta_json','record_meta_json'] as $field) {
                $meta = json_decode((string)($row[$field] ?? ''), true);
                if (!is_array($meta)) continue;
                if ($meta_name === '') $meta_name = $this->analytics_meta_location_name($meta);
                foreach ($this->analytics_meta_id_candidates($meta, ['location_id','store_id','shop_id','pdv_id']) as $id) $location_ids[$id] = $id;
                foreach ($this->analytics_meta_id_candidates($meta, ['route_stop_id','stop_id']) as $id) $stop_ids[$id] = $id;
                foreach ($this->analytics_meta_id_candidates($meta, ['route_id']) as $id) $route_ids[$id] = $id;
            }

            if ($meta_name !== '') {
                foreach ($location_ids as $location_id) {
                    if ($this->analytics_location_name_needs_resolution($location_names, $location_id)) $location_names[$location_id] = $meta_name;
                }
            }

            $contexts[$index] = [
                'location_ids' => array_values($location_ids),
                'stop_ids' => array_values($stop_ids),
                'route_ids' => array_values($route_ids),
                'binding_ids' => array_values($binding_ids),
                'meta_name' => $meta_name,
            ];
            foreach ($location_ids as $id) $all_location_ids[$id] = $id;
            foreach ($stop_ids as $id) $all_stop_ids[$id] = $id;
            foreach ($route_ids as $id) $all_route_ids[$id] = $id;
            foreach ($binding_ids as $id) $all_binding_ids[$id] = $id;
        }

        // Os bindings guardam contexto adicional e são especialmente úteis em
        // submissões antigas onde um dos IDs foi gravado no campo errado.
        if ($all_binding_ids) {
            $bindings_table = $px . 'form_bindings';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bindings_table)) === $bindings_table) {
                $bindings_by_id = [];
                foreach (array_chunk(array_values($all_binding_ids), 500) as $chunk) {
                    $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                    $sql = "SELECT id, route_id, stop_id, location_id FROM {$bindings_table} WHERE id IN ({$placeholders})";
                    foreach (($wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: []) as $binding_row) {
                        $bindings_by_id[(int)$binding_row['id']] = $binding_row;
                    }
                }
                foreach ($contexts as &$ctx) {
                    $ctx_locations = array_fill_keys(array_map('intval', (array)($ctx['location_ids'] ?? [])), true);
                    $ctx_stops = array_fill_keys(array_map('intval', (array)($ctx['stop_ids'] ?? [])), true);
                    $ctx_routes = array_fill_keys(array_map('intval', (array)($ctx['route_ids'] ?? [])), true);
                    foreach ((array)($ctx['binding_ids'] ?? []) as $binding_id) {
                        $binding_row = (array)($bindings_by_id[(int)$binding_id] ?? []);
                        $location_id = (int)($binding_row['location_id'] ?? 0);
                        $stop_id = (int)($binding_row['stop_id'] ?? 0);
                        $route_id = (int)($binding_row['route_id'] ?? 0);
                        if ($location_id > 0) { $ctx_locations[$location_id] = true; $all_location_ids[$location_id] = $location_id; }
                        if ($stop_id > 0) { $ctx_stops[$stop_id] = true; $all_stop_ids[$stop_id] = $stop_id; }
                        if ($route_id > 0) { $ctx_routes[$route_id] = true; $all_route_ids[$route_id] = $route_id; }
                    }
                    $ctx['location_ids'] = array_map('intval', array_keys($ctx_locations));
                    $ctx['stop_ids'] = array_map('intval', array_keys($ctx_stops));
                    $ctx['route_ids'] = array_map('intval', array_keys($ctx_routes));
                }
                unset($ctx);
            }
        }

        // Resolve IDs que continuam na base comercial.
        foreach (array_chunk(array_values($all_location_ids), 500) as $chunk) {
            if (!$chunk) continue;
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $sql = "SELECT id, COALESCE(NULLIF(name,''), NULLIF(address,''), NULLIF(city,'')) AS resolved_name FROM {$px}locations WHERE id IN ({$placeholders})";
            foreach (($wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: []) as $resolved_row) {
                $id = (int)($resolved_row['id'] ?? 0);
                $name = trim((string)($resolved_row['resolved_name'] ?? ''));
                if ($id > 0 && $name !== '' && $this->analytics_location_name_needs_resolution($location_names, $id)) $location_names[$id] = $name;
            }
        }

        // Usa fontes operacionais históricas que sobrevivem a alterações ou eliminações do PDV.
        $this->hydrate_analytics_historical_location_names($location_names, array_values($all_location_ids));

        // Inclui os IDs de loja como candidatos de paragem. Algumas versões antigas
        // gravaram o ID da paragem no campo location_id.
        $stop_lookup_ids = $all_stop_ids;
        foreach ($all_location_ids as $id) $stop_lookup_ids[$id] = $id;
        $stops_by_id = [];
        foreach (array_chunk(array_values($stop_lookup_ids), 500) as $chunk) {
            if (!$chunk) continue;
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $snapshot_join = $has_snapshot ? " LEFT JOIN {$snapshot_table} snap ON snap.id=(SELECT MAX(s2.id) FROM {$snapshot_table} s2 WHERE s2.route_id=rs.route_id AND s2.route_stop_id=rs.id)" : '';
            $snapshot_name = $has_snapshot ? "NULLIF(snap.name,'')" : 'NULL';
            $snapshot_location = $has_snapshot ? 'snap.location_id' : 'NULL';
            $sql = "SELECT rs.id AS route_stop_id, rs.route_id, rs.location_id AS current_location_id, rs.meta_json AS stop_meta_json,
                           {$snapshot_location} AS snapshot_location_id, {$snapshot_name} AS snapshot_name,
                           COALESCE(NULLIF(l.name,''), NULLIF(l.address,''), NULLIF(l.city,'')) AS current_name
                    FROM {$px}route_stops rs
                    LEFT JOIN {$px}locations l ON l.id=rs.location_id
                    {$snapshot_join}
                    WHERE rs.id IN ({$placeholders})";
            foreach (($wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: []) as $stop_row) {
                $resolution = $this->analytics_stop_location_resolution($stop_row, $location_names);
                if (!empty($resolution['stop_id'])) $stops_by_id[(int)$resolution['stop_id']] = $resolution;
            }
        }

        // Recupera snapshots mesmo quando a paragem já foi removida. Esta via é
        // independente de route_stops e cobre IDs históricos guardados como stop_id.
        $snapshot_stops_by_route = [];
        if ($has_snapshot) {
            $snapshot_seen = [];
            $consume_snapshot = function(array $snapshot_row) use (&$stops_by_id, &$snapshot_stops_by_route, &$snapshot_seen, &$location_names): void {
                $snapshot_id = (int)($snapshot_row['snapshot_id'] ?? 0);
                if ($snapshot_id > 0 && isset($snapshot_seen[$snapshot_id])) return;
                if ($snapshot_id > 0) $snapshot_seen[$snapshot_id] = true;
                $resolution = $this->analytics_route_snapshot_resolution($snapshot_row, $location_names);
                $stop_id = (int)($resolution['stop_id'] ?? 0);
                $route_id = (int)($resolution['route_id'] ?? 0);
                if ($stop_id > 0 && (!isset($stops_by_id[$stop_id]) || empty($stops_by_id[$stop_id]['name']))) $stops_by_id[$stop_id] = $resolution;
                if ($route_id > 0 && !empty($resolution['name'])) {
                    $route_key = $stop_id > 0 ? (string)$stop_id : ('snapshot:' . $snapshot_id);
                    if (!isset($snapshot_stops_by_route[$route_id][$route_key])) $snapshot_stops_by_route[$route_id][$route_key] = $resolution;
                }
            };
            foreach (array_chunk(array_values($stop_lookup_ids), 500) as $chunk) {
                if (!$chunk) continue;
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $sql = "SELECT id AS snapshot_id, route_stop_id, route_id, location_id AS snapshot_location_id, name AS snapshot_name, meta_json AS snapshot_meta_json
                        FROM {$snapshot_table}
                        WHERE route_stop_id IN ({$placeholders})
                        ORDER BY id DESC";
                foreach (($wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: []) as $snapshot_row) $consume_snapshot($snapshot_row);
            }
            foreach (array_chunk(array_values($all_route_ids), 250) as $chunk) {
                if (!$chunk) continue;
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $sql = "SELECT id AS snapshot_id, route_stop_id, route_id, location_id AS snapshot_location_id, name AS snapshot_name, meta_json AS snapshot_meta_json
                        FROM {$snapshot_table}
                        WHERE route_id IN ({$placeholders})
                        ORDER BY id DESC";
                foreach (($wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: []) as $snapshot_row) $consume_snapshot($snapshot_row);
            }
            unset($consume_snapshot);
        }

        // Carrega as paragens das rotas envolvidas para recuperar contexto quando o
        // route_stop_id antigo ficou vazio, incorreto ou foi substituído.
        $stops_by_route = $snapshot_stops_by_route;
        foreach (array_chunk(array_values($all_route_ids), 250) as $chunk) {
            if (!$chunk) continue;
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
            $snapshot_join = $has_snapshot ? " LEFT JOIN {$snapshot_table} snap ON snap.id=(SELECT MAX(s2.id) FROM {$snapshot_table} s2 WHERE s2.route_id=rs.route_id AND s2.route_stop_id=rs.id)" : '';
            $snapshot_name = $has_snapshot ? "NULLIF(snap.name,'')" : 'NULL';
            $snapshot_location = $has_snapshot ? 'snap.location_id' : 'NULL';
            $sql = "SELECT rs.id AS route_stop_id, rs.route_id, rs.location_id AS current_location_id, rs.meta_json AS stop_meta_json,
                           {$snapshot_location} AS snapshot_location_id, {$snapshot_name} AS snapshot_name,
                           COALESCE(NULLIF(l.name,''), NULLIF(l.address,''), NULLIF(l.city,'')) AS current_name
                    FROM {$px}route_stops rs
                    LEFT JOIN {$px}locations l ON l.id=rs.location_id
                    {$snapshot_join}
                    WHERE rs.route_id IN ({$placeholders})
                    ORDER BY rs.route_id ASC, rs.seq ASC, rs.id ASC";
            foreach (($wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: []) as $stop_row) {
                $resolution = $this->analytics_stop_location_resolution($stop_row, $location_names);
                $route_id = (int)($resolution['route_id'] ?? 0);
                $stop_id = (int)($resolution['stop_id'] ?? 0);
                if ($stop_id > 0) $stops_by_id[$stop_id] = $resolution;
                if ($route_id > 0) $stops_by_route[$route_id][$stop_id] = $resolution;
            }
        }

        // Snapshot global por ID, útil quando a paragem já não existe mas o snapshot ficou.
        if ($has_snapshot && $all_location_ids) {
            foreach (array_chunk(array_values($all_location_ids), 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $sql = "SELECT location_id, name FROM {$snapshot_table} WHERE location_id IN ({$placeholders}) AND name<>'' ORDER BY id DESC";
                foreach (($wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: []) as $snapshot_row) {
                    $id = (int)($snapshot_row['location_id'] ?? 0);
                    $name = trim((string)($snapshot_row['name'] ?? ''));
                    if ($id > 0 && $name !== '' && $this->analytics_location_name_needs_resolution($location_names, $id)) $location_names[$id] = $name;
                }
            }
        }

        foreach ($rows as $index => &$row) {
            $ctx = (array)($contexts[$index] ?? []);
            $location_ids = array_values(array_unique(array_filter(array_map('intval', (array)($ctx['location_ids'] ?? [])))));
            $stop_ids = array_values(array_unique(array_filter(array_map('intval', (array)($ctx['stop_ids'] ?? [])))));
            $route_ids = array_values(array_unique(array_filter(array_map('intval', (array)($ctx['route_ids'] ?? [])))));
            $resolution = [];

            // 1. Paragem explicitamente guardada na versão, submissão ou registo.
            // Prefere uma paragem compatível com a rota, mas não perde o nome quando
            // o route_id histórico também ficou desatualizado.
            $explicit_fallback = [];
            foreach ($stop_ids as $stop_id) {
                $candidate = (array)($stops_by_id[$stop_id] ?? []);
                if (!$candidate || empty($candidate['name'])) continue;
                if (!$explicit_fallback) $explicit_fallback = $candidate;
                $candidate_route_id = (int)($candidate['route_id'] ?? 0);
                if (!$route_ids || $candidate_route_id <= 0 || in_array($candidate_route_id, $route_ids, true)) {
                    $resolution = $candidate;
                    break;
                }
            }
            if (!$resolution && $explicit_fallback) $resolution = $explicit_fallback;

            // 2. Nome direto por qualquer ID de loja conhecido.
            if (!$resolution) {
                foreach ($location_ids as $location_id) {
                    if ($this->analytics_location_name_needs_resolution($location_names, $location_id)) continue;
                    $resolution = ['location_id' => $location_id, 'name' => trim((string)$location_names[$location_id]), 'source' => 'location'];
                    break;
                }
            }

            // 3. Procura a loja dentro da rota. Primeiro por ID exato, depois usa a
            // única paragem da rota quando o contexto é inequivocamente singular.
            if (!$resolution) {
                foreach ($route_ids as $route_id) {
                    $route_stops = array_values((array)($stops_by_route[$route_id] ?? []));
                    foreach ($route_stops as $candidate) {
                        $candidate_ids = array_filter([(int)($candidate['location_id'] ?? 0), (int)($candidate['snapshot_location_id'] ?? 0)]);
                        if ($location_ids && array_intersect($location_ids, $candidate_ids) && !empty($candidate['name'])) { $resolution = $candidate; break 2; }
                    }
                    if (count($route_stops) === 1 && !empty($route_stops[0]['name'])) { $resolution = $route_stops[0]; break; }
                }
            }

            // 4. Compatibilidade com o erro histórico location_id = route_stop_id.
            if (!$resolution) {
                foreach ($location_ids as $legacy_stop_id) {
                    $candidate = (array)($stops_by_id[$legacy_stop_id] ?? []);
                    if (!$candidate || empty($candidate['name'])) continue;
                    if ($route_ids && !in_array((int)($candidate['route_id'] ?? 0), $route_ids, true)) continue;
                    $resolution = $candidate;
                    break;
                }
            }

            $effective_id = (int)($resolution['location_id'] ?? 0);
            if ($effective_id <= 0) $effective_id = (int)($location_ids[0] ?? 0);
            $name = trim((string)($resolution['name'] ?? ''));
            if ($name === '') $name = trim((string)($ctx['meta_name'] ?? ''));
            if ($name === '' && $effective_id > 0 && !$this->analytics_location_name_needs_resolution($location_names, $effective_id)) $name = trim((string)$location_names[$effective_id]);
            if ($name === '' && $effective_id > 0) $name = 'Loja não identificada #' . $effective_id;
            if ($name === '') $name = 'Sem loja';

            if ($effective_id > 0 && $name !== '' && $this->analytics_location_name_needs_resolution($location_names, $effective_id)) $location_names[$effective_id] = $name;
            $row['_analytics_location_id'] = $effective_id;
            $row['_analytics_location_name'] = $name;
            $row['_analytics_location_source'] = (string)($resolution['source'] ?? ($ctx['meta_name'] ? 'meta' : 'fallback'));
        }
        unset($row);
    }

    private function analytics_stop_location_resolution(array $row, array &$location_names): array {
        $stop_id = (int)($row['route_stop_id'] ?? 0);
        $route_id = (int)($row['route_id'] ?? 0);
        $current_id = (int)($row['current_location_id'] ?? 0);
        $snapshot_id = (int)($row['snapshot_location_id'] ?? 0);
        $meta = json_decode((string)($row['stop_meta_json'] ?? ''), true);
        $meta_name = is_array($meta) ? $this->analytics_meta_location_name($meta) : '';
        $meta_ids = is_array($meta) ? $this->analytics_meta_id_candidates($meta, ['location_id','store_id','shop_id','pdv_id']) : [];
        $meta_id = (int)($meta_ids[0] ?? 0);
        $name = trim((string)($row['snapshot_name'] ?? ''));
        $source = $name !== '' ? 'route_snapshot' : '';
        if ($name === '') { $name = trim((string)($row['current_name'] ?? '')); if ($name !== '') $source = 'route_stop_location'; }
        if ($name === '' && $meta_name !== '') { $name = $meta_name; $source = 'route_stop_meta'; }
        if ($source === 'route_snapshot' && $snapshot_id > 0) $location_id = $snapshot_id;
        elseif ($source === 'route_stop_location' && $current_id > 0) $location_id = $current_id;
        elseif ($source === 'route_stop_meta' && $meta_id > 0) $location_id = $meta_id;
        elseif ($snapshot_id > 0) $location_id = $snapshot_id;
        elseif ($current_id > 0) $location_id = $current_id;
        else $location_id = $meta_id;
        foreach (array_unique(array_filter([$current_id, $snapshot_id, $meta_id])) as $candidate_id) {
            if ($name !== '' && $this->analytics_location_name_needs_resolution($location_names, $candidate_id)) $location_names[$candidate_id] = $name;
        }
        return [
            'stop_id' => $stop_id,
            'route_id' => $route_id,
            'location_id' => $location_id,
            'snapshot_location_id' => $snapshot_id,
            'name' => $name,
            'source' => $source,
        ];
    }

    private function analytics_route_snapshot_resolution(array $row, array &$location_names): array {
        $snapshot_id = (int)($row['snapshot_id'] ?? 0);
        $stop_id = (int)($row['route_stop_id'] ?? 0);
        $route_id = (int)($row['route_id'] ?? 0);
        $location_id = (int)($row['snapshot_location_id'] ?? $row['location_id'] ?? 0);
        $name = trim((string)($row['snapshot_name'] ?? $row['name'] ?? ''));
        if ($name === '') {
            $meta = json_decode((string)($row['snapshot_meta_json'] ?? $row['meta_json'] ?? ''), true);
            if (is_array($meta)) $name = $this->analytics_meta_location_name($meta);
        }
        if ($location_id > 0 && $name !== '' && $this->analytics_location_name_needs_resolution($location_names, $location_id)) {
            $location_names[$location_id] = $name;
        }
        return [
            'snapshot_id' => $snapshot_id,
            'stop_id' => $stop_id,
            'route_id' => $route_id,
            'location_id' => $location_id,
            'snapshot_location_id' => $location_id,
            'name' => $name,
            'source' => 'route_snapshot_history',
        ];
    }

    private function hydrate_analytics_historical_location_names(array &$location_names, array $location_ids): void {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $ids = array_values(array_unique(array_filter(array_map('intval', $location_ids), function(int $id) use ($location_names): bool {
            return $this->analytics_location_name_needs_resolution($location_names, $id);
        })));
        if (!$ids) return;
        static $table_exists = [];
        $sources = [
            ['table' => $px . 'stock_store_mappings', 'name' => 'source_store_name', 'order' => 'updated_at DESC, id DESC'],
            ['table' => $px . 'stock_items', 'name' => 'store_name', 'order' => 'id DESC'],
            ['table' => $px . 'briefing_store_aliases', 'name' => 'external_name', 'order' => 'updated_at DESC, id DESC'],
            ['table' => $px . 'briefing_store_items', 'name' => 'external_store_name', 'order' => 'id DESC'],
        ];
        foreach ($sources as $source) {
            $ids = array_values(array_filter($ids, function(int $id) use ($location_names): bool {
                return $this->analytics_location_name_needs_resolution($location_names, $id);
            }));
            if (!$ids) break;
            $table = (string)$source['table'];
            if (!array_key_exists($table, $table_exists)) $table_exists[$table] = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            if (!$table_exists[$table]) continue;
            foreach (array_chunk($ids, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $sql = "SELECT location_id, {$source['name']} AS resolved_name FROM {$table} WHERE location_id IN ({$placeholders}) AND {$source['name']} IS NOT NULL AND {$source['name']}<>'' ORDER BY {$source['order']}";
                foreach (($wpdb->get_results($wpdb->prepare($sql, ...$chunk), ARRAY_A) ?: []) as $historical_row) {
                    $id = (int)($historical_row['location_id'] ?? 0);
                    $name = trim((string)($historical_row['resolved_name'] ?? ''));
                    if ($id > 0 && $name !== '' && $this->analytics_location_name_needs_resolution($location_names, $id)) $location_names[$id] = $name;
                }
            }
        }
    }

    private function analytics_meta_id_candidates($value, array $keys, int $depth = 0): array {
        if ($depth > 6 || !is_array($value)) return [];
        $wanted = array_fill_keys($keys, true);
        $ids = [];
        foreach ($value as $key => $child) {
            $normalized_key = sanitize_key((string)$key);
            if (isset($wanted[$normalized_key]) && is_numeric($child)) {
                $id = (int)$child;
                if ($id > 0) $ids[$id] = $id;
            }
            if (is_array($child)) {
                foreach ($this->analytics_meta_id_candidates($child, $keys, $depth + 1) as $id) $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    private function analytics_location_name_needs_resolution(array $location_names, int $location_id): bool {
        if ($location_id <= 0) return true;
        $name = trim((string)($location_names[$location_id] ?? ''));
        if ($name === '') return true;
        $normalized = strtolower(remove_accents($name));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: $normalized;
        $placeholders = [
            '#' . $location_id,
            'loja #' . $location_id,
            'loja ' . $location_id,
            'local #' . $location_id,
            'local ' . $location_id,
            'pdv #' . $location_id,
            'pdv ' . $location_id,
            'loja nao identificada #' . $location_id,
            'loja nao identificada ' . $location_id,
            'local nao identificado #' . $location_id,
            'local nao identificado ' . $location_id,
            'pdv nao identificado #' . $location_id,
            'pdv nao identificado ' . $location_id,
        ];
        return in_array($normalized, $placeholders, true);
    }

    private function analytics_meta_location_name($value, int $depth = 0): string {
        if ($depth > 5 || !is_array($value)) return '';
        foreach (['location_name','store_name','shop_name','pdv_name','location_label','store_label'] as $key) {
            $candidate = trim((string)($value[$key] ?? ''));
            if ($candidate !== '') return $candidate;
        }
        foreach (['location','store','shop','pdv','snapshot','location_snapshot','route_location_snapshot','route_stop','context'] as $key) {
            if (!isset($value[$key])) continue;
            if (is_string($value[$key])) {
                $candidate = trim((string)$value[$key]);
                if ($candidate !== '' && !is_numeric($candidate)) return $candidate;
            }
            if (is_array($value[$key])) {
                foreach (['name','label','title'] as $name_key) {
                    $candidate = trim((string)($value[$key][$name_key] ?? ''));
                    if ($candidate !== '' && !is_numeric($candidate)) return $candidate;
                }
            }
            $candidate = $this->analytics_meta_location_name($value[$key], $depth + 1);
            if ($candidate !== '') return $candidate;
        }
        foreach ($value as $child) {
            if (!is_array($child)) continue;
            $candidate = $this->analytics_meta_location_name($child, $depth + 1);
            if ($candidate !== '') return $candidate;
        }
        return '';
    }

    private function analytics_evidence_groups(array $rows, array $location_names, bool $export_full = false): array {
        $groups = [];
        // MediaShadow is an observe-only control. Never let per-file telemetry
        // become an O(n) database bottleneck for full Excel/PDF exports. The
        // visible dashboard keeps a small representative sample; export_full
        // relies on the already-authenticated Analytics scope.
        $shadow_budget = $export_full ? 0 : 12;
        foreach ($rows as $row) {
            $location_id = (int)($row['_analytics_location_id'] ?? $row['location_id'] ?? 0);
            $location_name = trim((string)($row['_analytics_location_name'] ?? ($location_names[$location_id] ?? '')));
            $location_key = $location_id > 0
                ? ('id:' . $location_id)
                : ('name:' . md5(remove_accents(strtolower($location_name !== '' ? $location_name : 'sem-loja'))));
            $date = substr((string)($row['submitted_at'] ?? ''), 0, 10);
            if ($date === '') $date = 'sem-data';
            if (!isset($groups[$location_key])) {
                $groups[$location_key] = [
                    'location_id' => $location_id,
                    'location' => $location_name !== '' ? $location_name : ($location_id ? ('Loja não identificada #' . $location_id) : 'Sem loja'),
                    'files' => [],
                    'dates' => [],
                ];
            }
            if (!isset($groups[$location_key]['dates'][$date])) {
                $groups[$location_key]['dates'][$date] = [
                    'date' => $date === 'sem-data' ? '' : $date,
                    'date_label' => $date === 'sem-data' ? 'Sem data' : wp_date('d/m/Y', strtotime($date), wp_timezone()),
                    'route_id' => 0,
                    'route_ids' => [],
                    'files' => [],
                ];
            }
            $row_route_id = (int)($row['route_id'] ?? $row['submission_route_id'] ?? 0);
            if ($row_route_id > 0) $groups[$location_key]['dates'][$date]['route_ids'][$row_route_id] = $row_route_id;
            $row_files = $this->analytics_files_from_rows([$row], 0);
            foreach ($row_files as $file) {
                if (empty($file['url']) && empty($file['attachment_id'])) continue;
                $file['date'] = $date === 'sem-data' ? '' : $date;
                $file['date_label'] = $date === 'sem-data' ? 'Sem data' : wp_date('d/m/Y', strtotime($date), wp_timezone());
                $file['submitted_at'] = (string)($row['submitted_at'] ?? '');
                $file['route_id'] = (int)($row['route_id'] ?? $row['submission_route_id'] ?? 0);
                $file['location_id'] = $location_id;
                $file['location'] = (string)$groups[$location_key]['location'];
                if (!empty($file['url']) && $shadow_budget > 0 && class_exists('\RoutesPro\Security\MediaShadow')) {
                    \RoutesPro\Security\MediaShadow::observeUrl((string)$file['url'], 'analytics_render', [
                        'user_id' => get_current_user_id(),
                    ]);
                    $shadow_budget--;
                }
                $file_key = !empty($file['url']) ? (string)$file['url'] : ('attachment:' . (int)$file['attachment_id']);
                $hash = md5($file_key);
                $groups[$location_key]['files'][$hash] = $file;
                $groups[$location_key]['dates'][$date]['files'][$hash] = $file;
            }
        }
        $out = [];
        foreach ($groups as $group) {
            if (!$group['files']) continue;
            $group['files'] = array_values($group['files']);
            $dates = [];
            foreach ((array)$group['dates'] as $date_group) {
                if (empty($date_group['files'])) continue;
                $date_group['files'] = array_values($date_group['files']);
                $date_group['count'] = count($date_group['files']);
                $date_group['route_ids'] = array_values(array_map('intval', array_values((array)($date_group['route_ids'] ?? []))));
                $date_group['route_id'] = count($date_group['route_ids']) === 1 ? (int)$date_group['route_ids'][0] : 0;
                $date_group['route_count'] = count($date_group['route_ids']);
                if (!$export_full) {
                    $date_download_group = [
                        'location_id' => (int)($group['location_id'] ?? 0),
                        'location' => trim((string)($group['location'] ?? 'Loja') . ' · ' . (string)($date_group['date_label'] ?? 'Data')),
                        'files' => $date_group['files'],
                    ];
                    $date_group['download_url'] = $this->analytics_evidence_download_url($date_download_group);
                }
                $dates[] = $date_group;
            }
            usort($dates, static function(array $a, array $b): int { return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')); });
            $group['dates'] = $dates;
            $group['count'] = count($group['files']);
            if (!$export_full) $group['download_url'] = $this->analytics_evidence_download_url($group);
            $out[] = $group;
        }
        usort($out, static function(array $a, array $b): int { return strcasecmp((string)$a['location'], (string)$b['location']); });
        return $out;
    }

    private function analytics_evidence_download_url(array $group): string {
        if (!is_user_logged_in() || empty($group['files'])) return '';
        $user_id = get_current_user_id();
        $signature_data = [$user_id, (int)($group['location_id'] ?? 0), (string)($group['location'] ?? ''), []];
        foreach ((array)$group['files'] as $file) {
            $signature_data[3][] = [(string)($file['url'] ?? ''), (int)($file['attachment_id'] ?? 0), (string)($file['name'] ?? '')];
        }
        $token = substr(hash_hmac('sha256', wp_json_encode($signature_data), wp_salt('nonce')), 0, 40);
        $transient_key = 'ff_evidence_' . $user_id . '_' . $token;
        $group_ttl = function_exists('apply_filters') ? (int)apply_filters('fieldflow_evidence_group_token_ttl', 4 * HOUR_IN_SECONDS) : 4 * HOUR_IN_SECONDS;
        $group_ttl = max(30 * MINUTE_IN_SECONDS, min(8 * HOUR_IN_SECONDS, $group_ttl));
        set_transient($transient_key, [
            'user_id' => $user_id,
            'location' => sanitize_text_field((string)($group['location'] ?? 'loja')),
            'files' => array_values((array)$group['files']),
        ], $group_ttl);
        if (!get_transient($transient_key)) return '';
        return add_query_arg([
            'action' => 'routespro_download_analytics_evidence',
            'token' => $token,
            '_wpnonce' => wp_create_nonce('routespro_download_analytics_evidence_' . $token),
        ], admin_url('admin-post.php'));
    }

    private function analytics_files_from_rows(array $rows, int $limit = 250): array {
        $files = [];
        foreach ($rows as $row) {
            $values = [];
            if (!empty($row['value_json'])) {
                $decoded = json_decode((string)$row['value_json'], true);
                if ($decoded !== null) $values[] = $decoded;
            }
            if (!empty($row['value_text'])) $values[] = (string)$row['value_text'];
            foreach ($values as $value) $this->collect_analytics_file_links($value, $files);
        }
        $files = array_values($files);
        return $limit > 0 ? array_slice($files, 0, $limit) : $files;
    }

    private function collect_analytics_file_links($value, array &$files): void {
        if (is_array($value)) {
            $attachment_id = 0;
            foreach (['attachment_id','media_id'] as $id_key) {
                if (!empty($value[$id_key])) { $attachment_id = absint($value[$id_key]); break; }
            }
            $url = '';
            foreach (['url','file_url','download_url','src'] as $key) {
                if (!empty($value[$key]) && is_string($value[$key])) { $url = $this->normalize_analytics_evidence_url((string)$value[$key]); break; }
            }
            if ($attachment_id > 0 && $url === '') $url = (string)wp_get_attachment_url($attachment_id);
            if ($url !== '' || $attachment_id > 0) {
                $path = $url !== '' ? (string)wp_parse_url($url, PHP_URL_PATH) : '';
                $name = sanitize_file_name((string)($value['name'] ?? $value['filename'] ?? ($path !== '' ? basename($path) : '')));
                if ($name === '' && $attachment_id > 0) $name = sanitize_file_name((string)get_the_title($attachment_id));
                $type = sanitize_text_field((string)($value['type'] ?? $value['mime_type'] ?? ($attachment_id > 0 ? get_post_mime_type($attachment_id) : '')));
                $file_key = $url !== '' ? $url : ('attachment:' . $attachment_id);
                $files[md5($file_key)] = [
                    'url' => esc_url_raw($url),
                    'name' => $name ?: 'Evidência',
                    'type' => $type,
                    'attachment_id' => $attachment_id,
                ];
            }
            foreach ($value as $child) $this->collect_analytics_file_links($child, $files);
            return;
        }
        if (!is_string($value)) return;
        $text = trim(html_entity_decode(wp_unslash($value), ENT_QUOTES, 'UTF-8'));
        if ($text === '') return;
        if (($text[0] ?? '') === '{' || ($text[0] ?? '') === '[') {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) { $this->collect_analytics_file_links($decoded, $files); return; }
        }
        $candidates = preg_split('/[\s,;]+/', $text) ?: [];
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate, "\"'()[]<>");
            $candidate = $this->normalize_analytics_evidence_url($candidate);
            if ($candidate === '') continue;
            $path = (string)wp_parse_url($candidate, PHP_URL_PATH);
            if (!preg_match('/\.(?:jpe?g|png|gif|webp|heic|pdf)$/i', $path) && strpos($path, '/uploads/') === false) continue;
            $name = sanitize_file_name(basename($path));
            $files[md5($candidate)] = ['url' => esc_url_raw($candidate), 'name' => $name ?: 'Evidência', 'type' => '', 'attachment_id' => 0];
        }
    }

    private function normalize_analytics_evidence_url(string $url): string {
        $url = trim(html_entity_decode(wp_unslash($url), ENT_QUOTES, 'UTF-8'));
        if ($url === '') return '';
        if (strpos($url, '//') === 0) $url = (is_ssl() ? 'https:' : 'http:') . $url;
        elseif (strpos($url, '/') === 0) $url = home_url($url);
        elseif (preg_match('#^(?:wp-content/|uploads/)#i', $url)) $url = home_url('/' . ltrim($url, '/'));
        return filter_var($url, FILTER_VALIDATE_URL) ? esc_url_raw($url) : '';
    }

    public function evidenceArchive(WP_REST_Request $req) {
        $this->prepare_analytics_evidence_export_runtime();

        // The browser never supplies arbitrary evidence URLs here. Every store
        // and date group already receives a user-bound transient token when the
        // scoped Analytics payload is built. Reuse that token so the prepare
        // step can only package files the server already authorised.
        $token = sanitize_key((string)$req->get_param('token'));
        if ($token === '' || !preg_match('/^[a-f0-9]{40}$/', $token)) {
            return new \WP_Error('evidence_request_invalid', 'O pedido de evidências é inválido. Atualiza o Analytics e tenta novamente.', ['status' => 400]);
        }
        $transient_key = 'ff_evidence_' . get_current_user_id() . '_' . $token;
        $payload = get_transient($transient_key);
        if (!is_array($payload) || (int)($payload['user_id'] ?? 0) !== get_current_user_id() || empty($payload['files'])) {
            return new \WP_Error('evidence_request_expired', 'A preparação destas evidências expirou. Atualiza o Analytics e tenta novamente.', ['status' => 410]);
        }

        $archive = $this->create_analytics_evidence_archive(
            array_values((array)$payload['files']),
            sanitize_text_field((string)($payload['location'] ?? 'loja'))
        );
        if (is_wp_error($archive)) return $archive;

        $prepared_url = $this->analytics_prepared_evidence_download_url($archive);
        if (is_wp_error($prepared_url)) {
            $path = (string)($archive['path'] ?? '');
            if ($path !== '' && is_file($path)) @unlink($path);
            return $prepared_url;
        }

        return new WP_REST_Response([
            'url' => (string)$prepared_url,
            'filename' => (string)($archive['filename'] ?? ''),
            'count' => (int)($archive['count'] ?? 0),
            'skipped' => (int)($archive['skipped'] ?? 0),
            'bytes' => (int)($archive['bytes'] ?? 0),
        ], 200);
    }

    private static function analytics_prepared_evidence_ticket_key(string $ticket): string {
        return 'ff_evidence_ready_ticket_' . substr(hash('sha256', strtolower($ticket)), 0, 40);
    }

    private function analytics_prepared_evidence_download_url(array $archive) {
        $path = (string)($archive['path'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return new \WP_Error('evidence_prepared_missing', 'O ZIP foi preparado mas não ficou disponível para download.', ['status' => 500]);
        }
        try {
            $ticket = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            $ticket = hash('sha256', wp_generate_uuid4() . '|' . microtime(true) . '|' . get_current_user_id() . '|' . wp_salt('nonce'));
        }
        $ticket = strtolower(preg_replace('/[^a-f0-9]/', '', (string)$ticket));
        if (strlen($ticket) !== 64) {
            $ticket = hash('sha256', $ticket . '|' . wp_generate_uuid4() . '|' . microtime(true));
        }
        $transient_key = self::analytics_prepared_evidence_ticket_key($ticket);
        $ttl = function_exists('apply_filters') ? (int)apply_filters('fieldflow_evidence_prepared_ttl', 15 * MINUTE_IN_SECONDS) : 15 * MINUTE_IN_SECONDS;
        $ttl = max(5 * MINUTE_IN_SECONDS, min(HOUR_IN_SECONDS, $ttl));
        $expires = time() + $ttl;
        $stored = set_transient($transient_key, [
            'user_id' => get_current_user_id(),
            'path' => $path,
            'filename' => sanitize_file_name((string)($archive['filename'] ?? basename($path))),
            'count' => (int)($archive['count'] ?? 0),
            'bytes' => (int)($archive['bytes'] ?? 0),
            'created_at' => time(),
            'expires' => $expires,
            'downloads' => 0,
        ], $ttl);
        if (!$stored || !is_array(get_transient($transient_key))) {
            return new \WP_Error('evidence_prepared_token', 'Não foi possível criar o acesso temporário ao ZIP.', ['status' => 500]);
        }
        return add_query_arg([
            'fieldflow_evidence_zip_download' => '1',
            'ticket' => $ticket,
        ], home_url('/'));
    }

    private static function preparedEvidenceHttpError(int $status, string $code, string $message): void {
        while (ob_get_level() > 0) @ob_end_clean();
        status_header($status);
        nocache_headers();
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-FieldFlow-Evidence-Error: ' . sanitize_key($code));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        echo wp_strip_all_tags($message);
        exit;
    }

    /**
     * Cookie-free, idempotent download transport for prepared evidence ZIPs.
     *
     * Android/WebView download handlers may probe a URL with HEAD or retry the
     * same GET before handing it to the user. The previous admin-post transport
     * required the WordPress session/nonce and deleted the ticket after the
     * first request, so a probe or native retry could consume the ZIP and make
     * the next request fail with HTTP 410. This capability URL is opaque,
     * short-lived and intentionally reusable for its TTL.
     */
    public static function handlePreparedEvidencePublicDownload(): void {
        if (empty($_GET['fieldflow_evidence_zip_download'])) return;

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            self::preparedEvidenceHttpError(405, 'method_not_allowed', 'Método de download não suportado.');
        }

        $ticket = strtolower(sanitize_text_field((string)($_GET['ticket'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $ticket)) {
            self::preparedEvidenceHttpError(400, 'invalid_ticket', 'Pedido de download inválido.');
        }

        $transient_key = self::analytics_prepared_evidence_ticket_key($ticket);
        $payload = get_transient($transient_key);
        if (!is_array($payload)) {
            self::preparedEvidenceHttpError(410, 'ticket_expired', 'O acesso temporário ao ZIP expirou. Prepara novamente o ficheiro.');
        }

        $expires = (int)($payload['expires'] ?? 0);
        $path_hint = (string)($payload['path'] ?? '');
        if ($expires < time() || $expires > time() + (HOUR_IN_SECONDS + 60)) {
            delete_transient($transient_key);
            if ($path_hint !== '' && is_file($path_hint)) @unlink($path_hint);
            self::preparedEvidenceHttpError(410, 'ticket_expired', 'O acesso temporário ao ZIP expirou. Prepara novamente o ficheiro.');
        }

        $uploads = wp_upload_dir();
        $export_root = !empty($uploads['error']) ? false : realpath(trailingslashit((string)$uploads['basedir']) . 'fieldflow-evidence-exports');
        $path = realpath($path_hint);
        if (!$export_root || !$path || !is_file($path) || !is_readable($path) || ($path !== $export_root && strpos($path, $export_root . DIRECTORY_SEPARATOR) !== 0)) {
            delete_transient($transient_key);
            self::preparedEvidenceHttpError(404, 'prepared_file_missing', 'O ZIP preparado deixou de estar disponível.');
        }

        clearstatcache(true, $path);
        $size = (int)@filesize($path);
        if ($size < 22) {
            delete_transient($transient_key);
            @unlink($path);
            self::preparedEvidenceHttpError(500, 'prepared_file_invalid', 'O ZIP preparado está vazio ou incompleto.');
        }

        // Keep the ticket alive for native HEAD/GET probes and browser fallback.
        // It expires naturally and old ZIP files are removed by the export cleanup.
        $remaining = max(60, $expires - time());
        $payload['downloads'] = max(0, (int)($payload['downloads'] ?? 0)) + ($method === 'GET' ? 1 : 0);
        $payload['last_request_at'] = time();
        $payload['last_method'] = $method;
        set_transient($transient_key, $payload, $remaining);

        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) @session_write_close();
        while (ob_get_level() > 0) @ob_end_clean();
        if (function_exists('ini_set')) @ini_set('zlib.output_compression', '0');
        nocache_headers();
        $filename = sanitize_file_name((string)($payload['filename'] ?? basename($path))) ?: basename($path);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . (string)$size);
        header('Accept-Ranges: none');
        header('X-FieldFlow-Evidence-Count: ' . (string)((int)($payload['count'] ?? 0)));
        header('X-FieldFlow-Download-Integrity: evidence-zip-v4');
        header('X-FieldFlow-Download-Transport: capability-ticket');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, nosnippet');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform');
        header('Pragma: no-cache');
        header('Expires: 0');

        if ($method === 'HEAD') exit;

        $handle = @fopen($path, 'rb');
        if (!$handle) self::preparedEvidenceHttpError(500, 'prepared_file_open', 'Não foi possível abrir o ZIP preparado.');
        $sent = 0;
        while (!feof($handle)) {
            $chunk = fread($handle, 1048576);
            if ($chunk === false) break;
            if ($chunk === '') continue;
            echo $chunk;
            $sent += strlen($chunk);
            if (function_exists('flush')) @flush();
        }
        fclose($handle);
        if ($sent !== $size) error_log('FieldFlow prepared evidence ZIP stream mismatch v4: expected=' . $size . ' sent=' . $sent);
        exit;
    }

    public static function handlePreparedEvidenceDownload(): void {
        if (!is_user_logged_in() || (!current_user_can('routespro_manage') && !Permissions::can_access_front())) {
            wp_die('Sem permissões.', 'FieldFlow', ['response' => 403]);
        }
        $token = sanitize_key((string)($_GET['token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{32,64}$/', $token)) {
            wp_die('Pedido de download inválido.', 'FieldFlow', ['response' => 400]);
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce((string)$_GET['_wpnonce'], 'routespro_download_prepared_analytics_evidence_' . $token)) {
            wp_die('O acesso temporário ao ZIP expirou. Prepara novamente o ficheiro.', 'FieldFlow', ['response' => 410]);
        }
        $transient_key = 'ff_evidence_ready_' . get_current_user_id() . '_' . $token;
        $payload = get_transient($transient_key);
        if (!is_array($payload) || (int)($payload['user_id'] ?? 0) !== get_current_user_id()) {
            wp_die('O ZIP já não está disponível. Prepara novamente o ficheiro.', 'FieldFlow', ['response' => 410]);
        }

        $uploads = wp_upload_dir();
        $export_root = !empty($uploads['error']) ? false : realpath(trailingslashit((string)$uploads['basedir']) . 'fieldflow-evidence-exports');
        $path = realpath((string)($payload['path'] ?? ''));
        if (!$export_root || !$path || !is_file($path) || !is_readable($path) || ($path !== $export_root && strpos($path, $export_root . DIRECTORY_SEPARATOR) !== 0)) {
            delete_transient($transient_key);
            wp_die('O ZIP preparado deixou de estar disponível.', 'FieldFlow', ['response' => 404]);
        }
        clearstatcache(true, $path);
        $size = (int)@filesize($path);
        if ($size < 22) {
            delete_transient($transient_key);
            @unlink($path);
            wp_die('O ZIP preparado está vazio ou incompleto.', 'FieldFlow', ['response' => 500]);
        }

        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) @session_write_close();
        while (ob_get_level() > 0) @ob_end_clean();
        if (function_exists('ini_set')) @ini_set('zlib.output_compression', '0');
        nocache_headers();
        $filename = sanitize_file_name((string)($payload['filename'] ?? basename($path))) ?: basename($path);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string)$size);
        header('X-FieldFlow-Evidence-Count: ' . (string)((int)($payload['count'] ?? 0)));
        header('X-FieldFlow-Download-Integrity: evidence-zip-v3');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform');
        header('Pragma: no-cache');
        header('Expires: 0');

        $handle = @fopen($path, 'rb');
        if (!$handle) wp_die('Não foi possível abrir o ZIP preparado.', 'FieldFlow', ['response' => 500]);
        $sent = 0;
        while (!feof($handle)) {
            $chunk = fread($handle, 1048576);
            if ($chunk === false) break;
            if ($chunk === '') continue;
            echo $chunk;
            $sent += strlen($chunk);
        }
        fclose($handle);
        delete_transient($transient_key);
        @unlink($path);
        if ($sent !== $size) error_log('FieldFlow prepared evidence ZIP stream mismatch: expected=' . $size . ' sent=' . $sent);
        exit;
    }

    public static function handleEvidenceDownload(): void {
        if (!is_user_logged_in() || (!current_user_can('routespro_manage') && !Permissions::can_access_front())) wp_die('Sem permissões.');
        $token = sanitize_key((string)($_GET['token'] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{40}$/', $token)) wp_die('Pedido de evidências inválido.');
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce((string)$_GET['_wpnonce'], 'routespro_download_analytics_evidence_' . $token)) wp_die('Pedido de evidências expirado. Atualiza a página e tenta novamente.');
        $transient_key = 'ff_evidence_' . get_current_user_id() . '_' . $token;
        $payload = get_transient($transient_key);
        if (!is_array($payload) || (int)($payload['user_id'] ?? 0) !== get_current_user_id() || empty($payload['files'])) wp_die('A preparação destas evidências expirou. Atualiza a aba Analytics e tenta novamente.');

        $controller = new self();
        $controller->prepare_analytics_evidence_export_runtime();
        $archive = $controller->create_analytics_evidence_archive((array)$payload['files'], sanitize_text_field((string)($payload['location'] ?? 'loja')));
        if (is_wp_error($archive)) {
            if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
                \RoutesPro\Core\AuditLog::record('analytics_evidence_export_failed', 'analytics_evidence', 0, null, null, [
                    'code' => (string)$archive->get_error_code(),
                    'message' => (string)$archive->get_error_message(),
                    'location' => sanitize_text_field((string)($payload['location'] ?? 'loja')),
                    'file_count' => count((array)$payload['files']),
                ]);
            }
            wp_die(esc_html($archive->get_error_message()));
        }
        delete_transient($transient_key);
        $path = (string)($archive['path'] ?? '');
        if ($path === '' || !is_file($path)) wp_die('O ZIP das evidências não foi criado.');

        clearstatcache(true, $path);
        $size = (int)@filesize($path);
        if ($size < 20) {
            @unlink($path);
            wp_die('O ZIP das evidências ficou vazio.');
        }
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) @session_write_close();
        while (ob_get_level() > 0) @ob_end_clean();
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename((string)($archive['filename'] ?? basename($path))) . '"');
        header('Content-Length: ' . (string)$size);
        header('X-FieldFlow-Evidence-Count: ' . (string)((int)($archive['count'] ?? 0)));
        header('X-FieldFlow-Download-Integrity: evidence-zip-v2');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform');
        header('Pragma: no-cache');
        header('Expires: 0');

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            @unlink($path);
            wp_die('Não foi possível abrir o ZIP final para download.');
        }
        $sent = 0;
        while (!feof($handle)) {
            $chunk = fread($handle, 1048576);
            if ($chunk === false) break;
            if ($chunk === '') continue;
            echo $chunk;
            $sent += strlen($chunk);
        }
        fclose($handle);
        @unlink($path);
        if ($sent !== $size) error_log('FieldFlow evidence ZIP stream mismatch: expected=' . $size . ' sent=' . $sent);
        exit;
    }

    /**
     * Evidence ZIPs can contain hundreds of already-compressed photos. Keep the
     * request alive long enough to finish the archive, release the PHP session
     * lock and disable HTTP compression so binary bytes are never corrupted.
     */
    private function prepare_analytics_evidence_export_runtime(): void {
        if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('admin');
        if (function_exists('ignore_user_abort')) @ignore_user_abort(true);
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) @session_write_close();
        if (function_exists('ini_set')) {
            @ini_set('display_errors', '0');
            @ini_set('html_errors', '0');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '0');
        }
        if (function_exists('set_time_limit')) {
            $requested = function_exists('apply_filters') ? (int)apply_filters('fieldflow_evidence_export_time_limit', 900) : 900;
            $requested = max(180, min(1800, $requested));
            $configured = (int)ini_get('max_execution_time');
            if ($configured !== 0 && $configured < $requested) @set_time_limit($requested);
        }
    }

    private function create_analytics_evidence_archive(array $files, string $location) {
        if (!$files) return new \WP_Error('evidence_empty', 'Não existem imagens válidas para descarregar.', ['status' => 400]);

        // 2.2.468 Analytics Export Resilience: keep the same functional ceiling
        // while avoiding the previous double-copy staging cost that could turn a
        // large photo export into an HTTP 500 on production hosting.
        $max_files = function_exists('apply_filters') ? (int)apply_filters('fieldflow_evidence_export_max_files', 500) : 500;
        $max_files = max(1, min(2000, $max_files));
        $max_bytes = function_exists('apply_filters') ? (int)apply_filters('fieldflow_evidence_export_max_bytes', 500 * 1024 * 1024) : 500 * 1024 * 1024;
        $max_bytes = max(10 * 1024 * 1024, min(2 * 1024 * 1024 * 1024, $max_bytes));
        $files = array_slice($files, 0, $max_files);

        $valid = [];
        $total_bytes = 0;
        $skipped = 0;
        // MediaShadow is observability-only. A previous per-file write could
        // execute hundreds of DB lookups/inserts during one ZIP request. Keep
        // a representative bounded sample without putting telemetry on the
        // critical export path.
        $shadow_budget = 8;
        foreach ($files as $index => $file) {
            if (!is_array($file)) { $skipped++; continue; }
            $url = $this->normalize_analytics_evidence_url((string)($file['url'] ?? ''));
            $attachment_id = absint($file['attachment_id'] ?? 0);
            if ($url === '' && $attachment_id > 0) $url = (string)wp_get_attachment_url($attachment_id);
            if ($url !== '' && $shadow_budget > 0 && class_exists('\\RoutesPro\\Security\\MediaShadow')) {
                \RoutesPro\Security\MediaShadow::observeUrl($url, 'analytics_evidence_archive', [
                    'user_id' => get_current_user_id(),
                ]);
                $shadow_budget--;
            }
            $resolved = $this->analytics_evidence_local_file($url, $attachment_id);
            if (!$resolved || empty($resolved['path']) || !is_readable((string)$resolved['path'])) { $skipped++; continue; }
            $size = @filesize((string)$resolved['path']);
            if ($size === false || (int)$size <= 0) { $skipped++; continue; }
            if ($total_bytes + (int)$size > $max_bytes) { $skipped += max(1, count($files) - $index); break; }
            $total_bytes += (int)$size;
            $requested_name = sanitize_file_name((string)($file['name'] ?? ''));
            $name = $requested_name !== '' ? $requested_name : sanitize_file_name(basename((string)$resolved['path']));
            if ($name === '') $name = 'evidencia-' . ($index + 1);
            $source_ext = strtolower((string)pathinfo((string)$resolved['path'], PATHINFO_EXTENSION));
            if (pathinfo($name, PATHINFO_EXTENSION) === '' && $source_ext !== '') $name .= '.' . $source_ext;
            $valid[] = [
                'path' => (string)$resolved['path'],
                'name' => $name,
                'index' => $index + 1,
                'bytes' => (int)$size,
            ];
        }
        if (!$valid) return new \WP_Error('evidence_unavailable', 'As imagens não estão disponíveis no armazenamento local do WordPress ou estão vazias.', ['status' => 404]);

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) return new \WP_Error('evidence_upload_dir', 'Não foi possível preparar a pasta de exportação.', ['status' => 500]);
        $export_dir = trailingslashit($uploads['basedir']) . 'fieldflow-evidence-exports';
        if (!wp_mkdir_p($export_dir)) return new \WP_Error('evidence_export_dir', 'Não foi possível criar a pasta de exportação.', ['status' => 500]);
        $this->cleanup_analytics_evidence_exports($export_dir);

        $slug = sanitize_title($location) ?: 'loja';
        $base_name = 'evidencias-' . $slug . '-' . wp_date('Ymd-His') . '-' . strtolower(wp_generate_password(24, false, false));
        $zip_path = trailingslashit($export_dir) . $base_name . '.zip';
        $part_path = $zip_path . '.part';
        @unlink($part_path);
        @unlink($zip_path);

        // 2.2.470 Evidence ZIP Ticket Continuity. Build a STORE-only ZIP with a
        // small portable writer first. Evidence media is already compressed, so
        // this avoids host-specific ZipArchive/libzip behaviour and keeps the
        // export path deterministic even on constrained WordPress hosting.
        $created = false;
        $added = 0;
        $archive_bytes = 0;
        $portable = $this->write_analytics_evidence_zip_portable($part_path, $valid);
        if (is_array($portable) && !empty($portable['created'])) {
            $created = true;
            $added = (int)($portable['added'] ?? 0);
            $skipped += (int)($portable['skipped'] ?? 0);
        }

        // Compatibility fallback for an unexpected portable-writer failure.
        // Never stop at a broken ZipArchive installation merely because the
        // extension exists. If one engine fails, continue to the next engine.
        if (!$created) {
            @unlink($part_path);
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($part_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                    $zipAdded = 0;
                    foreach ($valid as $entry) {
                        $archive_name = sprintf('%03d-%s', (int)$entry['index'], sanitize_file_name((string)$entry['name']) ?: ('evidencia-' . (int)$entry['index']));
                        if (!$zip->addFile((string)$entry['path'], $archive_name)) continue;
                        $zipAdded++;
                        if (method_exists($zip, 'setCompressionName')) {
                            $ext = strtolower((string)pathinfo((string)$entry['path'], PATHINFO_EXTENSION));
                            $store = in_array($ext, ['jpg','jpeg','png','gif','webp','heic','heif','pdf','zip','mp4','mov'], true);
                            $method = $store ? \ZipArchive::CM_STORE : \ZipArchive::CM_DEFLATE;
                            @$zip->setCompressionName($archive_name, $method);
                        }
                    }
                    $closed = $zip->close();
                    $created = $zipAdded > 0 && $closed && is_file($part_path);
                    $added = $created ? $zipAdded : 0;
                }
            }
        }
        if (!$created) {
            @unlink($part_path);
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            if (class_exists('PclZip')) {
                $stage_dir = trailingslashit($export_dir) . $base_name . '-fallback';
                if (wp_mkdir_p($stage_dir)) {
                    $staged = [];
                    foreach ($valid as $entry) {
                        $name = sprintf('%03d-%s', (int)$entry['index'], sanitize_file_name((string)$entry['name']) ?: ('evidencia-' . (int)$entry['index']));
                        $target = trailingslashit($stage_dir) . $name;
                        if (@copy((string)$entry['path'], $target)) $staged[] = $target;
                    }
                    if ($staged) {
                        $archive = new \PclZip($part_path);
                        $result = $archive->create($staged, PCLZIP_OPT_REMOVE_ALL_PATH);
                        $created = is_array($result) && count($result) > 0 && is_file($part_path);
                        $added = $created ? count($result) : 0;
                    }
                    $this->remove_analytics_temp_dir($stage_dir);
                }
            }
        }

        clearstatcache(true, $part_path);
        if (!$created || $added < 1 || !is_file($part_path) || (int)@filesize($part_path) < 20) {
            @unlink($part_path);
            return new \WP_Error('evidence_zip_failed', 'O servidor não conseguiu criar o ZIP das evidências. Tenta novamente ou reduz o período selecionado.', ['status' => 500]);
        }

        // Validate the exact temporary package before exposing it. This catches
        // disk-full/truncated archives that previously surfaced as HTTP 500 or
        // as corrupt downloads only after the browser received the file.
        if (!$this->validate_analytics_evidence_zip($part_path, $added)) {
            @unlink($part_path);
            return new \WP_Error('evidence_zip_invalid', 'O ZIP das evidências ficou incompleto e foi descartado em segurança.', ['status' => 500]);
        }
        if (!@rename($part_path, $zip_path)) {
            if (!@copy($part_path, $zip_path)) {
                @unlink($part_path);
                return new \WP_Error('evidence_zip_finalize', 'Não foi possível finalizar o ZIP das evidências.', ['status' => 500]);
            }
            @unlink($part_path);
        }
        clearstatcache(true, $zip_path);
        $archive_bytes = (int)@filesize($zip_path);
        if ($archive_bytes < 20) {
            @unlink($zip_path);
            return new \WP_Error('evidence_zip_empty', 'O ZIP das evidências ficou vazio.', ['status' => 500]);
        }

        return [
            'path' => $zip_path,
            'filename' => basename($zip_path),
            'count' => $added,
            'skipped' => $skipped,
            'bytes' => $archive_bytes,
            'source_bytes' => $total_bytes,
        ];
    }

    /**
     * Portable ZIP writer for evidence media.
     *
     * Uses method STORE (no compression), streams every file in 1 MiB chunks
     * and keeps only the central-directory metadata in memory. The configured
     * evidence ceiling is below the classic ZIP32 4 GiB limit.
     */
    private function write_analytics_evidence_zip_portable(string $path, array $entries): array {
        @unlink($path);
        $out = @fopen($path, 'wb');
        if (!$out) return ['created'=>false,'added'=>0,'skipped'=>count($entries),'engine'=>'portable-store'];

        $central = [];
        $offset = 0;
        $added = 0;
        $skipped = 0;
        try {
            foreach ($entries as $entry) {
                $source = (string)($entry['path'] ?? '');
                if ($source === '' || !is_file($source) || !is_readable($source)) { $skipped++; continue; }
                $size = (int)@filesize($source);
                if ($size <= 0 || $size > 0xFFFFFFFF || $offset > 0xFFFFFFFF) { $skipped++; continue; }

                $name = sprintf('%03d-%s', (int)($entry['index'] ?? ($added + 1)), sanitize_file_name((string)($entry['name'] ?? '')) ?: ('evidencia-' . ($added + 1)));
                $name = str_replace(['\\', "\0"], ['/', ''], $name);
                if ($name === '') { $skipped++; continue; }
                $nameBytes = $name;
                $nameLen = strlen($nameBytes);
                if ($nameLen < 1 || $nameLen > 0xFFFF) { $skipped++; continue; }

                $crcHex = @hash_file('crc32b', $source);
                if (!is_string($crcHex) || strlen($crcHex) !== 8) { $skipped++; continue; }
                $crc = (int)hexdec($crcHex);
                [$dosTime, $dosDate] = $this->analytics_zip_dos_time((int)@filemtime($source));
                $localOffset = $offset;
                $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0) . $nameBytes;
                if (!$this->analytics_zip_write_all($out, $localHeader)) { throw new \RuntimeException('zip_local_header'); }
                $offset += strlen($localHeader);

                $in = @fopen($source, 'rb');
                if (!$in) { throw new \RuntimeException('zip_source_open'); }
                while (!feof($in)) {
                    $chunk = fread($in, 1048576);
                    if ($chunk === false) { fclose($in); throw new \RuntimeException('zip_source_read'); }
                    if ($chunk === '') continue;
                    if (!$this->analytics_zip_write_all($out, $chunk)) { fclose($in); throw new \RuntimeException('zip_source_write'); }
                    $offset += strlen($chunk);
                }
                fclose($in);

                $central[] = [
                    'name' => $nameBytes,
                    'name_len' => $nameLen,
                    'crc' => $crc,
                    'size' => $size,
                    'time' => $dosTime,
                    'date' => $dosDate,
                    'offset' => $localOffset,
                ];
                $added++;
            }

            if ($added < 1) throw new \RuntimeException('zip_empty');
            $centralOffset = $offset;
            foreach ($central as $row) {
                $header = pack(
                    'VvvvvvvVVVvvvvvVV',
                    0x02014b50,
                    20,
                    20,
                    0,
                    0,
                    (int)$row['time'],
                    (int)$row['date'],
                    (int)$row['crc'],
                    (int)$row['size'],
                    (int)$row['size'],
                    (int)$row['name_len'],
                    0,
                    0,
                    0,
                    0,
                    0,
                    (int)$row['offset']
                ) . (string)$row['name'];
                if (!$this->analytics_zip_write_all($out, $header)) throw new \RuntimeException('zip_central_write');
                $offset += strlen($header);
            }
            $centralSize = $offset - $centralOffset;
            if ($centralOffset > 0xFFFFFFFF || $centralSize > 0xFFFFFFFF || $added > 0xFFFF) throw new \RuntimeException('zip32_limit');
            $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, $added, $added, $centralSize, $centralOffset, 0);
            if (!$this->analytics_zip_write_all($out, $eocd)) throw new \RuntimeException('zip_eocd_write');
            fflush($out);
            fclose($out);
            clearstatcache(true, $path);
            return ['created'=>is_file($path) && (int)@filesize($path) > 20,'added'=>$added,'skipped'=>$skipped,'engine'=>'portable-store'];
        } catch (\Throwable $e) {
            if (is_resource($out)) fclose($out);
            @unlink($path);
            error_log('FieldFlow evidence portable ZIP writer failed: ' . $e->getMessage());
            return ['created'=>false,'added'=>0,'skipped'=>count($entries),'engine'=>'portable-store','error'=>$e->getMessage()];
        }
    }

    private function analytics_zip_write_all($handle, string $bytes): bool {
        $length = strlen($bytes);
        $written = 0;
        while ($written < $length) {
            $result = fwrite($handle, substr($bytes, $written));
            if ($result === false || $result === 0) return false;
            $written += $result;
        }
        return true;
    }

    private function analytics_zip_dos_time(int $timestamp): array {
        if ($timestamp <= 0) $timestamp = time();
        $parts = getdate($timestamp);
        $year = max(1980, min(2107, (int)($parts['year'] ?? 1980)));
        $month = max(1, min(12, (int)($parts['mon'] ?? 1)));
        $day = max(1, min(31, (int)($parts['mday'] ?? 1)));
        $hour = max(0, min(23, (int)($parts['hours'] ?? 0)));
        $minute = max(0, min(59, (int)($parts['minutes'] ?? 0)));
        $second = max(0, min(59, (int)($parts['seconds'] ?? 0)));
        $dosTime = ($hour << 11) | ($minute << 5) | intdiv($second, 2);
        $dosDate = (($year - 1980) << 9) | ($month << 5) | $day;
        return [$dosTime, $dosDate];
    }

    private function validate_analytics_evidence_zip(string $path, int $expected_entries): bool {
        if (!is_file($path) || (int)@filesize($path) < 22 || $expected_entries < 1) return false;
        $handle = @fopen($path, 'rb');
        if (!$handle) return false;
        $size = (int)@filesize($path);
        $signature = fread($handle, 4);
        if ($signature !== "PK\x03\x04") { fclose($handle); return false; }
        $tailLength = min(66000, $size);
        fseek($handle, max(0, $size - $tailLength));
        $tail = fread($handle, $tailLength);
        if (!is_string($tail)) { fclose($handle); return false; }
        $eocdPos = strrpos($tail, "PK\x05\x06");
        if ($eocdPos === false || strlen($tail) < $eocdPos + 22) { fclose($handle); return false; }
        $eocd = unpack('vdisk/vcentral_disk/ventries_disk/ventries_total/Vcentral_size/Vcentral_offset/vcomment_length', substr($tail, $eocdPos + 4, 18));
        if (!is_array($eocd)) { fclose($handle); return false; }
        $entries = (int)($eocd['entries_total'] ?? 0);
        $centralSize = (int)($eocd['central_size'] ?? 0);
        $centralOffset = (int)($eocd['central_offset'] ?? -1);
        if ($entries !== $expected_entries || $centralSize <= 0 || $centralOffset < 0 || ($centralOffset + $centralSize) > $size) { fclose($handle); return false; }
        fseek($handle, $centralOffset);
        $centralSignature = fread($handle, 4);
        fclose($handle);
        return $centralSignature === "PK\x01\x02";
    }

    private function cleanup_analytics_evidence_exports(string $export_dir): void {
        $cutoff = time() - (2 * HOUR_IN_SECONDS);
        foreach (glob(trailingslashit($export_dir) . '*') ?: [] as $old) {
            if (!file_exists($old) || (int)@filemtime($old) >= $cutoff) continue;
            if (is_file($old) && preg_match('/\.(?:zip|part)$/i', $old)) @unlink($old);
            elseif (is_dir($old)) $this->remove_analytics_temp_dir($old);
        }
    }

    private function analytics_evidence_local_file(string $url, int $attachment_id = 0): ?array {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) return null;
        $base_dir = realpath((string)$uploads['basedir']);
        if (!$base_dir) return null;

        if ($attachment_id <= 0 && $url !== '' && function_exists('attachment_url_to_postid')) $attachment_id = (int)attachment_url_to_postid($url);
        if ($attachment_id > 0) {
            $attached = realpath((string)get_attached_file($attachment_id));
            if ($attached && is_file($attached) && ($attached === $base_dir || strpos($attached, $base_dir . DIRECTORY_SEPARATOR) === 0)) return ['path' => $attached, 'source' => 'attachment'];
        }
        if ($url === '') return null;

        // Historical and recovered media can retain a public URL that no longer
        // maps 1:1 through attachment_url_to_postid(). The media registry keeps
        // the observed local storage path, so prefer it before reconstructing a
        // path from the URL. This is read-only and remains inside uploads.
        if (class_exists('\RoutesPro\Security\MediaRegistry') && \RoutesPro\Security\MediaRegistry::tableReady()) {
            global $wpdb;
            $registry = \RoutesPro\Security\MediaRegistry::table();
            $hashes = [hash('sha256', $url)];
            $parts = wp_parse_url($url);
            if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host']) && !empty($parts['path'])) {
                $canonical = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
                if ($canonical !== $url) $hashes[] = hash('sha256', $canonical);
            }
            foreach (array_unique($hashes) as $hash) {
                $registered = (string)$wpdb->get_var($wpdb->prepare("SELECT storage_path FROM {$registry} WHERE url_hash=%s AND storage_path<>'' LIMIT 1", $hash));
                if ($registered === '') continue;
                $registeredPath = realpath($registered);
                if ($registeredPath && is_file($registeredPath) && ($registeredPath === $base_dir || strpos($registeredPath, $base_dir . DIRECTORY_SEPARATOR) === 0)) return ['path' => $registeredPath, 'source' => 'media_registry'];
            }
        }

        $url_path = rawurldecode((string)wp_parse_url($url, PHP_URL_PATH));
        if ($url_path === '') return null;
        $base_path = rtrim(rawurldecode((string)wp_parse_url((string)$uploads['baseurl'], PHP_URL_PATH)), '/');
        $relative = '';
        if ($base_path !== '' && strpos($url_path, $base_path . '/') === 0) {
            $relative = ltrim(substr($url_path, strlen($base_path)), '/');
        } elseif (preg_match('#/(?:wp-content/)?uploads/(.+)$#i', $url_path, $match)) {
            $relative = ltrim((string)$match[1], '/');
        }
        if ($relative === '' || strpos($relative, '..') !== false) return null;
        $candidate = trailingslashit($base_dir) . $relative;
        $path = realpath($candidate);
        if (!$path || !is_file($path)) {
            $original_candidate = preg_replace('/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $candidate);
            $path = $original_candidate !== $candidate ? realpath((string)$original_candidate) : false;
        }
        if (!$path || !is_file($path) || ($path !== $base_dir && strpos($path, $base_dir . DIRECTORY_SEPARATOR) !== 0)) return null;
        return ['path' => $path, 'source' => 'upload_url'];
    }

    private function remove_analytics_temp_dir(string $dir): void {
        if (!is_dir($dir)) return;
        foreach (glob(trailingslashit($dir) . '*') ?: [] as $file) if (is_file($file)) @unlink($file);
        @rmdir($dir);
    }

    private function location_rows_for_range(WP_REST_Request $req, string $from, string $to): array {
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $clone = new \WP_REST_Request('GET','/');
        foreach (['client_id','project_id','user_id','role','route_id','location_id'] as $key) { $value=$req->get_param($key); if($value!==null&&$value!=='')$clone->set_param($key,$value); }
        $clone->set_param('from',$from); $clone->set_param('to',$to);
        $args=[]; $sqlWhere=$this->build_where($clone,$args,'r'); if($sqlWhere==='')return [];
        $locationScope=absint($clone->get_param('location_id')?:0); $stopLocationSql=$locationScope?$wpdb->prepare(' AND s.location_id=%d',$locationScope):'';
        return $wpdb->get_results("SELECT l.id as location_id,l.name as location_name,l.city,COUNT(s.id) stops,SUM(CASE WHEN s.status IN ('done','completed') THEN 1 ELSE 0 END) stops_done,ROUND(100.0*SUM(CASE WHEN s.status IN ('done','completed') THEN 1 ELSE 0 END)/NULLIF(COUNT(s.id),0),1) done_rate FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id=s.route_id INNER JOIN {$px}locations l ON l.id=s.location_id WHERE $sqlWhere{$stopLocationSql} GROUP BY l.id HAVING COUNT(s.id)>0 ORDER BY done_rate ASC,stops DESC LIMIT 300",ARRAY_A)?:[];
    }

    private function range_summary(WP_REST_Request $req, string $from, string $to): array {
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $clone = new \WP_REST_Request('GET', '/');
        foreach (['client_id','project_id','user_id','role','route_id','location_id'] as $key) {
            $value = $req->get_param($key);
            if ($value !== null && $value !== '') $clone->set_param($key, $value);
        }
        $clone->set_param('from', $from);
        $clone->set_param('to', $to);
        $args = [];
        $sqlWhere = $this->build_where($clone, $args, 'r');
        if ($sqlWhere === '') {
            return [
                'total_routes' => 0,
                'completed_routes' => 0,
                'total_stops' => 0,
                'done_stops' => 0,
                'done_rate' => 0,
                'completion_rate' => 0,
            ];
        }
        $this->reconcile_routes_for_where($sqlWhere);
        $location_scope_id = absint($clone->get_param('location_id') ?: 0);
        $stop_location_sql = $location_scope_id ? $wpdb->prepare(' AND s.location_id = %d', $location_scope_id) : '';
        $total_routes = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT r.id FROM {$px}routes r WHERE $sqlWhere GROUP BY r.id) x");
        $total_stops = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT DISTINCT s.id FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id = s.route_id WHERE $sqlWhere{$stop_location_sql}) x");
        $done_stops = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT DISTINCT s.id FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id = s.route_id WHERE $sqlWhere{$stop_location_sql} AND s.status IN ('done','completed')) x");
        if ($location_scope_id) {
            $completed_routes = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM (SELECT r.id FROM {$px}routes r WHERE $sqlWhere AND NOT EXISTS (SELECT 1 FROM {$px}route_stops s_done WHERE s_done.route_id = r.id AND s_done.location_id = %d AND (s_done.status NOT IN ('done','completed') AND NOT (s_done.status='failed' AND TRIM(COALESCE(s_done.fail_reason,''))<>''))) GROUP BY r.id) x", $location_scope_id));
        } else {
            $completed_routes = (int)$wpdb->get_var("SELECT COUNT(*) FROM (SELECT r.id FROM {$px}routes r WHERE $sqlWhere AND NOT EXISTS (SELECT 1 FROM {$px}route_stops s WHERE s.route_id = r.id AND (s.status NOT IN ('done','completed') AND NOT (s.status='failed' AND TRIM(COALESCE(s.fail_reason,''))<>''))) GROUP BY r.id) x");
        }
        return [
            'total_routes' => $total_routes,
            'completed_routes' => $completed_routes,
            'completion_rate' => $total_routes ? round($completed_routes/$total_routes*100,1) : 0,
            'total_stops' => $total_stops,
            'done_stops' => $done_stops,
            'done_rate' => $total_stops ? round($done_stops/$total_stops*100,1) : 0,
        ];
    }

    private function top_rankings(array $rows): array {
        $project = [];
        $owner = [];
        foreach ($rows as $row) {
            $p = trim((string)($row['project_name'] ?? '')) ?: 'Sem campanha';
            $o = trim((string)($row['user_name'] ?? '')) ?: 'Sem owner';
            $project[$p] = ($project[$p] ?? 0) + (int)($row['stops_done'] ?? 0);
            $owner[$o] = ($owner[$o] ?? 0) + (int)($row['stops_done'] ?? 0);
        }
        arsort($project);
        arsort($owner);
        return [
            'top_project' => key($project) ?: '',
            'top_project_value' => $project ? (int)reset($project) : 0,
            'top_owner' => key($owner) ?: '',
            'top_owner_value' => $owner ? (int)reset($owner) : 0,
        ];
    }


    /**
     * Audience-aware operational performance view.
     *
     * The score deliberately avoids using raw question volume because forms can
     * contain a different number of questions. It combines execution (80%) and
     * reporting coverage (20%), then uses completed visits as a tie breaker.
     * Client/manager views may compare members already inside the permitted
     * route scope. Merchandiser views are forced to the authenticated user by
     * the existing Stats permission layer.
     */
    private function team_performance(array $rows, WP_REST_Request $req, string $from, string $to): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $audience = sanitize_key((string)($req->get_param('analytics_audience') ?: 'client'));
        if (!in_array($audience, ['client','merchandiser','manager'], true)) $audience = 'client';
        $userParam = $req->get_param('user_id');
        $selectedUser = ($userParam === 'me' || $userParam === 'current') ? get_current_user_id() : absint($userParam ?: 0);
        $mode = $audience === 'merchandiser' ? 'personal' : ($selectedUser > 0 ? 'individual' : 'team');

        $members = [];
        $routeIds = [];
        foreach ($rows as $row) {
            $uid = absint($row['owner_user_id'] ?? 0);
            if ($uid <= 0) continue;
            $routeId = absint($row['route_id'] ?? $row['id'] ?? 0);
            if ($routeId > 0) $routeIds[$routeId] = $routeId;
            if (!isset($members[$uid])) {
                $members[$uid] = [
                    'user_id'=>$uid,
                    'name'=>trim((string)($row['user_name'] ?? $row['owner_name'] ?? '')) ?: ('Operativo #' . $uid),
                    'routes'=>0,
                    'planned_visits'=>0,
                    'completed_visits'=>0,
                    'terminal_visits'=>0,
                    'reports'=>0,
                    'question_responses'=>0,
                ];
            }
            $members[$uid]['routes']++;
            $members[$uid]['planned_visits'] += max(0, (int)($row['stops'] ?? 0));
            $members[$uid]['completed_visits'] += max(0, (int)($row['stops_done'] ?? 0));
            $members[$uid]['terminal_visits'] += max(0, (int)($row['stops_terminal'] ?? $row['stops_done'] ?? 0));
        }

        if ($members && $routeIds) {
            $records = $px . 'form_records';
            $versions = $px . 'form_record_versions';
            $values = $px . 'form_record_values';
            $submissions = $px . 'form_submissions';
            $allExist = true;
            foreach ([$records,$versions,$values,$submissions] as $table) {
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) { $allExist = false; break; }
            }
            if ($allExist) {
                $ids = array_values($routeIds);
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $sql = "SELECT COALESCE(NULLIF(v.owner_user_id,0),NULLIF(v.user_id,0),NULLIF(s.user_id,0),0) AS user_id,
                               COUNT(DISTINCT v.id) AS reports,
                               SUM(CASE WHEN rv.id IS NOT NULL AND ((rv.value_text IS NOT NULL AND TRIM(rv.value_text)<>'') OR rv.value_number IS NOT NULL OR (rv.value_json IS NOT NULL AND TRIM(rv.value_json)<>'')) THEN 1 ELSE 0 END) AS question_responses
                        FROM {$records} fr
                        INNER JOIN {$versions} v ON v.id=fr.current_version_id
                        INNER JOIN {$submissions} s ON s.id=v.submission_id AND s.status IN ('submitted','reviewed','approved')
                        LEFT JOIN {$values} rv ON rv.version_id=v.id
                        WHERE v.route_id IN ({$placeholders})
                        GROUP BY user_id";
                $responseRows = $wpdb->get_results($wpdb->prepare($sql, ...$ids), ARRAY_A) ?: [];
                foreach ($responseRows as $answerRow) {
                    $uid = absint($answerRow['user_id'] ?? 0);
                    if ($uid <= 0 || !isset($members[$uid])) continue;
                    $members[$uid]['reports'] = max(0, (int)($answerRow['reports'] ?? 0));
                    $members[$uid]['question_responses'] = max(0, (int)($answerRow['question_responses'] ?? 0));
                }
            }
        }

        $list = [];
        foreach ($members as $member) {
            $planned = max(0, (int)$member['planned_visits']);
            $done = max(0, (int)$member['completed_visits']);
            $reports = max(0, (int)$member['reports']);
            $execution = $planned > 0 ? round(($done / $planned) * 100, 1) : 0.0;
            $reporting = $done > 0 ? round(min(100, ($reports / $done) * 100), 1) : ($reports > 0 ? 100.0 : 0.0);
            $index = round(($execution * 0.80) + ($reporting * 0.20), 1);
            $member['execution_rate'] = $execution;
            $member['reporting_coverage'] = $reporting;
            $member['performance_index'] = $index;
            $member['questions_per_report'] = $reports > 0 ? round(((int)$member['question_responses']) / $reports, 1) : 0.0;
            $list[] = $member;
        }
        usort($list, static function(array $a, array $b): int {
            $cmp = ((float)($b['performance_index'] ?? 0)) <=> ((float)($a['performance_index'] ?? 0));
            if ($cmp !== 0) return $cmp;
            $cmp = ((float)($b['execution_rate'] ?? 0)) <=> ((float)($a['execution_rate'] ?? 0));
            if ($cmp !== 0) return $cmp;
            $cmp = ((int)($b['completed_visits'] ?? 0)) <=> ((int)($a['completed_visits'] ?? 0));
            if ($cmp !== 0) return $cmp;
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        foreach ($list as $idx => &$member) $member['position'] = $idx + 1;
        unset($member);

        $focus = null;
        if ($mode !== 'team') {
            if ($selectedUser > 0) {
                foreach ($list as $member) if ((int)$member['user_id'] === $selectedUser) { $focus = $member; break; }
            }
            if ($focus === null && $list) $focus = $list[0];
        }
        $leader = $mode === 'team' && $list ? $list[0] : null;
        $summary = [
            'members'=>count($list),
            'routes'=>array_sum(array_map(static fn($m)=>(int)($m['routes'] ?? 0), $list)),
            'planned_visits'=>array_sum(array_map(static fn($m)=>(int)($m['planned_visits'] ?? 0), $list)),
            'completed_visits'=>array_sum(array_map(static fn($m)=>(int)($m['completed_visits'] ?? 0), $list)),
            'reports'=>array_sum(array_map(static fn($m)=>(int)($m['reports'] ?? 0), $list)),
            'question_responses'=>array_sum(array_map(static fn($m)=>(int)($m['question_responses'] ?? 0), $list)),
        ];
        return [
            'contract'=>'fieldflow-audience-performance-v1',
            'audience'=>$audience,
            'mode'=>$mode,
            'from'=>$from,
            'to'=>$to,
            'members'=>$list,
            'leader'=>$leader,
            'focus'=>$focus,
            'summary'=>$summary,
            'score_basis'=>'80% execução + 20% cobertura de reportes; volume de visitas concluídas resolve empates.',
            'question_metric_scope'=>$mode === 'team' ? 'team' : 'individual',
        ];
    }

    private function reconcile_routes_for_where(string $sqlWhere): void {
        global $wpdb;
        if ($sqlWhere === '' || !class_exists('\RoutesPro\Services\RouteProgressService')) return;
        $px = $wpdb->prefix . 'routespro_';
        $route_ids = $wpdb->get_col("SELECT r.id FROM {$px}routes r WHERE {$sqlWhere} ORDER BY r.date DESC, r.id DESC LIMIT 500") ?: [];
        if ($route_ids) \RoutesPro\Services\RouteProgressService::sync_many($route_ids);
    }

    public function heatmap(WP_REST_Request $req) {
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $args = [];
        $sqlWhere = $this->build_where($req, $args, 'r');
        if ($sqlWhere === '') return new \WP_Error('forbidden', 'Sem acesso ao âmbito pedido.', ['status' => 403]);
        $rows = $wpdb->get_results("SELECT t.lat, t.lng, COUNT(*) as c FROM (SELECT DISTINCT s.id, l.lat, l.lng FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id = s.route_id INNER JOIN {$px}locations l ON l.id = s.location_id WHERE $sqlWhere AND l.lat IS NOT NULL AND l.lng IS NOT NULL) t GROUP BY t.lat, t.lng LIMIT 5000", ARRAY_A);
        return new WP_REST_Response(['points'=>$rows ?: []], 200);
    }
}
