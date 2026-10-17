<?php
namespace RoutesPro\Support;

use WP_REST_Request;
use WP_Error;

if (!defined('ABSPATH')) exit;

class Permissions {
    private static array $scope_cache = [];

    public static function clear_scope_cache(?int $user_id = null): void {
        if ($user_id === null) {
            self::$scope_cache = [];
            if (class_exists(ScopeIndex::class)) ScopeIndex::invalidateAll();
            return;
        }
        $user_id = max(0, $user_id);
        unset(self::$scope_cache[$user_id]);
        if ($user_id > 0 && class_exists(ScopeIndex::class)) ScopeIndex::invalidateUser($user_id);
    }

    public static function get_assignable_users(int $client_id = 0, int $project_id = 0, array $fields = ['ID','display_name','user_email','user_login']): array {
        return AssignmentMatrix::get_assignable_users($client_id, $project_id, $fields);
    }

    public static function can_access_route(int $route_id, ?int $user_id = null): bool {
        global $wpdb;
        $uid = $user_id ?: get_current_user_id();
        if (!$uid || $route_id <= 0) return false;
        if (self::is_manager($uid)) return true;
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, client_id, project_id, owner_user_id FROM {$px}routes WHERE id=%d", $route_id), ARRAY_A);
        if (!$row) return false;
        if ((int)($row['owner_user_id'] ?? 0) === $uid) return true;
        $assigned = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$px}assignments WHERE route_id=%d AND user_id=%d AND is_active=1", $route_id, $uid));
        if ($assigned > 0) return true;
        return !is_wp_error(self::assert_scope_or_error((int)($row['client_id'] ?? 0), (int)($row['project_id'] ?? 0), $uid));
    }

    /**
     * Escrita estrita numa rota.
     *
     * O scope de cliente/projeto concede leitura contextual, mas nunca deve
     * conceder mutação operacional de uma rota pertencente a outro utilizador.
     */
    public static function can_edit_route(int $route_id, ?int $user_id = null): bool {
        global $wpdb;
        $uid = $user_id ?: get_current_user_id();
        if (!$uid || $route_id <= 0) return false;
        if (self::is_manager($uid)) return true;
        $px = $wpdb->prefix . 'routespro_';
        $owner_user_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT owner_user_id FROM {$px}routes WHERE id=%d",
            $route_id
        ));
        if ($owner_user_id === $uid && $owner_user_id > 0) return true;
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$px}assignments WHERE route_id=%d AND user_id=%d AND is_active=1 LIMIT 1",
            $route_id,
            $uid
        ));
    }

    public static function is_manager(?int $user_id = null): bool {
        if ($user_id && $user_id !== get_current_user_id()) {
            $user = get_userdata($user_id);
            return $user ? user_can($user, 'routespro_manage') : false;
        }
        return current_user_can('routespro_manage');
    }

    public static function can_access_front(?int $user_id = null): bool {
        $uid = $user_id ?: get_current_user_id();
        if (!$uid) return false;
        if (self::is_manager($uid)) return true;
        if (user_can($uid, 'routespro_execute')) return true;
        $scope = self::get_scope($uid);
        return !empty($scope['client_ids']) || !empty($scope['project_ids']);
    }

    private static function parse_meta($json): array {
        if (is_array($json)) return $json;
        if (!is_string($json) || trim($json) === '') return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private static function extract_user_ids(array $meta): array {
        $candidates = [
            $meta['associated_user_ids'] ?? null,
            $meta['user_ids'] ?? null,
            $meta['users'] ?? null,
            $meta['assigned_users'] ?? null,
        ];
        $out = [];
        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $candidate = preg_split('/\s*,\s*/', trim($candidate));
            }
            if (!is_array($candidate)) continue;
            foreach ($candidate as $value) {
                if (is_array($value)) $value = $value['user_id'] ?? $value['id'] ?? 0;
                $id = absint($value);
                if ($id) $out[$id] = $id;
            }
        }
        ksort($out);
        return array_values($out);
    }


    /**
     * Uma loja atribuída no Centro de Atribuições é uma ligação operacional
     * direta à campanha para efeitos de Planear. O histórico de execução,
     * periodicidade e estado da rota nunca retiram a loja do catálogo do owner.
     * A remoção do catálogo faz-se retirando a própria atribuição assigned_to.
     */
    private static function assignedStoreScopeForUser(int $user_id): array {
        // 9BB predecessor marker: cl.assigned_to=%d AND cl.is_active=1 AND cl.status='active'
        // 9BC planner catalog authority: assigned_to is canonical; route history and execution state never filter the Planear catalogue.
        global $wpdb;
        $uid = absint($user_id);
        $scope = ['client_ids' => [], 'project_ids' => []];
        if (!$uid) return $scope;
        $px = $wpdb->prefix . 'routespro_';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT cl.project_id, p.client_id
             FROM {$px}campaign_locations cl
             INNER JOIN {$px}projects p ON p.id = cl.project_id
             WHERE cl.assigned_to=%d",
            $uid
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $pid = absint($row['project_id'] ?? 0);
            $cid = absint($row['client_id'] ?? 0);
            if ($pid) $scope['project_ids'][$pid] = $pid;
            if ($cid) $scope['client_ids'][$cid] = $cid;
        }
        ksort($scope['client_ids']);
        ksort($scope['project_ids']);
        $scope['client_ids'] = array_values($scope['client_ids']);
        $scope['project_ids'] = array_values($scope['project_ids']);
        return $scope;
    }



    public static function get_associated_user_ids(int $client_id = 0, int $project_id = 0): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $ids = [];
        if ($client_id > 0) {
            $meta_json = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$px}clients WHERE id=%d", $client_id));
            $meta = self::parse_meta($meta_json);
            foreach (self::extract_user_ids($meta) as $uid) $ids[$uid] = $uid;
        }
        if ($project_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT client_id, meta_json FROM {$px}projects WHERE id=%d", $project_id), ARRAY_A);
            if ($row) {
                $projectClientId = (int)($row['client_id'] ?? 0);
                if (!$client_id && $projectClientId > 0) {
                    $meta_json = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$px}clients WHERE id=%d", $projectClientId));
                    $meta = self::parse_meta($meta_json);
                    foreach (self::extract_user_ids($meta) as $uid) $ids[$uid] = $uid;
                }
                $meta = self::parse_meta($row['meta_json'] ?? '');
                $projectUsers = self::extract_user_ids($meta);
                if (!empty($projectUsers)) {
                    $ids = [];
                    foreach ($projectUsers as $uid) $ids[$uid] = $uid;
                }
            }
        }
        ksort($ids);
        return array_values($ids);
    }

    public static function get_associated_users(int $client_id = 0, int $project_id = 0, array $fields = ['ID','display_name','user_email','user_login']): array {
        $user_ids = self::get_associated_user_ids($client_id, $project_id);
        if (empty($user_ids)) return [];
        $users = get_users([
            'include' => $user_ids,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => $fields,
        ]);
        return is_array($users) ? $users : [];
    }

    public static function get_scope(?int $user_id = null): array {
        global $wpdb;
        $uid = $user_id ?: get_current_user_id();
        if ($uid > 0 && isset(self::$scope_cache[$uid])) return self::$scope_cache[$uid];
        $scope = [
            'is_manager' => false,
            'client_ids' => [],
            'project_ids' => [],
            'route_ids' => [],
            'role' => 'restricted',
        ];
        if (!$uid) return $scope;
        if (self::is_manager($uid)) {
            $scope['is_manager'] = true;
            $scope['role'] = 'manager';
            return self::$scope_cache[$uid] = $scope;
        }

        if (class_exists(ScopeIndex::class)) {
            $indexed = ScopeIndex::scopeForUser($uid);
            if (is_array($indexed)) {
                $client_ids = array_fill_keys(array_values(array_filter(array_map('absint', (array)($indexed['client_ids'] ?? [])))), true);
                $project_ids = array_fill_keys(array_values(array_filter(array_map('absint', (array)($indexed['project_ids'] ?? [])))), true);
                $scope['route_ids'] = array_values(array_filter(array_map('absint', (array)($indexed['route_ids'] ?? []))));
                $assignedStoreScope = self::assignedStoreScopeForUser($uid);
                foreach ($assignedStoreScope['client_ids'] as $cid) $client_ids[(int)$cid] = true;
                foreach ($assignedStoreScope['project_ids'] as $pid) $project_ids[(int)$pid] = true;
                ksort($client_ids);
                ksort($project_ids);
                $scope['client_ids'] = array_map('intval', array_keys($client_ids));
                $scope['project_ids'] = array_map('intval', array_keys($project_ids));
                return self::$scope_cache[$uid] = $scope;
            }
        }

        $px = $wpdb->prefix . 'routespro_';
        $client_ids = [];
        $project_ids = [];
        $route_ids = [];

        $clients = $wpdb->get_results("SELECT id, meta_json FROM {$px}clients", ARRAY_A) ?: [];
        foreach ($clients as $client) {
            $meta = self::parse_meta($client['meta_json'] ?? '');
            if (in_array($uid, self::extract_user_ids($meta), true)) {
                $client_ids[(int)$client['id']] = (int)$client['id'];
            }
        }

        $projects = $wpdb->get_results("SELECT id, client_id, status, meta_json FROM {$px}projects", ARRAY_A) ?: [];
        foreach ($projects as $project) {
            $pid = (int)($project['id'] ?? 0);
            $cid = (int)($project['client_id'] ?? 0);
            $meta = self::parse_meta($project['meta_json'] ?? '');
            $project_users = self::extract_user_ids($meta);
            // A associação ao cliente concede acesso de leitura a todas as campanhas desse cliente.
            // A lista do projeto define a equipa operacional, não deve bloquear o portal do cliente
            // nem impedir a resolução do tema específico da campanha.
            $inherits_client_access = $cid > 0 && isset($client_ids[$cid]);
            $has_direct_project_access = in_array($uid, $project_users, true);
            if ($has_direct_project_access || $inherits_client_access) {
                $project_ids[$pid] = $pid;
                if ($cid) $client_ids[$cid] = $cid;
            }
        }

        $projectAssignments = $wpdb->get_results($wpdb->prepare(
            "SELECT pa.project_id, p.client_id
             FROM {$px}project_assignments pa
             INNER JOIN {$px}projects p ON p.id = pa.project_id
             WHERE pa.user_id = %d AND pa.is_active = 1",
            $uid
        ), ARRAY_A) ?: [];
        foreach ($projectAssignments as $row) {
            $pid = (int)($row['project_id'] ?? 0);
            $cid = (int)($row['client_id'] ?? 0);
            if ($pid) $project_ids[$pid] = $pid;
            if ($cid) $client_ids[$cid] = $cid;
        }

        $assignedStoreScope = self::assignedStoreScopeForUser($uid);
        foreach ($assignedStoreScope['project_ids'] as $pid) $project_ids[(int)$pid] = (int)$pid;
        foreach ($assignedStoreScope['client_ids'] as $cid) $client_ids[(int)$cid] = (int)$cid;

        $routeAssignments = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT r.id AS route_id, r.client_id, r.project_id
             FROM {$px}routes r
             LEFT JOIN {$px}assignments a ON a.route_id = r.id AND a.is_active = 1
             WHERE r.owner_user_id = %d OR a.user_id = %d",
            $uid, $uid
        ), ARRAY_A) ?: [];
        foreach ($routeAssignments as $row) {
            $rid = (int)($row['route_id'] ?? 0);
            $pid = (int)($row['project_id'] ?? 0);
            $cid = (int)($row['client_id'] ?? 0);
            if ($rid) $route_ids[$rid] = $rid;
            if ($pid) $project_ids[$pid] = $pid;
            if ($cid) $client_ids[$cid] = $cid;
        }

        ksort($client_ids);
        ksort($project_ids);
        ksort($route_ids);
        $scope['client_ids'] = array_values($client_ids);
        $scope['project_ids'] = array_values($project_ids);
        $scope['route_ids'] = array_values($route_ids);
        return self::$scope_cache[$uid] = $scope;
    }

    public static function filter_clients(array $clients, ?int $user_id = null): array {
        $scope = self::get_scope($user_id);
        if ($scope['is_manager']) return $clients;
        $allowed = array_fill_keys($scope['client_ids'], true);
        return array_values(array_filter($clients, function($row) use ($allowed){ return !empty($allowed[(int)($row['id'] ?? 0)]); }));
    }

    public static function filter_projects(array $projects, ?int $user_id = null): array {
        $scope = self::get_scope($user_id);
        if ($scope['is_manager']) return $projects;
        $allowedProjects = array_fill_keys($scope['project_ids'], true);
        return array_values(array_filter($projects, function($row) use ($allowedProjects){
            $pid = (int)($row['id'] ?? 0);
            return !empty($allowedProjects[$pid]);
        }));
    }

    public static function is_allowed_client(int $client_id, ?int $user_id = null): bool {
        if ($client_id <= 0) return true;
        $scope = self::get_scope($user_id);
        return $scope['is_manager'] || in_array($client_id, $scope['client_ids'], true);
    }

    public static function is_allowed_project(int $project_id, ?int $user_id = null): bool {
        if ($project_id <= 0) return true;
        $scope = self::get_scope($user_id);
        return $scope['is_manager'] || in_array($project_id, $scope['project_ids'], true);
    }


    /**
     * Campanhas às quais o utilizador está diretamente ligado no Centro de
     * Atribuições ou numa rota. Não inclui a herança ampla do cliente.
     */
    public static function get_direct_project_ids(?int $user_id = null): array {
        global $wpdb;
        $uid = $user_id ?: get_current_user_id();
        if (!$uid) return [];
        $px = $wpdb->prefix . 'routespro_';
        $ids = [];
        $indexed = null;
        if (class_exists(ScopeIndex::class)) {
            $indexed = ScopeIndex::directProjectIdsForUser($uid);
            if (is_array($indexed)) {
                foreach ($indexed as $pid) { $pid = absint($pid); if ($pid) $ids[$pid] = $pid; }
            }
        }

        if (!is_array($indexed)) {
            $projects = $wpdb->get_results("SELECT id, meta_json FROM {$px}projects", ARRAY_A) ?: [];
            foreach ($projects as $project) {
                $meta = self::parse_meta($project['meta_json'] ?? '');
                if (in_array($uid, self::extract_user_ids($meta), true)) {
                    $pid = (int)($project['id'] ?? 0);
                    if ($pid) $ids[$pid] = $pid;
                }
            }

            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT project_id FROM {$px}project_assignments WHERE user_id=%d AND is_active=1",
                $uid
            )) ?: [];
            foreach ($rows as $pid) { $pid = absint($pid); if ($pid) $ids[$pid] = $pid; }

            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT r.project_id
                 FROM {$px}routes r
                 LEFT JOIN {$px}assignments a ON a.route_id=r.id AND a.is_active=1
                 WHERE r.project_id>0 AND (r.owner_user_id=%d OR a.user_id=%d)",
                $uid,
                $uid
            )) ?: [];
            foreach ($rows as $pid) { $pid = absint($pid); if ($pid) $ids[$pid] = $pid; }
        }

        // Atribuições reais de lojas também são uma associação direta à campanha.
        // Manter esta leitura live evita depender da geração atual do ScopeIndex.
        $assignedStoreScope = self::assignedStoreScopeForUser($uid);
        foreach ($assignedStoreScope['project_ids'] as $pid) { $pid = absint($pid); if ($pid) $ids[$pid] = $pid; }

        ksort($ids);
        return array_values($ids);
    }

    /**
     * Resolve um contexto de front sem inventar acessos.
     * Para utilizadores restritos, um único cliente/projeto disponível é selecionado
     * automaticamente. Gestores mantêm o contexto explícito para evitar surpresas.
     */
    public static function resolve_front_context(int $client_id = 0, int $project_id = 0, ?int $user_id = null): array {
        global $wpdb;
        $uid = $user_id ?: get_current_user_id();
        $client_id = absint($client_id);
        $project_id = absint($project_id);

        if ($project_id > 0) {
            $project_client_id = absint($wpdb->get_var($wpdb->prepare(
                "SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d LIMIT 1",
                $project_id
            )));
            if ($project_client_id > 0) $client_id = $project_client_id;
        }

        $scope = self::get_scope($uid);
        if (!empty($scope['is_manager'])) return [$client_id, $project_id];

        [$client_id, $project_id] = self::sanitize_scope_selection($client_id, $project_id, $uid);
        $client_ids = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
        $project_ids = array_values(array_filter(array_map('absint', (array)($scope['project_ids'] ?? []))));

        if ($client_id <= 0 && count($client_ids) === 1) $client_id = (int)$client_ids[0];

        if ($project_id <= 0) {
            // Quando existe uma única associação direta, ela ganha prioridade sobre
            // a herança ampla do cliente. Isto garante o tema da campanha certa no
            // primeiro carregamento sem retirar ao cliente acesso às restantes.
            $direct_candidates = self::get_direct_project_ids($uid);
            if ($client_id > 0 && $direct_candidates) {
                $placeholders = implode(',', array_fill(0, count($direct_candidates), '%d'));
                $args = array_merge([$client_id], $direct_candidates);
                $sql = "SELECT id FROM {$wpdb->prefix}routespro_projects WHERE client_id=%d AND id IN ({$placeholders}) ORDER BY id ASC";
                $direct_candidates = array_values(array_filter(array_map('absint', $wpdb->get_col($wpdb->prepare($sql, ...$args)) ?: [])));
            }
            if (count($direct_candidates) === 1) {
                $project_id = (int)$direct_candidates[0];
            } else {
                $candidates = $project_ids;
                if ($client_id > 0 && $candidates) {
                    $placeholders = implode(',', array_fill(0, count($candidates), '%d'));
                    $args = array_merge([$client_id], $candidates);
                    $sql = "SELECT id FROM {$wpdb->prefix}routespro_projects WHERE client_id=%d AND id IN ({$placeholders}) ORDER BY id ASC";
                    $candidates = array_values(array_filter(array_map('absint', $wpdb->get_col($wpdb->prepare($sql, ...$args)) ?: [])));
                }

                // A unique campaign-specific theme is a strong, explicit context signal.
                // Prefer it over the global theme when the user has broad client access.
                $themed_candidates = [];
                if (class_exists('\RoutesPro\Support\Theme')) {
                    foreach ($candidates as $candidate_id) {
                        if (\RoutesPro\Support\Theme::has_campaign_override($client_id, (int)$candidate_id)) {
                            $themed_candidates[] = (int)$candidate_id;
                        }
                    }
                }
                if (count($themed_candidates) === 1) {
                    $project_id = (int)$themed_candidates[0];
                } elseif (count($candidates) === 1) {
                    $project_id = (int)$candidates[0];
                } else {
                    // Last safe fallback: one active campaign inside the allowed client.
                    if ($candidates) {
                        $placeholders = implode(',', array_fill(0, count($candidates), '%d'));
                        $sql = "SELECT id FROM {$wpdb->prefix}routespro_projects WHERE id IN ({$placeholders}) AND status IN ('active','published') ORDER BY id ASC";
                        $active_candidates = array_values(array_filter(array_map('absint', $wpdb->get_col($wpdb->prepare($sql, ...$candidates)) ?: [])));
                        if (count($active_candidates) === 1) $project_id = (int)$active_candidates[0];
                    }
                }
            }
        }

        if ($project_id > 0 && $client_id <= 0) {
            $client_id = absint($wpdb->get_var($wpdb->prepare(
                "SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d LIMIT 1",
                $project_id
            )));
        }

        return [$client_id, $project_id];
    }

    public static function sanitize_scope_selection(int $client_id, int $project_id, ?int $user_id = null): array {
        $scope = self::get_scope($user_id);
        if ($scope['is_manager']) return [$client_id, $project_id];
        if ($client_id && !in_array($client_id, $scope['client_ids'], true)) $client_id = 0;
        if ($project_id && !in_array($project_id, $scope['project_ids'], true)) $project_id = 0;
        return [$client_id, $project_id];
    }

    public static function assert_scope_or_error(int $client_id, int $project_id, ?int $user_id = null) {
        if ($client_id && !self::is_allowed_client($client_id, $user_id)) {
            return new WP_Error('forbidden', 'Sem acesso ao cliente selecionado.', ['status' => 403]);
        }
        if ($project_id && !self::is_allowed_project($project_id, $user_id)) {
            return new WP_Error('forbidden', 'Sem acesso à campanha selecionada.', ['status' => 403]);
        }
        return true;
    }

    public static function scope_sql(string $routeAlias = 'r', ?string $clientColumn = null, ?string $projectColumn = null, ?int $user_id = null): array {
        $scope = self::get_scope($user_id);
        if ($scope['is_manager']) return ['1=1', []];
        $clientColumn = $clientColumn ?: ($routeAlias ? $routeAlias . '.client_id' : 'client_id');
        $projectColumn = $projectColumn ?: ($routeAlias ? $routeAlias . '.project_id' : 'project_id');
        $args = [];
        $clientIds = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
        $projectIds = array_values(array_filter(array_map('absint', (array)($scope['project_ids'] ?? []))));

        if (!empty($projectIds)) {
            $parts = [];
            if (!empty($clientIds)) {
                $parts[] = $clientColumn . ' IN (' . implode(',', array_fill(0, count($clientIds), '%d')) . ')';
                $args = array_merge($args, $clientIds);
            }
            $parts[] = $projectColumn . ' IN (' . implode(',', array_fill(0, count($projectIds), '%d')) . ')';
            $args = array_merge($args, $projectIds);
            return ['(' . implode(' AND ', $parts) . ')', $args];
        }

        if (!empty($clientIds)) {
            return [$clientColumn . ' IN (' . implode(',', array_fill(0, count($clientIds), '%d')) . ')', $clientIds];
        }

        return ['1=0', []];
    }
}
