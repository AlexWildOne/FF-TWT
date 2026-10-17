<?php
/**
 * AJAX endpoints para RoutesPro (BO/Front)
 * - Garantem autenticação e saneamento de inputs
 * - Alimentam selects de Projeto, Utilizador e Função
 * - Inclui uploader simples para “foto/prova” no front
 */

if (!defined('ABSPATH')) { exit; }

/**
 * CLIENTES acessíveis ao utilizador atual (owner ou assignment em alguma rota)
 */
add_action('wp_ajax_routespro_clients_for_user', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);

    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $rows = $wpdb->get_results("SELECT id, name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
    $rows = \RoutesPro\Support\Permissions::filter_clients($rows);
    wp_send_json($rows ?: []);
});

/**
 * PROJETOS por CLIENTE (legacy – usado nalguns ecrãs)
 */
add_action('wp_ajax_routespro_projects_for_client', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);

    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $cid = absint($_GET['client_id'] ?? 0);
    if ($cid && !\RoutesPro\Support\Permissions::is_allowed_client($cid)) wp_send_json([], 200);
    $sql = "SELECT id, name, client_id FROM {$px}projects" . ($cid ? $wpdb->prepare(" WHERE client_id=%d", $cid) : '') . " ORDER BY name ASC";
    $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
    $rows = \RoutesPro\Support\Permissions::filter_projects($rows);
    $rows = array_map(function($r){ return ['id'=>(int)$r['id'], 'name'=>(string)$r['name']]; }, $rows);
    wp_send_json($rows ?: []);
});

/**
 * NOVO: PROJETOS acessíveis ao utilizador (sem depender de cliente)
 * Filtros opcionais:
 *   - user_id: filtra por um funcionário específico (owner/assignment)
 *   - role:    filtra pela função na assignment
 */
add_action('wp_ajax_routespro_projects_for_user', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);

    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $client_id = absint($_GET['client_id'] ?? 0);
    if ($client_id && !\RoutesPro\Support\Permissions::is_allowed_client($client_id)) wp_send_json([], 200);
    $sql = "SELECT id, name, client_id FROM {$px}projects" . ($client_id ? $wpdb->prepare(" WHERE client_id=%d", $client_id) : '') . " ORDER BY name ASC";
    $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
    $rows = \RoutesPro\Support\Permissions::filter_projects($rows);
    $rows = array_map(function($r){ return ['id'=>(int)$r['id'], 'name'=>(string)$r['name'], 'client_id'=>(int)$r['client_id']]; }, $rows);
    wp_send_json($rows ?: []);
});

/**
 * UTILIZADORES (para combos no BO / dashboard)
 * Retorna um label pronto para UI e campos úteis.
 */
add_action('wp_ajax_routespro_users', function () {
    if (!\RoutesPro\Support\Permissions::can_access_front()) wp_send_json([], 403);

    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $client_id = absint($_GET['client_id'] ?? 0);
    $project_id = absint($_GET['project_id'] ?? 0);
    $role = sanitize_key($_GET['role'] ?? '');

    if ($client_id || $project_id) {
        $scope_check = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id);
        if (is_wp_error($scope_check)) {
            wp_send_json(['message' => $scope_check->get_error_message()], 403);
        }
    }
    $users = \RoutesPro\Support\Permissions::get_associated_users($client_id, $project_id, ['ID', 'display_name', 'user_email', 'user_login']);

    // O Intelligence Center é a fonte de verdade para responsáveis de projeto.
    if ($client_id || $project_id) {
        $project_where = ['pa.is_active = 1'];
        $project_args = [];
        if ($client_id > 0) { $project_where[] = 'p.client_id = %d'; $project_args[] = $client_id; }
        if ($project_id > 0) { $project_where[] = 'pa.project_id = %d'; $project_args[] = $project_id; }
        if ($role !== '') { $project_where[] = 'pa.role = %s'; $project_args[] = $role; }
        $project_sql = "SELECT DISTINCT u.ID, u.display_name, u.user_email, u.user_login
                        FROM {$wpdb->users} u
                        INNER JOIN {$px}project_assignments pa ON pa.user_id = u.ID
                        INNER JOIN {$px}projects p ON p.id = pa.project_id
                        WHERE " . implode(' AND ', $project_where) . "
                        ORDER BY COALESCE(NULLIF(u.display_name,''), u.user_login) ASC";
        $project_users = $project_args ? ($wpdb->get_results($wpdb->prepare($project_sql, ...$project_args)) ?: []) : ($wpdb->get_results($project_sql) ?: []);
        if ($project_users) {
            $merged = [];
            foreach ((array)$users as $u) $merged[(int)$u->ID] = $u;
            foreach ((array)$project_users as $u) $merged[(int)$u->ID] = $u;
            $users = array_values($merged);
        }
    }

    $explicit_user_ids = [];
    foreach ((array)$users as $u) $explicit_user_ids[(int)($u->ID ?? 0)] = true;

    if ($client_id || $project_id || $role) {
        $where = ['a.is_active = 1'];
        $args = [];
        if ($client_id > 0) { $where[] = 'r.client_id = %d'; $args[] = $client_id; }
        if ($project_id > 0) { $where[] = 'r.project_id = %d'; $args[] = $project_id; }
        if ($role !== '') { $where[] = 'a.role = %s'; $args[] = $role; }
        $sql = "SELECT DISTINCT u.ID, u.display_name, u.user_email, u.user_login
                FROM {$wpdb->users} u
                INNER JOIN {$px}assignments a ON a.user_id = u.ID
                INNER JOIN {$px}routes r ON r.id = a.route_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(NULLIF(u.display_name,''), u.user_login) ASC";
        $assignment_users = $args ? ($wpdb->get_results($wpdb->prepare($sql, ...$args)) ?: []) : ($wpdb->get_results($sql) ?: []);
        if (!empty($assignment_users)) {
            $merged = [];
            foreach ((array)$users as $u) { $merged[(int)$u->ID] = $u; }
            foreach ((array)$assignment_users as $u) {
                $candidate_id = (int)($u->ID ?? 0);
                if ($candidate_id && \RoutesPro\Support\Permissions::is_manager($candidate_id) && empty($explicit_user_ids[$candidate_id])) continue;
                $merged[$candidate_id] = $u;
            }
            $users = array_values($merged);
        }
    }

    if (($client_id || $project_id) && is_user_logged_in()) {
        $current = wp_get_current_user();
        if ($current && !empty($current->ID) && !\RoutesPro\Support\Permissions::is_manager((int)$current->ID)) {
            $found = false;
            foreach ((array)$users as $u) {
                if ((int)($u->ID ?? 0) === (int)$current->ID) { $found = true; break; }
            }
            $scope = \RoutesPro\Support\Permissions::get_scope((int)$current->ID);
            $matches_client = !$client_id || in_array($client_id, (array)($scope['client_ids'] ?? []), true);
            $matches_project = !$project_id || in_array($project_id, (array)($scope['project_ids'] ?? []), true);
            if (!$found && $matches_client && $matches_project) {
                $users[] = (object)[
                    'ID' => (int)$current->ID,
                    'display_name' => (string)$current->display_name,
                    'user_email' => (string)$current->user_email,
                    'user_login' => (string)$current->user_login,
                ];
            }
        }
    }

    if (!$users) {
        $users = [];
    }

    $out = array_map(function ($u) {
        $label = trim(($u->display_name ?: $u->user_login).' • '.($u->user_email ?: ''));
        return [
            'ID'          => (int)$u->ID,
            'displayName' => $u->display_name,
            'email'       => $u->user_email,
            'username'    => $u->user_login,
            'label'       => $label,
        ];
    }, $users);

    wp_send_json($out);
});

/**
 * FUNÇÕES (roles) para assignments
 * - Lista consolidada: defaults + DISTINCT na tabela assignments
 */
add_action('wp_ajax_routespro_roles', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);

    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $client_id = absint($_GET['client_id'] ?? 0);
    $project_id = absint($_GET['project_id'] ?? 0);
    if ($client_id && !\RoutesPro\Support\Permissions::is_allowed_client($client_id)) wp_send_json([], 200);
    if ($project_id && !\RoutesPro\Support\Permissions::is_allowed_project($project_id)) wp_send_json([], 200);

    $defaults = ['implementacao','merchandising','comercial','driver'];
    $where = ["role IS NOT NULL", "role <> ''"];
    $args = [];
    if ($client_id > 0 || $project_id > 0) {
        $where[] = "route_id IN (SELECT id FROM {$px}routes WHERE 1=1" . ($client_id > 0 ? " AND client_id = %d" : '') . ($project_id > 0 ? " AND project_id = %d" : '') . ")";
        if ($client_id > 0) $args[] = $client_id;
        if ($project_id > 0) $args[] = $project_id;
    }
    $sql = "SELECT DISTINCT role FROM {$px}assignments WHERE " . implode(' AND ', $where) . " ORDER BY role ASC";
    $dbRoles  = $args ? $wpdb->get_col($wpdb->prepare($sql, ...$args)) : $wpdb->get_col($sql);
    $merged   = array_values(array_unique(array_merge($defaults, $dbRoles ?: [])));

    $out = array_map(function($r){
        return ['id'=>$r, 'name'=>ucfirst(str_replace('_',' ',$r))];
    }, $merged);

    wp_send_json($out);
});

/**
 * Alias para compatibilidade: algumas UIs pedem routespro_functions_list
 */
add_action('wp_ajax_routespro_functions_list', function () {
    do_action('wp_ajax_routespro_roles');
});

/**
 * LISTAR atribuições de uma rota (com detalhes de utilizador)
 */
add_action('wp_ajax_routespro_assignments_for_route', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);

    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $rid = absint($_GET['route_id'] ?? 0);
    if (!$rid) wp_send_json([], 200);
    if (!\RoutesPro\Support\Permissions::can_access_route($rid)) {
        wp_send_json(['message' => 'Sem acesso a esta rota.'], 403);
    }

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT a.id,
               a.user_id,
               a.role,
               u.user_login,
               u.user_email,
               u.display_name
        FROM {$px}assignments a
        LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
        WHERE a.route_id = %d
        ORDER BY a.id ASC
    ", $rid), ARRAY_A);

    wp_send_json($rows ?: []);
});

/**
 * IDs de utilizadores atribuídos a uma rota (usado no front se só precisarmos dos IDs)
 */
add_action('wp_ajax_routespro_route_owner_ids', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);

    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $rid = absint($_GET['route_id'] ?? 0);
    if (!$rid) wp_send_json([], 200);
    if (!\RoutesPro\Support\Permissions::can_access_route($rid)) {
        wp_send_json(['message' => 'Sem acesso a esta rota.'], 403);
    }

    $ids = $wpdb->get_col($wpdb->prepare("
        SELECT user_id FROM {$px}assignments
        WHERE route_id = %d ORDER BY id ASC
    ", $rid));

    wp_send_json($ids ?: []);
});

/**
 * ATRIBUIR rota a utilizadores (com função)
 *
 * Aceita formatos:
 *  (A) POST 'payload' JSON:
 *      { "route_id": 123, "assignments": [ {"user_id":12,"role":"comercial"}, ... ] }
 *  (B) POST 'assignments' JSON: [ {"user_id":12,"role":"..."} ]
 *      + POST 'route_id'
 *  (C) POST 'owners' CSV (legado) + 'route_id' → role padrão 'driver'
 */
add_action('wp_ajax_routespro_assign_route', function () {
    if (!current_user_can('routespro_manage')) {
        wp_send_json(['ok' => false, 'error' => 'forbidden'], 403);
    }

    $nonce = sanitize_text_field(wp_unslash((string)($_POST['_wpnonce'] ?? $_POST['_ajax_nonce'] ?? $_POST['nonce'] ?? '')));
    $nonce_ok = $nonce !== '' && (wp_verify_nonce($nonce, 'wp_rest') || wp_verify_nonce($nonce, 'routespro_assign_route'));
    if (!$nonce_ok) {
        wp_send_json(['ok' => false, 'error' => 'invalid_nonce'], 403);
    }

    global $wpdb; $px = $wpdb->prefix . 'routespro_';

    $rid = absint($_POST['route_id'] ?? 0);
    $assignments = [];

    // (A) payload JSON completo
    if (!empty($_POST['payload'])) {
        $payload = json_decode(stripslashes((string)$_POST['payload']), true);
        if (is_array($payload)) {
            $rid = absint($payload['route_id'] ?? $rid);
            if (isset($payload['assignments']) && is_array($payload['assignments'])) {
                foreach ($payload['assignments'] as $row) {
                    $uid  = absint($row['user_id'] ?? 0);
                    $role = sanitize_text_field($row['role'] ?? 'driver');
                    if ($uid) $assignments[] = ['user_id' => $uid, 'role' => $role];
                }
            }
        }
    }

    // (B) assignments JSON + route_id separado
    if (!$assignments && !empty($_POST['assignments'])) {
        $json = json_decode(stripslashes((string)$_POST['assignments']), true);
        if (is_array($json)) {
            foreach ($json as $row) {
                $uid  = absint($row['user_id'] ?? 0);
                $role = sanitize_text_field($row['role'] ?? 'driver');
                if ($uid) $assignments[] = ['user_id' => $uid, 'role' => $role];
            }
        }
    }

    // (C) owners CSV legado
    if (!$assignments && !empty($_POST['owners'])) {
        $owners = array_filter(array_map('absint', explode(',', (string)$_POST['owners'])));
        foreach ($owners as $uid) { $assignments[] = ['user_id' => $uid, 'role' => 'driver']; }
    }

    if (!$rid) wp_send_json(['ok'=>false,'error'=>'missing route_id'], 400);

    $route = $wpdb->get_row($wpdb->prepare(
        "SELECT id, client_id, project_id, owner_user_id FROM {$px}routes WHERE id=%d",
        $rid
    ), ARRAY_A);
    if (!$route) wp_send_json(['ok' => false, 'error' => 'route_not_found'], 404);

    $affected_user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$px}assignments WHERE route_id=%d",
        $rid
    )) ?: [];
    $before_user_ids = array_values(array_unique(array_filter(array_map('intval', $affected_user_ids))));

    $transaction_started = $wpdb->query('START TRANSACTION') !== false;
    if (!$transaction_started) {
        if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
            \RoutesPro\Core\AuditLog::record(
                'route.assignments_update_failed',
                'route',
                $rid,
                null,
                null,
                [
                    'actor_user_id' => get_current_user_id(),
                    'client_id' => (int)($route['client_id'] ?? 0),
                    'project_id' => (int)($route['project_id'] ?? 0),
                    'route_id' => $rid,
                    'source' => 'backoffice',
                ],
                ['error_code' => 'transaction_start_failed']
            );
        }
        wp_send_json(['ok' => false, 'error' => 'assignment_update_failed'], 500);
    }

    try {
        $deleted = $wpdb->delete($px.'assignments', ['route_id' => $rid]);
        if ($deleted === false) throw new \RuntimeException('assignment_delete_failed');

        $owner_user_id = 0;
        foreach ($assignments as $i => $as) {
            $inserted = $wpdb->insert($px.'assignments', [
                'route_id' => $rid,
                'user_id'  => $as['user_id'],
                'role'     => $as['role'],
            ], ['%d','%d','%s']);
            if ($inserted === false) throw new \RuntimeException('assignment_insert_failed');
            if ($i === 0) $owner_user_id = (int)$as['user_id'];
        }

        if ($owner_user_id) {
            $updated = $wpdb->update($px.'routes', ['owner_user_id' => $owner_user_id], ['id' => $rid], ['%d'], ['%d']);
            if ($updated === false) throw new \RuntimeException('route_owner_update_failed');
        }

        if ($transaction_started && $wpdb->query('COMMIT') === false) {
            throw new \RuntimeException('transaction_commit_failed');
        }
    } catch (\Throwable $e) {
        if ($transaction_started) $wpdb->query('ROLLBACK');
        if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
            \RoutesPro\Core\AuditLog::record(
                'route.assignments_update_failed',
                'route',
                $rid,
                null,
                null,
                [
                    'actor_user_id' => get_current_user_id(),
                    'client_id' => (int)($route['client_id'] ?? 0),
                    'project_id' => (int)($route['project_id'] ?? 0),
                    'route_id' => $rid,
                    'source' => 'backoffice',
                ],
                ['error_code' => sanitize_key($e->getMessage())]
            );
        }
        wp_send_json(['ok' => false, 'error' => 'assignment_update_failed'], 500);
    }

    foreach ($assignments as $assignment) $affected_user_ids[] = (int)$assignment['user_id'];
    foreach (array_unique(array_map('intval', $affected_user_ids)) as $uid) {
        if ($uid > 0) \RoutesPro\Support\Permissions::clear_scope_cache($uid);
    }

    if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
        \RoutesPro\Core\AuditLog::record(
            'route.assignments_updated',
            'route',
            $rid,
            ['assigned_user_ids' => $before_user_ids],
            ['assigned_user_ids' => array_values(array_map(static function ($row) { return (int)$row['user_id']; }, $assignments))],
            [
                'actor_user_id' => get_current_user_id(),
                'client_id' => (int)($route['client_id'] ?? 0),
                'project_id' => (int)($route['project_id'] ?? 0),
                'route_id' => $rid,
                'source' => 'backoffice',
            ],
            ['assignment_count' => count($assignments)]
        );
    }

    wp_send_json(['ok' => true, 'count' => count($assignments)]);
});


add_action('wp_ajax_routespro_campaign_plan_preview', function () {
    \RoutesPro\Admin\CampaignLocations::ajax_plan_preview();
});
/**
 * UPLOAD de foto/prova (front)
 * - recebe ficheiro em $_FILES['file']
 * - retorna { ok, url, file } em sucesso
 * - exige login; (opcional) verifica nonce se passado
 */
add_action('wp_ajax_routespro_upload_proof', function () {
    if (!is_user_logged_in()) wp_send_json(['ok'=>false,'error'=>'forbidden'], 403);

    if (!empty($_POST['_wpnonce']) && !wp_verify_nonce($_POST['_wpnonce'], 'routespro_upload_proof')) {
        wp_send_json(['ok'=>false,'error'=>'bad_nonce'], 403);
    }

    if (empty($_FILES['file'])) {
        wp_send_json(['ok'=>false,'error'=>'missing_file'], 400);
    }

    $max_size = 10 * 1024 * 1024;
    $size = (int) ($_FILES['file']['size'] ?? 0);
    if ($size <= 0 || $size > $max_size) {
        wp_send_json(['ok'=>false,'error'=>'invalid_size'], 400);
    }

    // Limitar tipos básicos de imagem (podes expandir)
    $allowed_types = ['image/jpeg','image/png','image/webp','image/gif'];
    $tmp_name = $_FILES['file']['tmp_name'] ?? '';
    $original_name = sanitize_file_name((string)($_FILES['file']['name'] ?? 'upload'));
    $checked = wp_check_filetype_and_ext($tmp_name, $original_name);
    $real_mime = (string) ($checked['type'] ?? '');
    if ($real_mime === '' || !in_array($real_mime, $allowed_types, true)) {
        wp_send_json(['ok'=>false,'error'=>'invalid_type'], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $overrides = ['test_form' => false, 'mimes' => null, 'unique_filename_callback' => null]; // usar mimes do WP
    $movefile  = wp_handle_upload($_FILES['file'], $overrides);

    if (!empty($movefile['error'])) {
        wp_send_json(['ok'=>false,'error'=>$movefile['error']], 500);
    }

    $url  = $movefile['url'];
    $file = $movefile['file'];

    // Opcional: criar attachment na Media Library:
    // require_once ABSPATH . 'wp-admin/includes/image.php';
    // $attach_id = wp_insert_attachment([
    //     'post_mime_type' => $movefile['type'],
    //     'post_title'     => wp_basename($file),
    //     'post_content'   => '',
    //     'post_status'    => 'inherit'
    // ], $file);
    // $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    // wp_update_attachment_metadata($attach_id, $attach_data);

    wp_send_json([
        'ok'   => true,
        'url'  => esc_url_raw($url),
        'file' => $file,
        // 'attachment_id' => $attach_id ?? null,
    ]);
});

/**
 * Operativos disponíveis nos filtros do portal do cliente.
 * Usa exclusivamente a equipa definida no Intelligence Center e nas rotas.
 */
add_action('wp_ajax_routespro_project_operatives', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);

    $client_id = absint($_GET['client_id'] ?? 0);
    $project_id = absint($_GET['project_id'] ?? 0);
    $perm = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id);
    if (is_wp_error($perm)) wp_send_json(['message' => $perm->get_error_message()], 403);

    if ($project_id > 0) {
        $ids = \RoutesPro\Support\AssignmentResolver::get_project_operational_user_ids($project_id);
    } elseif ($client_id > 0) {
        $ids = \RoutesPro\Support\AssignmentResolver::get_client_operational_user_ids($client_id);
    } else {
        $ids = [];
        $scope = \RoutesPro\Support\Permissions::get_scope();
        foreach ((array)($scope['project_ids'] ?? []) as $pid) {
            foreach (\RoutesPro\Support\AssignmentResolver::get_project_operational_user_ids((int)$pid) as $uid) $ids[$uid] = $uid;
        }
        $ids = array_values($ids);
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', (array)$ids))));
    if (!$ids) wp_send_json([]);
    $users = get_users([
        'include' => $ids,
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => ['ID','display_name','user_email','user_login'],
    ]);
    $out = [];
    foreach ((array)$users as $user) {
        $display = (string)($user->display_name ?: $user->user_login ?: ('#' . $user->ID));
        $out[] = [
            'ID' => (int)$user->ID,
            'display_name' => $display,
            'user_email' => (string)$user->user_email,
            'user_login' => (string)$user->user_login,
            'label' => trim($display . ((string)$user->user_email !== '' ? ' • ' . $user->user_email : '')),
        ];
    }
    wp_send_json($out);
});

/**
 * 2.2.315: autorização explícita para ações do portal do cliente.
 * Mantém admins e utilizadores de cliente com scope, mas não transforma um
 * utilizador operacional/merchandiser num remetente de comunicação do cliente.
 */
if (!function_exists('routespro_user_can_use_client_team_messaging')) {
    function routespro_user_can_use_client_team_messaging(int $user_id): bool {
        if ($user_id <= 0) return false;
        $user = get_userdata($user_id);
        if (!$user || !($user instanceof \WP_User) || !$user->exists()) return false;
        if (user_can($user, 'routespro_manage')) return true;

        $scope = \RoutesPro\Support\Permissions::get_scope($user_id);
        $has_scope = !empty($scope['client_ids']) || !empty($scope['project_ids']);
        if (!$has_scope) return false;

        $roles = array_map('strtolower', array_values((array)$user->roles));
        $client_role = false;
        $operational_role = false;
        foreach ($roles as $role) {
            if (preg_match('/(^|_)(client|cliente|customer|brand|account)(_|$)/', $role)) $client_role = true;
            if (preg_match('/(^|_)(merchandiser|merchan|promotor|promoter|route_agent|field|operational|operacional|supervisor|coordinator|coordenador|team_lead|teamlead)(_|$)/', $role)) $operational_role = true;
        }
        $can_execute = user_can($user, 'routespro_execute');
        $has_routes = !empty($scope['route_ids']);
        $operational = $can_execute || $has_routes || $operational_role;

        return $client_role || (!$operational && $has_scope);
    }
}

/**
 * Owners / membros relevantes para uma campanha, para uso no portal do cliente.
 */
add_action('wp_ajax_routespro_team_recipients', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);
    check_ajax_referer('wp_rest', '_wpnonce');
    if (!routespro_user_can_use_client_team_messaging(get_current_user_id())) {
        wp_send_json(['message' => 'Sem permissão para a comunicação da equipa do cliente.'], 403);
    }

    $client_id = absint($_GET['client_id'] ?? 0);
    $project_id = absint($_GET['project_id'] ?? 0);
    $perm = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id);
    if (is_wp_error($perm)) wp_send_json(['message' => $perm->get_error_message()], 403);

    $users = \RoutesPro\Admin\Emails::get_team_recipients($client_id, $project_id);
    wp_send_json($users ?: []);
});


add_action('wp_ajax_routespro_portal_locations', function () {
    if (!is_user_logged_in()) wp_send_json([], 403);

    global $wpdb;
    $px = $wpdb->prefix . 'routespro_';
    $client_id = absint($_GET['client_id'] ?? 0);
    $project_id = absint($_GET['project_id'] ?? 0);
    $owner_user_id = absint($_GET['owner_user_id'] ?? 0);
    $date_from = sanitize_text_field((string) ($_GET['date_from'] ?? ''));
    $date_to = sanitize_text_field((string) ($_GET['date_to'] ?? ''));
    if ($date_from === '') $date_from = date('Y-m-01');
    if ($date_to === '') $date_to = current_time('Y-m-d');

    $perm = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id);
    if (is_wp_error($perm)) wp_send_json(['message' => $perm->get_error_message()], 403);

    $where = ["r.date BETWEEN %s AND %s"];
    $args = [$date_from, $date_to];
    if ($client_id) { $where[] = "r.client_id = %d"; $args[] = $client_id; }
    if ($project_id) { $where[] = "r.project_id = %d"; $args[] = $project_id; }
    if ($owner_user_id) {
        $where[] = "(r.owner_user_id = %d OR EXISTS (SELECT 1 FROM {$px}assignments ax WHERE ax.route_id = r.id AND ax.user_id = %d AND ax.is_active = 1))";
        $args[] = $owner_user_id; $args[] = $owner_user_id;
    }
    list($scopeSql, $scopeArgs) = \RoutesPro\Support\Permissions::scope_sql('r');
    if ($scopeSql !== '1=1') { $where[] = $scopeSql; $args = array_merge($args, $scopeArgs); }

    $sql = "SELECT DISTINCT l.id, l.name, l.city, l.address
            FROM {$px}route_stops rs
            INNER JOIN {$px}routes r ON r.id = rs.route_id
            INNER JOIN {$px}locations l ON l.id = rs.location_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.name ASC, l.city ASC, l.id ASC";
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];

    if (!$rows && ($project_id || $client_id)) {
        $where2 = ["1=1"];
        $args2 = [];
        if ($project_id) { $where2[] = "cl.project_id = %d"; $args2[] = $project_id; }
        elseif ($client_id) { $where2[] = "p.client_id = %d"; $args2[] = $client_id; }
        if ($owner_user_id) { $where2[] = "(cl.assigned_to = %d OR cl.assigned_to IS NULL OR cl.assigned_to = 0)"; $args2[] = $owner_user_id; }
        $sql2 = "SELECT DISTINCT l.id, l.name, l.city, l.address
                 FROM {$px}campaign_locations cl
                 INNER JOIN {$px}locations l ON l.id = cl.location_id
                 INNER JOIN {$px}projects p ON p.id = cl.project_id
                 WHERE " . implode(' AND ', $where2) . "
                 ORDER BY l.name ASC, l.city ASC, l.id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql2, ...$args2), ARRAY_A) ?: [];
    }

    $items = array_map(static function(array $row){
        $label = trim((string) ($row['name'] ?? ''));
        $city = trim((string) ($row['city'] ?? ''));
        $address = trim((string) ($row['address'] ?? ''));
        if ($city !== '') $label .= ' · ' . $city;
        elseif ($address !== '') $label .= ' · ' . $address;
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'label' => $label !== '' ? $label : ('Local #' . (int) ($row['id'] ?? 0)),
        ];
    }, $rows);

    wp_send_json(array_values($items));
});


if (!function_exists('routespro_collect_uploaded_attachments')) {
    function routespro_collect_uploaded_attachments(string $field_key = 'attachments', int $max_files = 10, int $max_bytes = 15728640): array {
        if (empty($_FILES[$field_key])) return ['items' => [], 'paths' => [], 'error' => ''];
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = $_FILES[$field_key];
        $names = is_array($file['name'] ?? null) ? $file['name'] : [$file['name'] ?? ''];
        $total = min(count($names), $max_files);
        $items = [];
        $paths = [];
        for ($i = 0; $i < $total; $i++) {
            $name = is_array($file['name'] ?? null) ? (string)($file['name'][$i] ?? '') : (string)($file['name'] ?? '');
            if ($name === '') continue;
            $size = is_array($file['size'] ?? null) ? (int)($file['size'][$i] ?? 0) : (int)($file['size'] ?? 0);
            $err  = is_array($file['error'] ?? null) ? (int)($file['error'][$i] ?? 0) : (int)($file['error'] ?? 0);
            if ($err !== UPLOAD_ERR_OK) return ['items' => [], 'paths' => [], 'error' => 'Falha no upload de um anexo.'];
            if ($size <= 0 || $size > $max_bytes) return ['items' => [], 'paths' => [], 'error' => 'Cada anexo deve ter no máximo 15 MB.'];
            $single = [
                'name' => sanitize_file_name($name),
                'type' => is_array($file['type'] ?? null) ? (string)($file['type'][$i] ?? '') : (string)($file['type'] ?? ''),
                'tmp_name' => is_array($file['tmp_name'] ?? null) ? (string)($file['tmp_name'][$i] ?? '') : (string)($file['tmp_name'] ?? ''),
                'error' => $err,
                'size' => $size,
            ];
            $allowedMimes = [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'heic' => 'image/heic',
                'heif' => 'image/heif',
                'pdf' => 'application/pdf',
                'csv' => 'text/csv',
                'txt' => 'text/plain',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'zip' => 'application/zip',
            ];
            try {
                $uploaded = wp_handle_upload($single, [
                    'test_form' => false,
                    'test_type' => true,
                    'mimes' => $allowedMimes,
                ]);
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('[FieldFlow attachment upload] ' . $e->getMessage());
                return ['items' => [], 'paths' => [], 'error' => 'Falha técnica ao processar o anexo ' . sanitize_file_name($name) . '.'];
            }
            if (!is_array($uploaded)) return ['items' => [], 'paths' => [], 'error' => 'Resposta inválida ao processar o anexo.'];
            if (!empty($uploaded['error'])) return ['items' => [], 'paths' => [], 'error' => (string)$uploaded['error']];
            if (empty($uploaded['url']) || empty($uploaded['file'])) return ['items' => [], 'paths' => [], 'error' => 'O anexo foi recebido, mas não foi possível concluir o armazenamento.'];
            $items[] = [
                'name' => sanitize_file_name($name),
                'url' => esc_url_raw((string)$uploaded['url']),
                'type' => sanitize_text_field((string)($uploaded['type'] ?? $single['type'])),
                'size' => $size,
            ];
            $paths[] = (string)$uploaded['file'];
        }
        return ['items' => $items, 'paths' => $paths, 'error' => ''];
    }
}

if (!function_exists('routespro_render_attachment_links_html')) {
    function routespro_render_attachment_links_html(array $attachments): string {
        if (!$attachments) return '';
        $html = '<div style="margin-top:14px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff"><strong>Anexos</strong><ul style="margin:8px 0 0;padding-left:18px">';
        foreach ($attachments as $att) {
            $url = esc_url((string)($att['url'] ?? ''));
            if (!$url) continue;
            $name = esc_html((string)($att['name'] ?? basename($url)));
            $html .= '<li><a href="' . $url . '" target="_blank" rel="noopener">' . $name . '</a></li>';
        }
        return $html . '</ul></div>';
    }
}

add_action('wp_ajax_routespro_get_client_team_senders', function () {
    if (!is_user_logged_in()) wp_send_json(['message' => 'Sem sessão ativa.'], 403);
    check_ajax_referer('wp_rest', '_wpnonce');
    if (!routespro_user_can_use_client_team_messaging(get_current_user_id())) {
        wp_send_json(['message' => 'Sem permissão para a comunicação da equipa do cliente.'], 403);
    }
    $client_id = absint($_GET['client_id'] ?? 0);
    $project_id = absint($_GET['project_id'] ?? 0);
    $perm = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id);
    if (is_wp_error($perm)) wp_send_json(['message' => $perm->get_error_message()], 403);
    $users = \RoutesPro\Admin\Emails::get_available_senders_for_user($client_id, $project_id, get_current_user_id());
    wp_send_json($users ?: []);
});

/**
 * Envio de email do cliente para membro da equipa, filtrado por campanha.
 */
function routespro_send_client_team_message_delivery(array $ctx, array $recipient): array {
    global $wpdb;

    $recipient_user_id = absint($recipient['ID'] ?? 0);
    $recipient_email = sanitize_email((string) ($recipient['user_email'] ?? ''));
    if (!$recipient_user_id || !$recipient_email) {
        return ['ok' => false, 'recipient_user_id' => $recipient_user_id, 'message' => 'Destinatário inválido.'];
    }

    $sender = $ctx['sender'];
    $current_user = $ctx['current_user'];
    $client = $ctx['client'];
    $project = $ctx['project'];
    $opts = $ctx['opts'];
    $attachmentsMeta = $ctx['attachments_meta'];
    $attachmentPaths = $ctx['attachment_paths'];
    $broadcast_id = (string) ($ctx['broadcast_id'] ?? '');
    $broadcast_total = absint($ctx['broadcast_total'] ?? 1);

    $preMeta = [
        'priority' => $ctx['priority'],
        'copied_to_sender' => !empty($ctx['include_sender_copy']),
        'sender_email' => (string) ($sender->user_email ?? ''),
        'selected_sender_user_id' => (int) ($sender->ID ?: 0),
        'selected_sender_email' => (string) ($sender->user_email ?? ''),
        'submitted_by_user_id' => get_current_user_id(),
        'submitted_by_email' => (string) ($current_user->user_email ?? ''),
        'workflow_status' => 'novo',
        'attachments' => $attachmentsMeta,
    ];
    if ($broadcast_id !== '') {
        $preMeta['broadcast_id'] = $broadcast_id;
        $preMeta['broadcast_total'] = $broadcast_total;
        $preMeta['broadcast_recipient_user_id'] = $recipient_user_id;
    }

    $prelog_id = \RoutesPro\Admin\Emails::log_email([
        'email_type' => 'client_team_message',
        'context_key' => $broadcast_id !== '' ? 'portal_broadcast' : 'portal',
        'client_id' => $ctx['client_id'],
        'project_id' => $ctx['project_id'],
        'sender_user_id' => (int) ($sender->ID ?: get_current_user_id()),
        'recipient_user_id' => $recipient_user_id,
        'recipient_email' => $recipient_email,
        'recipient_name' => (string) ($recipient['display_name'] ?? ''),
        'message_kind' => $ctx['message_kind'],
        'subject' => '[Portal Cliente] ' . $ctx['subject'],
        'body' => $ctx['body'],
        'meta' => $preMeta,
        'mail_result' => 'pending',
    ]);

    $message_app_url = \RoutesPro\Admin\Emails::get_message_app_url($prelog_id);
    $tokens = [
        '{client_name}' => (string) ($client['name'] ?? ''),
        '{project_name}' => (string) ($project['name'] ?? ''),
        '{user_name}' => (string) ($recipient['display_name'] ?? ''),
        '{message_kind}' => ucfirst($ctx['message_kind'] ?: 'geral'),
        '{priority}' => $ctx['priority'],
        '{sender_name}' => (string) ($sender->display_name ?: $sender->user_login),
        '{sender_email}' => (string) ($sender->user_email ?? ''),
    ];

    $plain_body = "Olá {user_name},\n\nRecebeste uma nova mensagem do portal do cliente.\n\nCliente: {client_name}\nCampanha: {project_name}\nCategoria: {message_kind}\nPrioridade: {priority}\nEnviado por: {sender_name} <{sender_email}>\n\nMensagem:\n" . wp_strip_all_tags($ctx['body']) . "\n\nCumprimentos,\nRoutesPro";
    $final_subject = '[Portal Cliente] ' . $ctx['subject'];
    $final_body = strtr($plain_body, $tokens);
    $html_body = '<p>Olá <strong>' . esc_html((string) ($recipient['display_name'] ?? 'equipa')) . '</strong>,</p>'
        . '<p>Recebeste uma nova mensagem do portal do cliente.</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:14px 0">'
        . '<tr><td style="padding:6px 0;color:#64748b">Cliente</td><td style="padding:6px 0"><strong>' . esc_html((string) ($client['name'] ?? '-')) . '</strong></td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b">Campanha</td><td style="padding:6px 0"><strong>' . esc_html((string) ($project['name'] ?? '-')) . '</strong></td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b">Categoria</td><td style="padding:6px 0">' . esc_html(ucfirst($ctx['message_kind'] ?: 'geral')) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b">Prioridade</td><td style="padding:6px 0">' . esc_html($ctx['priority']) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b">Enviado por</td><td style="padding:6px 0">' . esc_html((string) ($sender->display_name ?: $sender->user_login)) . ' &lt;' . esc_html((string) ($sender->user_email ?? '')) . '&gt;</td></tr>'
        . '</table>'
        . '<div style="padding:14px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc">' . \RoutesPro\Admin\Emails::format_body_html($ctx['body']) . '</div>'
        . routespro_render_attachment_links_html($attachmentsMeta);

    $headers = [];
    if (!empty($opts['send_as_html'])) $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $fromEmail = sanitize_email((string) ($opts['from_email'] ?? ''));
    $fromName = wp_specialchars_decode((string) ($opts['from_name'] ?? get_bloginfo('name')), ENT_QUOTES);
    if ($fromEmail) $headers[] = 'From: ' . ($fromName ?: get_bloginfo('name')) . ' <' . $fromEmail . '>';
    $replyToEmail = sanitize_email((string) ($sender->user_email ?? ''));
    $replyToName = wp_specialchars_decode((string) ($sender->display_name ?: $sender->user_login ?: ''), ENT_QUOTES);
    if ($replyToEmail) $headers[] = 'Reply-To: ' . ($replyToName ?: $replyToEmail) . ' <' . $replyToEmail . '>';
    elseif (!empty($opts['reply_to'])) $headers[] = 'Reply-To: ' . sanitize_email((string) $opts['reply_to']);

    $message_payload = !empty($opts['send_as_html'])
        ? \RoutesPro\Admin\Emails::render_message_html($final_subject, $html_body, [
            'brand_primary' => $opts['brand_primary'] ?? '',
            'footer_text' => $opts['footer_text'] ?? '',
            'button_label' => 'Abrir portal',
            'route_url' => $message_app_url,
        ])
        : $final_body;

    $mailRecipients = [$recipient_email];
    if (!empty($ctx['include_sender_copy'])) {
        $copyEmail = sanitize_email((string) ($sender->user_email ?? ''));
        if ($copyEmail && $copyEmail !== $recipient_email) $mailRecipients[] = $copyEmail;
    }
    $mailRecipients = array_values(array_unique(array_filter($mailRecipients)));
    $mailDispatch = \RoutesPro\Services\MailDispatchService::dispatch($mailRecipients, $final_subject, $message_payload, $headers, $attachmentPaths, ['source'=>'client_team_message','client_id'=>(int)$ctx['client_id'],'project_id'=>(int)$ctx['project_id'],'sender_user_id'=>(int)($sender->ID ?: get_current_user_id())]);
    $sent = !empty($mailDispatch['accepted']);
    $preMeta['mail_dispatch_id'] = (string)($mailDispatch['dispatch_id'] ?? '');
    $preMeta['mail_error'] = (string)($mailDispatch['error'] ?? '');
    $preMeta['app_url'] = $message_app_url;
    $wpdb->update($wpdb->prefix . 'routespro_email_logs', [
        'mail_result' => $sent ? 'sent' : 'failed',
        'meta_json' => wp_json_encode($preMeta),
    ], ['id' => $prelog_id], ['%s','%s'], ['%d']);

    if (!empty($ctx['include_sender_copy'])) {
        $copyEmail = sanitize_email((string) ($sender->user_email ?? ''));
        if ($copyEmail && $copyEmail !== $recipient_email) {
            \RoutesPro\Admin\Emails::log_email([
                'email_type' => 'client_team_message',
                'context_key' => 'portal_copy',
                'client_id' => $ctx['client_id'],
                'project_id' => $ctx['project_id'],
                'sender_user_id' => (int) ($sender->ID ?: get_current_user_id()),
                'recipient_user_id' => (int) ($sender->ID ?: 0),
                'recipient_email' => $copyEmail,
                'recipient_name' => (string) ($sender->display_name ?: $sender->user_login),
                'message_kind' => $ctx['message_kind'],
                'subject' => $final_subject,
                'body' => $ctx['body'],
                'meta' => array_merge($preMeta, ['copy_of_log_id' => $prelog_id]),
                'mail_result' => $sent ? 'sent' : 'failed',
            ]);
        }
    }

    return [
        'ok' => (bool) $sent,
        'log_id' => (int) $prelog_id,
        'recipient_user_id' => $recipient_user_id,
        'recipient_name' => (string) ($recipient['display_name'] ?? ''),
    ];
}



/**
 * Processa uma entrega individual de broadcast fora do pedido AJAX principal.
 * O job contém apenas dados serializáveis e os anexos já foram persistidos pelo upload inicial.
 */
add_action('routespro_process_client_team_broadcast_delivery', function ($job) {
    if (!is_array($job)) return;
    try {
        $sender = get_user_by('id', absint($job['sender_user_id'] ?? 0));
        $current_user = get_user_by('id', absint($job['submitted_by_user_id'] ?? 0));
        if (!$sender || !($sender instanceof \WP_User) || !$sender->exists()) return;
        if (!$current_user || !($current_user instanceof \WP_User) || !$current_user->exists()) $current_user = $sender;
        $recipient = is_array($job['recipient'] ?? null) ? $job['recipient'] : [];
        $ctx = [
            'client_id' => absint($job['client_id'] ?? 0),
            'project_id' => absint($job['project_id'] ?? 0),
            'message_kind' => sanitize_key((string)($job['message_kind'] ?? 'geral')),
            'subject' => sanitize_text_field((string)($job['subject'] ?? '')),
            'body' => wp_kses_post((string)($job['body'] ?? '')),
            'priority' => sanitize_text_field((string)($job['priority'] ?? 'normal')),
            'attachments_meta' => is_array($job['attachments_meta'] ?? null) ? $job['attachments_meta'] : [],
            'attachment_paths' => array_values(array_filter(array_map('strval', is_array($job['attachment_paths'] ?? null) ? $job['attachment_paths'] : []), static function($path){ return $path !== '' && is_file($path); })),
            'sender' => $sender,
            'current_user' => $current_user,
            'client' => is_array($job['client'] ?? null) ? $job['client'] : [],
            'project' => is_array($job['project'] ?? null) ? $job['project'] : [],
            'opts' => \RoutesPro\Admin\Emails::get(),
            'broadcast_id' => sanitize_text_field((string)($job['broadcast_id'] ?? '')),
            'broadcast_total' => absint($job['broadcast_total'] ?? 1),
            'include_sender_copy' => false,
        ];
        routespro_send_client_team_message_delivery($ctx, $recipient);
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('[FieldFlow broadcast queue] ' . $e->getMessage());
    }
}, 10, 1);

add_action('wp_ajax_routespro_send_client_team_mail', function () {
    if (!is_user_logged_in()) wp_send_json(['message' => 'Sem sessão ativa.'], 403);
    check_ajax_referer('wp_rest', '_wpnonce');

    $client_id = absint($_POST['client_id'] ?? 0);
    $project_id = absint($_POST['project_id'] ?? 0);
    $current_user_id = get_current_user_id();
    $sender_user_id = absint($_POST['sender_user_id'] ?? 0);
    if (!$sender_user_id && $current_user_id > 0) $sender_user_id = $current_user_id;
    $recipient_raw = sanitize_text_field((string) ($_POST['recipient_user_id'] ?? ''));
    $broadcast = $recipient_raw === 'all';
    $recipient_user_id = $broadcast ? 0 : absint($recipient_raw);
    $message_kind = sanitize_key($_POST['message_kind'] ?? 'geral');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $body = wp_kses_post($_POST['message'] ?? '');
    $priority = sanitize_text_field($_POST['priority'] ?? 'normal');
    $send_copy = !empty($_POST['send_copy']);

    // Autoriza o contexto antes de aceitar qualquer ficheiro no servidor.
    if (!$project_id || !$sender_user_id || (!$broadcast && !$recipient_user_id) || $subject === '' || trim(wp_strip_all_tags($body)) === '') {
        wp_send_json(['message' => 'Preenche campanha, destinatário, assunto e mensagem.'], 422);
    }
    if (!routespro_user_can_use_client_team_messaging($current_user_id)) {
        wp_send_json(['message' => 'Sem permissão para enviar mensagens em nome do portal do cliente.'], 403);
    }
    $preflight_perm = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id);
    if (is_wp_error($preflight_perm)) wp_send_json(['message' => $preflight_perm->get_error_message()], 403);

    $uploadPack = routespro_collect_uploaded_attachments('attachments');
    if (!empty($uploadPack['error'])) wp_send_json(['message' => $uploadPack['error']], 422);
    $attachmentsMeta = is_array($uploadPack['items'] ?? null) ? $uploadPack['items'] : [];
    $attachmentPaths = is_array($uploadPack['paths'] ?? null) ? $uploadPack['paths'] : [];

    if (!$project_id || !$sender_user_id || (!$broadcast && !$recipient_user_id) || $subject === '' || trim(wp_strip_all_tags($body)) === '') {
        wp_send_json(['message' => 'Preenche campanha, destinatário, assunto e mensagem.'], 422);
    }

    $perm = \RoutesPro\Support\Permissions::assert_scope_or_error($client_id, $project_id);
    if (is_wp_error($perm)) wp_send_json(['message' => $perm->get_error_message()], 403);

    $allowed = \RoutesPro\Admin\Emails::get_team_recipients($client_id, $project_id);
    $allowedSenders = \RoutesPro\Admin\Emails::get_available_senders_for_user($client_id, $project_id, get_current_user_id());
    $senderChoice = null;
    foreach ($allowedSenders as $user) {
        if ($sender_user_id && (int) ($user['ID'] ?? 0) === $sender_user_id) { $senderChoice = $user; break; }
    }
    if (!$senderChoice && $sender_user_id) {
        $contextIds = array_fill_keys(\RoutesPro\Support\Permissions::get_associated_user_ids($client_id, $project_id), true);
        foreach ($allowed as $user) {
            $uid = (int) ($user['ID'] ?? 0);
            if ($uid === $sender_user_id && (!empty($contextIds[$uid]) || current_user_can('routespro_manage'))) {
                $senderChoice = $user;
                break;
            }
        }
    }
    if (!$senderChoice || empty($senderChoice['user_email'])) {
        wp_send_json(['message' => 'O owner remetente selecionado não está disponível para esta campanha.'], 422);
    }

    $eligible = [];
    foreach ($allowed as $user) {
        $uid = absint($user['ID'] ?? 0);
        if (!$uid || empty($user['user_email'])) continue;
        $wpUser = get_user_by('id', $uid);
        if (!$wpUser || !($wpUser instanceof \WP_User) || !$wpUser->exists()) continue;
        if (isset($wpUser->user_status) && (int) $wpUser->user_status !== 0) continue;
        $eligible[$uid] = $user;
    }

    if ($broadcast) {
        if (!$eligible) wp_send_json(['message' => 'Não existem membros ativos disponíveis nesta campanha.'], 422);
        $recipients = array_values($eligible);
    } else {
        if (empty($eligible[$recipient_user_id])) {
            wp_send_json(['message' => 'O owner destinatário selecionado não está disponível para esta campanha.'], 422);
        }
        $recipients = [$eligible[$recipient_user_id]];
    }

    global $wpdb;
    $px = $wpdb->prefix . 'routespro_';
    $client = $client_id ? ($wpdb->get_row($wpdb->prepare("SELECT id,name,email FROM {$px}clients WHERE id=%d", $client_id), ARRAY_A) ?: []) : [];
    $project = $wpdb->get_row($wpdb->prepare("SELECT id,name,client_id FROM {$px}projects WHERE id=%d", $project_id), ARRAY_A) ?: [];
    if (!$client_id && !empty($project['client_id'])) $client_id = (int) $project['client_id'];
    if (!$project) wp_send_json(['message' => 'Campanha inválida.'], 404);

    // Registry observe-only: nunca bloqueia o envio e não reescreve URLs.
    if ($attachmentsMeta && class_exists('\RoutesPro\Security\MediaRegistry')) {
        foreach ($attachmentsMeta as $attachment) {
            if (!is_array($attachment) || empty($attachment['url'])) continue;
            \RoutesPro\Security\MediaRegistry::registerUrl((string)$attachment['url'], [
                'owner_user_id' => $current_user_id,
                'client_id' => $client_id,
                'project_id' => $project_id,
                'category' => 'team_attachment',
                'source' => 'team_message',
            ]);
        }
    }

    $current_user = wp_get_current_user();
    $sender = get_user_by('id', (int) $senderChoice['ID']);
    if (!$sender || !($sender instanceof \WP_User) || !$sender->exists()) $sender = $current_user;

    $broadcast_id = $broadcast ? wp_generate_uuid4() : '';
    $dedupeTarget = $broadcast ? ('all:' . implode(',', array_map(static fn($u) => absint($u['ID'] ?? 0), $recipients))) : (string) $recipient_user_id;
    $dedupe_key = 'routespro_ctm_' . md5(get_current_user_id() . '|' . $project_id . '|' . $sender->ID . '|' . $dedupeTarget . '|' . strtolower($subject) . '|' . wp_strip_all_tags($body) . '|' . md5(wp_json_encode($attachmentsMeta)));
    if (get_transient($dedupe_key)) {
        wp_send_json(['ok' => true, 'message' => 'Mensagem já submetida há instantes. Evitámos duplicados.']);
    }
    set_transient($dedupe_key, 1, 30);

    $ctx = [
        'client_id' => $client_id,
        'project_id' => $project_id,
        'message_kind' => $message_kind,
        'subject' => $subject,
        'body' => $body,
        'priority' => $priority,
        'attachments_meta' => $attachmentsMeta,
        'attachment_paths' => $attachmentPaths,
        'sender' => $sender,
        'current_user' => $current_user,
        'client' => $client,
        'project' => $project,
        'opts' => \RoutesPro\Admin\Emails::get(),
        'broadcast_id' => $broadcast_id,
        'broadcast_total' => count($recipients),
        'include_sender_copy' => (!$broadcast && $send_copy),
    ];

    if ($broadcast) {
        $recipientEmails = [];
        $recipientLogs = [];
        $opts = \RoutesPro\Admin\Emails::get();
        $headers = [];
        if (!empty($opts['send_as_html'])) $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $fromEmail = sanitize_email((string)($opts['from_email'] ?? ''));
        $fromName = wp_specialchars_decode((string)($opts['from_name'] ?? get_bloginfo('name')), ENT_QUOTES);
        if ($fromEmail) $headers[] = 'From: ' . ($fromName ?: get_bloginfo('name')) . ' <' . $fromEmail . '>';
        $replyToEmail = sanitize_email((string)($sender->user_email ?? ''));
        $replyToName = wp_specialchars_decode((string)($sender->display_name ?: $sender->user_login ?: ''), ENT_QUOTES);
        if ($replyToEmail) $headers[] = 'Reply-To: ' . ($replyToName ?: $replyToEmail) . ' <' . $replyToEmail . '>';
        elseif (!empty($opts['reply_to'])) $headers[] = 'Reply-To: ' . sanitize_email((string)$opts['reply_to']);

        foreach ($recipients as $recipient) {
            $rid = absint($recipient['ID'] ?? 0);
            $remail = sanitize_email((string)($recipient['user_email'] ?? ''));
            if (!$rid || !$remail) continue;
            $recipientEmails[$remail] = $remail;
            $meta = [
                'priority' => $priority,
                'copied_to_sender' => false,
                'sender_email' => (string)($sender->user_email ?? ''),
                'selected_sender_user_id' => (int)$sender->ID,
                'selected_sender_email' => (string)($sender->user_email ?? ''),
                'submitted_by_user_id' => (int)$current_user->ID,
                'submitted_by_email' => (string)($current_user->user_email ?? ''),
                'workflow_status' => 'novo',
                'attachments' => $attachmentsMeta,
                'broadcast_id' => $broadcast_id,
                'broadcast_total' => count($recipients),
                'broadcast_recipient_user_id' => $rid,
                'transport' => 'single_mail_bcc',
            ];
            $logId = \RoutesPro\Admin\Emails::log_email([
                'email_type' => 'client_team_message',
                'context_key' => 'portal_broadcast',
                'client_id' => $client_id,
                'project_id' => $project_id,
                'sender_user_id' => (int)$sender->ID,
                'recipient_user_id' => $rid,
                'recipient_email' => $remail,
                'recipient_name' => (string)($recipient['display_name'] ?? ''),
                'message_kind' => $message_kind,
                'subject' => '[Portal Cliente] ' . $subject,
                'body' => $body,
                'meta' => $meta,
                'mail_result' => 'pending',
            ]);
            if ($logId) $recipientLogs[(int)$logId] = $meta;
        }

        $recipientEmails = array_values($recipientEmails);
        if (!$recipientEmails) {
            delete_transient($dedupe_key);
            wp_send_json(['ok'=>false,'message'=>'Não existem emails válidos para os membros selecionados.'], 422);
        }

        $to = array_shift($recipientEmails);
        foreach ($recipientEmails as $bccEmail) $headers[] = 'Bcc: ' . $bccEmail;
        if ($send_copy) {
            $copyEmail = sanitize_email((string)($sender->user_email ?? ''));
            if ($copyEmail && $copyEmail !== $to && !in_array($copyEmail, $recipientEmails, true)) $headers[] = 'Bcc: ' . $copyEmail;
        }

        $finalSubject = '[Portal Cliente] ' . $subject;
        $htmlBody = '<p>Olá equipa,</p>'
            . '<p>Recebeste uma nova mensagem do portal do cliente.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:14px 0">'
            . '<tr><td style="padding:6px 0;color:#64748b">Cliente</td><td style="padding:6px 0"><strong>' . esc_html((string)($client['name'] ?? '-')) . '</strong></td></tr>'
            . '<tr><td style="padding:6px 0;color:#64748b">Campanha</td><td style="padding:6px 0"><strong>' . esc_html((string)($project['name'] ?? '-')) . '</strong></td></tr>'
            . '<tr><td style="padding:6px 0;color:#64748b">Categoria</td><td style="padding:6px 0">' . esc_html(ucfirst($message_kind ?: 'geral')) . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#64748b">Prioridade</td><td style="padding:6px 0">' . esc_html($priority) . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#64748b">Enviado por</td><td style="padding:6px 0">' . esc_html((string)($sender->display_name ?: $sender->user_login)) . ' &lt;' . esc_html((string)($sender->user_email ?? '')) . '&gt;</td></tr>'
            . '</table>'
            . '<div style="padding:14px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc">' . \RoutesPro\Admin\Emails::format_body_html($body) . '</div>'
            . routespro_render_attachment_links_html($attachmentsMeta);
        $plainBody = "Olá equipa,\n\nRecebeste uma nova mensagem do portal do cliente.\n\nCliente: " . (string)($client['name'] ?? '-') . "\nCampanha: " . (string)($project['name'] ?? '-') . "\nCategoria: " . ucfirst($message_kind ?: 'geral') . "\nPrioridade: " . $priority . "\nEnviado por: " . (string)($sender->display_name ?: $sender->user_login) . " <" . (string)($sender->user_email ?? '') . ">\n\nMensagem:\n" . wp_strip_all_tags($body) . "\n";
        $messagePayload = !empty($opts['send_as_html'])
            ? \RoutesPro\Admin\Emails::render_message_html($finalSubject, $htmlBody, [
                'brand_primary' => $opts['brand_primary'] ?? '',
                'footer_text' => $opts['footer_text'] ?? '',
                'button_label' => 'Abrir portal',
                'route_url' => '',
            ])
            : $plainBody;

        $mailDispatch = \RoutesPro\Services\MailDispatchService::dispatch($to, $finalSubject, $messagePayload, $headers, $attachmentPaths, ['source'=>'client_team_broadcast','client_id'=>$client_id,'project_id'=>$project_id,'sender_user_id'=>(int)($sender->ID ?: get_current_user_id())]);
        $sent = !empty($mailDispatch['accepted']);

        foreach ($recipientLogs as $logId => $meta) {
            $wpdb->update($wpdb->prefix . 'routespro_email_logs', [
                'mail_result' => $sent ? 'sent' : 'failed',
                'meta_json' => wp_json_encode($meta),
            ], ['id'=>(int)$logId], ['%s','%s'], ['%d']);
        }

        if ($send_copy) {
            $copyEmail = sanitize_email((string)($sender->user_email ?? ''));
            if ($copyEmail) {
                \RoutesPro\Admin\Emails::log_email([
                    'email_type' => 'client_team_message',
                    'context_key' => 'portal_broadcast_copy',
                    'client_id' => $client_id,
                    'project_id' => $project_id,
                    'sender_user_id' => (int)$sender->ID,
                    'recipient_user_id' => (int)$sender->ID,
                    'recipient_email' => $copyEmail,
                    'recipient_name' => (string)($sender->display_name ?: $sender->user_login),
                    'message_kind' => $message_kind,
                    'subject' => $finalSubject,
                    'body' => $body,
                    'meta' => ['broadcast_id'=>$broadcast_id,'broadcast_total'=>count($recipients),'attachments'=>$attachmentsMeta,'copy'=>true],
                    'mail_result' => $sent ? 'sent' : 'failed',
                ]);
            }
        }

        if (!$sent) {
            delete_transient($dedupe_key);
            wp_send_json(['ok'=>false,'message'=>'O servidor de email recusou o broadcast. O anexo foi carregado, mas nenhuma entrega foi marcada como enviada.'], 502);
        }

        wp_send_json([
            'ok'=>true,
            'message'=>sprintf('Mensagem enviada para todos os %d membros da equipa.', count($recipients)),
            'broadcast'=>true,
            'broadcast_id'=>$broadcast_id,
            'recipient_count'=>count($recipients),
            'transport'=>'direct_bcc',
        ]);
    }

    $results = [];
    foreach ($recipients as $recipient) {
        try { $results[] = routespro_send_client_team_message_delivery($ctx, $recipient); }
        catch (\Throwable $e) {
            $results[] = ['ok'=>false,'recipient_user_id'=>absint($recipient['ID'] ?? 0),'recipient_name'=>(string)($recipient['display_name'] ?? ''),'message'=>'Falha técnica na entrega.'];
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('[FieldFlow team mail] ' . $e->getMessage());
        }
    }
    $sentCount = count(array_filter($results, static fn($r) => !empty($r['ok'])));
    $sent = $sentCount === 1;
    wp_send_json(['ok' => $sent, 'message' => $sent ? 'Mensagem enviada com sucesso.' : 'Falha ao enviar o email.']);
});




/**
 * Links de download temporários para anexos das mensagens de equipa.
 *
 * A app não deve navegar diretamente para um XLSX/DOCX dentro da WebView.
 * Estes links são assinados, de curta duração, e servem apenas ficheiros locais
 * que já pertencem ao registo de mensagem solicitado.
 */
if (!function_exists('routespro_team_attachment_local_path')) {
    function routespro_team_attachment_local_path(array $attachment): string {
        $url = esc_url_raw((string)($attachment['url'] ?? ''));
        if ($url === '') return '';
        $uploads = wp_upload_dir();
        if (!empty($uploads['error']) || empty($uploads['baseurl']) || empty($uploads['basedir'])) return '';
        $fileParts = wp_parse_url($url);
        $baseParts = wp_parse_url((string)$uploads['baseurl']);
        $filePath = rawurldecode((string)($fileParts['path'] ?? ''));
        $basePath = rtrim(rawurldecode((string)($baseParts['path'] ?? '')), '/');
        if ($filePath === '' || $basePath === '') return '';

        // A URL pública pode usar outro host (CDN/proxy) sem que o ficheiro deixe
        // de existir no uploads local. A autorização não depende do host da URL:
        // depende sempre do realpath final ficar estritamente dentro de basedir.
        $relative = '';
        if (strpos($filePath, $basePath . '/') === 0) {
            $relative = ltrim(substr($filePath, strlen($basePath)), '/');
        } else {
            $uploadsLeaf = trim((string)basename($basePath), '/');
            $marker = $uploadsLeaf !== '' ? ('/' . $uploadsLeaf . '/') : '';
            $markerPos = $marker !== '' ? strrpos($filePath, $marker) : false;
            if ($markerPos !== false) {
                $relative = ltrim(substr($filePath, $markerPos + strlen($marker)), '/');
            }
        }
        if ($relative === '' || strpos($relative, '..') !== false) return '';
        $candidate = wp_normalize_path(trailingslashit((string)$uploads['basedir']) . $relative);
        $baseReal = realpath((string)$uploads['basedir']);
        $fileReal = realpath($candidate);
        if (!$baseReal || !$fileReal || !is_file($fileReal)) return '';
        $baseReal = rtrim(wp_normalize_path($baseReal), '/') . '/';
        $fileRealNorm = wp_normalize_path($fileReal);
        if (strpos($fileRealNorm, $baseReal) !== 0) return '';
        return $fileReal;
    }
}

if (!function_exists('routespro_team_attachment_signature')) {
    function routespro_team_attachment_signature(int $messageId, string $slot, int $index, int $userId, int $expires): string {
        $payload = $messageId . '|' . $slot . '|' . $index . '|' . $userId . '|' . $expires;
        return hash_hmac('sha256', $payload, wp_salt('auth'));
    }
}

if (!function_exists('routespro_team_attachment_is_image')) {
    function routespro_team_attachment_is_image(array $attachment): bool {
        $type = strtolower(trim((string)($attachment['type'] ?? '')));
        if (strpos($type, 'image/') === 0) return true;
        $name = strtolower((string)($attachment['name'] ?? ($attachment['url'] ?? '')));
        $path = (string)(wp_parse_url($name, PHP_URL_PATH) ?: $name);
        return (bool)preg_match('/\.(?:avif|bmp|gif|jpe?g|png|webp|heic|heif)$/i', $path);
    }
}

if (!function_exists('routespro_team_attachment_download_url')) {
    function routespro_team_attachment_download_url(int $messageId, string $slot, int $index, int $userId, array $attachment, bool $inline = false): string {
        if ($messageId <= 0 || $userId <= 0 || !in_array($slot, ['attachments','last_reply_attachments'], true)) return '';
        if (routespro_team_attachment_local_path($attachment) === '') return '';
        if ($inline && !routespro_team_attachment_is_image($attachment)) $inline = false;
        $expires = time() + HOUR_IN_SECONDS;
        $token = routespro_team_attachment_signature($messageId, $slot, $index, $userId, $expires);
        // Since 2.2.313 team documents leave the WebView and are opened by the
        // system browser. Use admin-post as the canonical binary endpoint so the
        // response bypasses front-page cache/canonical/proxy transformations.
        // Keep the template_redirect endpoint below only for already-issued legacy
        // home_url() links.
        return add_query_arg([
            'action' => 'routespro_download_team_attachment',
            'routespro_team_attachment_download' => '1',
            'message_id' => $messageId,
            'slot' => $slot,
            'attachment' => $index,
            'uid' => $userId,
            'expires' => $expires,
            'token' => $token,
            'disposition' => $inline ? 'inline' : 'attachment',
        ], admin_url('admin-post.php'));
    }
}

if (!function_exists('routespro_enrich_team_message_downloads')) {
    function routespro_enrich_team_message_downloads(array $rows, int $userId): array {
        foreach ($rows as &$row) {
            $messageId = absint($row['id'] ?? 0);
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : json_decode((string)($row['meta_json'] ?? ''), true);
            if (!is_array($meta)) $meta = [];
            foreach (['attachments','last_reply_attachments'] as $slot) {
                $items = is_array($meta[$slot] ?? null) ? $meta[$slot] : [];
                foreach ($items as $index => &$attachment) {
                    if (!is_array($attachment)) continue;
                    if (!empty($attachment['url']) && class_exists('\RoutesPro\Security\MediaShadow')) {
                        \RoutesPro\Security\MediaShadow::observeUrl((string)$attachment['url'], 'team_message_render', [
                            'user_id' => $userId,
                            'explicit_allow' => true,
                            'explicit_reason' => 'message_participant',
                        ]);
                    }
                    $downloadUrl = routespro_team_attachment_download_url($messageId, $slot, (int)$index, $userId, $attachment, false);
                    if ($downloadUrl !== '') {
                        $attachment['download_url'] = $downloadUrl;
                        $attachment['download_name'] = sanitize_file_name((string)($attachment['name'] ?? 'anexo'));
                        $attachment['attachment_kind'] = routespro_team_attachment_is_image($attachment) ? 'image' : 'document';
                        if ($attachment['attachment_kind'] === 'image') {
                            $viewUrl = routespro_team_attachment_download_url($messageId, $slot, (int)$index, $userId, $attachment, true);
                            if ($viewUrl !== '') $attachment['view_url'] = $viewUrl;
                        }
                    }
                }
                unset($attachment);
                if ($items) $meta[$slot] = $items;
            }
            $row['meta'] = $meta;
        }
        unset($row);
        return $rows;
    }
}

if (!function_exists('routespro_stream_team_attachment')) {
    function routespro_stream_team_attachment(): void {
        $messageId = absint($_GET['message_id'] ?? 0);
        $slot = sanitize_key((string)($_GET['slot'] ?? 'attachments'));
        $index = max(0, (int)($_GET['attachment'] ?? 0));
        $userId = absint($_GET['uid'] ?? 0);
        $expires = (int)($_GET['expires'] ?? 0);
        $token = sanitize_text_field((string)($_GET['token'] ?? ''));
        if (!$messageId || !$userId || !$expires || $token === '' || !in_array($slot, ['attachments','last_reply_attachments'], true)) {
            status_header(400); exit;
        }
        if ($expires < time() || $expires > time() + (2 * HOUR_IN_SECONDS)) {
            status_header(403); exit;
        }
        $expected = routespro_team_attachment_signature($messageId, $slot, $index, $userId, $expires);
        if (!hash_equals($expected, $token)) {
            status_header(403); exit;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_email_logs';
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, client_id, project_id, route_id, sender_user_id, recipient_user_id, meta_json FROM {$table} WHERE id=%d LIMIT 1", $messageId), ARRAY_A);
        if (!$row) { status_header(404); exit; }
        $meta = json_decode((string)($row['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $items = is_array($meta[$slot] ?? null) ? $meta[$slot] : [];
        $attachment = $items[$index] ?? null;
        if (!is_array($attachment)) { status_header(404); exit; }
        $path = routespro_team_attachment_local_path($attachment);
        if ($path === '') { status_header(404); exit; }

        clearstatcache(true, $path);
        $size = @filesize($path);
        if ($size === false || $size < 1 || !is_readable($path)) { status_header(404); exit; }
        $fh = @fopen($path, 'rb');
        if (!$fh) { status_header(500); exit; }

        if (!empty($attachment['url']) && class_exists('\\RoutesPro\\Security\\MediaShadow')) {
            $isParticipant = $userId > 0 && in_array($userId, [absint($row['sender_user_id'] ?? 0), absint($row['recipient_user_id'] ?? 0)], true);
            \RoutesPro\Security\MediaShadow::observeUrl((string)$attachment['url'], 'team_attachment_download', [
                'user_id' => $userId,
                'explicit_allow' => $isParticipant,
                'explicit_reason' => $isParticipant ? 'signed_message_participant' : 'signed_link_context',
            ]);
        }

        $filename = sanitize_file_name((string)($attachment['name'] ?? basename($path)));
        if ($filename === '') $filename = sanitize_file_name(basename($path));
        $requestedDisposition = sanitize_key((string)($_GET['disposition'] ?? 'attachment'));
        $inline = $requestedDisposition === 'inline' && routespro_team_attachment_is_image($attachment);
        $disposition = $inline ? 'inline' : 'attachment';

        $declaredType = sanitize_text_field((string)($attachment['type'] ?? ''));
        if ($declaredType === '') {
            $checked = wp_check_filetype($filename);
            $declaredType = (string)($checked['type'] ?? '');
        }
        // Documents are intentionally served as octet-stream. This prevents web
        // server/proxy content transformations from corrupting ZIP-based formats
        // such as XLSX/DOCX while Content-Disposition keeps the real filename.
        $responseType = $inline && strpos(strtolower($declaredType), 'image/') === 0
            ? $declaredType
            : 'application/octet-stream';

        // Release any PHP/session/output state before emitting the binary response.
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) @session_write_close();
        @ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('zlib.output_compression', '0');
        if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
        while (ob_get_level() > 0) @ob_end_clean();
        foreach (['Content-Encoding','Transfer-Encoding','Content-Length','Content-Disposition','Content-Type'] as $headerName) {
            if (function_exists('header_remove')) @header_remove($headerName);
        }

        status_header(200);
        nocache_headers();
        header('Content-Type: ' . ($responseType ?: 'application/octet-stream'), true);
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename), true);
        header('Content-Length: ' . (string)$size, true);
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, nofollow, nosnippet', true);
        header('Content-Transfer-Encoding: binary', true);
        header('X-FieldFlow-Team-Attachment: 1', true);
        header('X-FieldFlow-Download-Transport: admin-post', true);
        header('X-FieldFlow-File-Size: ' . (string)$size, true);

        // fpassthru avoids the previous manual flush loop, which could interact
        // badly with FastCGI/proxy buffering while a fixed Content-Length was set.
        @fpassthru($fh);
        fclose($fh);
        exit;
    }
}

add_action('admin_post_routespro_download_team_attachment', 'routespro_stream_team_attachment');
add_action('admin_post_nopriv_routespro_download_team_attachment', 'routespro_stream_team_attachment');
add_action('template_redirect', static function (): void {
    if (empty($_GET['routespro_team_attachment_download'])) return;
    routespro_stream_team_attachment();
}, 0);

add_action('wp_ajax_routespro_get_team_messages', function () {
    if (!is_user_logged_in()) wp_send_json(['message' => 'Sem sessão ativa.'], 403);
    check_ajax_referer('wp_rest', '_wpnonce');
    $filters = [
        'client_id' => absint($_GET['client_id'] ?? 0),
        'project_id' => absint($_GET['project_id'] ?? 0),
        'status' => sanitize_text_field($_GET['status'] ?? ''),
        'message_id' => absint($_GET['message_id'] ?? 0),
        'recipient_user_id' => absint($_GET['recipient_user_id'] ?? 0),
        'sender_user_id' => absint($_GET['sender_user_id'] ?? 0),
        'participant_user_id' => absint($_GET['participant_user_id'] ?? 0),
        'only_direct' => !empty($_GET['only_direct']) ? 1 : 0,
    ];
    $rows = \RoutesPro\Admin\Emails::get_user_message_logs(get_current_user_id(), $filters);
    $rows = routespro_enrich_team_message_downloads($rows, get_current_user_id());
    wp_send_json(['ok' => true, 'items' => $rows]);
});


add_action('wp_ajax_routespro_update_team_message', function () {
    if (!is_user_logged_in()) wp_send_json(['message' => 'Sem sessão ativa.'], 403);
    check_ajax_referer('wp_rest', '_wpnonce');
    global $wpdb;
    $table = $wpdb->prefix . 'routespro_email_logs';
    $log_id = absint($_POST['log_id'] ?? 0);
    $action = sanitize_key($_POST['message_action'] ?? '');
    if (!$log_id || !$action) wp_send_json(['message' => 'Pedido inválido.'], 422);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $log_id), ARRAY_A);
    if (!$row) wp_send_json(['message' => 'Mensagem não encontrada.'], 404);
    $uid = get_current_user_id();
    if ((int)($row['recipient_user_id'] ?? 0) !== $uid && (int)($row['sender_user_id'] ?? 0) !== $uid && !current_user_can('routespro_manage')) wp_send_json(['message' => 'Sem permissão para esta mensagem.'], 403);
    $meta = json_decode((string)($row['meta_json'] ?? ''), true);
    if (!is_array($meta)) $meta = [];
    if ($action === 'status') {
        $status = sanitize_text_field($_POST['status'] ?? 'novo');
        $meta['workflow_status'] = $status;
        if ($status === 'concluido') $meta['closed_at'] = current_time('mysql');
        $meta['last_action_at'] = current_time('mysql');
        $wpdb->update($table, ['meta_json' => wp_json_encode($meta)], ['id' => $log_id], ['%s'], ['%d']);
        wp_send_json(['ok' => true, 'message' => 'Estado atualizado.']);
    }
    if ($action === 'reply') {
        $reply = wp_kses_post($_POST['reply_message'] ?? '');
        if (trim(wp_strip_all_tags($reply)) === '') wp_send_json(['message' => 'Escreve uma resposta.'], 422);
        $uploadPack = routespro_collect_uploaded_attachments('attachments');
        if (!empty($uploadPack['error'])) wp_send_json(['message' => $uploadPack['error']], 422);
        $attachmentsMeta = is_array($uploadPack['items'] ?? null) ? $uploadPack['items'] : [];
        $attachmentPaths = is_array($uploadPack['paths'] ?? null) ? $uploadPack['paths'] : [];
        $target_user = get_user_by('id', (int)($row['sender_user_id'] ?? 0));
        $target_email = sanitize_email((string)($target_user->user_email ?? ''));
        if ($target_email === '') {
            $submitted = get_userdata((int)($meta['submitted_by_user_id'] ?? 0));
            $target_email = sanitize_email((string)($submitted->user_email ?? ''));
        }
        if ($target_email === '') wp_send_json(['message' => 'Não foi possível encontrar o email de resposta.'], 422);
        $sender = wp_get_current_user();
        $opts = \RoutesPro\Admin\Emails::get();
        $subject = '[Operação] Re: ' . sanitize_text_field((string)($row['subject'] ?? 'Mensagem'));
        $bodyHtml = '<p>Olá,</p><p>Recebeste uma resposta operacional a uma mensagem do portal.</p><div style="padding:14px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc">' . \RoutesPro\Admin\Emails::format_body_html($reply) . '</div>' . routespro_render_attachment_links_html($attachmentsMeta);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $fromEmail = sanitize_email((string)($opts['from_email'] ?? ''));
        $fromName = wp_specialchars_decode((string)($opts['from_name'] ?? get_bloginfo('name')), ENT_QUOTES);
        if ($fromEmail) $headers[] = 'From: ' . ($fromName ?: get_bloginfo('name')) . ' <' . $fromEmail . '>';
        if (!empty($sender->user_email)) $headers[] = 'Reply-To: ' . wp_specialchars_decode((string)($sender->display_name ?: $sender->user_login), ENT_QUOTES) . ' <' . sanitize_email((string)$sender->user_email) . '>';
        $payload = \RoutesPro\Admin\Emails::render_message_html($subject, $bodyHtml, [
            'brand_primary' => $opts['brand_primary'] ?? '',
            'footer_text' => $opts['footer_text'] ?? '',
            'button_label' => 'Abrir mensagem',
            'route_url' => \RoutesPro\Admin\Emails::get_message_app_url($log_id),
        ]);
        $mailDispatch = \RoutesPro\Services\MailDispatchService::dispatch($target_email, $subject, $payload, $headers, $attachmentPaths, ['source'=>'client_team_message_reply','client_id'=>(int)($row['client_id']??0),'project_id'=>(int)($row['project_id']??0),'route_id'=>(int)($row['route_id']??0),'sender_user_id'=>$uid]);
        $sent = !empty($mailDispatch['accepted']);
        $meta['mail_dispatch_id'] = (string)($mailDispatch['dispatch_id'] ?? '');
        $meta['mail_error'] = (string)($mailDispatch['error'] ?? '');
        $meta['workflow_status'] = $sent ? 'respondido' : ($meta['workflow_status'] ?? 'novo');
        $meta['last_reply_at'] = current_time('mysql');
        $meta['last_reply_by_user_id'] = $uid;
        $meta['last_reply_excerpt'] = wp_trim_words(wp_strip_all_tags($reply), 18, '...');
        if ($attachmentsMeta) $meta['last_reply_attachments'] = $attachmentsMeta;
        $wpdb->update($table, ['meta_json' => wp_json_encode($meta)], ['id' => $log_id], ['%s'], ['%d']);
        \RoutesPro\Admin\Emails::log_email([
            'email_type' => 'client_team_message_reply',
            'context_key' => 'routespro_app',
            'client_id' => (int)($row['client_id'] ?? 0),
            'project_id' => (int)($row['project_id'] ?? 0),
            'route_id' => (int)($row['route_id'] ?? 0),
            'sender_user_id' => $uid,
            'recipient_user_id' => (int)($row['sender_user_id'] ?? 0),
            'recipient_email' => $target_email,
            'recipient_name' => (string)($target_user->display_name ?? $target_email),
            'message_kind' => 'reply',
            'subject' => $subject,
            'body' => $reply,
            'meta' => ['parent_log_id' => $log_id, 'attachments' => $attachmentsMeta],
            'mail_result' => $sent ? 'sent' : 'failed',
        ]);
        wp_send_json(['ok' => (bool)$sent, 'message' => $sent ? 'Resposta enviada com sucesso.' : 'Falha ao enviar a resposta.']);
    }
    wp_send_json(['message' => 'Ação inválida.'], 422);
});

add_action('wp_ajax_routespro_front_clients', function () {
    if (!is_user_logged_in()) wp_send_json([]);
    check_ajax_referer('wp_rest', '_wpnonce');
    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $rows = $wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
    $rows = \RoutesPro\Support\Permissions::filter_clients($rows);
    wp_send_json(array_values($rows));
});

add_action('wp_ajax_routespro_front_projects', function () {
    if (!is_user_logged_in()) wp_send_json([]);
    check_ajax_referer('wp_rest', '_wpnonce');
    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $client_id = absint($_GET['client_id'] ?? 0);
    $where = $client_id ? $wpdb->prepare(" WHERE client_id=%d", $client_id) : '';
    $rows = $wpdb->get_results("SELECT id,client_id,name FROM {$px}projects" . $where . " ORDER BY name ASC", ARRAY_A) ?: [];
    $rows = \RoutesPro\Support\Permissions::filter_projects($rows);
    wp_send_json(array_values($rows));
});

/**
 * Mobile Analytics export tickets.
 *
 * The WebView creates the report while the WordPress session is available,
 * then hands a short-lived opaque URL to the operating system. This avoids
 * asking the native downloader/system browser to carry WordPress cookies.
 */
if (!function_exists('routespro_dashboard_export_ticket_key')) {
    function routespro_dashboard_export_ticket_key(string $ticket): string {
        return 'ff_dash_export_' . substr(hash('sha256', $ticket), 0, 40);
    }
}

if (!function_exists('routespro_dashboard_export_ticket_url')) {
    function routespro_dashboard_export_ticket_url(string $ticket): string {
        return add_query_arg([
            'fieldflow_dashboard_export_download' => '1',
            'ticket' => $ticket,
        ], home_url('/'));
    }
}

add_action('wp_ajax_routespro_create_dashboard_export_ticket', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Sem sessão ativa.'], 403);
    }
    $nonce = sanitize_text_field((string)($_POST['_wpnonce'] ?? ''));
    if ($nonce === '' || !wp_verify_nonce($nonce, 'routespro_export_dashboard')) {
        wp_send_json_error(['message' => 'Pedido de exportação inválido.'], 403);
    }
    if (!current_user_can('routespro_manage') && !\RoutesPro\Support\Permissions::can_access_front()) {
        wp_send_json_error(['message' => 'Sem permissões para exportar Analytics.'], 403);
    }
    if (!class_exists('\RoutesPro\Admin\FormSubmissions')) {
        wp_send_json_error(['message' => 'O motor de exportação não está disponível.'], 500);
    }

    $allowed = [
        'format','from','to','client_id','project_id','user_id','location_id',
        'category','product','role','analytics_audience','self_mode',
    ];
    $request = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $_POST)) $request[$key] = wp_unslash($_POST[$key]);
    }

    try {
        $file = \RoutesPro\Admin\FormSubmissions::createDashboardExportTicketFile($request);
        $path = (string)($file['path'] ?? '');
        if ($path === '' || !is_file($path) || (int)@filesize($path) < 1) {
            throw new \RuntimeException('O ficheiro de exportação não foi criado.');
        }
        $ticket = bin2hex(random_bytes(32));
        $expires = time() + (10 * MINUTE_IN_SECONDS);
        $payload = [
            'path' => $path,
            'filename' => sanitize_file_name((string)($file['filename'] ?? basename($path))),
            'mime' => sanitize_text_field((string)($file['mime'] ?? 'application/octet-stream')),
            'format' => sanitize_key((string)($file['format'] ?? '')),
            'user_id' => get_current_user_id(),
            'expires' => $expires,
        ];
        if ($payload['filename'] === '') $payload['filename'] = 'fieldflow-export';
        $stored = set_transient(routespro_dashboard_export_ticket_key($ticket), $payload, 10 * MINUTE_IN_SECONDS);
        if (!$stored) {
            @unlink($path);
            throw new \RuntimeException('Não foi possível criar o ticket de download.');
        }
        if (class_exists('\RoutesPro\Core\AuditLog')) {
            try {
                \RoutesPro\Core\AuditLog::record('analytics_export_ticket_created', 'analytics_export', 0, null, null, [
                    'format' => $payload['format'],
                    'user_id' => $payload['user_id'],
                    'expires_at' => gmdate('c', $expires),
                ]);
            } catch (\Throwable $e) {}
        }
        wp_send_json_success([
            'url' => routespro_dashboard_export_ticket_url($ticket),
            'filename' => $payload['filename'],
            'format' => $payload['format'],
            'expires' => $expires,
        ]);
    } catch (\Throwable $e) {
        if (class_exists('\RoutesPro\Security\SecurityService')) {
            try {
                \RoutesPro\Security\SecurityService::record('analytics_export_ticket_failed', 'warning', [
                    'user_id' => get_current_user_id(),
                    'format' => sanitize_key((string)($request['format'] ?? '')),
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $ignored) {}
        }
        wp_send_json_error(['message' => $e->getMessage() ?: 'Não foi possível preparar a exportação.'], 500);
    }
});


/**
 * Route export tickets for Portal Cliente.
 * Uses the same opaque download transport as Analytics, but builds the route
 * export while the authenticated WordPress session and client scope exist.
 */
add_action('wp_ajax_routespro_create_route_export_ticket', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Sem sessão ativa.'], 403);
    }
    if (!class_exists('\\RoutesPro\\Admin\\Routes')) {
        wp_send_json_error(['message' => 'O motor de exportação de rotas não está disponível.'], 500);
    }

    try {
        $request = [];
        foreach (['_wpnonce','format','route_id','client_id','project_id','owner_user_id','date','date_from','date_to'] as $key) {
            if (array_key_exists($key, $_POST)) $request[$key] = wp_unslash($_POST[$key]);
        }
        $file = \RoutesPro\Admin\Routes::create_client_portal_export_ticket_file($request);
        $path = (string)($file['path'] ?? '');
        if ($path === '' || !is_file($path) || (int)@filesize($path) < 1) {
            throw new \RuntimeException('O ficheiro de exportação de rotas não foi criado.');
        }

        $ticket = bin2hex(random_bytes(32));
        $expires = time() + (10 * MINUTE_IN_SECONDS);
        $payload = [
            'path' => $path,
            'filename' => sanitize_file_name((string)($file['filename'] ?? basename($path))),
            'mime' => sanitize_text_field((string)($file['mime'] ?? 'application/octet-stream')),
            'format' => sanitize_key((string)($file['format'] ?? '')),
            'user_id' => get_current_user_id(),
            'expires' => $expires,
            'source' => 'client_routes',
        ];
        if ($payload['filename'] === '') $payload['filename'] = 'fieldflow-rotas';
        $stored = set_transient(routespro_dashboard_export_ticket_key($ticket), $payload, 10 * MINUTE_IN_SECONDS);
        if (!$stored) {
            @unlink($path);
            throw new \RuntimeException('Não foi possível criar o ticket de download das rotas.');
        }

        if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
            try {
                \RoutesPro\Core\AuditLog::record('client_route_export_ticket_created', 'route_export', 0, null, null, [
                    'format' => $payload['format'],
                    'user_id' => $payload['user_id'],
                    'expires_at' => gmdate('c', $expires),
                ]);
             } catch (\Throwable $e) {}
        }

        wp_send_json_success([
            'url' => routespro_dashboard_export_ticket_url($ticket),
            'filename' => $payload['filename'],
            'format' => $payload['format'],
            'expires' => $expires,
        ]);
     } catch (\Throwable $e) {
        if (class_exists('\\RoutesPro\\Security\\SecurityService')) {
            try {
                \RoutesPro\Security\SecurityService::record('client_route_export_ticket_failed', 'warning', [
                    'user_id' => get_current_user_id(),
                    'format' => sanitize_key((string)($_POST['format'] ?? '')),
                    'error' => $e->getMessage(),
                ]);
             } catch (\Throwable $ignored) {}
        }
        wp_send_json_error(['message' => $e->getMessage() ?: 'Não foi possível preparar a exportação de rotas.'], 500);
    }
});

if (!function_exists('routespro_stream_dashboard_export_ticket')) {
    function routespro_stream_dashboard_export_ticket(): void {
        $ticket = strtolower(sanitize_text_field((string)($_GET['ticket'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $ticket)) {
            status_header(400); exit;
        }
        $key = routespro_dashboard_export_ticket_key($ticket);
        $payload = get_transient($key);
        if (!is_array($payload)) {
            status_header(404); exit;
        }
        $expires = (int)($payload['expires'] ?? 0);
        $path = (string)($payload['path'] ?? '');
        if ($expires < time() || $expires > time() + (11 * MINUTE_IN_SECONDS)) {
            delete_transient($key);
            if ($path !== '' && is_file($path)) @unlink($path);
            status_header(403); exit;
        }
        if ($path === '' || !is_file($path)) {
            delete_transient($key);
            status_header(404); exit;
        }

        $filename = sanitize_file_name((string)($payload['filename'] ?? basename($path)));
        if ($filename === '') $filename = sanitize_file_name(basename($path));
        $mime = sanitize_text_field((string)($payload['mime'] ?? 'application/octet-stream'));
        if ($mime === '') $mime = 'application/octet-stream';
        clearstatcache(true, $path);
        $size = (int)@filesize($path);
        if ($size < 1) {
            delete_transient($key);
            @unlink($path);
            status_header(500); exit;
        }

        while (ob_get_level() > 0) @ob_end_clean();
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . $size);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, nosnippet');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fh = @fopen($path, 'rb');
        if (!$fh) { status_header(500); exit; }
        while (!feof($fh)) {
            $chunk = fread($fh, 1048576);
            if ($chunk === false) break;
            if ($chunk === '') continue;
            echo $chunk;
            flush();
        }
        fclose($fh);
        delete_transient($key);
        @unlink($path);
        exit;
    }
}

add_action('template_redirect', static function (): void {
    if (empty($_GET['fieldflow_dashboard_export_download'])) return;
    routespro_stream_dashboard_export_ticket();
}, -5);
