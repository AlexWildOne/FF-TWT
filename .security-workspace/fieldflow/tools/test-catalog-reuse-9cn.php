<?php
// 9CN runtime test: catalog reuse across clients (copies with shared images, no duplicates, refresh from source).
// Runs the real ProductCatalogService against an in-memory WordPress post/meta stub (no database, no bootstrap).
namespace {
    $root = dirname(__DIR__);
    define('ABSPATH', '/tmp/');
    define('ARRAY_A', 'ARRAY_A');
    $GLOBALS['ff9cn'] = ['posts' => [], 'meta' => [], 'next' => 100, 'options' => [], 'attachments' => [501 => true, 502 => true, 503 => true, 504 => true]];
    function ff9cn_fail(string $reason): void { fwrite(STDERR, "CATALOG CROSS CLIENT REUSE 9CN RUNTIME FAIL: {$reason}\n"); exit(1); }
    function ff9cn_assert(bool $ok, string $reason): void { if (!$ok) ff9cn_fail($reason); }
    function wp_strip_all_tags($v) { return strip_tags((string)$v); }
    function remove_accents($v) { return strtr((string)$v, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','É'=>'E','Ó'=>'O','Ç'=>'C']); }
    function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$v))); }
    function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$v)); }
    function absint($v) { return abs((int)$v); }
    function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }
    function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
    function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
    function current_time($t) { return '2026-10-08 10:00:00'; }
    function update_option($k, $v, $a = null) { $GLOBALS['ff9cn']['options'][$k] = $v; return true; }
    function get_option($k, $d = false) { return $GLOBALS['ff9cn']['options'][$k] ?? $d; }
    function post_type_exists($t) { return !empty($GLOBALS['ff9cn']['types'][$t]); }
    function register_post_type($t, $args) { $GLOBALS['ff9cn']['types'][$t] = $args; }
    function is_wp_error($v) { return false; }
    function wp_attachment_is_image($id) { return !empty($GLOBALS['ff9cn']['attachments'][(int)$id]); }
    function wp_get_attachment_image_src($id, $size) { return wp_attachment_is_image($id) ? ['https://example.test/img-' . (int)$id . '-' . $size . '.jpg', 150, 150] : false; }
    function wp_insert_post($data, $err = false) { $id = $GLOBALS['ff9cn']['next']++; $GLOBALS['ff9cn']['posts'][$id] = (object)['ID' => $id, 'post_type' => $data['post_type'], 'post_status' => $data['post_status'] ?? 'publish', 'post_title' => wp_unslash($data['post_title'] ?? '')]; return $id; }
    function wp_update_post($data) { $p = $GLOBALS['ff9cn']['posts'][(int)$data['ID']] ?? null; if ($p && isset($data['post_title'])) $p->post_title = wp_unslash($data['post_title']); return $p ? $p->ID : 0; }
    function wp_trash_post($id) { if (isset($GLOBALS['ff9cn']['posts'][$id])) $GLOBALS['ff9cn']['posts'][$id]->post_status = 'trash'; }
    function get_post($id) { return $GLOBALS['ff9cn']['posts'][(int)$id] ?? null; }
    function get_the_title($id) { return (string)(get_post($id)->post_title ?? ''); }
    function get_post_meta($id, $key = '', $single = false) { $values = $GLOBALS['ff9cn']['meta'][(int)$id][$key] ?? []; return $single ? ($values[0] ?? '') : $values; }
    function update_post_meta($id, $key, $value) { $GLOBALS['ff9cn']['meta'][(int)$id][$key] = [wp_unslash($value)]; return true; }
    function add_post_meta($id, $key, $value) { $GLOBALS['ff9cn']['meta'][(int)$id][$key][] = wp_unslash($value); return true; }
    function delete_post_meta($id, $key, $value = '') {
        if ($value === '') { unset($GLOBALS['ff9cn']['meta'][(int)$id][$key]); return true; }
        $GLOBALS['ff9cn']['meta'][(int)$id][$key] = array_values(array_filter($GLOBALS['ff9cn']['meta'][(int)$id][$key] ?? [], static function($v) use ($value) { return (string)$v !== (string)$value; }));
        return true;
    }
    function ff9cn_clause(int $id, array $c): bool {
        if (isset($c['relation']) || isset($c[0])) {
            $rel = strtoupper($c['relation'] ?? 'AND'); $results = [];
            foreach ($c as $k => $sub) if ($k !== 'relation' && is_array($sub)) $results[] = ff9cn_clause($id, $sub);
            return $rel === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
        }
        $values = array_map('strval', $GLOBALS['ff9cn']['meta'][$id][$c['key']] ?? []);
        $cmp = strtoupper($c['compare'] ?? (is_array($c['value'] ?? null) ? 'IN' : '='));
        if ($cmp === 'NOT EXISTS') return !$values;
        if ($cmp === 'IN') return (bool)array_intersect($values, array_map('strval', (array)$c['value']));
        if ($cmp === '!=') return (bool)$values && !in_array((string)$c['value'], $values, true);
        return in_array((string)$c['value'], $values, true);
    }
    function get_posts($args) {
        $status = (array)($args['post_status'] ?? 'publish'); $out = [];
        foreach ($GLOBALS['ff9cn']['posts'] as $id => $post) {
            if ($post->post_type !== ($args['post_type'] ?? '') || !in_array($post->post_status, $status, true)) continue;
            if (!empty($args['meta_query']) && !ff9cn_clause($id, $args['meta_query'])) continue;
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
    class FF9CNWpdb { public $prefix = 'wp_'; public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts'; public function prepare($q, ...$a) { return ['sql' => $q, 'args' => $a]; } public function get_var($q) { return null; } public function get_results($q, $m = null) { return []; } }
    $GLOBALS['wpdb'] = new FF9CNWpdb();
    function wp_insert_attachment() { $GLOBALS['ff9cn']['attachment_inserts'] = ($GLOBALS['ff9cn']['attachment_inserts'] ?? 0) + 1; return 0; }
    require $root . '/src/Catalog/ProductCatalogService.php';
}
namespace {
    use RoutesPro\Catalog\ProductCatalogService as C;

    // Client 9 (source): curated catalog with images. Client 12 (target): its own Cardex product without images.
    $jameson = C::save(0, ['client_id' => 9, 'name' => 'Jameson Original 70cl', 'brand' => 'Jameson', 'reference' => '2050501', 'category' => 'WHISKEY', 'capacity_cl' => '70', 'ean' => '5011007003005', 'kind' => 'own', 'aliases' => "Jameson Standard", 'image_ids' => [501, 502]]);
    $tullamore = C::save(0, ['client_id' => 9, 'name' => 'Tullamore D.E.W. 70cl', 'brand' => 'Tullamore', 'capacity_cl' => '70', 'kind' => 'competitor', 'image_ids' => [503]]);
    $beefeater = C::save(0, ['client_id' => 9, 'name' => 'Beefeater 70cl', 'reference' => '3010101', 'capacity_cl' => '70', 'kind' => 'own']);
    C::mapLabel(9, 'Tullamore', [$tullamore]);
    $targetJameson = C::save(0, ['client_id' => 12, 'name' => 'JAMESON 70CL', 'reference' => '205-0501', 'capacity_cl' => '70', 'kind' => 'competitor']);

    // 1. Copy: existing identity is linked (images added), new products are copied with shared images.
    $stats = C::copyFromClient(12, [$jameson, $tullamore, 999999], 'keep');
    ff9cn_assert($stats['copied'] === 1 && $stats['linked'] === 1 && $stats['images_added'] === 1 && $stats['skipped'] === 1, 'copy_stats ' . json_encode($stats));
    $linked = C::get($targetJameson);
    ff9cn_assert($linked['name'] === 'JAMESON 70CL' && $linked['kind'] === 'competitor' && array_column($linked['images'], 'id') === [501, 502] && $linked['source_product_id'] === $jameson && $linked['source_client_id'] === 9, 'linked_existing_keeps_target_data');
    $copyId = C::resolveProductId(12, '', 'Tullamore D.E.W. 70cl', 70);
    $copy = C::get($copyId);
    ff9cn_assert($copy && $copyId !== $tullamore && $copy['client_id'] === 12 && $copy['kind'] === 'competitor' && $copy['origin'] === 'reused' && $copy['brand'] === 'Tullamore', 'copied_product');
    ff9cn_assert(array_column($copy['images'], 'id') === [503] && empty($GLOBALS['ff9cn']['attachment_inserts']), 'shared_images_no_file_copy');
    ff9cn_assert($copy['label_aliases'] === [] && C::productIdsForLabel(12, 'Tullamore') === [], 'briefing_labels_stay_per_client');
    $shared = true;

    // 2. Repeating the copy never duplicates; the kind can be set on copy; same-client copies are ignored.
    $again = C::copyFromClient(12, [$jameson, $tullamore], 'own');
    ff9cn_assert($again['copied'] === 0 && $again['linked'] === 2 && C::summary(12)['total'] === 2, 'no_duplicates');
    $asCompetitor = C::copyFromClient(12, [$beefeater], 'competitor');
    $beefCopy = C::get($asCompetitor['product_ids'][0]);
    ff9cn_assert($asCompetitor['copied'] === 1 && $beefCopy['kind'] === 'competitor' && C::get($beefeater)['kind'] === 'own', 'kind_override_target_only');
    ff9cn_assert(C::copyFromClient(9, [$jameson], 'keep')['skipped'] === 1, 'same_client_ignored');
    try { C::copyFromClient(0, [$jameson]); ff9cn_fail('target_required'); } catch (\RuntimeException $e) {}
    $noDuplicates = true;

    // 3. Copies are independent; images can be refreshed from the source.
    C::save($copyId, ['name' => 'Tullamore DEW Original 70cl', 'brand' => 'Tullamore', 'capacity_cl' => '70', 'kind' => 'competitor', 'image_ids' => [503, 504]]);
    ff9cn_assert(C::get($tullamore)['name'] === 'Tullamore D.E.W. 70cl' && array_column(C::get($tullamore)['images'], 'id') === [503], 'source_untouched');
    C::save($tullamore, ['name' => 'Tullamore D.E.W. 70cl', 'brand' => 'Tullamore', 'capacity_cl' => '70', 'kind' => 'competitor', 'image_ids' => [502, 503]]);
    ff9cn_assert(C::refreshImagesFromSource($copyId) === 2 && array_column(C::get($copyId)['images'], 'id') === [502, 503, 504], 'refresh_from_source');
    try { C::refreshImagesFromSource($tullamore); ff9cn_fail('refresh_requires_source'); } catch (\RuntimeException $e) {}
    C::delete($copyId);
    ff9cn_assert(C::get($copyId) === null && C::get($tullamore) !== null, 'reused_copy_deletable');
    $independent = true;

    // 4. Back-office search uses the 's' filter.
    ff9cn_assert(C::query(['client_id' => 9, 's' => 'beef'])['total'] === 1, 'search_filter');

    // 5. Static contracts.
    $read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
    $admin = $read('src/Admin/ProductCatalog.php');
    foreach (['handle_reuse' => "self::guard('fieldflow_catalog_reuse');", 'handle_refresh_source' => "self::guard('fieldflow_catalog_refresh_source_' . \$productId);"] as $method => $marker) {
        $a = strpos($admin, 'public static function ' . $method . '(');
        $body = $a === false ? '' : substr($admin, $a, 600);
        ff9cn_assert(strpos($body, $marker) !== false, 'guarded_' . $method);
    }
    ff9cn_assert(strpos($admin, "add_action('admin_post_fieldflow_catalog_reuse', [self::class, 'handle_reuse']);") !== false && strpos($admin, 'Reutilizar de outro cliente') !== false, 'admin_wired');
    $service = $read('src/Catalog/ProductCatalogService.php');
    ff9cn_assert(stripos($service . $admin, 'CREATE TABLE') === false && stripos($service, 'wp_insert_attachment') === false, 'no_schema_no_file_copy');
    ff9cn_assert(strpos($read('src/Forms/FormRenderer.php'), "public const FRONTS_VISUAL_CONTRACT = 'fieldflow-fronts-mobile-catalog-visual-v1';") !== false && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false, 'historical_continuity');

    echo 'CATALOG CROSS CLIENT REUSE 9CN RUNTIME OK | shared_images=1 | no_duplicates=1 | independent_copies=1 | refresh_from_source=1 | labels_per_client=1 | guarded_admin=1 | no_schema=1 | historical_continuity=1' . "\n";
}
