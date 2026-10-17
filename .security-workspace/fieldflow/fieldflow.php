<?php
/**
 * Plugin Name: FieldFlow
 * Description: Plataforma profissional de rotas, equipas de terreno e reporting para merchandisers, promotores, equipas comerciais e clientes.
 * Version: 2.2.659
 * Author: The Wild Theory
 * Text Domain: routes-pro
 */
if (!defined('ABSPATH')) exit;

define('FIELDFLOW_VERSION', '2.2.659');
// 9DW predecessor: define('FIELDFLOW_VERSION', '2.2.656');
// 9DV predecessor: define('FIELDFLOW_VERSION', '2.2.655');
// 9DS predecessor: define('FIELDFLOW_VERSION', '2.2.652');
// 9DR predecessor: define('FIELDFLOW_VERSION', '2.2.651');
// 9DQ predecessor: define('FIELDFLOW_VERSION', '2.2.650');
// 9DP predecessor: define('FIELDFLOW_VERSION', '2.2.649');
// 9DO predecessor: define('FIELDFLOW_VERSION', '2.2.648');
// 9DN predecessor: define('FIELDFLOW_VERSION', '2.2.647');
// 9DM predecessor: define('FIELDFLOW_VERSION', '2.2.646');
// 9DL predecessor: define('FIELDFLOW_VERSION', '2.2.645');
// 9DK predecessor: define('FIELDFLOW_VERSION', '2.2.644');
// 9DJ predecessor: define('FIELDFLOW_VERSION', '2.2.643');
// 9DI predecessor: define('FIELDFLOW_VERSION', '2.2.642');
// 9DG predecessor: define('FIELDFLOW_VERSION', '2.2.640');
// 9DF predecessor: define('FIELDFLOW_VERSION', '2.2.639');
// 9DE predecessor: define('FIELDFLOW_VERSION', '2.2.638');
// 9DD predecessor: define('FIELDFLOW_VERSION', '2.2.637');
// 9DC predecessor: define('FIELDFLOW_VERSION', '2.2.636');
// 9DB predecessor: define('FIELDFLOW_VERSION', '2.2.635');
// 9DA predecessor: define('FIELDFLOW_VERSION', '2.2.634');
// 9D9 predecessor: define('FIELDFLOW_VERSION', '2.2.633');
// 9D8 predecessor marker: define('FIELDFLOW_VERSION', '2.2.632');
// 9D7 predecessor marker: define('FIELDFLOW_VERSION', '2.2.631');
// 9D6 predecessor marker: define('FIELDFLOW_VERSION', '2.2.630');
// 9D5 predecessor marker: define('FIELDFLOW_VERSION', '2.2.629');
// 9D3 predecessor marker: define('FIELDFLOW_VERSION', '2.2.627');
// 9D2 predecessor marker: define('FIELDFLOW_VERSION', '2.2.626');
// 9D0A predecessor (uncertified candidate): define('FIELDFLOW_VERSION', '2.2.624');
// 9CX historical runtime marker: define('FIELDFLOW_VERSION', '2.2.620');
// 9CW historical runtime marker: define('FIELDFLOW_VERSION', '2.2.619');
// 9CV historical runtime marker: define('FIELDFLOW_VERSION', '2.2.618');
// 9CU historical package marker: Version: 2.2.617
// 9CU historical runtime marker: define('FIELDFLOW_VERSION', '2.2.617');
// 9CT historical package marker: Version: 2.2.616
// 9CT historical runtime marker: define('FIELDFLOW_VERSION', '2.2.616');
// 9CS historical package marker: Version: 2.2.615
// 9CS historical runtime marker: define('FIELDFLOW_VERSION', '2.2.615');
// 9CR predecessor marker: define('FIELDFLOW_VERSION', '2.2.614');
// 9CQ historical package marker: Version: 2.2.614
// 9CQ historical runtime marker: define('FIELDFLOW_VERSION', '2.2.614');
// 9CQ predecessor marker: define('FIELDFLOW_VERSION', '2.2.613');
// 9CP historical package marker: Version: 2.2.613
// 9CP historical runtime marker: define('FIELDFLOW_VERSION', '2.2.613');
// 9CP predecessor marker: define('FIELDFLOW_VERSION', '2.2.612');
// 9CO historical package marker: Version: 2.2.612
// 9CO historical runtime marker: define('FIELDFLOW_VERSION', '2.2.612');
// 9CO predecessor marker: define('FIELDFLOW_VERSION', '2.2.611');
// 9CN historical package marker: Version: 2.2.611
// 9CN historical runtime marker: define('FIELDFLOW_VERSION', '2.2.611');
// 9CN predecessor marker: define('FIELDFLOW_VERSION', '2.2.610');
// 9CM historical package marker: Version: 2.2.610
// 9CM historical runtime marker: define('FIELDFLOW_VERSION', '2.2.610');
// 9CM predecessor marker: define('FIELDFLOW_VERSION', '2.2.609');
// 9CL historical package marker: Version: 2.2.609
// 9CL historical runtime marker: define('FIELDFLOW_VERSION', '2.2.609');
// 9CL predecessor marker: define('FIELDFLOW_VERSION', '2.2.608');
// 9CK historical package marker: Version: 2.2.608
// 9CK historical runtime marker: define('FIELDFLOW_VERSION', '2.2.608');
// 9CK predecessor marker: define('FIELDFLOW_VERSION', '2.2.607');
// 9CJ historical package marker: Version: 2.2.607
// 9CJ historical runtime marker: define('FIELDFLOW_VERSION', '2.2.607');
// 9CJ predecessor marker: define('FIELDFLOW_VERSION', '2.2.606');
// 9CI historical package marker: Version: 2.2.606
// 9CI historical runtime marker: define('FIELDFLOW_VERSION', '2.2.606');
// 9CI predecessor marker: define('FIELDFLOW_VERSION', '2.2.605');
// 9CH historical package marker: Version: 2.2.605
// 9CH historical runtime marker: define('FIELDFLOW_VERSION', '2.2.605');
// 9CH predecessor marker: define('FIELDFLOW_VERSION', '2.2.604');
// 9CG historical package marker: Version: 2.2.604
// 9CG historical runtime marker: define('FIELDFLOW_VERSION', '2.2.604');
// 9CG predecessor marker: define('FIELDFLOW_VERSION', '2.2.603');
// 9CF historical package marker: Version: 2.2.603
// 9CF historical runtime marker: define('FIELDFLOW_VERSION', '2.2.603');
// 9CF predecessor marker: define('FIELDFLOW_VERSION', '2.2.602');
// 9CE historical package marker: Version: 2.2.602
// 9CE historical runtime marker: define('FIELDFLOW_VERSION', '2.2.602');
// 9CE predecessor marker: define('FIELDFLOW_VERSION', '2.2.601');
// 9CD historical package marker: Version: 2.2.601
// 9CD predecessor marker: define('FIELDFLOW_VERSION', '2.2.600');
// 9CC historical package marker: Version: 2.2.600
// 9CC predecessor marker: define('FIELDFLOW_VERSION', '2.2.599');
// 9BZ predecessor marker: define('FIELDFLOW_VERSION', '2.2.597');
// 9BY predecessor marker: define('FIELDFLOW_VERSION', '2.2.596');
define('FIELDFLOW_PATH', plugin_dir_path(__FILE__));
define('FIELDFLOW_URL', plugin_dir_url(__FILE__));

// Compatibilidade retroativa para instalações existentes e código legado.
if (!defined('ROUTESPRO_VERSION')) define('ROUTESPRO_VERSION', FIELDFLOW_VERSION);
if (!defined('ROUTESPRO_PATH')) define('ROUTESPRO_PATH', FIELDFLOW_PATH);
if (!defined('ROUTESPRO_URL')) define('ROUTESPRO_URL', FIELDFLOW_URL);

require_once ROUTESPRO_PATH . 'src/Support/Loader.php';
\RoutesPro\Support\Loader::registerAutoload();

register_activation_hook(__FILE__, ['RoutesPro\Activator','activate']);
register_deactivation_hook(__FILE__, ['RoutesPro\Cloud\LicenseInstallationLifecycle','deactivateSchedule']);

// 2.2.562: WordPress Media Library isolation. Core image uploads and image-editor
// requests do not need the FieldFlow operational runtime. Returning before the
// bootstrap keeps GD/Imagick memory headroom available for WordPress sub-size
// generation and prevents FieldFlow upload policies from touching foreign media.
if (\RoutesPro\Support\Plugin::isWordPressMediaProcessingRequest()) {
    return;
}

\RoutesPro\Support\Loader::requireBootstrapFiles();
\RoutesPro\Support\Plugin::bootstrapUtf8Runtime();
\RoutesPro\Support\Plugin::registerWordPressHooks();


add_action('admin_post_routespro_download_commercial_template', function(){
    if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
    $headers = ['name','address','district','county','city','parish','postal_code','country','category','subcategory','contact_person','phone','email','website','lat','lng','external_ref','place_id','source'];
    $sample = ['Exemplo PDV','Rua Exemplo 123','Lisboa','Lisboa','Lisboa','','1000-100','Portugal','Horeca','Restaurante','Joao Silva','910000000','geral@example.com','https://example.com','38.7223','-9.1393','REF-001','','csv'];
    $csv = implode(',', $headers) . "
" . implode(',', array_map(function($v){ return '"' . str_replace('"', '""', $v) . '"'; }, $sample)) . "
";
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="routespro-commercial-template.csv"');
    echo $csv;
    exit;
});


add_action('admin_post_routespro_export_commercial_existing', function(){
    if (!current_user_can('routespro_manage')) wp_die('Forbidden');
    global $wpdb; $px = $wpdb->prefix . 'routespro_';
    $client_id = absint($_GET['client_id'] ?? 0);
    $project_id = absint($_GET['project_id'] ?? 0);
    if (!$client_id && $project_id) $client_id = absint($wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$px}projects WHERE id=%d", $project_id)));
    if (class_exists('\RoutesPro\SaaS\EntitlementService')) {
        if ($client_id > 0 && !\RoutesPro\SaaS\EntitlementService::clientHas($client_id, 'commercial.view')) wp_die('A Base Comercial não está incluída no plano deste cliente.', 'FieldFlow', ['response'=>403,'back_link'=>true]);
        \RoutesPro\SaaS\EntitlementService::assertExportOrDie('csv', $client_id, true);
    }
    $sql = "SELECT DISTINCT l.*, c.name AS category_name, sc.name AS subcategory_name, cl.assigned_to AS owner_user_id, owner.display_name AS owner_name FROM {$px}locations l LEFT JOIN {$px}categories c ON c.id=l.category_id LEFT JOIN {$px}categories sc ON sc.id=l.subcategory_id LEFT JOIN {$px}campaign_locations cl ON cl.location_id=l.id LEFT JOIN {$wpdb->users} owner ON owner.ID=cl.assigned_to LEFT JOIN {$px}projects p ON p.id=cl.project_id WHERE 1=1";
    $args = [];
    if ($project_id) { $sql .= " AND cl.project_id=%d"; $args[] = $project_id; } elseif ($client_id) { $sql .= " AND p.client_id=%d"; $args[] = $client_id; }
    $sql .= " ORDER BY l.id DESC";
    $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : ($wpdb->get_results($sql, ARRAY_A) ?: []);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="routespro-commercial-existing.csv"');
    $out = fopen('php://output', 'w');
    $headers = ['id','name','address','district','county','city','parish','postal_code','country','category','subcategory','contact_person','phone','email','website','lat','lng','external_ref','place_id','source','owner_user_id','owner_nome','is_active'];
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'] ?? '', $r['name'] ?? '', $r['address'] ?? '', $r['district'] ?? '', $r['county'] ?? '', $r['city'] ?? '', $r['parish'] ?? '', $r['postal_code'] ?? '', $r['country'] ?? '',
            $r['category_name'] ?? '', $r['subcategory_name'] ?? '', $r['contact_person'] ?? '', $r['phone'] ?? '', $r['email'] ?? '', $r['website'] ?? '', $r['lat'] ?? '', $r['lng'] ?? '',
            $r['external_ref'] ?? '', $r['place_id'] ?? '', $r['source'] ?? '', $r['owner_user_id'] ?? '', $r['owner_name'] ?? '', $r['is_active'] ?? 1
        ]);
    }
    fclose($out); exit;
});


function fieldflow_public_search_permission() {
    if (!is_user_logged_in()) {
        return new \WP_Error('fieldflow_search_auth_required', 'É necessário iniciar sessão para pesquisar PDVs.', ['status' => 401]);
    }
    $rate_identity = get_current_user_id() . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rate_key = 'fieldflow_public_search_' . md5($rate_identity);
    $hits = (int) get_transient($rate_key);
    if ($hits >= 60) {
        return new \WP_Error('fieldflow_rate_limited', 'Demasiados pedidos. Tenta novamente dentro de momentos.', ['status' => 429]);
    }
    set_transient($rate_key, $hits + 1, MINUTE_IN_SECONDS);
    return true;
}

// FieldFlow autocomplete endpoint
add_action('rest_api_init', function () {
    register_rest_route('fieldflow/v1', '/pdvs-search', [
        'methods' => 'GET',
        'callback' => function($request){
            global $wpdb;
            $q = sanitize_text_field((string) $request->get_param('q'));
            $q = trim($q);
            if (function_exists('mb_strlen') ? mb_strlen($q) < 2 : strlen($q) < 2) {
                return new \WP_Error('fieldflow_query_too_short', 'Pesquisa demasiado curta.', ['status' => 400]);
            }

            $table = $wpdb->prefix . 'ff_pdvs';
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($table_exists !== $table) {
                return [];
            }

            $like = '%' . $wpdb->esc_like($q) . '%';
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, nome, cidade FROM {$table} WHERE nome LIKE %s ORDER BY nome ASC LIMIT 10",
                    $like
                ),
                ARRAY_A
            );

            return is_array($results) ? $results : [];
        },
        'permission_callback' => 'fieldflow_public_search_permission'
    ]);
});


// O autocomplete legado de PDVs (tabela ff_pdvs) fica desligado por defeito.
// 9CI fieldflow-legacy-pdv-autocomplete-retirement-v1: o plugin nunca cria a tabela
// ff_pdvs, por isso o script mostrava "Sem resultados" no Descobrir e na Base Comercial
// por cima do autocomplete real. Pode ser reativado com o filtro abaixo.
add_action('wp_enqueue_scripts', function(){
    if (!apply_filters('fieldflow_legacy_pdv_autocomplete_enabled', false)) return;
    if (!class_exists('\\RoutesPro\\Front\\ResponsiveAssets') || !\RoutesPro\Front\ResponsiveAssets::shouldLoadForRequest()) return;
    wp_enqueue_script('ff-autocomplete', plugin_dir_url(__FILE__).'assets/ff-autocomplete.js', [], FIELDFLOW_VERSION, true);
    wp_localize_script('ff-autocomplete', 'FieldFlowAutocomplete', [
        'endpoint' => rest_url('fieldflow/v1/pdvs-search'),
        'nonce' => wp_create_nonce('wp_rest'),
        'minChars' => 3,
    ]);
});
