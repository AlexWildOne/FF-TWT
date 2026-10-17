<?php
namespace RoutesPro\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoutesPro\Repositories\RouteAccessRepository;
use RoutesPro\Support\Permissions;
use RoutesPro\Support\AssignmentResolver;
use RoutesPro\Support\RouteStatus;
use RoutesPro\Services\RouteEndpointProfileService;
use RoutesPro\Services\VisitExceptionService;

class RoutesController {
    const NS = 'routespro/v1';

    public function register_routes() {
        // /routes
        register_rest_route(self::NS, '/routes', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'list_routes'],
                'permission_callback' => [$this, 'can_list_routes'],
            ],
            [
                'methods'  => 'POST',
                'callback' => [$this, 'create_route'],
                'permission_callback' => [$this, 'can_create_route']
            ],
        ]);

        // /routes/{id}
        register_rest_route(self::NS, '/routes/(?P<id>\d+)', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'get_route'],
                'permission_callback' => [$this, 'can_read_route'],
            ],
            [
                'methods'  => 'PATCH',
                'callback' => [$this, 'update_route'],
                'permission_callback' => [$this, 'can_edit_route'],
            ],
            [
                'methods'  => 'DELETE',
                'callback' => [$this, 'delete_route'],
                'permission_callback' => function() { return current_user_can('routespro_manage'); }
            ],
        ]);

        register_rest_route(self::NS, '/routes/(?P<id>\d+)/workspace', [[
            'methods'  => 'POST',
            'callback' => [$this, 'save_route_workspace'],
            'permission_callback' => [$this, 'can_edit_route'],
        ]]);

        register_rest_route(self::NS, '/routes/(?P<id>\d+)/workspace/restore', [[
            'methods'  => 'POST',
            'callback' => [$this, 'restore_route_workspace'],
            'permission_callback' => [$this, 'can_edit_route'],
        ]]);

        // /routes/{id}/stops  (GET para debug + DELETE para limpar antes de recriar)
        register_rest_route(self::NS, '/routes/(?P<id>\d+)/stops', [
            [
                'methods'  => 'GET',
                'callback' => [$this, 'list_stops'],
                'permission_callback' => [$this, 'can_read_route'],
            ],
            [
                'methods'  => 'DELETE',
                'callback' => [$this, 'clear_stops'],
                'permission_callback' => function() { return current_user_can('routespro_manage'); }
            ],
        ]);

        register_rest_route(self::NS, '/routes/(?P<id>\d+)/stops/bulk-remove', [[
            'methods'  => 'POST',
            'callback' => [$this, 'bulk_remove_stops'],
            'permission_callback' => [$this, 'can_edit_route'],
        ]]);

        // /stops (criar)
        register_rest_route(self::NS, '/stops', [
            [
                'methods'  => 'POST',
                'callback' => [$this, 'create_stop'],
                'permission_callback' => [$this, 'can_create_stop'],
            ]
        ]);

        register_rest_route(self::NS, '/campaign-pdvs', [[
            'methods' => 'GET',
            'callback' => [$this, 'list_campaign_pdvs'],
            'permission_callback' => [$this, 'can_access_campaign_pdvs'],
        ]]);
        register_rest_route(self::NS, '/my-periodicity', [[
            'methods' => 'GET',
            'callback' => [$this, 'my_periodicity'],
            'permission_callback' => [$this, 'can_access_campaign_pdvs'],
        ]]);
        register_rest_route(self::NS, '/my-priorities', [[
            'methods' => 'GET',
            'callback' => [$this, 'my_priorities'],
            'permission_callback' => [$this, 'can_access_campaign_pdvs'],
        ]]);
        register_rest_route(self::NS, '/my-execution', [[
            'methods' => 'GET',
            'callback' => [$this, 'my_execution'],
            'permission_callback' => [$this, 'can_access_campaign_pdvs'],
        ]]);
        register_rest_route(self::NS, '/visit-exceptions', [[
            'methods' => 'POST',
            'callback' => [$this, 'save_visit_exception'],
            'permission_callback' => [$this, 'can_manage_visit_exception'],
        ]]);
        register_rest_route(self::NS, '/visit-exceptions/(?P<link_id>\d+)', [[
            'methods' => 'DELETE',
            'callback' => [$this, 'clear_visit_exception'],
            'permission_callback' => [$this, 'can_manage_visit_exception'],
        ]]);
        register_rest_route(self::NS, '/campaign-pdvs/(?P<id>\d+)', [[
            'methods' => 'PATCH',
            'callback' => [$this, 'update_campaign_pdv'],
            'permission_callback' => [$this, 'can_manage_campaign_pdvs'],
        ]]);

        // /stops/{id} (apagar/atualizar)
        register_rest_route(self::NS, '/stops/(?P<id>\d+)', [
            [
                'methods'  => 'DELETE',
                'callback' => [$this, 'delete_stop'],
                'permission_callback' => [$this, 'can_mutate_stop'],
            ],
            [
                'methods'  => 'PATCH',
                'callback' => [$this, 'update_stop_seq_or_status'],
                'permission_callback' => [$this, 'can_mutate_stop'],
            ]
        ]);

        // Alguns alojamentos, WAFs e WebViews bloqueiam DELETE. Este endpoint
        // executa exatamente a mesma operacao e mantem as mesmas permissoes.
        register_rest_route(self::NS, '/stops/(?P<id>\d+)/remove', [[
            'methods'  => 'POST',
            'callback' => [$this, 'delete_stop'],
            'permission_callback' => [$this, 'can_mutate_stop'],
        ]]);

        register_rest_route(self::NS, '/stops/(?P<id>\d+)/reset-pending', [[
            'methods'  => 'POST',
            'callback' => [$this, 'reset_stop_pending'],
            'permission_callback' => static function() { return current_user_can('routespro_manage'); },
        ]]);

        register_rest_route(self::NS, '/stops/(?P<id>\d+)/reopen', [[
            'methods'  => 'POST',
            'callback' => [$this, 'reopen_stop_pending'],
            'permission_callback' => static function() { return current_user_can('routespro_manage'); },
        ]]);

        register_rest_route(self::NS, '/stops/(?P<id>\d+)/complete-manual', [[
            'methods'  => 'POST',
            'callback' => [$this, 'complete_stop_manual'],
            'permission_callback' => static function() { return current_user_can('routespro_manage'); },
        ]]);
    }

    /* =========================================================
     * PERMISSIONS HELPERS
     * ======================================================= */

    private function is_route_readable_by_current($route_id){
        return Permissions::can_access_route((int)$route_id, get_current_user_id());
    }

    private function is_route_editable_by_current($route_id){
        return Permissions::can_edit_route((int)$route_id, get_current_user_id());
    }

    public function can_list_routes(WP_REST_Request $req){
        // Permite GET /routes?user_id=me a utilizadores autenticados
        $mine = ($req->get_param('user_id') === 'me');
        if ($mine) return is_user_logged_in();
        // Gestores veem tudo, users com âmbito restrito podem listar por cliente/campanha
        if (current_user_can('routespro_manage')) return true;
        $client_id = absint($req->get_param('client_id') ?: 0);
        $project_id = absint($req->get_param('project_id') ?: 0);
        return !is_wp_error(Permissions::assert_scope_or_error($client_id, $project_id)) && Permissions::can_access_front();
    }

    public function can_read_route(WP_REST_Request $req){
        $id = absint($req['id']);
        return $this->is_route_readable_by_current($id);
    }

    public function can_edit_route(WP_REST_Request $req){
        // Permite PATCH se o utilizador tiver acesso à rota
        $id = absint($req['id']);
        return $this->is_route_editable_by_current($id);
    }

    public function can_create_route(WP_REST_Request $req){
        if (!is_user_logged_in()) return false;
        if (current_user_can('routespro_manage')) return true;
        if (!Permissions::can_access_front()) return false;
        $data = $req->get_json_params() ?: [];
        $client_id = absint($data['client_id'] ?? 0);
        $project_id = absint($data['project_id'] ?? 0);
        if (!$client_id || !$project_id) return false;
        $scope = Permissions::assert_scope_or_error($client_id, $project_id);
        if (is_wp_error($scope)) return $scope;
        return $this->project_has_assigned_pdvs($project_id, get_current_user_id());
    }


    public function can_access_campaign_pdvs(WP_REST_Request $req){
        if (!is_user_logged_in()) return false;
        if (current_user_can('routespro_manage')) return true;
        return Permissions::can_access_front();
    }

    public function can_manage_campaign_pdvs(WP_REST_Request $req){
        return current_user_can('routespro_manage');
    }

    private function campaign_projects_for_request(WP_REST_Request $req): array {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $client_id = absint($req->get_param('client_id') ?: 0);
        $project_id = absint($req->get_param('project_id') ?: 0);
        $plannerAssigned = (string)$req->get_param('planner_assigned') === '1';
        if ($plannerAssigned && !current_user_can('routespro_manage') && $project_id > 0) {
            $uid = get_current_user_id();
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT p.id, p.client_id, p.name
                 FROM {$px}projects p
                 WHERE p.id=%d
                   AND EXISTS (SELECT 1 FROM {$px}campaign_locations cl WHERE cl.project_id=p.id AND cl.assigned_to=%d)
                 LIMIT 1",
                $project_id,
                $uid
            ), ARRAY_A);
            if (!$row || ($client_id > 0 && (int)$row['client_id'] !== $client_id)) return [];
            return [$row];
        }
        [$client_id, $project_id] = Permissions::sanitize_scope_selection($client_id, $project_id);
        if ($project_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, client_id, name FROM {$px}projects WHERE id=%d", $project_id), ARRAY_A);
            return $row ? [$row] : [];
        }
        $where = ['1=1']; $args = [];
        if ($client_id > 0) { $where[] = 'client_id=%d'; $args[] = $client_id; }
        if (!current_user_can('routespro_manage')) {
            $scope = Permissions::get_scope();
            $allowedProjects = array_values(array_filter(array_map('absint', (array)($scope['project_ids'] ?? []))));
            $allowedClients = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
            $parts = [];
            if ($allowedProjects) { $parts[] = 'id IN (' . implode(',', array_fill(0, count($allowedProjects), '%d')) . ')'; $args = array_merge($args, $allowedProjects); }
            if ($allowedClients) { $parts[] = 'client_id IN (' . implode(',', array_fill(0, count($allowedClients), '%d')) . ')'; $args = array_merge($args, $allowedClients); }
            $where[] = $parts ? '(' . implode(' OR ', $parts) . ')' : '1=0';
        }
        $sql = "SELECT id, client_id, name FROM {$px}projects WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";
        return $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: []) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
    }

    private function campaign_pdv_insignia(array $row): array {
        $raw = trim(implode(' ', [
            (string)($row['name'] ?? ''),
            (string)($row['tags'] ?? ''),
            (string)($row['meta_json'] ?? ''),
            (string)($row['category_name'] ?? ''),
            (string)($row['subcategory_name'] ?? ''),
        ]));
        if (function_exists('remove_accents')) $raw = remove_accents($raw);
        $value = strtolower($raw);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = ' ' . trim((string)$value) . ' ';
        $patterns = [
            'continente_bom_dia' => '/\b(bom dia|bomdia|cbd)\b/',
            'continente_modelo' => '/\b(continente modelo|modelo|mdl)\b/',
            'continente' => '/\b(continente|cnt)\b/',
            'pingo_doce' => '/\b(pingo doce|pingodoce|pd)\b/',
            'intermarche' => '/\bintermarche\b/',
            'recheio' => '/\brecheio\b/',
            'makro' => '/\bmakro\b/',
            'auchan' => '/\b(auchan|jumbo)\b/',
            'mercadona' => '/\bmercadona\b/',
            'lidl' => '/\blidl\b/',
            'aldi' => '/\baldi\b/',
            'minipreco' => '/\bminipreco\b/',
            'el_corte_ingles' => '/\b(el corte ingles|eci)\b/',
            'froiz' => '/\bfroiz\b/',
            'apolonia' => '/\bapolonia\b/',
            'e_leclerc' => '/\b(e leclerc|eleclerc|leclerc)\b/',
        ];
        $key = 'outras';
        foreach ($patterns as $candidate => $pattern) {
            if (preg_match($pattern, $value)) { $key = $candidate; break; }
        }
        $labels = [
            'continente_bom_dia' => 'Continente Bom Dia',
            'continente_modelo' => 'Continente Modelo',
            'continente' => 'Continente',
            'pingo_doce' => 'Pingo Doce',
            'intermarche' => 'Intermarché',
            'recheio' => 'Recheio',
            'makro' => 'Makro',
            'auchan' => 'Auchan',
            'mercadona' => 'Mercadona',
            'lidl' => 'Lidl',
            'aldi' => 'Aldi',
            'minipreco' => 'Minipreço',
            'el_corte_ingles' => 'El Corte Inglés',
            'froiz' => 'Froiz',
            'apolonia' => 'Apolónia',
            'e_leclerc' => 'E.Leclerc',
            'outras' => 'Outras',
        ];
        return ['key' => $key, 'label' => $labels[$key] ?? 'Outras'];
    }

    public function my_periodicity(WP_REST_Request $req){
        $month = sanitize_text_field((string)($req->get_param('month') ?: current_time('Y-m')));
        $project_id = absint($req->get_param('project_id') ?: 0);
        $client_id = absint($req->get_param('client_id') ?: 0);
        $result = \RoutesPro\Services\PeriodicityProgressService::forUser(get_current_user_id(), $month, $project_id, $client_id);
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function my_priorities(WP_REST_Request $req){
        $date = sanitize_text_field((string)($req->get_param('date') ?: current_time('Y-m-d')));
        $project_id = absint($req->get_param('project_id') ?: 0);
        $client_id = absint($req->get_param('client_id') ?: 0);
        $result = \RoutesPro\Services\DailyOperationalPriorityService::forUser(get_current_user_id(), $date, $project_id, $client_id);
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function my_execution(WP_REST_Request $req){
        $date = sanitize_text_field((string)($req->get_param('date') ?: current_time('Y-m-d')));
        $project_id = absint($req->get_param('project_id') ?: 0);
        $client_id = absint($req->get_param('client_id') ?: 0);
        $result = \RoutesPro\Services\DailyExecutionExceptionService::forUser(get_current_user_id(), $date, $project_id, $client_id);
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function can_manage_visit_exception(WP_REST_Request $req){
        if (!is_user_logged_in()) return false;
        $linkId = absint($req['link_id'] ?? 0);
        if ($linkId <= 0) {
            $payload = $req->get_json_params() ?: [];
            $linkId = absint($payload['link_id'] ?? 0);
        }
        return $linkId > 0 && VisitExceptionService::canManageLink($linkId, get_current_user_id());
    }

    public function save_visit_exception(WP_REST_Request $req){
        $payload = $req->get_json_params() ?: [];
        $linkId = absint($payload['link_id'] ?? 0);
        $result = VisitExceptionService::upsert($linkId, $payload, get_current_user_id());
        return is_wp_error($result) ? $result : new WP_REST_Response(['ok'=>true,'exception'=>$result], 200);
    }

    public function clear_visit_exception(WP_REST_Request $req){
        $linkId = absint($req['link_id'] ?? 0);
        $result = VisitExceptionService::clear($linkId, get_current_user_id());
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function list_campaign_pdvs(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $projects = $this->campaign_projects_for_request($req);
        $projectIds = array_values(array_filter(array_map(fn($r)=>absint($r['id'] ?? 0), $projects)));
        if (!$projectIds) return new WP_REST_Response(['rows'=>[], 'users'=>[], 'summary'=>['total'=>0,'filtered'=>0,'active'=>0,'with_owner'=>0,'with_coords'=>0,'routes_count'=>0,'routes_km'=>0], 'page'=>1, 'total_pages'=>1], 200);
        $where = ['cl.project_id IN (' . implode(',', array_fill(0, count($projectIds), '%d')) . ')'];
        $args = $projectIds;
        $q = sanitize_text_field((string)($req->get_param('q') ?: ''));
        if ($q !== '') { $like = '%' . $wpdb->esc_like($q) . '%'; $where[] = '(l.name LIKE %s OR l.address LIKE %s OR l.city LIKE %s OR l.phone LIKE %s OR l.postal_code LIKE %s OR p.name LIKE %s OR c.name LIKE %s)'; array_push($args, $like, $like, $like, $like, $like, $like, $like); }
        $plannerAssigned = (string)$req->get_param('planner_assigned') === '1';
        // 9BC planner_assigned bypasses lifecycle filters so Planear always receives the complete assigned catalogue.
        $status = sanitize_text_field((string)($req->get_param('status') ?: ''));
        $active = (string)($req->get_param('active') ?? '');
        if (!$plannerAssigned) {
            if (in_array($status, ['active','paused'], true)) { $where[] = 'cl.status=%s'; $args[] = $status; }
            if ($active === '1' || $active === '0') { $where[] = 'cl.is_active=%d'; $args[] = (int)$active; }
        }
        $owner = absint($req->get_param('owner_user_id') ?: 0);
        if (!current_user_can('routespro_manage')) $owner = get_current_user_id();
        if ($owner > 0) { $where[] = 'cl.assigned_to=%d'; $args[] = $owner; }
        $page = max(1, absint($req->get_param('page') ?: 1));
        $perPage = max(5, min(100, absint($req->get_param('per_page') ?: 25)));
        $baseJoin = " FROM {$px}campaign_locations cl INNER JOIN {$px}locations l ON l.id=cl.location_id INNER JOIN {$px}projects p ON p.id=cl.project_id LEFT JOIN {$px}clients c ON c.id=p.client_id LEFT JOIN {$px}categories cat ON cat.id=l.category_id LEFT JOIN {$px}categories scat ON scat.id=l.subcategory_id LEFT JOIN {$wpdb->users} owner ON owner.ID=cl.assigned_to WHERE " . implode(' AND ', $where);
        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*)" . $baseJoin, ...$args));
        $offset = ($page - 1) * $perPage;
        $select = "SELECT cl.id AS link_id, cl.project_id, cl.location_id, cl.status AS campaign_status, cl.priority, cl.visit_frequency, cl.frequency_count, cl.visit_duration_min, cl.min_gap_days, cl.max_gap_days, cl.preferred_weekdays, cl.blocked_weekdays, cl.time_window_start, cl.time_window_end, cl.allow_auto_reschedule, cl.allow_overtime, cl.rule_notes, cl.assigned_to, cl.is_active AS campaign_active, l.id, l.name, l.address, l.city, l.postal_code, l.phone, l.lat, l.lng, l.tags, l.meta_json, cat.name AS category_name, scat.name AS subcategory_name, p.name AS project_name, c.name AS client_name, owner.display_name AS assigned_to_name";
        $rows = $wpdb->get_results($wpdb->prepare($select . $baseJoin . " ORDER BY p.name ASC, cl.priority DESC, l.city ASC, l.name ASC LIMIT %d OFFSET %d", ...array_merge($args, [$perPage, $offset])), ARRAY_A) ?: [];
        foreach ($rows as &$campaignPdvRow) {
            $insignia = $this->campaign_pdv_insignia($campaignPdvRow);
            $campaignPdvRow['insignia_key'] = $insignia['key'];
            $campaignPdvRow['insignia_label'] = $insignia['label'];
            unset($campaignPdvRow['tags'], $campaignPdvRow['meta_json']);
        }
        unset($campaignPdvRow);
        $aggregate = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS filtered,
                    COALESCE(SUM(cl.is_active=1),0) AS active,
                    COALESCE(SUM(cl.assigned_to IS NOT NULL AND cl.assigned_to>0),0) AS with_owner,
                    COALESCE(SUM(l.lat IS NOT NULL AND l.lng IS NOT NULL),0) AS with_coords" . $baseJoin,
            ...$args
        ), ARRAY_A) ?: [];
        $summary = [
            'total' => $total,
            'filtered' => (int)($aggregate['filtered'] ?? $total),
            'active' => (int)($aggregate['active'] ?? 0),
            'with_owner' => (int)($aggregate['with_owner'] ?? 0),
            'with_coords' => (int)($aggregate['with_coords'] ?? 0),
            'routes_count' => 0,
            'routes_km' => 0,
        ];
        $routeWhere = ['project_id IN (' . implode(',', array_fill(0, count($projectIds), '%d')) . ')']; $routeArgs = $projectIds;
        if ($owner > 0) { $routeWhere[] = 'owner_user_id=%d'; $routeArgs[] = $owner; }
        $routeSummary = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(distance_km),0) AS km FROM {$px}routes WHERE " . implode(' AND ', $routeWhere), ...$routeArgs), ARRAY_A) ?: [];
        $summary['routes_count'] = (int)($routeSummary['cnt'] ?? 0); $summary['routes_km'] = (float)($routeSummary['km'] ?? 0);
        $userIds = [];
        foreach ($projects as $pr) {
            foreach (AssignmentResolver::get_project_operational_user_ids((int)($pr['id'] ?? 0)) as $uid) {
                $userIds[$uid] = $uid;
            }
        }
        // Keep assigned users visible only when they are real field accounts.
        foreach ($rows as $r) {
            $uid = absint($r['assigned_to'] ?? 0);
            if ($uid && !Permissions::is_manager($uid)) $userIds[$uid] = $uid;
        }
        $users = $userIds ? get_users(['include'=>array_values($userIds), 'orderby'=>'display_name', 'order'=>'ASC', 'fields'=>['ID','display_name','user_login']]) : [];
        return new WP_REST_Response(['rows'=>$rows, 'users'=>$users, 'summary'=>$summary, 'page'=>$page, 'total_pages'=>max(1, (int)ceil($total / $perPage))], 200);
    }

    public function update_campaign_pdv(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        if (!current_user_can('routespro_manage')) {
            return new WP_Error('forbidden', 'Apenas a gestão pode alterar a configuração dos PDV de campanha.', ['status'=>403]);
        }
        $link_id = absint($req['id'] ?? 0);
        $row = $wpdb->get_row($wpdb->prepare("SELECT cl.id, cl.project_id, p.client_id FROM {$px}campaign_locations cl INNER JOIN {$px}projects p ON p.id=cl.project_id WHERE cl.id=%d", $link_id), ARRAY_A);
        if (!$row) return new WP_Error('not_found', 'PDV de campanha não encontrado.', ['status'=>404]);
        $scope = Permissions::assert_scope_or_error((int)($row['client_id'] ?? 0), (int)($row['project_id'] ?? 0));
        if (is_wp_error($scope)) return $scope;
        $p = $req->get_json_params() ?: [];
        $freq = sanitize_text_field((string)($p['visit_frequency'] ?? 'weekly')); if (!in_array($freq, ['weekly','monthly'], true)) $freq = 'weekly';
        $status = sanitize_text_field((string)($p['status'] ?? 'active')); if (!in_array($status, ['active','paused'], true)) $status = 'active';
        $payload = [
            'assigned_to' => absint($p['assigned_to'] ?? 0),
            'visit_frequency' => $freq,
            'frequency_count' => max(1, min(7, absint($p['frequency_count'] ?? 1))),
            'visit_duration_min' => max(0, min(360, absint($p['visit_duration_min'] ?? 45))),
            'priority' => max(0, min(999, absint($p['priority'] ?? 0))),
            'min_gap_days' => max(0, min(31, absint($p['min_gap_days'] ?? 0))),
            'max_gap_days' => max(0, min(90, absint($p['max_gap_days'] ?? 0))),
            'preferred_weekdays' => sanitize_text_field((string)($p['preferred_weekdays'] ?? '')),
            'blocked_weekdays' => sanitize_text_field((string)($p['blocked_weekdays'] ?? '')),
            'time_window_start' => sanitize_text_field((string)($p['time_window_start'] ?? '')),
            'time_window_end' => sanitize_text_field((string)($p['time_window_end'] ?? '')),
            'allow_auto_reschedule' => !empty($p['allow_auto_reschedule']) ? 1 : 0,
            'allow_overtime' => !empty($p['allow_overtime']) ? 1 : 0,
            'rule_notes' => sanitize_textarea_field((string)($p['rule_notes'] ?? '')),
            'is_active' => !empty($p['is_active']) ? 1 : 0,
            'status' => $status,
        ];
        $wpdb->update($px . 'campaign_locations', $payload, ['id'=>$link_id], ['%d','%s','%d','%d','%d','%d','%d','%s','%s','%s','%s','%d','%d','%s','%d','%s'], ['%d']);
        return new WP_REST_Response(['ok'=>true, 'id'=>$link_id], 200);
    }

    public function can_create_stop(WP_REST_Request $req){
        // Verifica route_id no corpo
        $p = $req->get_json_params() ?: [];
        $route_id = absint($p['route_id'] ?? 0);
        return $route_id ? $this->is_route_editable_by_current($route_id) : current_user_can('routespro_manage');
    }

    public function can_mutate_stop(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $id = absint($req['id']);
        $route_id = (int)$wpdb->get_var($wpdb->prepare("SELECT route_id FROM {$px}route_stops WHERE id=%d",$id));
        return $route_id ? $this->is_route_editable_by_current($route_id) : current_user_can('routespro_manage');
    }

    // 9BB predecessor marker: assigned_to=%d AND is_active=1 AND status='active' LIMIT 1
    // 9BC planner assignment authority: assigned_to alone defines catalogue membership for the merchandiser.
    private function project_has_assigned_pdvs(int $project_id, int $user_id): bool {
        if ($project_id <= 0 || $user_id <= 0) return false;
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$px}campaign_locations WHERE project_id=%d AND assigned_to=%d LIMIT 1",
            $project_id,
            $user_id
        ));
    }


    private function is_valid_route_date(string $date): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year);
    }

    private function existing_route_for_owner_project_date(int $owner_id, int $project_id, string $date, int $exclude_id = 0): int {
        if ($owner_id <= 0 || $project_id <= 0 || !$this->is_valid_route_date($date)) return 0;
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $sql = "SELECT id FROM {$px}routes WHERE owner_user_id=%d AND project_id=%d AND date=%s AND status NOT IN ('canceled','cancelled')";
        $args = [$owner_id, $project_id, $date];
        if ($exclude_id > 0) { $sql .= ' AND id<>%d'; $args[] = $exclude_id; }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        return (int)$wpdb->get_var($wpdb->prepare($sql, ...$args));
    }

    private function location_is_assigned_to_route_owner(int $route_id, int $location_id): bool {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $route = $wpdb->get_row($wpdb->prepare(
            "SELECT project_id, owner_user_id, meta_json FROM {$px}routes WHERE id=%d LIMIT 1",
            $route_id
        ), ARRAY_A);
        if (!$route) return false;
        $meta = !empty($route['meta_json']) ? json_decode((string)$route['meta_json'], true) : [];
        if (!is_array($meta) || empty($meta['self_planned'])) return true;
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$px}campaign_locations WHERE project_id=%d AND location_id=%d AND assigned_to=%d LIMIT 1",
            absint($route['project_id'] ?? 0),
            $location_id,
            absint($route['owner_user_id'] ?? 0)
        ));
    }

    /* =========================================================
     * UTILS
     * ======================================================= */

    /**
     * Converte ISO8601/datetime-local para formato MySQL (UTC).
     * Aceita: "2025-10-29T09:30", "2025-10-29T09:30:00Z", etc.
     */
    private function iso_to_mysql( $val ){
        if (!$val) return null;
        $ts = strtotime($val);
        if (!$ts) return null;
        // Armazena em UTC
        return gmdate('Y-m-d H:i:s', $ts);
    }

    /* =========================================================
     * ROUTES
     * ======================================================= */

    public function list_routes(WP_REST_Request $req) {
        $date = sanitize_text_field($req->get_param('date') ?: '');
        $date_from = sanitize_text_field($req->get_param('date_from') ?: '');
        $date_to = sanitize_text_field($req->get_param('date_to') ?: '');
        $filters = [
            'mine' => ($req->get_param('user_id') === 'me'),
            'client_id' => absint($req->get_param('client_id') ?: 0),
            'project_id' => absint($req->get_param('project_id') ?: 0),
            'owner_user_id' => absint($req->get_param('owner_user_id') ?: 0),
            'location_id' => absint($req->get_param('location_id') ?: 0),
            'limit' => absint($req->get_param('limit') ?: 500),
        ];
        if ($date_from || $date_to) {
            $filters['date_from'] = $date_from;
            $filters['date_to'] = $date_to;
        } else {
            $filters['date'] = $date ?: current_time('Y-m-d');
        }

        $routes = RouteAccessRepository::listRoutes($filters);

        if (is_wp_error($routes)) {
            return $routes;
        }

        return new WP_REST_Response(['routes' => $routes], 200);
    }

    public function get_route(WP_REST_Request $req) {
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $id = absint($req['id']);

        $route = $wpdb->get_row($wpdb->prepare("SELECT r.*, c.name AS client_name, p.name AS project_name, COALESCE(NULLIF(owner.display_name,''), owner.user_login) AS owner_name FROM {$px}routes r LEFT JOIN {$px}clients c ON c.id = r.client_id LEFT JOIN {$px}projects p ON p.id = r.project_id LEFT JOIN {$wpdb->users} owner ON owner.ID = r.owner_user_id WHERE r.id=%d", $id), ARRAY_A);
        if (!$route) return new WP_Error('not_found', 'Route not found', ['status'=>404]);
        if (!$this->is_route_readable_by_current($id)) return new WP_Error('forbidden','Sem acesso à rota', ['status'=>403]);

        // Uma submissão válida significa visita executada. Reconciliar antes de
        // devolver o detalhe impede que um refresh volte a mostrar a paragem pendente.
        if (class_exists('\RoutesPro\Services\RouteProgressService')) {
            $progress = \RoutesPro\Services\RouteProgressService::sync($id);
            if (is_wp_error($progress)) return $progress;
            $route = $wpdb->get_row($wpdb->prepare("SELECT r.*, c.name AS client_name, p.name AS project_name, COALESCE(NULLIF(owner.display_name,''), owner.user_login) AS owner_name FROM {$px}routes r LEFT JOIN {$px}clients c ON c.id = r.client_id LEFT JOIN {$px}projects p ON p.id = r.project_id LEFT JOIN {$wpdb->users} owner ON owner.ID = r.owner_user_id WHERE r.id=%d", $id), ARRAY_A);
        }

        $stops = $wpdb->get_results($wpdb->prepare("
            SELECT rs.*, l.name AS location_name, l.address, l.lat, l.lng
            FROM {$px}route_stops rs
            INNER JOIN {$px}locations l ON l.id = rs.location_id
            WHERE rs.route_id = %d
            ORDER BY rs.seq ASC, rs.id ASC
        ", $id), ARRAY_A);

        // assignments (utilizadores + função)
        $assigns = $wpdb->get_results($wpdb->prepare("
            SELECT a.id, a.user_id, a.role,
                   u.display_name, u.user_email, u.user_login
            FROM {$px}assignments a
            LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
            WHERE a.route_id = %d
            ORDER BY a.id ASC
        ", $id), ARRAY_A);

        // Flatten para o front. O estado canónico da visita prevalece sobre
        // qualquer projeção legacy ainda presente em route_stops. Isto mantém
        // Reportar e Minha Rota alinhados mesmo quando uma submissão acabou de
        // fechar a visita e um leitor anterior ainda tinha "pending" em memória.
        foreach ($stops as &$stop) {
            $stop_meta = !empty($stop['meta_json']) ? json_decode((string) $stop['meta_json'], true) : [];
            if (is_array($stop_meta)) {
                $stop['visit_time_min'] = max(0, (int) ($stop_meta['visit_time_min'] ?? 0));
                $stop['visit_time_mode'] = sanitize_text_field((string) ($stop_meta['visit_time_mode'] ?? ''));
            }

            $legacy_status = sanitize_key((string)($stop['status'] ?? 'pending')) ?: 'pending';
            $canonical_status = '';
            if (class_exists('\RoutesPro\Core\CanonicalStateService')) {
                $visit_contract = \RoutesPro\Core\CanonicalStateService::visit(absint($stop['id'] ?? 0));
                $visit_state = is_array($visit_contract['data'] ?? null) ? (array)$visit_contract['data'] : [];
                $canonical_status = sanitize_key((string)($visit_state['status'] ?? ''));
            }
            $stop['legacy_status'] = $legacy_status;
            $stop['canonical_status'] = $canonical_status !== '' ? $canonical_status : self::canonical_front_stop_status($legacy_status);
            $stop['status_source'] = 'canonical_visit_state';
            $stop['status'] = self::front_stop_status_from_canonical((string)$stop['canonical_status'], $legacy_status);
        }
        unset($stop);

        $route_meta = !empty($route['meta_json']) ? json_decode((string) $route['meta_json'], true) : [];
        if (!is_array($route_meta)) $route_meta = [];
        // Nao recalcular métricas no portal: o detalhe deve refletir a rota criada e gravada.
        $done_count = 0;
        foreach ($stops as $stop_row) {
            if (in_array((string)($stop_row['status'] ?? ''), ['done','completed'], true)) $done_count++;
        }
        $stored_route_metrics = is_array($route_meta['route_metrics'] ?? null)
            ? (array)$route_meta['route_metrics']
            : (is_array($route_meta['plan_summary'] ?? null) ? (array)$route_meta['plan_summary'] : []);
        $canonical_route_metrics = class_exists('\RoutesPro\Services\RouteMetricsService')
            ? \RoutesPro\Services\RouteMetricsService::canonicalFromRouteArray(array_merge($route, ['meta_decoded'=>$route_meta, 'metrics'=>$stored_route_metrics]))
            : $stored_route_metrics;
        $route_metrics = array_merge($stored_route_metrics, $canonical_route_metrics);
        $distance_km = is_numeric($canonical_route_metrics['distance_km'] ?? null) ? (float)$canonical_route_metrics['distance_km'] : null;
        $toll_cost_eur = is_numeric($canonical_route_metrics['toll_cost_eur'] ?? null) ? (float)$canonical_route_metrics['toll_cost_eur'] : null;

        $maps_payload = \RoutesPro\Services\GoogleMapsUrlService::routePayload($route_meta, $stops);
        $mobility_cost = [];
        if (class_exists('\RoutesPro\Services\MobilityCostService')) {
            $mobility_cost = \RoutesPro\Services\MobilityCostService::calculateForRouteArray(array_merge($route, [
                'meta_decoded' => $route_meta,
                'metrics' => $route_metrics,
            ]));
            $mobility_cost = \RoutesPro\Services\MobilityCostService::reportingProjection($mobility_cost);
        }
        $payload = array_merge($route, $maps_payload, [
            'meta'       => $route_meta,
            'stops'      => $stops,
            'assignments'=> $assigns,
            'stops_count'=> count($stops),
            'stops_done' => $done_count,
            'done_rate'  => count($stops) > 0 ? round(($done_count / count($stops)) * 100, 1) : 0,
            'distance_km'=> $distance_km !== null ? round((float)$distance_km, 2) : null,
            'route_metrics'=> $route_metrics,
            'known_distance_km'=> is_numeric($route_metrics['known_distance_km'] ?? null) ? round((float)$route_metrics['known_distance_km'], 2) : null,
            'access_distance_km'=> is_numeric($route_metrics['access_distance_km'] ?? null) ? round((float)$route_metrics['access_distance_km'], 2) : null,
            'inter_stop_distance_km'=> is_numeric($route_metrics['inter_stop_distance_km'] ?? null) ? round((float)$route_metrics['inter_stop_distance_km'], 2) : null,
            'return_distance_km'=> is_numeric($route_metrics['return_distance_km'] ?? null) ? round((float)$route_metrics['return_distance_km'], 2) : null,
            'travel_min'=> is_numeric($route_metrics['travel_min'] ?? null) ? round((float)$route_metrics['travel_min'], 1) : null,
            'distance_status'=> (string)($route_metrics['distance_status'] ?? ''),
            'distance_source'=> (string)($route_metrics['distance_source'] ?? ''),
            'matrix_provider'=> (string)($route_metrics['matrix_provider'] ?? ''),
            'optimization_method'=> (string)($route_metrics['optimization_method'] ?? ''),
            'optimization_saved_distance_km'=> is_numeric($route_metrics['optimization_saved_distance_km'] ?? null) ? round((float)$route_metrics['optimization_saved_distance_km'], 2) : 0.0,
            'optimization_saved_travel_min'=> is_numeric($route_metrics['optimization_saved_travel_min'] ?? null) ? round((float)$route_metrics['optimization_saved_travel_min'], 1) : 0.0,
            'territorial_coherence_mode'=> (string)($route_metrics['territorial_coherence_mode'] ?? 'strong'),
            'territory_same_base'=> !empty($route_metrics['territory_same_base']) ? 1 : 0,
            'territory_dominant_direction'=> (string)($route_metrics['territory_dominant_direction'] ?? 'base'),
            'territory_dominant_direction_label'=> (string)($route_metrics['territory_dominant_direction_label'] ?? 'Zona da base'),
            'territory_dominant_share_pct'=> is_numeric($route_metrics['territory_dominant_share_pct'] ?? null) ? round((float)$route_metrics['territory_dominant_share_pct'], 1) : 100.0,
            'territory_direction_switches'=> (int)($route_metrics['territory_direction_switches'] ?? 0),
            'territory_direction_reentries'=> (int)($route_metrics['territory_direction_reentries'] ?? 0),
            'territory_opposite_mix'=> !empty($route_metrics['territory_opposite_mix']) ? 1 : 0,
            'territory_locality_count'=> (int)($route_metrics['territory_locality_count'] ?? 0),
            'territory_locality_reentries'=> (int)($route_metrics['territory_locality_reentries'] ?? 0),
            'territory_bearing_span_deg'=> is_numeric($route_metrics['territory_bearing_span_deg'] ?? null) ? round((float)$route_metrics['territory_bearing_span_deg'], 1) : 0.0,
            'territory_far_stop_count'=> (int)($route_metrics['territory_far_stop_count'] ?? 0),
            'territory_base_crossing_risk'=> !empty($route_metrics['territory_base_crossing_risk']) ? 1 : 0,
            'territory_route_cone_label'=> (string)($route_metrics['territory_route_cone_label'] ?? 'Corredor focado'),
            'territory_coherence_score'=> is_numeric($route_metrics['territory_coherence_score'] ?? null) ? round((float)$route_metrics['territory_coherence_score'], 1) : 100.0,
            'territory_coherence_status'=> (string)($route_metrics['territory_coherence_status'] ?? 'coherent'),
            'territory_coherence_label'=> (string)($route_metrics['territory_coherence_label'] ?? 'Coerente'),
            'toll_cost_eur'=> $toll_cost_eur !== null ? round((float)$toll_cost_eur, 2) : null,
            'toll_model'=> $this->route_toll_model($route_meta),
            'toll_provider'=> $this->route_toll_provider($route_meta),
            'toll_is_real_api'=> $this->route_toll_is_real_api($route_meta),
            'mobility_cost'=> $mobility_cost,
        ]);

        return new WP_REST_Response($payload, 200);
    }

    private static function canonical_front_stop_status(string $status): string {
        $status = sanitize_key($status);
        if (in_array($status, ['done', 'completed'], true)) return 'completed';
        if ($status === 'failed') return 'failed';
        if (in_array($status, ['in_progress', 'active', 'started'], true)) return 'in_progress';
        return 'pending';
    }

    private static function front_stop_status_from_canonical(string $canonical_status, string $fallback = 'pending'): string {
        $canonical_status = sanitize_key($canonical_status);
        if ($canonical_status === 'completed') return 'done';
        if ($canonical_status === 'failed') return 'failed';
        if ($canonical_status === 'in_progress') return 'in_progress';
        if ($canonical_status === 'pending') return 'pending';
        $fallback = sanitize_key($fallback);
        return $fallback !== '' ? $fallback : 'pending';
    }

    private function route_edge_stop_points(int $routeId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $routeId = absint($routeId);
        if ($routeId <= 0) return ['first'=>[], 'last'=>[]];
        $select = "SELECT rs.id AS route_stop_id, rs.location_id, l.name, l.address, l.lat, l.lng, l.place_id, rs.meta_json AS stop_meta_json
                   FROM {$px}route_stops rs
                   LEFT JOIN {$px}locations l ON l.id=rs.location_id
                   WHERE rs.route_id=%d";
        $first = $wpdb->get_row($wpdb->prepare($select . " ORDER BY rs.seq ASC, rs.id ASC LIMIT 1", $routeId), ARRAY_A) ?: [];
        $last = $wpdb->get_row($wpdb->prepare($select . " ORDER BY rs.seq DESC, rs.id DESC LIMIT 1", $routeId), ARRAY_A) ?: [];
        $normalise = static function(array $row): array {
            $meta = [];
            if (!empty($row['stop_meta_json'])) {
                $decoded = json_decode((string)$row['stop_meta_json'], true);
                if (is_array($decoded)) $meta = $decoded;
            }
            $snapshot = is_array($meta['snapshot'] ?? null) ? (array)$meta['snapshot'] : [];
            return [
                'id' => absint($row['location_id'] ?? $snapshot['location_id'] ?? 0),
                'name' => sanitize_text_field((string)($row['name'] ?? $snapshot['name'] ?? '')),
                'address' => sanitize_text_field((string)($row['address'] ?? $snapshot['address'] ?? '')),
                'lat' => is_numeric($row['lat'] ?? null) ? (float)$row['lat'] : (is_numeric($snapshot['lat'] ?? null) ? (float)$snapshot['lat'] : null),
                'lng' => is_numeric($row['lng'] ?? null) ? (float)$row['lng'] : (is_numeric($snapshot['lng'] ?? null) ? (float)$snapshot['lng'] : null),
                'place_id' => sanitize_text_field((string)($row['place_id'] ?? $snapshot['place_id'] ?? '')),
            ];
        };
        return ['first'=>$first ? $normalise($first) : [], 'last'=>$last ? $normalise($last) : []];
    }

    public function create_route(WP_REST_Request $req) {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $data = $req->get_json_params() ?: [];
        $uid  = get_current_user_id();

        $client_id = absint($data['client_id'] ?? 0);
        if (!$client_id) return new WP_Error('bad_request', 'client_id obrigatório', ['status'=>400]);
        $project_id = absint($data['project_id'] ?? 0) ?: null;
        $route_date = sanitize_text_field((string)($data['date'] ?? current_time('Y-m-d')));
        if (!$this->is_valid_route_date($route_date)) {
            return new WP_Error('invalid_route_date', 'A data da rota não é válida.', ['status'=>400]);
        }
        $is_manager = current_user_can('routespro_manage');
        if (!$is_manager) {
            if (!$project_id) return new WP_Error('project_required', 'Seleciona uma campanha para criar a tua rota.', ['status'=>400]);
            $scope_check = Permissions::assert_scope_or_error($client_id, (int)$project_id);
            if (is_wp_error($scope_check)) return $scope_check;
            if (!$this->project_has_assigned_pdvs((int)$project_id, $uid)) {
                return new WP_Error('no_assigned_pdvs', 'Não existem lojas ativas atribuídas a este utilizador nesta campanha.', ['status'=>403]);
            }
            $duplicate_id = $this->existing_route_for_owner_project_date($uid, (int)$project_id, $route_date);
            if ($duplicate_id > 0) {
                return new WP_Error('route_already_exists', 'Já existe uma rota ativa para esta campanha neste dia.', ['status'=>409, 'route_id'=>$duplicate_id, 'date'=>$route_date]);
            }
        }
        if (class_exists('\RoutesPro\SaaS\EntitlementService') && !\RoutesPro\SaaS\EntitlementService::clientHas($client_id, 'routes.create')) {
            return new WP_Error('fieldflow_plan_required', 'A criação de rotas não está incluída no plano deste cliente.', ['status'=>403,'capability'=>'routes.create','client_id'=>$client_id]);
        }
        if (class_exists('\RoutesPro\SaaS\UsageService')) {
            $quota = \RoutesPro\SaaS\UsageService::canConsume($client_id, 'routes_monthly', 1);
            if (empty($quota['allowed'])) return \RoutesPro\SaaS\UsageService::quotaError($client_id, 'routes_monthly');
        }


        // owner: se admin passar explicitamente, respeita; senão, current user
        $owner_id = $uid;
        if (isset($data['owner_user_id']) && current_user_can('routespro_manage')) {
            $owner_id = absint($data['owner_user_id']) ?: $uid;
        }

        $meta = is_array($data['meta'] ?? null) ? (array)$data['meta'] : [];
        $route_status_raw = sanitize_key((string)($data['status'] ?? 'draft'));
        if (!RouteStatus::isValid($route_status_raw)) {
            return new WP_Error('invalid_route_status', 'O estado da rota não é válido.', ['status'=>400]);
        }
        $route_status = RouteStatus::normalize($route_status_raw);
        if (class_exists(RouteEndpointProfileService::class) && $project_id) {
            $resolvedEndpoints = RouteEndpointProfileService::resolveForRoute(
                $client_id,
                (int)$project_id,
                (int)$owner_id,
                $meta,
                is_array($meta['start_point'] ?? null) ? (array)$meta['start_point'] : [],
                is_array($meta['end_point'] ?? null) ? (array)$meta['end_point'] : [],
                [],
                [],
                ['return_to_start'=>!empty($meta['return_to_start'])]
            );
            $meta['start_point'] = (array)($resolvedEndpoints['start_point'] ?? []);
            $meta['end_point'] = (array)($resolvedEndpoints['end_point'] ?? []);
            $meta['return_to_start'] = !empty($resolvedEndpoints['return_to_start']) ? 1 : 0;
            $meta['route_endpoint_profile_contract'] = RouteEndpointProfileService::CONTRACT;
            $meta['route_endpoint_start_source'] = (string)($resolvedEndpoints['start_source'] ?? 'missing');
            $meta['route_endpoint_end_source'] = (string)($resolvedEndpoints['end_source'] ?? 'missing');
        }
        $start_point = is_array($meta['start_point'] ?? null) ? (array)$meta['start_point'] : [];
        $end_point = is_array($meta['end_point'] ?? null) ? (array)$meta['end_point'] : [];
        $endpoint_integrity = \RoutesPro\Services\RouteMetricsService::endpointIntegrity($start_point, $end_point);
        if ($route_status === 'planned' && empty($endpoint_integrity['endpoints_complete'])) {
            return new WP_Error('route_endpoints_required', 'O ponto de partida e o ponto de chegada são obrigatórios para publicar a rota.', ['status'=>400, 'endpoint_status'=>$endpoint_integrity['endpoint_status']]);
        }
        if (!$is_manager) {
            if (empty($endpoint_integrity['endpoints_complete'])) {
                return new WP_Error('route_endpoints_required', 'O ponto de partida e o ponto de chegada são obrigatórios.', ['status'=>400]);
            }
            $route_status = in_array($route_status, ['draft','planned'], true) ? $route_status : 'planned';
            $meta['self_planned'] = 1;
            $meta['planning_source'] = sanitize_key((string)($meta['planning_source'] ?? 'merchandiser_front')) ?: 'merchandiser_front';
            $meta['created_by_user_id'] = $uid;
        }
        $row = [
            'client_id'     => $client_id,
            'date'          => $route_date,
            'status'        => $route_status,
            'owner_user_id' => $owner_id,
            'meta_json'     => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
        ];
        if ($project_id !== null) { $row['project_id'] = $project_id; }

        $ok = $wpdb->insert("{$px}routes", $row);
        if (!$ok) return new WP_Error('db_error', $wpdb->last_error ?: 'DB insert falhou', ['status'=>500]);

        $id = (int)$wpdb->insert_id;
        if (class_exists('\RoutesPro\Services\MobilityCostService')) {
            \RoutesPro\Services\MobilityCostService::ensureRouteSnapshot($id, false, $uid);
        }
        // assignment default para o owner
        \RoutesPro\Support\AssignmentMatrix::sync_route_owner_assignment((int)$id, (int)$owner_id, 'owner');

        $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: $row;
        $context = [
            'source' => 'api',
            'entity_type' => 'route',
            'entity_id' => $id,
            'route_id' => $id,
            'client_id' => $client_id,
            'project_id' => absint($project_id),
            'actor_user_id' => $uid,
        ];
        \RoutesPro\Core\AuditLog::record('route.created', 'route', $id, null, $after, $context);
        \RoutesPro\Core\EventBus::emit('route.created', ['route' => $after], $context, 'api');

        return new WP_REST_Response(['id'=>$id], 201);
    }

    public function update_route(WP_REST_Request $req) {
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $id = absint($req['id']);
        $scope_user_ids_before = class_exists('\RoutesPro\Support\ScopeIndex') ? \RoutesPro\Support\ScopeIndex::routeUserIds($id) : [];
        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: [];
        if (!$before) return new WP_Error('not_found','Rota não existe', ['status'=>404]);
        if (!$this->is_route_editable_by_current($id)) return new WP_Error('forbidden','Sem acesso à rota', ['status'=>403]);

        $data = $req->get_json_params() ?: [];
        $fields = []; $formats = []; $where = ['id'=>$id];
        $is_manager = current_user_can('routespro_manage');
        $before_meta = !empty($before['meta_json']) ? json_decode((string)$before['meta_json'], true) : [];
        if (!is_array($before_meta)) $before_meta = [];
        $is_self_planned = !empty($before_meta['self_planned']);

        if (isset($data['status'])) {
            $status_raw = sanitize_key((string)$data['status']);
            if (!RouteStatus::isValid($status_raw)) {
                return new WP_Error('invalid_route_status', 'O estado da rota não é válido.', ['status'=>400]);
            }
            $fields['status'] = RouteStatus::normalize($status_raw);
            $formats[] = '%s';
        }

        if (isset($data['date'])) {
            $route_date = sanitize_text_field((string)$data['date']);
            if (!$this->is_valid_route_date($route_date)) {
                return new WP_Error('invalid_route_date', 'A data da rota não é válida.', ['status'=>400]);
            }
            if (!$is_manager && !$is_self_planned && $route_date !== (string)($before['date'] ?? '')) {
                return new WP_Error('route_date_locked', 'A data de uma rota atribuída pela gestão não pode ser alterada pelo merchandiser.', ['status'=>403]);
            }
            if (!$is_manager && $route_date !== (string)($before['date'] ?? '')) {
                $duplicate_id = $this->existing_route_for_owner_project_date(
                    absint($before['owner_user_id'] ?? 0),
                    absint($before['project_id'] ?? 0),
                    $route_date,
                    $id
                );
                if ($duplicate_id > 0) {
                    return new WP_Error('route_already_exists', 'Já existe uma rota ativa para esta campanha neste dia.', ['status'=>409, 'route_id'=>$duplicate_id, 'date'=>$route_date]);
                }
            }
            $fields['date'] = $route_date;
            $formats[] = '%s';
        }

        if (isset($data['meta'])) {
            $incoming_meta = is_array($data['meta']) ? (array)$data['meta'] : [];
            if ($is_manager) {
                $next_meta = $incoming_meta;
            } else {
                if (!$is_self_planned) {
                    return new WP_Error('route_meta_locked', 'Os pontos e o planeamento de uma rota atribuída pela gestão não podem ser alterados pelo merchandiser.', ['status'=>403]);
                }
                $existing_meta = $before_meta;
                $next_meta = $existing_meta;
                foreach (['start_point','end_point','planning_period','store_count','return_to_start','manual_order_locked','position_locked_location_ids','route_workspace_version','route_workspace_last_saved_at'] as $key) {
                    if (array_key_exists($key, $incoming_meta)) $next_meta[$key] = $incoming_meta[$key];
                }
                $next_meta['self_planned'] = 1;
                $next_meta['planning_source'] = (string)($existing_meta['planning_source'] ?? 'merchandiser_front');
                $next_meta['created_by_user_id'] = absint($existing_meta['created_by_user_id'] ?? $before['owner_user_id'] ?? get_current_user_id());
            }
            $fields['meta_json'] = wp_json_encode($next_meta, JSON_UNESCAPED_UNICODE);
            $formats[] = '%s';
        }

        $set_project_null = false;
        if ($is_manager) {
            if (isset($data['owner_user_id'])) {
                $fields['owner_user_id'] = absint($data['owner_user_id']);
                $formats[] = '%d';
            }
            if (array_key_exists('client_id', $data)) {
                $fields['client_id'] = absint($data['client_id']);
                $formats[] = '%d';
            }
            if (array_key_exists('project_id', $data)) {
                $pid = absint($data['project_id']) ?: null;
                if ($pid === null) $set_project_null = true;
                else { $fields['project_id'] = $pid; $formats[] = '%d'; }
            }
        }

        if (!$fields && !$set_project_null) return new WP_REST_Response(['ok'=>true], 200);

        $prospective_status = sanitize_key((string)($fields['status'] ?? $before['status'] ?? 'draft'));
        $prospective_meta = isset($next_meta) && is_array($next_meta) ? $next_meta : $before_meta;
        $prospective_client_id = absint($fields['client_id'] ?? $before['client_id'] ?? 0);
        $prospective_project_id = $set_project_null ? 0 : absint($fields['project_id'] ?? $before['project_id'] ?? 0);
        $prospective_owner_id = absint($fields['owner_user_id'] ?? $before['owner_user_id'] ?? 0);
        $should_resolve_endpoints = isset($data['meta']) || isset($data['status']) || isset($data['owner_user_id']) || array_key_exists('project_id', $data) || array_key_exists('client_id', $data);
        if ($should_resolve_endpoints && class_exists(RouteEndpointProfileService::class) && $prospective_project_id > 0) {
            $edges = $this->route_edge_stop_points($id);
            $resolvedEndpoints = RouteEndpointProfileService::resolveForRoute(
                $prospective_client_id,
                $prospective_project_id,
                $prospective_owner_id,
                $prospective_meta,
                [],
                [],
                (array)($edges['first'] ?? []),
                (array)($edges['last'] ?? []),
                ['return_to_start'=>!empty($prospective_meta['return_to_start'])]
            );
            $prospective_meta['start_point'] = (array)($resolvedEndpoints['start_point'] ?? []);
            $prospective_meta['end_point'] = (array)($resolvedEndpoints['end_point'] ?? []);
            $prospective_meta['return_to_start'] = !empty($resolvedEndpoints['return_to_start']) ? 1 : 0;
            $prospective_meta['route_endpoint_profile_contract'] = RouteEndpointProfileService::CONTRACT;
            $prospective_meta['route_endpoint_start_source'] = (string)($resolvedEndpoints['start_source'] ?? 'missing');
            $prospective_meta['route_endpoint_end_source'] = (string)($resolvedEndpoints['end_source'] ?? 'missing');
            $fields['meta_json'] = wp_json_encode($prospective_meta, JSON_UNESCAPED_UNICODE);
            if (!isset($next_meta)) $formats[] = '%s';
            $next_meta = $prospective_meta;
        }
        if ($prospective_status === 'planned') {
            $endpoint_integrity = \RoutesPro\Services\RouteMetricsService::endpointIntegrity(
                is_array($prospective_meta['start_point'] ?? null) ? (array)$prospective_meta['start_point'] : [],
                is_array($prospective_meta['end_point'] ?? null) ? (array)$prospective_meta['end_point'] : []
            );
            if (empty($endpoint_integrity['endpoints_complete'])) {
                return new WP_Error('route_endpoints_required', 'O ponto de partida e o ponto de chegada são obrigatórios para publicar a rota.', ['status'=>400, 'endpoint_status'=>$endpoint_integrity['endpoint_status']]);
            }
        }

        if ($fields) {
            $ok = $wpdb->update("{$px}routes", $fields, $where, $formats, ['%d']);
            if ($ok === false) return new WP_Error('db_error', $wpdb->last_error ?: 'DB update falhou', ['status'=>500]);
        }
        if ($set_project_null) {
            $ok2 = $wpdb->query($wpdb->prepare("UPDATE {$px}routes SET project_id = NULL WHERE id = %d", $id));
            if ($ok2 === false) return new WP_Error('db_error', $wpdb->last_error ?: 'DB update falhou (project_id NULL)', ['status'=>500]);
        }
        if ($is_manager && array_key_exists('owner_user_id', $data)) {
            \RoutesPro\Support\AssignmentMatrix::sync_route_owner_assignment($id, absint($data['owner_user_id']), 'owner');
        }

        $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: [];
        if ($after && class_exists('\RoutesPro\Services\MobilityCostService')) {
            $snapshotResult = \RoutesPro\Services\MobilityCostService::refreshSnapshotForIdentityChange($id, $before, $after, get_current_user_id());
            if (!empty($snapshotResult['ok']) && empty($snapshotResult['skipped'])) {
                $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: $after;
            }
        }
        if ($before !== $after) {
            $scope_user_ids_after = class_exists('\RoutesPro\Support\ScopeIndex') ? \RoutesPro\Support\ScopeIndex::routeUserIds($id) : [];
            foreach (array_unique(array_merge($scope_user_ids_before, $scope_user_ids_after)) as $scope_user_id) {
                Permissions::clear_scope_cache((int)$scope_user_id);
            }
            $context = [
                'source' => 'api', 'entity_type' => 'route', 'entity_id' => $id, 'route_id' => $id,
                'client_id' => absint($after['client_id'] ?? $before['client_id'] ?? 0),
                'project_id' => absint($after['project_id'] ?? $before['project_id'] ?? 0),
            ];
            \RoutesPro\Core\AuditLog::record('route.updated', 'route', $id, $before, $after, $context);
            \RoutesPro\Core\EventBus::emit('route.updated', ['before' => $before, 'route' => $after], $context, 'api');
        }
        return new WP_REST_Response(['ok'=>true], 200);
    }

    public function delete_route(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $id = absint($req['id']);
        if (!current_user_can('routespro_manage')) return new WP_Error('forbidden','Apenas BO pode apagar rotas', ['status'=>403]);
        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: [];
        if (!$before) return new WP_Error('not_found','Rota não existe', ['status'=>404]);
        $affected_user_ids = class_exists('\RoutesPro\Support\ScopeIndex') ? \RoutesPro\Support\ScopeIndex::routeUserIds($id) : [];

        // limpeza em cascata
        $stop_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$px}route_stops WHERE route_id=%d", $id));
        if ($stop_ids) {
            $in = implode(',', array_map('intval', $stop_ids));
            $wpdb->query("DELETE FROM {$px}events WHERE route_stop_id IN ($in)");
        }
        $wpdb->delete($px.'assignments',  ['route_id'=>$id], ['%d']);
        $wpdb->delete($px.'route_stops',  ['route_id'=>$id], ['%d']);
        $ok = $wpdb->delete($px.'routes', ['id'=>$id], ['%d']);
        if ($ok === false) return new WP_Error('db_error', $wpdb->last_error ?: 'DB delete falhou', ['status'=>500]);
        foreach ($affected_user_ids as $affected_user_id) Permissions::clear_scope_cache((int)$affected_user_id);

        $context = [
            'source' => 'api',
            'entity_type' => 'route',
            'entity_id' => $id,
            'route_id' => $id,
            'client_id' => absint($before['client_id'] ?? 0),
            'project_id' => absint($before['project_id'] ?? 0),
        ];
        \RoutesPro\Core\AuditLog::record('route.deleted', 'route', $id, $before, null, $context);
        \RoutesPro\Core\EventBus::emit('route.deleted', ['route' => $before], $context, 'api');

        return new WP_REST_Response(['ok'=>true],200);
    }

    /* =========================================================
     * STOPS
     * ======================================================= */

    public function list_stops(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $id = absint($req['id']);
        if (!$this->is_route_readable_by_current($id)) return new WP_Error('forbidden','Sem acesso à rota', ['status'=>403]);

        $stops = $wpdb->get_results($wpdb->prepare("
            SELECT rs.*, l.name AS location_name, l.address, l.lat, l.lng
            FROM {$px}route_stops rs
            INNER JOIN {$px}locations l ON l.id = rs.location_id
            WHERE rs.route_id = %d
            ORDER BY rs.seq ASC, rs.id ASC
        ", $id), ARRAY_A);

        return new WP_REST_Response(['stops'=>$stops], 200);
    }

    public function clear_stops(WP_REST_Request $req){
        global $wpdb;
        if (!current_user_can('routespro_manage')) return new WP_Error('forbidden','Apenas BO pode limpar paragens', ['status'=>403]);

        $route_id = absint($req['id']);
        if (!$route_id) return new WP_Error('bad_request','route_id em falta', ['status'=>400]);

        $wpdb->query('START TRANSACTION');
        $sync = \RoutesPro\Services\RoutePlanSyncService::sync($route_id, []);
        if (is_wp_error($sync)) {
            $wpdb->query('ROLLBACK');
            return $sync;
        }
        $progress = \RoutesPro\Services\RouteProgressService::sync($route_id, true);
        if (is_wp_error($progress)) {
            $wpdb->query('ROLLBACK');
            return $progress;
        }
        $wpdb->query('COMMIT');

        return new WP_REST_Response(['ok'=>true, 'sync'=>$sync, 'route_progress'=>$progress], 200);
    }

    public function create_stop(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $p = $req->get_json_params() ?: [];

        $route_id = absint($p['route_id'] ?? 0);
        $loc_id   = absint($p['location_id'] ?? 0);
        if (!$route_id || !$loc_id) return new WP_Error('bad_request','route_id/location_id obrigatórios', ['status'=>400]);
        if (!$this->is_route_editable_by_current($route_id)) return new WP_Error('forbidden','Sem acesso à rota', ['status'=>403]);
        $route_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $route_id), ARRAY_A) ?: [];
        $route_safety = \RoutesPro\Services\RouteOptimizationService::routeSafety($route_id, $route_row);
        if (empty($route_safety['can_plan'])) return new WP_Error('route_locked', 'A rota já não permite adicionar lojas.', ['status'=>409, 'reason'=>$route_safety['reason'] ?? 'route_locked']);
        if (!current_user_can('routespro_manage') && !$this->location_is_assigned_to_route_owner($route_id, $loc_id)) {
            return new WP_Error('location_not_assigned', 'Só podes adicionar lojas ativas que te estejam atribuídas nesta campanha.', ['status'=>403]);
        }

        // seq explícito ou next auto
        if (isset($p['seq']) && $p['seq'] !== null && $p['seq'] !== '') {
            $seq = absint($p['seq']);
        } else {
            $seq = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(seq),-1)+1 FROM {$px}route_stops WHERE route_id=%d", $route_id));
        }

        $visit_time_min = isset($p['visit_time_min']) ? max(0, (int) $p['visit_time_min']) : 0;
        $visit_time_mode = sanitize_text_field((string) ($p['visit_time_mode'] ?? ''));
        $row = [
            'route_id'    => $route_id,
            'location_id' => $loc_id,
            'seq'         => $seq,
            'status'      => sanitize_text_field($p['status'] ?? 'pending'),
            'note'        => sanitize_text_field($p['note'] ?? ''),
            'meta_json'   => wp_json_encode(['visit_time_min' => $visit_time_min, 'visit_time_mode' => $visit_time_mode], JSON_UNESCAPED_UNICODE),
        ];
        $wpdb->query('START TRANSACTION');
        $ok = $wpdb->insert($px.'route_stops', $row, ['%d','%d','%d','%s','%s','%s']);
        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $wpdb->last_error ?: 'DB insert falhou', ['status'=>500]);
        }
        $stop_id = (int)$wpdb->insert_id;
        if (class_exists('\\RoutesPro\\Services\\RouteSnapshotService')) {
            \RoutesPro\Services\RouteSnapshotService::capture($route_id, $stop_id, $loc_id);
        }
        $progress = \RoutesPro\Services\RouteProgressService::sync($route_id, true);
        if (is_wp_error($progress)) {
            $wpdb->query('ROLLBACK');
            return $progress;
        }
        $wpdb->query('COMMIT');

        $stop_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1", $stop_id), ARRAY_A) ?: $row;
        $route_context = $wpdb->get_row($wpdb->prepare("SELECT client_id,project_id FROM {$px}routes WHERE id=%d LIMIT 1", $route_id), ARRAY_A) ?: [];
        $context = [
            'source' => 'api',
            'entity_type' => 'visit',
            'entity_id' => $stop_id,
            'route_id' => $route_id,
            'route_stop_id' => $stop_id,
            'location_id' => $loc_id,
            'client_id' => absint($route_context['client_id'] ?? 0),
            'project_id' => absint($route_context['project_id'] ?? 0),
        ];
        \RoutesPro\Core\AuditLog::record('visit.created', 'visit', $stop_id, null, $stop_after, $context);
        \RoutesPro\Core\EventBus::emit('visit.created', ['visit' => $stop_after], $context, 'api');

        return new WP_REST_Response(['id'=>$stop_id, 'route_progress'=>$progress],201);
    }

    public function restore_route_workspace(WP_REST_Request $req){
        $route_id = absint($req['id']);
        if (!$route_id || !$this->is_route_editable_by_current($route_id)) {
            return new WP_Error('forbidden', 'Sem acesso à rota.', ['status'=>403]);
        }
        $data = $req->get_json_params() ?: [];
        $revision_id = sanitize_text_field((string)($data['revision_id'] ?? ''));
        if ($revision_id === '') return new WP_Error('revision_required', 'Seleciona uma versão para restaurar.', ['status'=>400]);
        if (!class_exists('\RoutesPro\Services\RouteWorkspaceService')) {
            return new WP_Error('workspace_unavailable', 'O histórico da rota não está disponível.', ['status'=>503]);
        }
        $result = \RoutesPro\Services\RouteWorkspaceService::restore($route_id, $revision_id, get_current_user_id());
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function save_route_workspace(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $route_id = absint($req['id']);
        if (!$route_id || !$this->is_route_editable_by_current($route_id)) {
            return new WP_Error('forbidden', 'Sem acesso à rota.', ['status'=>403]);
        }
        $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $route_id), ARRAY_A) ?: [];
        if (!$route) return new WP_Error('not_found', 'A rota já não existe.', ['status'=>404]);
        $safety = \RoutesPro\Services\RouteOptimizationService::routeSafety($route_id, $route);
        if (empty($safety['can_plan'])) {
            return new WP_Error('route_locked', 'A rota já não permite alterações de planeamento.', ['status'=>409, 'reason'=>$safety['reason'] ?? 'route_locked']);
        }

        $data = $req->get_json_params() ?: [];
        $ordered_stop_ids = array_values(array_unique(array_filter(array_map('absint', (array)($data['stop_ids'] ?? [])))));
        $current_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,location_id,seq,status,arrived_at,departed_at FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC,id ASC",
            $route_id
        ), ARRAY_A) ?: [];
        $current_stop_ids = array_values(array_map(static fn($row) => absint($row['id'] ?? 0), $current_rows));
        if (!$ordered_stop_ids || count($ordered_stop_ids) !== count($current_stop_ids) || array_diff($ordered_stop_ids, $current_stop_ids) || array_diff($current_stop_ids, $ordered_stop_ids)) {
            return new WP_Error('workspace_stop_mismatch', 'A lista de lojas mudou. Atualiza a rota antes de voltar a guardar.', ['status'=>409]);
        }
        if (!empty($safety['live_adjustable'])) {
            $protected_ids = array_values(array_map('absint', (array)($safety['protected_stop_ids'] ?? [])));
            $requested_protected = array_values(array_filter($ordered_stop_ids, static fn($id) => in_array($id, $protected_ids, true)));
            if ($requested_protected !== $protected_ids) {
                return new WP_Error('workspace_operational_order_protected', 'As lojas já executadas ou em curso mantêm a ordem original.', ['status'=>409, 'protected_stop_ids'=>$protected_ids]);
            }
        }
        $status = sanitize_key((string)($data['status'] ?? $route['status'] ?? 'draft'));
        if (!empty($safety['live_adjustable'])) {
            $status = sanitize_key((string)($route['status'] ?? 'in_progress'));
            if ($status === 'completed') $status = 'in_progress';
        } elseif (!in_array($status, ['draft','planned'], true)) {
            return new WP_Error('workspace_status_invalid', 'O workspace só pode guardar rascunhos ou rotas planeadas.', ['status'=>400]);
        }
        $manual_locked = !empty($data['manual_order_locked']);
        $locked_location_ids = array_values(array_unique(array_filter(array_map('absint', (array)($data['locked_location_ids'] ?? [])))));
        $route_location_ids = array_values(array_map(static fn($row) => absint($row['location_id'] ?? 0), $current_rows));
        $locked_location_ids = array_values(array_intersect($locked_location_ids, $route_location_ids));

        $snapshot = class_exists('\RoutesPro\Services\RouteWorkspaceService')
            ? \RoutesPro\Services\RouteWorkspaceService::captureCurrent($route_id)
            : null;
        if (is_wp_error($snapshot)) return $snapshot;

        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('transaction_failed', 'Não foi possível iniciar a gravação segura.', ['status'=>500]);
        }
        foreach ($ordered_stop_ids as $seq => $stop_id) {
            $updated = $wpdb->update($px.'route_stops', ['seq'=>$seq], ['id'=>$stop_id, 'route_id'=>$route_id], ['%d'], ['%d','%d']);
            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('workspace_order_update', 'Não foi possível guardar a ordem completa da rota.', ['status'=>500]);
            }
        }
        $meta = !empty($route['meta_json']) ? json_decode((string)$route['meta_json'], true) : [];
        if (!is_array($meta)) $meta = [];
        if (is_array($snapshot) && class_exists('\RoutesPro\Services\RouteWorkspaceService')) {
            $revision_action = !empty($safety['live_adjustable']) ? 'live_adjustment' : ($status === 'draft' ? 'save_draft' : 'publish');
            $revision_label = !empty($safety['live_adjustable']) ? 'Antes do ajuste em rota ativa' : ($status === 'draft' ? 'Antes de guardar rascunho' : 'Antes de publicar');
            $meta = \RoutesPro\Services\RouteWorkspaceService::appendRevision(
                $meta,
                $snapshot,
                $revision_action,
                get_current_user_id(),
                $revision_label
            );
        }
        $meta['manual_order_locked'] = $manual_locked ? 1 : 0;
        $meta['position_locked_location_ids'] = $locked_location_ids;
        $meta['route_workspace_version'] = class_exists('\RoutesPro\Services\RouteWorkspaceService') ? \RoutesPro\Services\RouteWorkspaceService::VERSION : 'fieldflow_route_workspace_v1';
        $meta['route_workspace_last_saved_at'] = current_time('mysql');
        $meta['route_workspace_last_saved_by'] = get_current_user_id();
        if (!empty($safety['live_adjustable'])) {
            $meta['last_live_route_adjustment_at'] = current_time('mysql');
            $meta['last_live_route_adjustment_by'] = get_current_user_id();
        }
        $meta['route_metrics_stale'] = 1;
        $meta['routing_refresh_required'] = 1;
        unset($meta['route_metrics']);
        $route_updated = $wpdb->update(
            $px.'routes',
            ['status'=>$status, 'meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ['id'=>$route_id],
            ['%s','%s'],
            ['%d']
        );
        if ($route_updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('workspace_route_update', 'A ordem foi preparada, mas não foi possível guardar o workspace.', ['status'=>500]);
        }
        $progress = \RoutesPro\Services\RouteProgressService::sync($route_id, true);
        if (is_wp_error($progress)) {
            $wpdb->query('ROLLBACK');
            return $progress;
        }
        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('commit_failed', 'Não foi possível confirmar a gravação do workspace.', ['status'=>500]);
        }

        \RoutesPro\Services\RouteOptimizationService::invalidateUserRouteCache(absint($route['owner_user_id'] ?? 0));
        $context = [
            'source'=>'api', 'entity_type'=>'route', 'entity_id'=>$route_id, 'route_id'=>$route_id,
            'client_id'=>absint($route['client_id'] ?? 0), 'project_id'=>absint($route['project_id'] ?? 0),
            'actor_user_id'=>get_current_user_id(),
        ];
        \RoutesPro\Core\AuditLog::record('route.workspace_saved', 'route', $route_id, $route, [
            'status'=>$status, 'stop_ids'=>$ordered_stop_ids, 'manual_order_locked'=>$manual_locked ? 1 : 0,
            'locked_location_ids'=>$locked_location_ids,
        ], $context);
        \RoutesPro\Core\EventBus::emit('route.workspace_saved', [
            'route_id'=>$route_id, 'status'=>$status, 'stop_ids'=>$ordered_stop_ids,
            'manual_order_locked'=>$manual_locked ? 1 : 0, 'locked_location_ids'=>$locked_location_ids,
        ], $context, 'api');

        return new WP_REST_Response([
            'ok'=>true,
            'route_id'=>$route_id,
            'status'=>$status,
            'stop_ids'=>$ordered_stop_ids,
            'manual_order_locked'=>$manual_locked ? 1 : 0,
            'locked_location_ids'=>$locked_location_ids,
            'workspace_mode'=>!empty($safety['live_adjustable']) ? 'live' : 'full',
            'route_progress'=>$progress,
        ], 200);
    }

    public function bulk_remove_stops(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $route_id = absint($req['id']);
        if (!$route_id || !$this->is_route_editable_by_current($route_id)) {
            return new WP_Error('forbidden', 'Sem acesso à rota.', ['status'=>403]);
        }
        $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $route_id), ARRAY_A) ?: [];
        if (!$route) return new WP_Error('not_found', 'A rota já não existe.', ['status'=>404]);
        $safety = \RoutesPro\Services\RouteOptimizationService::routeSafety($route_id, $route);
        if (empty($safety['can_plan'])) {
            return new WP_Error('route_locked', 'A rota já não permite remover lojas.', ['status'=>409, 'reason'=>$safety['reason'] ?? 'route_locked']);
        }

        $data = $req->get_json_params() ?: [];
        $stop_ids = array_values(array_unique(array_filter(array_map('absint', (array)($data['stop_ids'] ?? [])))));
        if (!$stop_ids) return new WP_Error('stops_required', 'Seleciona pelo menos uma loja para remover.', ['status'=>400]);
        if (count($stop_ids) > 100) return new WP_Error('too_many_stops', 'Não é possível remover mais de 100 lojas de uma só vez.', ['status'=>400]);

        $placeholders = implode(',', array_fill(0, count($stop_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$px}route_stops WHERE route_id=%d AND id IN ({$placeholders}) ORDER BY seq ASC,id ASC",
            $route_id, ...$stop_ids
        ), ARRAY_A) ?: [];
        if (count($rows) !== count($stop_ids)) {
            return new WP_Error('stops_mismatch', 'Algumas lojas selecionadas já não pertencem a esta rota.', ['status'=>409]);
        }
        $protected = [];
        foreach ($rows as $row) {
            if (!\RoutesPro\Services\RoutePlanSyncService::stop_is_mutable($row)) $protected[] = absint($row['id'] ?? 0);
        }
        if ($protected) {
            return new WP_Error('operational_stops_protected', 'As lojas já executadas ou em curso não podem ser removidas.', ['status'=>409, 'protected_stop_ids'=>$protected]);
        }

        $snapshot = class_exists('\RoutesPro\Services\RouteWorkspaceService')
            ? \RoutesPro\Services\RouteWorkspaceService::captureCurrent($route_id)
            : null;
        if (is_wp_error($snapshot)) return $snapshot;
        $removed_location_ids = array_values(array_unique(array_filter(array_map(static fn($row) => absint($row['location_id'] ?? 0), $rows))));

        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('transaction_failed', 'Não foi possível iniciar a remoção segura.', ['status'=>500]);
        }
        $removed = [];
        foreach ($rows as $row) {
            $result = \RoutesPro\Services\RoutePlanSyncService::remove_stop(absint($row['id'] ?? 0));
            if (is_wp_error($result)) {
                $wpdb->query('ROLLBACK');
                return $result;
            }
            $removed[] = $result;
        }
        $progress = \RoutesPro\Services\RouteProgressService::sync($route_id, true);
        if (is_wp_error($progress)) {
            $wpdb->query('ROLLBACK');
            return $progress;
        }

        $latest = $wpdb->get_row($wpdb->prepare("SELECT meta_json FROM {$px}routes WHERE id=%d LIMIT 1", $route_id), ARRAY_A) ?: [];
        $meta = !empty($latest['meta_json']) ? json_decode((string)$latest['meta_json'], true) : [];
        if (!is_array($meta)) $meta = [];
        if (is_array($snapshot) && class_exists('\RoutesPro\Services\RouteWorkspaceService')) {
            $meta = \RoutesPro\Services\RouteWorkspaceService::appendRevision(
                $meta,
                $snapshot,
                'bulk_remove',
                get_current_user_id(),
                sprintf('%d loja(s) removida(s)', count($rows))
            );
        }
        $locked = array_values(array_unique(array_filter(array_map('absint', (array)($meta['position_locked_location_ids'] ?? [])))));
        $meta['position_locked_location_ids'] = array_values(array_diff($locked, $removed_location_ids));
        $meta['store_count'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$px}route_stops WHERE route_id=%d", $route_id));
        $meta['last_self_planned_edit_at'] = current_time('mysql');
        $meta['last_self_planned_edit_by'] = get_current_user_id();
        $meta['route_metrics_stale'] = 1;
        $meta['routing_refresh_required'] = 1;
        unset($meta['route_metrics']);
        $meta_updated = $wpdb->update(
            $px.'routes',
            ['meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ['id'=>$route_id],
            ['%s'],
            ['%d']
        );
        if ($meta_updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('route_meta_update', 'As lojas foram removidas, mas não foi possível atualizar o workspace.', ['status'=>500]);
        }
        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('commit_failed', 'Não foi possível confirmar a remoção das lojas.', ['status'=>500]);
        }

        $context = [
            'source'=>'api', 'entity_type'=>'route', 'entity_id'=>$route_id, 'route_id'=>$route_id,
            'client_id'=>absint($route['client_id'] ?? 0), 'project_id'=>absint($route['project_id'] ?? 0),
            'actor_user_id'=>get_current_user_id(),
        ];
        \RoutesPro\Core\AuditLog::record('route.stops_bulk_removed', 'route', $route_id, $rows, null, $context, [
            'stop_ids'=>$stop_ids, 'location_ids'=>$removed_location_ids, 'count'=>count($rows),
        ]);
        \RoutesPro\Core\EventBus::emit('route.stops_bulk_removed', [
            'route_id'=>$route_id, 'stop_ids'=>$stop_ids, 'location_ids'=>$removed_location_ids, 'count'=>count($rows),
        ], $context, 'api');

        return new WP_REST_Response([
            'ok'=>true,
            'removed_count'=>count($rows),
            'removed_stop_ids'=>$stop_ids,
            'removed_location_ids'=>$removed_location_ids,
            'route_progress'=>$progress,
            'store_count'=>$meta['store_count'],
        ], 200);
    }

    public function delete_stop(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $id = absint($req['id']);
        $stop_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: [];
        $route_id = absint($stop_before['route_id'] ?? 0);
        if (!$route_id) return new WP_Error('not_found','Stop não existe',['status'=>404]);
        if (!$this->is_route_editable_by_current($route_id)) return new WP_Error('forbidden','Sem acesso à rota', ['status'=>403]);

        $wpdb->query('START TRANSACTION');
        $removed = \RoutesPro\Services\RoutePlanSyncService::remove_stop($id);
        if (is_wp_error($removed)) {
            $wpdb->query('ROLLBACK');
            return $removed;
        }
        $progress = \RoutesPro\Services\RouteProgressService::sync($route_id, true);
        if (is_wp_error($progress)) {
            $wpdb->query('ROLLBACK');
            return $progress;
        }
        $route_row = $wpdb->get_row($wpdb->prepare("SELECT meta_json FROM {$px}routes WHERE id=%d LIMIT 1", $route_id), ARRAY_A) ?: [];
        $route_meta = !empty($route_row['meta_json']) ? json_decode((string)$route_row['meta_json'], true) : [];
        if (!is_array($route_meta)) $route_meta = [];
        $route_meta['store_count'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$px}route_stops WHERE route_id=%d", $route_id));
        $route_meta['last_self_planned_edit_at'] = current_time('mysql');
        $route_meta['last_self_planned_edit_by'] = get_current_user_id();
        $route_meta['route_metrics_stale'] = 1;
        $route_meta['routing_refresh_required'] = 1;
        unset($route_meta['route_metrics']);
        $meta_updated = $wpdb->update(
            $px . 'routes',
            ['meta_json' => wp_json_encode($route_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ['id' => $route_id],
            ['%s'],
            ['%d']
        );
        if ($meta_updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('route_stop_meta_update', 'A loja foi removida, mas não foi possível atualizar o resumo da rota.', ['status'=>500]);
        }
        $wpdb->query('COMMIT');
        $route_context = $wpdb->get_row($wpdb->prepare("SELECT client_id,project_id FROM {$px}routes WHERE id=%d LIMIT 1", $route_id), ARRAY_A) ?: [];
        $context = [
            'source' => 'api',
            'entity_type' => 'visit',
            'entity_id' => $id,
            'route_id' => $route_id,
            'route_stop_id' => $id,
            'location_id' => absint($stop_before['location_id'] ?? 0),
            'client_id' => absint($route_context['client_id'] ?? 0),
            'project_id' => absint($route_context['project_id'] ?? 0),
        ];
        \RoutesPro\Core\AuditLog::record('visit.deleted', 'visit', $id, $stop_before, null, $context);
        \RoutesPro\Core\EventBus::emit('visit.deleted', ['visit' => $stop_before], $context, 'api');

        return new WP_REST_Response(['ok'=>true, 'removed'=>$removed, 'route_progress'=>$progress],200);
    }

    public function reset_stop_pending(WP_REST_Request $req){
        $id = absint($req['id']);
        $body = $req->get_json_params() ?: [];
        $reason = sanitize_textarea_field((string)($body['reason'] ?? ''));
        $result = \RoutesPro\Services\VisitResetService::resetToPending($id, get_current_user_id(), $reason);
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function reopen_stop_pending(WP_REST_Request $req){
        $id = absint($req['id']);
        $body = $req->get_json_params() ?: [];
        $reason = sanitize_textarea_field((string)($body['reason'] ?? ''));
        $result = \RoutesPro\Services\VisitResetService::reopenToPending($id, get_current_user_id(), $reason);
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function complete_stop_manual(WP_REST_Request $req){
        $id = absint($req['id']);
        $body = $req->get_json_params() ?: [];
        $reason = sanitize_textarea_field((string)($body['reason'] ?? ''));
        $completed_at = sanitize_text_field((string)($body['completed_at'] ?? ''));
        $allow_without_draft = !empty($body['allow_without_draft']);
        $result = \RoutesPro\Services\VisitManualCompletionService::complete(
            $id,
            $completed_at,
            get_current_user_id(),
            $reason,
            $allow_without_draft
        );
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    public function update_stop_seq_or_status(WP_REST_Request $req){
        global $wpdb; $px = $wpdb->prefix.'routespro_';
        $id = absint($req['id']);
        $p  = $req->get_json_params() ?: [];

        $stop_before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: [];
        $route_id = absint($stop_before['route_id'] ?? 0);
        if (!$route_id) return new WP_Error('not_found','Stop não existe',['status'=>404]);
        if (!$this->is_route_editable_by_current($route_id)) return new WP_Error('forbidden','Sem acesso à rota', ['status'=>403]);

        $requested_status = isset($p['status']) ? sanitize_key((string)$p['status']) : '';
        if ($requested_status === 'failed') {
            $effective_reason = isset($p['fail_reason']) ? sanitize_text_field((string)$p['fail_reason']) : sanitize_text_field((string)($stop_before['fail_reason'] ?? ''));
            if (trim($effective_reason) === '') return new WP_Error('failure_reason_required', 'Indica a justificação para concluir a loja como não visitada.', ['status'=>422]);
            $p['fail_reason'] = $effective_reason;
        }

        // normalização de datas
        $arrived_at  = isset($p['arrived_at'])  ? $this->iso_to_mysql($p['arrived_at'])   : null;
        $departed_at = isset($p['departed_at']) ? $this->iso_to_mysql($p['departed_at'])  : null;

        $fields = []; $formats = [];

        // existentes
        if (isset($p['seq']))           { $fields['seq']           = absint($p['seq']);                       $formats[]='%d'; }
        if (isset($p['status']))        { $fields['status']        = sanitize_text_field($p['status']);       $formats[]='%s'; }
        if (isset($p['note']))          { $fields['note']          = sanitize_text_field($p['note']);         $formats[]='%s'; }

        // novos campos de reporte
        if (isset($p['fail_reason']))   { $fields['fail_reason']   = sanitize_text_field($p['fail_reason']);  $formats[]='%s'; }
        if (isset($p['photo_url']))     { $fields['photo_url']     = esc_url_raw($p['photo_url']);            $formats[]='%s'; }
        if (isset($p['signature_data'])){ $fields['signature_data']= $p['signature_data'];                    $formats[]='%s'; } // dataURL base64

        if ($arrived_at !== null)       { $fields['arrived_at']    = $arrived_at;                             $formats[]='%s'; }
        if ($departed_at !== null)      { $fields['departed_at']   = $departed_at;                            $formats[]='%s'; }
        if (isset($p['duration_s']))    { $fields['duration_s']    = absint($p['duration_s']);                $formats[]='%d'; }

        if (isset($p['qty']))           { $fields['qty']           = floatval($p['qty']);                     $formats[]='%f'; }
        if (isset($p['weight']))        { $fields['weight']        = floatval($p['weight']);                  $formats[]='%f'; }
        if (isset($p['volume']))        { $fields['volume']        = floatval($p['volume']);                  $formats[]='%f'; }

        if (isset($p['real_lat']))      { $fields['real_lat']      = floatval($p['real_lat']);                $formats[]='%f'; }
        if (isset($p['real_lng']))      { $fields['real_lng']      = floatval($p['real_lng']);                $formats[]='%f'; }

        if (!$fields) return new WP_REST_Response(['ok'=>true],200);

        $wpdb->query('START TRANSACTION');
        $ok = $wpdb->update($px.'route_stops', $fields, ['id'=>$id], $formats, ['%d']);
        if ($ok === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', $wpdb->last_error ?: 'DB update falhou', ['status'=>500]);
        }

        $route_progress = null;
        if (array_key_exists('status', $fields)) {
            $route_progress = \RoutesPro\Services\RouteProgressService::sync($route_id);
            if (is_wp_error($route_progress)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('route_progress_update', $route_progress->get_error_message(), ['status'=>500]);
            }
        }
        $wpdb->query('COMMIT');
        if (is_array($route_progress)
            && sanitize_key((string)($route_progress['route_status'] ?? '')) === 'completed'
            && !empty($route_progress['operationally_complete'])
            && class_exists('\RoutesPro\Admin\ReportAutomations')) {
            \RoutesPro\Admin\ReportAutomations::queueDailyCloseForRoute($route_id);
        }

        $stop_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: [];
        if ($stop_before !== $stop_after) {
            $route_context = $wpdb->get_row($wpdb->prepare("SELECT client_id,project_id FROM {$px}routes WHERE id=%d LIMIT 1", $route_id), ARRAY_A) ?: [];
            $context = [
                'source' => 'api',
                'entity_type' => 'visit',
                'entity_id' => $id,
                'route_id' => $route_id,
                'route_stop_id' => $id,
                'location_id' => absint($stop_after['location_id'] ?? $stop_before['location_id'] ?? 0),
                'client_id' => absint($route_context['client_id'] ?? 0),
                'project_id' => absint($route_context['project_id'] ?? 0),
            ];
            \RoutesPro\Core\AuditLog::record('visit.updated', 'visit', $id, $stop_before, $stop_after, $context);
            \RoutesPro\Core\EventBus::emit('visit.updated', ['before' => $stop_before, 'visit' => $stop_after], $context, 'api');
            $before_status = sanitize_key((string)($stop_before['status'] ?? 'pending'));
            $after_status = sanitize_key((string)($stop_after['status'] ?? 'pending'));
            if ($before_status !== $after_status) {
                if (in_array($after_status, ['in_progress','started','arrived'], true)) {
                    \RoutesPro\Core\EventBus::emit('visit.started', ['before' => $stop_before, 'visit' => $stop_after], $context, 'api');
                } elseif (in_array($after_status, ['done','completed'], true)) {
                    \RoutesPro\Core\EventBus::emit('visit.completed', ['before' => $stop_before, 'visit' => $stop_after], $context, 'api');
                } elseif (in_array($after_status, ['failed','cancelled'], true)) {
                    \RoutesPro\Core\EventBus::emit('visit.failed', ['before' => $stop_before, 'visit' => $stop_after], $context, 'api');
                }
            }
        }

        return new WP_REST_Response(['ok'=>true, 'updated'=>$ok, 'route_progress'=>$route_progress],200);
    }

    private function route_display_distance_km(int $route_id, array $meta): float {
        foreach (['route_metrics', 'portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (!is_array($bucket)) continue;
            if (isset($bucket['distance_km']) && is_numeric($bucket['distance_km'])) return (float)$bucket['distance_km'];
            if (isset($bucket['known_distance_km']) && is_numeric($bucket['known_distance_km'])) return (float)$bucket['known_distance_km'];
            if (isset($bucket['total_distance_km']) && is_numeric($bucket['total_distance_km'])) return (float)$bucket['total_distance_km'];
            if (isset($bucket['suggestion_distance_km']) && is_numeric($bucket['suggestion_distance_km'])) return (float)$bucket['suggestion_distance_km'];
        }
        if (isset($meta['distance_km']) && is_numeric($meta['distance_km'])) return (float)$meta['distance_km'];
        if (isset($meta['total_distance_km']) && is_numeric($meta['total_distance_km'])) return (float)$meta['total_distance_km'];
        return $this->estimate_route_distance_km($route_id, $meta);
    }

    private function route_display_toll_cost_eur(int $route_id, array $meta): float {
        foreach (['route_metrics', 'portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (!is_array($bucket)) continue;
            foreach (['toll_cost_eur', 'toll_estimated_eur', 'suggestion_toll_cost_eur'] as $key) {
                if (isset($bucket[$key]) && is_numeric($bucket[$key])) return (float)$bucket[$key];
            }
        }
        if (isset($meta['toll_cost_eur']) && is_numeric($meta['toll_cost_eur'])) return (float)$meta['toll_cost_eur'];
        return 0.0;
    }

    private function is_automatic_suggestion_route(array $meta): bool {
        if (!empty($meta['generated_week_plan'])) return true;
        foreach (['portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (!is_array($bucket)) continue;
            foreach (['source', 'created_from', 'origin'] as $key) {
                if (!empty($bucket[$key]) && strpos((string)$bucket[$key], 'automatic_route_suggestion') !== false) return true;
            }
        }
        return false;
    }

    private function estimate_suggestion_route_distance_km(int $route_id, array $route_meta = []): float {
        if ($route_id <= 0) return 0.0;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $stops = $wpdb->get_results($wpdb->prepare("SELECT l.lat, l.lng FROM {$px}route_stops rs LEFT JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC", $route_id), ARRAY_A) ?: [];
        $points = [];
        $start = is_array($route_meta['start_point'] ?? null) ? (array)$route_meta['start_point'] : [];
        if (is_numeric($start['lat'] ?? null) && is_numeric($start['lng'] ?? null)) $points[] = [(float)$start['lat'], (float)$start['lng']];
        foreach ($stops as $stop) {
            if (is_numeric($stop['lat'] ?? null) && is_numeric($stop['lng'] ?? null)) $points[] = [(float)$stop['lat'], (float)$stop['lng']];
        }
        $end = is_array($route_meta['end_point'] ?? null) ? (array)$route_meta['end_point'] : [];
        if (is_numeric($end['lat'] ?? null) && is_numeric($end['lng'] ?? null)) $points[] = [(float)$end['lat'], (float)$end['lng']];
        $km = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $km += $this->haversine_km($points[$i-1][0], $points[$i-1][1], $points[$i][0], $points[$i][1]);
        }
        return round($km, 2);
    }

    private function has_route_metrics(array $meta): bool {
        return is_array($meta['route_metrics'] ?? null) && (isset($meta['route_metrics']['distance_km']) || isset($meta['route_metrics']['toll_cost_eur']));
    }

    private function route_distance_km(array $meta): float {
        foreach ([['route_metrics','distance_km'], ['metrics','distance_km'], ['plan_summary','distance_km']] as $path) {
            $bucket = $meta[$path[0]] ?? null;
            if (is_array($bucket) && isset($bucket[$path[1]]) && is_numeric($bucket[$path[1]])) {
                return (float) $bucket[$path[1]];
            }
        }
        if (isset($meta['distance_km']) && is_numeric($meta['distance_km'])) return (float) $meta['distance_km'];
        if (isset($meta['total_distance_km']) && is_numeric($meta['total_distance_km'])) return (float) $meta['total_distance_km'];
        return 0.0;
    }


    private function route_toll_cost_eur(array $meta): float {
        foreach ([['route_metrics','toll_cost_eur'], ['route_metrics','toll_estimated_eur'], ['plan_summary','toll_cost_eur'], ['plan_summary','toll_estimated_eur'], ['metrics','toll_cost_eur']] as $path) {
            $bucket = $meta[$path[0]] ?? null;
            if (is_array($bucket) && isset($bucket[$path[1]]) && is_numeric($bucket[$path[1]])) return (float) $bucket[$path[1]];
        }
        if (isset($meta['toll_cost_eur']) && is_numeric($meta['toll_cost_eur'])) return (float) $meta['toll_cost_eur'];
        return 0.0;
    }

    private function route_toll_model(array $meta): string {
        foreach (['portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'route_metrics', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (is_array($bucket) && !empty($bucket['toll_model'])) return (string) $bucket['toll_model'];
        }
        return '';
    }

    private function route_toll_provider(array $meta): string {
        foreach (['portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'route_metrics', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (is_array($bucket) && !empty($bucket['toll_provider'])) return (string) $bucket['toll_provider'];
            if (is_array($bucket) && !empty($bucket['routing_provider'])) return (string) $bucket['routing_provider'];
        }
        return '';
    }

    private function route_toll_is_real_api(array $meta): int {
        foreach (['portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'route_metrics', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (is_array($bucket) && array_key_exists('toll_is_real_api', $bucket)) return !empty($bucket['toll_is_real_api']) ? 1 : 0;
        }
        return 0;
    }

    private function estimate_route_distance_km(int $route_id, array $route_meta = []): float {
        if ($route_id <= 0) return 0.0;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $stops = $wpdb->get_results($wpdb->prepare("SELECT rs.meta_json, l.lat, l.lng FROM {$px}route_stops rs LEFT JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC", $route_id), ARRAY_A) ?: [];
        $km = 0.0;
        $hasStoredLegs = false;
        foreach ($stops as $stop) {
            $meta = !empty($stop['meta_json']) ? json_decode((string) $stop['meta_json'], true) : [];
            if (!is_array($meta)) $meta = [];
            foreach (['distance_from_prev_km', 'leg_distance_km'] as $key) {
                if (isset($meta[$key]) && is_numeric($meta[$key]) && (float)$meta[$key] > 0) {
                    $km += (float)$meta[$key];
                    $hasStoredLegs = true;
                    break;
                }
            }
        }
        if (!$hasStoredLegs) {
            $points = [];
            $start = is_array($route_meta['start_point'] ?? null) ? (array)$route_meta['start_point'] : [];
            if (is_numeric($start['lat'] ?? null) && is_numeric($start['lng'] ?? null)) $points[] = [(float)$start['lat'], (float)$start['lng']];
            foreach ($stops as $stop) {
                if (is_numeric($stop['lat'] ?? null) && is_numeric($stop['lng'] ?? null)) $points[] = [(float)$stop['lat'], (float)$stop['lng']];
            }
            $end = is_array($route_meta['end_point'] ?? null) ? (array)$route_meta['end_point'] : [];
            if (is_numeric($end['lat'] ?? null) && is_numeric($end['lng'] ?? null)) $points[] = [(float)$end['lat'], (float)$end['lng']];
            for ($i = 1; $i < count($points); $i++) {
                $km += $this->haversine_km($points[$i-1][0], $points[$i-1][1], $points[$i][0], $points[$i][1]) * 1.25;
            }
        }
        $metrics = is_array($route_meta['route_metrics'] ?? null) ? (array) $route_meta['route_metrics'] : (is_array($route_meta['plan_summary'] ?? null) ? (array) $route_meta['plan_summary'] : []);
        $returnKm = $metrics['return_distance_km'] ?? ($metrics['end_leg_distance_km'] ?? null);
        if ($hasStoredLegs && is_numeric($returnKm)) $km += (float)$returnKm;
        return round($km, 2);
    }

    private function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earth * asin(min(1, sqrt($a)));
    }

}
