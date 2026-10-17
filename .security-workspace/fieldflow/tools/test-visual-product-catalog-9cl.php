<?php
// 9CL runtime test: visual product catalog identity, Cardex sync, merge and briefing label mapping.
// Runs the real ProductCatalogService against an in-memory WordPress post/meta stub (no database, no bootstrap).
namespace {
    $root = dirname(__DIR__);
    define('ABSPATH', '/tmp/');
    define('ARRAY_A', 'ARRAY_A');
    $GLOBALS['ff9cl'] = ['posts' => [], 'meta' => [], 'next' => 100, 'options' => [], 'attachments' => [501 => true, 502 => true, 503 => true]];
    function ff9cl_fail(string $reason): void { fwrite(STDERR, "VISUAL PRODUCT CATALOG 9CL RUNTIME FAIL: {$reason}\n"); exit(1); }
    function ff9cl_assert(bool $ok, string $reason): void { if (!$ok) ff9cl_fail($reason); }
    function wp_strip_all_tags($v) { return strip_tags((string)$v); }
    function remove_accents($v) { return strtr((string)$v, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','É'=>'E','Ó'=>'O','Ç'=>'C']); }
    function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$v))); }
    function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$v)); }
    function absint($v) { return abs((int)$v); }
    function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }
    function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
    function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
    function current_time($t) { return '2026-10-08 10:00:00'; }
    function update_option($k, $v, $a = null) { $GLOBALS['ff9cl']['options'][$k] = $v; return true; }
    function get_option($k, $d = false) { return $GLOBALS['ff9cl']['options'][$k] ?? $d; }
    function post_type_exists($t) { return !empty($GLOBALS['ff9cl']['types'][$t]); }
    function register_post_type($t, $args) { $GLOBALS['ff9cl']['types'][$t] = $args; }
    function is_wp_error($v) { return false; }
    function wp_attachment_is_image($id) { return !empty($GLOBALS['ff9cl']['attachments'][(int)$id]); }
    function wp_get_attachment_image_src($id, $size) { return wp_attachment_is_image($id) ? ['https://example.test/img-' . (int)$id . '-' . $size . '.jpg', 150, 150] : false; }
    function wp_insert_post($data, $err = false) { $id = $GLOBALS['ff9cl']['next']++; $GLOBALS['ff9cl']['posts'][$id] = (object)['ID' => $id, 'post_type' => $data['post_type'], 'post_status' => $data['post_status'] ?? 'publish', 'post_title' => wp_unslash($data['post_title'] ?? '')]; return $id; }
    function wp_update_post($data) { $p = $GLOBALS['ff9cl']['posts'][(int)$data['ID']] ?? null; if ($p && isset($data['post_title'])) $p->post_title = wp_unslash($data['post_title']); return $p ? $p->ID : 0; }
    function wp_trash_post($id) { if (isset($GLOBALS['ff9cl']['posts'][$id])) $GLOBALS['ff9cl']['posts'][$id]->post_status = 'trash'; }
    function get_post($id) { return $GLOBALS['ff9cl']['posts'][(int)$id] ?? null; }
    function get_the_title($id) { return (string)(get_post($id)->post_title ?? ''); }
    function get_post_meta($id, $key = '', $single = false) { $values = $GLOBALS['ff9cl']['meta'][(int)$id][$key] ?? []; return $single ? ($values[0] ?? '') : $values; }
    function update_post_meta($id, $key, $value) { $GLOBALS['ff9cl']['meta'][(int)$id][$key] = [wp_unslash($value)]; return true; }
    function add_post_meta($id, $key, $value) { $GLOBALS['ff9cl']['meta'][(int)$id][$key][] = wp_unslash($value); return true; }
    function delete_post_meta($id, $key, $value = '') {
        if ($value === '') { unset($GLOBALS['ff9cl']['meta'][(int)$id][$key]); return true; }
        $GLOBALS['ff9cl']['meta'][(int)$id][$key] = array_values(array_filter($GLOBALS['ff9cl']['meta'][(int)$id][$key] ?? [], static function($v) use ($value) { return (string)$v !== (string)$value; }));
        return true;
    }
    function ff9cl_clause(int $id, array $c): bool {
        if (isset($c['relation']) || isset($c[0])) {
            $rel = strtoupper($c['relation'] ?? 'AND'); $results = [];
            foreach ($c as $k => $sub) if ($k !== 'relation' && is_array($sub)) $results[] = ff9cl_clause($id, $sub);
            return $rel === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
        }
        $values = array_map('strval', $GLOBALS['ff9cl']['meta'][$id][$c['key']] ?? []);
        $cmp = strtoupper($c['compare'] ?? (is_array($c['value'] ?? null) ? 'IN' : '='));
        if ($cmp === 'NOT EXISTS') return !$values;
        if ($cmp === 'IN') return (bool)array_intersect($values, array_map('strval', (array)$c['value']));
        if ($cmp === '!=') return (bool)$values && !in_array((string)$c['value'], $values, true);
        return in_array((string)$c['value'], $values, true);
    }
    function get_posts($args) {
        $status = (array)($args['post_status'] ?? 'publish'); $out = [];
        foreach ($GLOBALS['ff9cl']['posts'] as $id => $post) {
            if ($post->post_type !== ($args['post_type'] ?? '') || !in_array($post->post_status, $status, true)) continue;
            if (!empty($args['meta_query']) && !ff9cl_clause($id, $args['meta_query'])) continue;
            $out[] = $post;
        }
        usort($out, static function($a, $b) { return strcasecmp($a->post_title, $b->post_title); });
        $limit = (int)($args['numberposts'] ?? 5); if ($limit > 0) $out = array_slice($out, 0, $limit);
        return ($args['fields'] ?? '') === 'ids' ? array_map(static function($p) { return $p->ID; }, $out) : $out;
    }
    class WP_Query {
        public $posts = []; public $found_posts = 0; public $max_num_pages = 0;
        public function __construct($args) {
            $all = get_posts(['numberposts' => -1] + $args);
            if (!empty($args['s'])) $all = array_values(array_filter($all, static function($p) use ($args) { return stripos($p->post_title, $args['s']) !== false; }));
            $this->found_posts = count($all); $per = (int)$args['posts_per_page']; $this->max_num_pages = (int)ceil($this->found_posts / max(1, $per));
            $this->posts = array_slice($all, ($args['paged'] - 1) * $per, $per);
        }
    }
    class FF9CLWpdb {
        public $prefix = 'wp_'; public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts';
        public function prepare($sql, ...$args) { return ['sql' => $sql, 'args' => $args]; }
        public function get_var($q) {
            $sql = is_array($q) ? $q['sql'] : $q;
            if (strpos($sql, 'SHOW TABLES') !== false) return null; // no Stocks/briefing tables in this harness
            if (strpos($sql, 'routespro_projects') !== false) return ['77' => 9][(string)$q['args'][0]] ?? 0;
            return null;
        }
        public function get_results($q, $mode = null) {
            $sql = is_array($q) ? $q['sql'] : $q;
            if (strpos($sql, 'routespro_product_cardex_items') !== false) return $GLOBALS['ff9cl']['cardex_items'][(int)$q['args'][0]] ?? [];
            return [];
        }
    }
    $GLOBALS['wpdb'] = new FF9CLWpdb();
}

namespace RoutesPro\Forms {
    class ProductCardex {
        public static function install(): void {}
        public static function list_cardex(): array { return $GLOBALS['ff9cl']['cardex']; }
        public static function table_items(): string { return 'wp_routespro_product_cardex_items'; }
    }
}

namespace {
    use RoutesPro\Catalog\ProductCatalogService as C;
    require $root . '/src/Catalog/ProductCatalogService.php';

    // Two Cardex of the same client (one by client, one through a campaign) share Jameson; a third client is separate.
    $GLOBALS['ff9cl']['cardex'] = [
        ['id' => 1, 'client_id' => 9, 'project_id' => 0],
        ['id' => 2, 'client_id' => 0, 'project_id' => 77],
        ['id' => 3, 'client_id' => 12, 'project_id' => 0],
        ['id' => 4, 'client_id' => 0, 'project_id' => 0],
    ];
    $GLOBALS['ff9cl']['cardex_items'] = [
        1 => [
            ['id' => 11, 'category' => 'WHISKEY', 'reference' => '2050501', 'product_name' => 'JAMESON 70CL', 'capacity_cl' => '70'],
            ['id' => 12, 'category' => 'WHISKEY', 'reference' => '', 'product_name' => 'Jameson Black Barrel', 'capacity_cl' => '70'],
        ],
        2 => [
            ['id' => 21, 'category' => 'WHISKEY', 'reference' => '205-0501', 'product_name' => 'Jameson Original 70 cl', 'capacity_cl' => '70.0'],
            ['id' => 22, 'category' => 'WHISKEY', 'reference' => '', 'product_name' => 'JAMESON BLACK BARREL', 'capacity_cl' => 70],
        ],
        3 => [['id' => 31, 'category' => 'GIN', 'reference' => '2050501', 'product_name' => 'Outro cliente', 'capacity_cl' => '70']],
        4 => [['id' => 41, 'category' => 'GIN', 'reference' => 'X1', 'product_name' => 'Sem cliente', 'capacity_cl' => '70']],
    ];

    // 1. De-duplication: one product per client identity, independent of Cardex and store.
    // 9CP: one product per group of lines (EAN, code inside its insignia, word signature), so
    // five lines of client 9 and 12 make three products; the article code resolves while unique.
    $stats = C::syncFromCardex();
    ff9cl_assert($stats['items'] === 5 && $stats['groups'] === 3 && $stats['created'] === 3 && $stats['skipped_no_client'] === 1, 'sync_stats ' . json_encode($stats));
    $client9 = C::query(['client_id' => 9]);
    ff9cl_assert($client9['total'] === 2, 'dedupe_total');
    $jamesonId = C::resolveProductId(9, '2050501', '', null);
    $jameson = C::get($jamesonId);
    ff9cl_assert($jameson && $jameson['cardex_ids'] === [1, 2] && in_array(77, $jameson['project_ids'], true), 'dedupe_sources');
    ff9cl_assert($jamesonId > 0 && C::resolveProductId(12, '2050501', '', null) !== $jamesonId, 'client_scope');
    $dedupe = true;

    // 2. Manual enrichment survives a Cardex re-import ("Substituir" recreates items with new IDs).
    $saved = C::save($jamesonId, ['name' => 'Jameson Original 70cl', 'brand' => 'Jameson', 'reference' => '2050501', 'category' => 'Irish Whiskey', 'capacity_cl' => '70', 'kind' => 'own', 'aliases' => "Jameson Standard\nJameson \"Original\"", 'image_ids' => [501, 999, 502], 'project_ids' => [77], 'replace_projects' => true]);
    $GLOBALS['ff9cl']['cardex_items'][1][0]['id'] = 911;
    $GLOBALS['ff9cl']['cardex_items'][1][0]['product_name'] = 'JAMESON IRISH WHISKEY 70CL';
    C::syncFromCardex(9);
    $after = C::get($jamesonId);
    ff9cl_assert($saved === $jamesonId && $after['name'] === 'Jameson Original 70cl' && $after['brand'] === 'Jameson' && $after['category'] === 'Irish Whiskey', 'manual_fields');
    ff9cl_assert(array_column($after['images'], 'id') === [501, 502] && $after['ready_for_reading'], 'images_validated');
    ff9cl_assert(in_array('Jameson "Original"', $after['aliases'], true), 'aliases_slashes');
    ff9cl_assert(C::query(['client_id' => 9])['total'] === 2, 'stable_identity_after_reimport');
    $manualPreserved = true;

    // 3. Resolution used by fronts questions: reference, name+capacity and aliases.
    ff9cl_assert(C::resolveProductId(9, '2050501', 'qualquer', 70) === $jamesonId, 'resolve_reference');
    ff9cl_assert(C::resolveProductId(9, '', 'Jameson Standard', 70) === $jamesonId, 'resolve_alias');
    ff9cl_assert(C::resolveProductId(9, 'ZZZ', 'Jameson Black Barrel', '70') > 0, 'resolve_name_capacity');
    ff9cl_assert(C::resolveProductId(12, '', 'Jameson Standard', 70) === 0, 'resolve_scoped');
    ff9cl_assert(C::primaryThumb(C::resolveProduct(9, '2050501', '', 70)) === 'https://example.test/img-501-thumbnail.jpg', 'primary_thumb');
    $resolution = true;

    // 4. Competitor products and briefing label mapping (one label, several SKUs).
    $tullamore = C::save(0, ['client_id' => 9, 'name' => 'Tullamore D.E.W. 70cl', 'brand' => 'Tullamore', 'capacity_cl' => '70', 'kind' => 'competitor', 'origin' => 'briefing']);
    $tullamore1l = C::save(0, ['client_id' => 9, 'name' => 'Tullamore D.E.W. 1L', 'brand' => 'Tullamore', 'capacity_cl' => '100', 'kind' => 'competitor']);
    try { C::save(0, ['client_id' => 9, 'name' => 'tullamore d.e.w. 70 CL', 'capacity_cl' => '70']); ff9cl_fail('duplicate_create'); } catch (\RuntimeException $e) {}
    ff9cl_assert(C::summary(9)['competitors'] === 2, 'competitor_summary');
    ff9cl_assert(C::query(['client_id' => 9, 'kind' => 'competitor'])['total'] === 2 && C::query(['client_id' => 9, 'kind' => 'own'])['total'] === 2, 'kind_filter');
    ff9cl_assert(C::query(['client_id' => 9, 'images' => 'missing'])['total'] === 3 && C::query(['client_id' => 9, 'images' => 'ready'])['total'] === 1, 'images_filter');
    $suggest = C::suggestionsForLabel(9, 'Tullamore');
    ff9cl_assert(count($suggest) === 2 && $suggest[0]['kind'] === 'competitor', 'suggestions');
    ff9cl_assert(C::mapLabel(9, 'Tullamore', [$tullamore, $tullamore1l, $jamesonId + 100000]) === 2, 'map_label');
    ff9cl_assert(C::productIdsForLabel(9, ' TULLAMORE ') === [$tullamore1l, $tullamore] || C::productIdsForLabel(9, ' TULLAMORE ') === [$tullamore, $tullamore1l], 'label_lookup_normalized');
    ff9cl_assert(C::mapLabel(9, 'Tullamore', [$tullamore]) === 1 && C::productIdsForLabel(9, 'Tullamore') === [$tullamore], 'map_label_exact_set');
    ff9cl_assert(C::mapLabel(12, 'Tullamore', [$tullamore]) === 0, 'map_label_client_scope');
    $labelMapping = true;

    // 5. Merge keeps images, aliases, labels and identity keys on the survivor.
    $dup = C::save(0, ['client_id' => 9, 'name' => 'Tullamore Dew Original', 'kind' => 'competitor', 'image_ids' => [503]]);
    C::mapLabel(9, 'Tullamore Dew', [$dup]);
    C::merge($dup, $tullamore);
    $survivor = C::get($tullamore);
    ff9cl_assert(C::get($dup) === null && in_array(503, array_column($survivor['images'], 'id'), true), 'merge_images');
    ff9cl_assert(in_array('Tullamore Dew Original', $survivor['aliases'], true) && C::productIdsForLabel(9, 'Tullamore Dew') === [$tullamore], 'merge_labels');
    ff9cl_assert(C::resolveProductId(9, '', 'Tullamore Dew Original', null) === $tullamore, 'merge_identity');
    try { C::delete($jamesonId); ff9cl_fail('cardex_delete_guard'); } catch (\RuntimeException $e) {}
    C::delete($tullamore1l);
    ff9cl_assert(C::get($tullamore1l) === null, 'delete_manual');
    $merge = true;

    // 6. Project filter keeps client-wide (no campaign) products visible.
    $projectView = C::query(['client_id' => 9, 'project_id' => 77]);
    ff9cl_assert($projectView['total'] === 3, 'project_filter ' . $projectView['total']);

    // 7. Static contracts: private storage, guarded handlers, no schema change, historical continuity.
    $read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
    $service = $read('src/Catalog/ProductCatalogService.php');
    $admin = $read('src/Admin/ProductCatalog.php');
    $type = $GLOBALS['ff9cl']['types'][C::POST_TYPE] ?? [];
    $private = $type && $type['public'] === false && $type['show_ui'] === false && $type['show_in_rest'] === false && $type['publicly_queryable'] === false;
    ff9cl_assert($private, 'private_storage');
    $handlers = ['handle_sync' => "self::guard('fieldflow_catalog_sync')", 'handle_save' => "self::guard('fieldflow_catalog_save')", 'handle_merge' => "self::guard('fieldflow_catalog_merge_'", 'handle_map_label' => "self::guard('fieldflow_catalog_map_label')", 'handle_delete' => "check_admin_referer('fieldflow_catalog_delete_'"];
    foreach ($handlers as $method => $marker) {
        $a = strpos($admin, 'public static function ' . $method . '(');
        $b = $a === false ? false : strpos($admin, 'public static function', $a + 10);
        $body = $a === false ? '' : substr($admin, $a, ($b === false ? strlen($admin) : $b) - $a);
        ff9cl_assert($body !== '' && strpos($body, $marker) !== false, 'guarded_' . $method);
    }
    ff9cl_assert(strpos($admin, "if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');\n        check_admin_referer(\$action, 'fieldflow_catalog_nonce');") !== false, 'guard_capability');
    $noSchema = stripos($service . $admin, 'CREATE TABLE') === false && stripos($service . $admin, 'ALTER TABLE') === false && stripos($service . $admin, 'dbDelta') === false;
    ff9cl_assert($noSchema, 'no_schema');
    $registry = $read('src/Architecture/RuntimeRegistry.php');
    $cardexAdmin = $read('src/Admin/ProductCardex.php');
    $wired = strpos($registry, '\\RoutesPro\\Catalog\\ProductCatalogService::registerHooks();') !== false
        && strpos($registry, "add_action('admin_init', ['RoutesPro\\\\Admin\\\\ProductCatalog', 'register_hooks']);") !== false
        && strpos($cardexAdmin, "if (ProductCatalog::current_tab() !== 'cardex') { ProductCatalog::render(); return; }") !== false
        && strpos($read('src/Support/Loader.php'), "'src/Catalog/ProductCatalogService.php'") !== false;
    ff9cl_assert($wired, 'wired');
    $historical = strpos($read('src/Shortcodes.php'), 'fieldflow-planner-local-preview-cost-authority-v1') !== false
        && strpos($read('src/Shortcodes.php'), 'fieldflow-visit-closure-selector-return-v1') !== false
        && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false
        && strpos($cardexAdmin, "do_action('fieldflow_mapping_source_changed', 'cardex_import', 0);") !== false;
    ff9cl_assert($historical, 'historical_continuity');

    echo 'VISUAL PRODUCT CATALOG 9CL RUNTIME OK | dedupe=1 | manual_preserved=1 | resolution=1 | competitor_mapping=1 | merge=1 | private_storage=1 | guarded_admin=1 | no_schema=1 | historical_continuity=1' . "\n";
}
