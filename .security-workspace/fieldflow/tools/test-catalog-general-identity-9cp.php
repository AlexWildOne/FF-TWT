<?php
// 9CP runtime test: general catalog identity across insignias (no duplicates per chain), reorganisation of the
// catalogs built by 2.2.609–2.2.612, duplicate suggestions and field reference photos from the camera sheet.
// Runs the real ProductCatalogService and FrontsVisionService against an in-memory WordPress stub (no database).
namespace {
    $root = dirname(__DIR__);
    define('ABSPATH', '/tmp/');
    define('ARRAY_A', 'ARRAY_A');
    define('HOUR_IN_SECONDS', 3600);
    $GLOBALS['ff9cp'] = ['posts' => [], 'meta' => [], 'next' => 100, 'options' => [], 'attachments' => [501 => true, 502 => true, 503 => true], 'transients' => [], 'scheduled' => [], 'user' => 7];
    function ff9cp_fail(string $reason): void { fwrite(STDERR, "CATALOG GENERAL IDENTITY 9CP RUNTIME FAIL: {$reason}\n"); exit(1); }
    function ff9cp_assert(bool $ok, string $reason): void { if (!$ok) ff9cp_fail($reason); }
    function wp_strip_all_tags($v) { return strip_tags((string)$v); }
    function remove_accents($v) { return strtr((string)$v, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','É'=>'E','Ó'=>'O','Ç'=>'C']); }
    function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$v))); }
    function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$v)); }
    function absint($v) { return abs((int)$v); }
    function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }
    function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
    function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
    function current_time($t) { return $t === 'mysql' ? '2026-10-08 10:00:00' : date($t, strtotime('2026-10-08 10:00:00')); }
    function update_option($k, $v, $a = null) { $GLOBALS['ff9cp']['options'][$k] = $v; return true; }
    function get_option($k, $d = false) { return $GLOBALS['ff9cp']['options'][$k] ?? $d; }
    function get_transient($k) { return $GLOBALS['ff9cp']['transients'][$k] ?? false; }
    function set_transient($k, $v, $t = 0) { $GLOBALS['ff9cp']['transients'][$k] = $v; return true; }
    function wp_next_scheduled($hook, $args = []) { return isset($GLOBALS['ff9cp']['scheduled'][$hook]) ? 1 : false; }
    function wp_schedule_single_event($time, $hook, $args = []) { $GLOBALS['ff9cp']['scheduled'][$hook] = $args; return true; }
    function post_type_exists($t) { return !empty($GLOBALS['ff9cp']['types'][$t]); }
    function register_post_type($t, $args) { $GLOBALS['ff9cp']['types'][$t] = $args; }
    function is_wp_error($v) { return false; }
    function wp_attachment_is_image($id) { return !empty($GLOBALS['ff9cp']['attachments'][(int)$id]); }
    function wp_get_attachment_image_src($id, $size) { return wp_attachment_is_image($id) ? ['https://example.test/img-' . (int)$id . '-' . $size . '.jpg', 150, 150] : false; }
    function wp_insert_post($data, $err = false) { $id = $GLOBALS['ff9cp']['next']++; $GLOBALS['ff9cp']['posts'][$id] = (object)['ID' => $id, 'post_type' => $data['post_type'], 'post_status' => $data['post_status'] ?? 'publish', 'post_title' => wp_unslash($data['post_title'] ?? ''), 'post_date' => '2026-10-08 10:00:00']; return $id; }
    function wp_insert_attachment($data, $file, $parent = 0, $err = false) { $id = wp_insert_post(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => $data['post_title']]); $GLOBALS['ff9cp']['attachments'][$id] = $file; return $id; }
    function wp_generate_attachment_metadata($id, $file) { return ['file' => basename($file), 'sizes' => ['thumbnail' => []]]; }
    function wp_update_attachment_metadata($id, $data) { $GLOBALS['ff9cp']['meta'][(int)$id]['_wp_attachment_metadata'] = [$data]; return true; }
    function wp_update_post($data) { $p = $GLOBALS['ff9cp']['posts'][(int)$data['ID']] ?? null; if ($p && isset($data['post_title'])) $p->post_title = wp_unslash($data['post_title']); return $p ? $p->ID : 0; }
    function wp_trash_post($id) { if (isset($GLOBALS['ff9cp']['posts'][$id])) $GLOBALS['ff9cp']['posts'][$id]->post_status = 'trash'; }
    function get_post($id) { return $GLOBALS['ff9cp']['posts'][(int)$id] ?? null; }
    function get_post_meta($id, $key = '', $single = false) { $values = $GLOBALS['ff9cp']['meta'][(int)$id][$key] ?? []; return $single ? ($values[0] ?? '') : $values; }
    function update_post_meta($id, $key, $value) { $GLOBALS['ff9cp']['meta'][(int)$id][$key] = [wp_unslash($value)]; return true; }
    function add_post_meta($id, $key, $value) { $GLOBALS['ff9cp']['meta'][(int)$id][$key][] = wp_unslash($value); return true; }
    function delete_post_meta($id, $key, $value = '') {
        if ($value === '') { unset($GLOBALS['ff9cp']['meta'][(int)$id][$key]); return true; }
        $GLOBALS['ff9cp']['meta'][(int)$id][$key] = array_values(array_filter($GLOBALS['ff9cp']['meta'][(int)$id][$key] ?? [], static function($v) use ($value) { return (string)$v !== (string)$value; }));
        return true;
    }
    function ff9cp_clause(int $id, array $c): bool {
        if (isset($c['relation']) || isset($c[0])) {
            $rel = strtoupper($c['relation'] ?? 'AND'); $results = [];
            foreach ($c as $k => $sub) if ($k !== 'relation' && is_array($sub)) $results[] = ff9cp_clause($id, $sub);
            return $rel === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
        }
        $values = array_map('strval', $GLOBALS['ff9cp']['meta'][$id][$c['key']] ?? []);
        $cmp = strtoupper($c['compare'] ?? (is_array($c['value'] ?? null) ? 'IN' : '='));
        if ($cmp === 'NOT EXISTS') return !$values;
        if ($cmp === 'IN') return (bool)array_intersect($values, array_map('strval', (array)$c['value']));
        if ($cmp === '!=') return (bool)$values && !in_array((string)$c['value'], $values, true);
        return in_array((string)$c['value'], $values, true);
    }
    function get_posts($args) {
        $status = (array)($args['post_status'] ?? 'publish'); $out = [];
        foreach ($GLOBALS['ff9cp']['posts'] as $id => $post) {
            if ($post->post_type !== ($args['post_type'] ?? '') || !in_array($post->post_status, $status, true)) continue;
            if (!empty($args['meta_query']) && !ff9cp_clause($id, $args['meta_query'])) continue;
            $out[] = $post;
        }
        usort($out, static function($a, $b) { return strcasecmp($a->post_title, $b->post_title) ?: $a->ID <=> $b->ID; });
        $limit = (int)($args['numberposts'] ?? 5); if ($limit > 0) $out = array_slice($out, 0, $limit);
        return ($args['fields'] ?? '') === 'ids' ? array_map(static function($p) { return $p->ID; }, $out) : $out;
    }
    class WP_Query {
        public $posts = []; public $found_posts = 0; public $max_num_pages = 0;
        public function __construct($args) {
            $all = get_posts(['numberposts' => -1] + $args);
            if (!empty($args['s'])) $all = array_values(array_filter($all, static function($p) use ($args) { return stripos($p->post_title, $args['s']) !== false; }));
            $this->found_posts = count($all); $per = (int)($args['posts_per_page'] ?? 10); $this->max_num_pages = (int)ceil($this->found_posts / max(1, $per));
            $this->posts = array_slice($all, (($args['paged'] ?? 1) - 1) * $per, $per);
        }
    }
    class FF9CPWpdb {
        public $prefix = 'wp_'; public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts';
        public function prepare($sql, ...$args) { return ['sql' => $sql, 'args' => $args]; }
        public function get_var($q) {
            $sql = is_array($q) ? $q['sql'] : $q;
            if (strpos($sql, 'routespro_projects') !== false) return ['77' => 9, '78' => 9][(string)$q['args'][0]] ?? 0;
            return null;
        }
        public function get_results($q, $mode = null) {
            $sql = is_array($q) ? $q['sql'] : $q;
            if (strpos($sql, 'routespro_product_cardex_items') !== false) return $GLOBALS['ff9cp']['cardex_items'][(int)$q['args'][0]] ?? [];
            return [];
        }
    }
    $GLOBALS['wpdb'] = new FF9CPWpdb();
}

namespace RoutesPro\Forms {
    class ProductCardex {
        public static function install(): void {}
        public static function list_cardex(): array { return array_values($GLOBALS['ff9cp']['cardex']); }
        public static function get_cardex(int $id): ?array { return $GLOBALS['ff9cp']['cardex'][$id] ?? null; }
        public static function table_items(): string { return 'wp_routespro_product_cardex_items'; }
    }
}

namespace {
    use RoutesPro\Catalog\ProductCatalogService as C;
    use RoutesPro\Vision\FrontsVisionService as V;
    require $root . '/src/Catalog/ProductCatalogService.php';
    require $root . '/src/Vision/FrontsVisionService.php';

    $line = static function (int $id, string $ref, string $name, $capacity, string $ean = '', string $category = 'WHISKEY'): array {
        return ['id' => $id, 'category' => $category, 'reference' => $ref, 'product_name' => $name, 'capacity_cl' => $capacity, 'meta_json' => json_encode(['managed_by' => 'briefing_weekly', 'ean' => $ean])];
    };
    // Pernod briefing: one Cardex per insignia (and per campaign), each chain with its own codes and names.
    $GLOBALS['ff9cp']['cardex'] = [
        1 => ['id' => 1, 'client_id' => 0, 'project_id' => 77, 'insignia' => 'Continente', 'name' => 'Pernod · Continente'],
        2 => ['id' => 2, 'client_id' => 0, 'project_id' => 77, 'insignia' => 'Pingo Doce', 'name' => 'Pernod · Pingo Doce'],
        3 => ['id' => 3, 'client_id' => 0, 'project_id' => 77, 'insignia' => 'Auchan', 'name' => 'Pernod · Auchan'],
        4 => ['id' => 4, 'client_id' => 0, 'project_id' => 78, 'insignia' => 'Continente', 'name' => 'Pernod Natal · Continente'],
        5 => ['id' => 5, 'client_id' => 9, 'project_id' => 0, 'insignia' => 'Intermarché', 'name' => 'Pernod · Intermarché'],
    ];
    $GLOBALS['ff9cp']['cardex_items'] = [
        1 => [
            $line(11, '5203', 'WHISKY JAMESON 70CL', '70'),
            $line(12, '5204', 'WHISKY JAMESON 1L', '1'),
            $line(13, '5205', 'WHISKY JAMESON BLACK BARREL 70CL', '70'),
            $line(14, '6001', 'GIN BEEFEATER 70CL', '70', '5000329002223', 'GIN'),
            $line(15, '7001', 'VODKA ABSOLUT 70CL', '70', '', 'VODKA'),
            $line(16, '8012', 'WHISKY CHIVAS REGAL 12 ANOS 70CL', '70'),
            $line(17, '8018', 'WHISKY CHIVAS REGAL 18 ANOS 70CL', '70'),
            $line(19, '6002', 'GIN BEEFEATER PINK 70CL', '70', '', 'GIN'),
        ],
        2 => [
            $line(21, '100200', 'JAMESON IRISH WHISKEY 0,7L', '0.7'),
            $line(22, '100201', 'JAMESON 1 LT', '100'),
            $line(23, '100202', 'JAMESON BLACK BARREL 70 CL', '70'),
            $line(24, '100300', 'BEEFEATER LONDON DRY GIN 70CL', '70', '05000329002223', 'GIN'),
            $line(25, '5203', 'ABSOLUT VODKA 70CL', '70', '', 'VODKA'),
            $line(26, '100400', 'CHIVAS REGAL 12Y 70CL', '70'),
        ],
        3 => [
            $line(31, 'A1', 'WHISKEY JAMESON STANDARD 70CL', '70'),
            $line(32, 'A2', 'BEEFEATER GIN 70 CL', '70', '', 'GIN'),
            $line(33, 'A3', 'CHIVAS 12 ANOS 70CL', '70'),
        ],
        4 => [$line(41, '5203', 'WHISKY JAMESON 70CL', '70')],
        5 => [$line(51, 'I1', 'MALIBU 70CL', '70', '', 'LICOR'), $line(52, 'I2', 'WHISKY CHIVAS REGAL 15 ANOS 70CL', '70')],
    ];

    // Catalog as 2.2.609–2.2.612 left it: one product per chain name, litre capacities, global codes.
    $legacy = static function (string $title, array $meta) {
        $id = wp_insert_post(['post_type' => C::POST_TYPE, 'post_status' => 'publish', 'post_title' => $title]);
        $defaults = ['_ff_client_id' => '9', '_ff_kind' => 'own', '_ff_active' => '1', '_ff_origin' => 'cardex', '_ff_image_ids' => '[]', '_ff_aliases' => '[]'];
        foreach ($meta + $defaults as $key => $value) {
            if (is_array($value)) { foreach ($value as $v) add_post_meta($id, $key, $v); } else update_post_meta($id, $key, $value);
        }
        return $id;
    };
    // The global code 5203 attached the Pingo Doce Absolut line to the Continente Jameson product.
    $oldJameson = $legacy('WHISKY JAMESON 70CL', ['_ff_capacity_cl' => '70', '_ff_reference' => '5203', '_ff_image_ids' => '[501]', '_ff_label_alias' => ['jameson'], '_ff_cardex_ids' => '[1,2,4]', '_ff_identity_key' => ['r:5203', 'n:whisky jameson|70', 'n:absolut vodka|70']]);
    $oldJamesonPd = $legacy('JAMESON IRISH WHISKEY 0,7L', ['_ff_capacity_cl' => '0.7', '_ff_cardex_ids' => '[2]', '_ff_identity_key' => ['r:100200', 'n:jameson irish whiskey|0.7']]);
    $oldLitreC = $legacy('WHISKY JAMESON 1L', ['_ff_capacity_cl' => '1', '_ff_cardex_ids' => '[1]', '_ff_identity_key' => ['r:5204', 'n:whisky jameson|1']]);
    $oldLitrePd = $legacy('JAMESON 1 LT', ['_ff_capacity_cl' => '100', '_ff_cardex_ids' => '[2]', '_ff_identity_key' => ['r:100201', 'n:jameson|100']]);
    $manualAbsolut = $legacy('Absolut Vodka 70cl', ['_ff_origin' => 'manual', '_ff_capacity_cl' => '70', '_ff_image_ids' => '[502]', '_ff_identity_key' => ['n:absolut vodka|70']]);
    // A product renamed in the back office before 9CP, found only through its other name.
    $legacyMalibu = $legacy('Malibu Original Coco', ['_ff_origin' => 'manual', '_ff_capacity_cl' => '70', '_ff_aliases' => '["MALIBU 70CL"]', '_ff_identity_key' => ['n:malibu original coco|70', 'n:malibu|70']]);
    $oldAbsolut = $legacy('VODKA ABSOLUT 70CL', ['_ff_capacity_cl' => '70', '_ff_cardex_ids' => '[1]', '_ff_identity_key' => ['r:7001', 'n:vodka absolut|70']]);
    $orphan = $legacy('WHISKY OLD BRAND 70CL', ['_ff_capacity_cl' => '70', '_ff_cardex_ids' => '[1]', '_ff_identity_key' => ['r:9999']]);
    $orphanImage = $legacy('DESCONTINUADO 70CL', ['_ff_capacity_cl' => '70', '_ff_image_ids' => '[503]', '_ff_identity_key' => ['r:9998']]);
    $orphanLocked = $legacy('Produto renomeado no BO', ['_ff_capacity_cl' => '70', '_ff_name_locked' => '1', '_ff_identity_key' => ['r:9997']]);
    $competitor = $legacy('Tullamore D.E.W.', ['_ff_origin' => 'briefing', '_ff_kind' => 'competitor', '_ff_label_alias' => ['tullamore']]);
    $otherClient = $legacy('WHISKY JAMESON 70CL', ['_ff_client_id' => '12', '_ff_capacity_cl' => '70', '_ff_identity_key' => ['r:5203']]);
    $countClient = static function (int $clientId): int { return count(C::clientIndex($clientId)); };
    ff9cp_assert($countClient(9) === 11, 'legacy_fixture');

    // 1. Identity: chain names, capacities in litres and leading zeros of the EAN do not split a product.
    ff9cp_assert(C::nameSignature('WHISKY JAMESON 70CL', '70') === C::nameSignature("JAMESON IRISH WHISKEY 0,7L", '70') && C::nameSignature('CHIVAS REGAL 12Y 70CL', '70') === C::nameSignature('WHISKY CHIVAS REGAL 12 ANOS 70CL', '70'), 'signature_across_insignias');
    ff9cp_assert(C::normalizeCapacity('1', 'WHISKY JAMESON 1L') === '100' && C::normalizeCapacity('0.7', 'X') === '70' && C::normalizeCapacity('70', '') === '70' && C::normalizeCapacity('', 'JAMESON 1 LT') === '100', 'capacity_litres');
    ff9cp_assert(C::normalizeEan('05000329002223') === C::normalizeEan('5000329002223') && C::normalizeEan('123') === '', 'ean_normalized');
    $keysC = C::identityKeys('5203', 'WHISKY JAMESON 70CL', '70', '', 'continente');
    $keysP = C::identityKeys('5203', 'ABSOLUT VODKA 70CL', '70', '', 'pingo_doce');
    ff9cp_assert(in_array('c:continente:5203', $keysC, true) && !array_intersect($keysC, $keysP), 'codes_scoped_by_insignia');
    $identity = true;

    // 9CQ: "STD" (a chain code) now joins automatically; "STANDARD" is a word, so it stays a suggestion.
    // 2. Sync: 20 lines of 5 Cardex (4 insignias, 2 campaigns) become 12 products; the old catalog is reorganised.
    $stats = C::syncFromCardex();
    ff9cp_assert($stats['items'] === 20 && $stats['groups'] === 12 && $stats['clients'] === [9] && count($stats['insignias']) === 4, 'sync_groups ' . json_encode($stats));
    ff9cp_assert($stats['created'] === 8 && $stats['updated'] === 4 && $stats['merged'] === 3 && $stats['removed'] === 1, 'sync_reorganised ' . json_encode($stats));
    ff9cp_assert($countClient(9) === 15 && $countClient(12) === 1, 'catalog_size ' . $countClient(9));
    ff9cp_assert(C::resolveProductIdScoped(9, 'I1', 'MALIBU 70CL', '70', 'intermarche') === $legacyMalibu && C::get($legacyMalibu)['name'] === 'Malibu Original Coco', 'found_through_other_name');
    $jameson = C::get($oldJameson);
    ff9cp_assert($jameson && $jameson['name'] === 'WHISKY JAMESON 70CL' && $jameson['cardex_ids'] === [1, 2, 4] && array_column($jameson['images'], 'id') === [501] && $jameson['label_aliases'] === ['jameson'], 'jameson_survivor');
    ff9cp_assert(C::get($oldJamesonPd) === null && in_array('JAMESON IRISH WHISKEY 0,7L', $jameson['aliases'], true), 'chain_duplicate_merged');
    $jamesonKeys = array_map('strval', get_post_meta($oldJameson, '_ff_identity_key'));
    ff9cp_assert(in_array('c:continente:5203', $jamesonKeys, true) && in_array('c:pingo_doce:100200', $jamesonKeys, true) && !in_array('r:5203', $jamesonKeys, true) && !in_array('n:absolut vodka|70', $jamesonKeys, true), 'old_global_keys_dropped');
    $litre = C::get(C::resolveProductIdScoped(9, '100201', 'JAMESON 1 LT', '100', 'pingo_doce'));
    ff9cp_assert($litre && $litre['capacity_cl'] === '100' && in_array($litre['id'], [$oldLitreC, $oldLitrePd], true) && (C::get($oldLitreC) === null) !== (C::get($oldLitrePd) === null), 'litre_capacity_fixed');
    $absolutId = C::resolveProductIdScoped(9, '5203', 'ABSOLUT VODKA 70CL', '70', 'pingo_doce');
    ff9cp_assert($absolutId === $manualAbsolut && C::get($manualAbsolut)['cardex_ids'] === [1, 2] && C::get($manualAbsolut)['origin'] === 'manual' && C::get($oldAbsolut) === null, 'manual_product_reused');
    ff9cp_assert(!in_array('r:7001', array_map('strval', get_post_meta($manualAbsolut, '_ff_identity_key')), true) && in_array('VODKA ABSOLUT 70CL', C::get($manualAbsolut)['aliases'], true), 'old_keys_not_carried_to_survivor');
    ff9cp_assert(C::resolveProductIdScoped(9, '5203', 'qualquer nome', '70', 'continente') === $oldJameson && C::resolveProductId(9, '5203', 'qualquer nome', null) === 0, 'code_inside_insignia_only');
    $beefeater = C::resolveProductIdScoped(9, '', 'outro nome', null, '', '5000329002223');
    ff9cp_assert($beefeater > 0 && C::get($beefeater)['cardex_ids'] === [1, 2, 3] && C::get($beefeater)['ean'] === '5000329002223', 'ean_joins_three_insignias');
    $chivas12 = C::resolveProductIdScoped(9, '100400', 'CHIVAS REGAL 12Y 70CL', '70', 'pingo_doce');
    $chivas18 = C::resolveProductIdScoped(9, '8018', 'WHISKY CHIVAS REGAL 18 ANOS 70CL', '70', 'continente');
    ff9cp_assert($chivas12 > 0 && $chivas18 > 0 && $chivas12 !== $chivas18 && C::get($chivas12)['cardex_ids'] === [1, 2], 'ages_not_merged');
    ff9cp_assert(C::get($orphan) === null && C::get($orphanImage) !== null && C::get($orphanLocked) !== null && C::get($competitor) !== null && C::get($otherClient) !== null, 'only_empty_orphans_removed');
    ff9cp_assert(C::insigniasForCardexIds([1, 4, 2]) === ['Continente', 'Pingo Doce'], 'insignia_chips');
    ff9cp_assert((int)get_option('fieldflow_catalog_identity_version') === C::IDENTITY_VERSION && C::identityCurrentFor(9) && get_option('fieldflow_catalog_identity_clients') === [9 => C::IDENTITY_VERSION], 'identity_version_saved');
    $reorganised = true;

    // 3. Idempotent: a second sync changes nothing.
    $again = C::syncFromCardex();
    ff9cp_assert($again['created'] === 0 && $again['merged'] === 0 && $again['removed'] === 0 && $again['groups'] === 12 && $countClient(9) === 15, 'idempotent ' . json_encode($again));
    $idempotent = true;

    // 4. Probable duplicates the names do not join are suggested, never merged automatically.
    $std = C::resolveProductIdScoped(9, 'A1', 'WHISKEY JAMESON STANDARD 70CL', '70', 'auchan');
    $chivasAuchan = C::resolveProductIdScoped(9, 'A3', 'CHIVAS 12 ANOS 70CL', '70', 'auchan');
    $pairs = array_map(static function ($p) { return $p['keep'] . '>' . $p['drop']; }, C::duplicateSuggestions(9));
    ff9cp_assert(in_array($oldJameson . '>' . $std, $pairs, true) && (in_array($chivas12 . '>' . $chivasAuchan, $pairs, true) || in_array($chivasAuchan . '>' . $chivas12, $pairs, true)), 'suggestions ' . json_encode($pairs));
    $bb = C::resolveProductIdScoped(9, '5205', 'WHISKY JAMESON BLACK BARREL 70CL', '70', 'continente');
    $pink = C::resolveProductIdScoped(9, '6002', 'GIN BEEFEATER PINK 70CL', '70', 'continente');
    $chivas15 = C::resolveProductIdScoped(9, 'I2', 'WHISKY CHIVAS REGAL 15 ANOS 70CL', '70', 'intermarche');
    ff9cp_assert($pink > 0 && $pink !== $beefeater, 'pink_separate');
    foreach (C::duplicateSuggestions(9) as $pair) {
        $ids = [$pair['keep'], $pair['drop']];
        // Listed together in one insignia (Beefeater and Beefeater Pink in Continente) or different ages: never suggested.
        ff9cp_assert(!(in_array($chivas12, $ids, true) && in_array($chivas18, $ids, true)) && !(in_array($oldJameson, $ids, true) && in_array($bb, $ids, true)) && !(in_array($beefeater, $ids, true) && in_array($pink, $ids, true)) && !(in_array($chivas12, $ids, true) && in_array($chivas15, $ids, true)), 'no_false_suggestion ' . json_encode($pair));
    }
    C::markDistinct(9, $chivas12, $chivasAuchan);
    ff9cp_assert(!in_array($chivas12 . '>' . $chivasAuchan, array_map(static function ($p) { return $p['keep'] . '>' . $p['drop']; }, C::duplicateSuggestions(9)), true) && !in_array($chivasAuchan . '>' . $chivas12, array_map(static function ($p) { return $p['keep'] . '>' . $p['drop']; }, C::duplicateSuggestions(9)), true), 'mark_distinct');
    // A merge confirmed in the back office survives the next sync (the Auchan line follows the surviving product).
    C::merge($std, $oldJameson);
    $afterMerge = C::syncFromCardex();
    ff9cp_assert($afterMerge['created'] === 0 && $afterMerge['groups'] === 11 && C::get($oldJameson)['cardex_ids'] === [1, 2, 3, 4] && $countClient(9) === 14, 'manual_merge_persists ' . json_encode($afterMerge));
    $suggestions = true;

    // 5. A code reused by the chain for another product does not merge two products of the same insignia.
    $GLOBALS['ff9cp']['cardex_items'][1][2] = $line(13, '5299', 'WHISKY JAMESON BLACK BARREL 70CL', '70');
    $GLOBALS['ff9cp']['cardex_items'][1][] = $line(18, '5205', 'WHISKY JAMESON CASKMATES 70CL', '70');
    $reuse = C::syncFromCardex(9);
    $caskmates = C::resolveProductIdScoped(9, '5205', 'WHISKY JAMESON CASKMATES 70CL', '70', 'continente');
    ff9cp_assert($reuse['created'] === 1 && $reuse['conflicts'] >= 1 && $caskmates > 0 && $caskmates !== $bb && C::get($bb) !== null && C::resolveProductIdScoped(9, '5299', 'x', null, 'continente') === $bb, 'code_reuse_guard ' . json_encode($reuse));
    $conflictGuard = true;

    // 6. Back office edits are never undone: a renamed product keeps its name and is not removed.
    C::save($bb, ['name' => 'Jameson Black Barrel 70cl', 'capacity_cl' => '70', 'kind' => 'own', 'lock_name' => true, 'image_ids' => []]);
    C::syncFromCardex(9);
    ff9cp_assert(C::get($bb)['name'] === 'Jameson Black Barrel 70cl' && (string)get_post_meta($bb, '_ff_name_locked', true) === '1', 'name_lock');
    // The update schedules one rebuild per site; nothing is scheduled once the identity is current.
    $GLOBALS['ff9cp']['options']['fieldflow_catalog_identity_version'] = 1;
    C::maybeScheduleIdentityRebuild();
    C::maybeScheduleIdentityRebuild();
    ff9cp_assert(isset($GLOBALS['ff9cp']['scheduled'][C::SYNC_HOOK]) && !empty($GLOBALS['ff9cp']['transients']['fieldflow_catalog_identity_rebuild_scheduled']), 'rebuild_scheduled_once');
    $GLOBALS['ff9cp']['scheduled'] = []; $GLOBALS['ff9cp']['transients'] = []; $GLOBALS['ff9cp']['options']['fieldflow_catalog_identity_version'] = C::IDENTITY_VERSION;
    C::maybeScheduleIdentityRebuild();
    ff9cp_assert(!$GLOBALS['ff9cp']['scheduled'], 'no_rebuild_when_current');
    $manualPreserved = true;

    // 7. Field reference photo from the camera sheet: product without image gets it, no duplicate is created.
    $photo = static function (): array {
        $path = sys_get_temp_dir() . '/ff9cp-ref-' . getmypid() . '-' . mt_rand() . '.jpg';
        file_put_contents($path, base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gNzAK/9sAQwAKBwcIBwYKCAgICwoKCw4YEA4NDQ4dFRYRGCMfJSQiHyIhJis3LyYpNCkhIjBBMTQ5Oz4+PiUuRElDPEg3PT47/9sAQwEKCwsODQ4cEBAcOygiKDs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7/8AAEQgABAAEAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A52iiiuM+fP/Z'));
        return ['path' => $path, 'url' => 'https://ff.test/uploads/' . basename($path), 'mime' => 'image/jpeg'];
    };
    $request = ['client_id' => 9, 'project_id' => 77, 'location_id' => 345, 'question_key' => 'frentes', 'question_type' => 'product_matrix', 'date' => '2026-10-08'];
    // 9CQ: camera reading is on by default (question switch); a client set to "off" gets no field references.
    V::saveClientSettings(9, ['mode' => 'off']);
    V::saveClientSettings(12, ['mode' => 'off']);
    try { V::addFieldReference($request, ['name' => 'MALIBU 70CL', 'ref' => 'I1', 'capacity' => '70', 'cardex_id' => 5], $photo(), 7); ff9cp_fail('reference_requires_pilot'); } catch (\RuntimeException $e) {}
    V::saveClientSettings(9, ['enabled' => 1, 'share_space' => 1, 'monthly_limit' => 0, 'reference_images' => 12]);
    ff9cp_assert(V::clientSettings(9)['field_references'] === true && V::clientSettings(12)['field_references'] === true && V::fieldReferencesEnabled(9) && !V::fieldReferencesEnabled(12), 'field_references_default_on');
    $malibu = C::resolveProductIdScoped(9, 'I1', 'MALIBU 70CL', '70', 'intermarche');
    $before = $countClient(9);
    $ref = V::addFieldReference($request, ['name' => 'MALIBU 70CL', 'ref' => 'I1', 'capacity' => '70', 'cardex_id' => 5], $photo(), 7);
    $attachmentMeta = json_decode((string)get_post_meta($ref['attachment_id'], V::FIELD_REFERENCE_META, true), true);
    ff9cp_assert($ref['product_id'] === $malibu && !$ref['created'] && $ref['image_count'] === 1 && $countClient(9) === $before && strpos($ref['thumb'], 'img-' . $ref['attachment_id'] . '-thumbnail') !== false, 'reference_on_existing_product');
    ff9cp_assert($attachmentMeta['user_id'] === 7 && $attachmentMeta['location_id'] === 345 && $attachmentMeta['client_id'] === 9 && C::get($malibu)['images'][0]['field'] === true, 'reference_tagged');
    $second = $photo();
    try { V::addFieldReference($request, ['name' => 'MALIBU 70CL', 'ref' => 'I1', 'capacity' => '70', 'cardex_id' => 5], $second, 7); ff9cp_fail('reference_only_without_image'); } catch (\RuntimeException $e) { ff9cp_assert(!is_file($second['path']), 'rejected_photo_removed'); }
    // Rows with a chain code of another insignia resolve inside their own insignia (Absolut, not Jameson).
    // Names changed by the chain since the last sync: only the code, read inside its insignia, identifies them.
    $absolutRow = V::buildItems(9, 'product_matrix', [['key' => 'r0', 'name' => 'ABSOLUT AZUL 70CL', 'ref' => '5203', 'capacity' => '70', 'cardex_id' => 2], ['key' => 'r1', 'name' => 'WHISKY JAMESON NOVA EMB 70CL', 'ref' => '5203', 'capacity' => '70', 'cardex_id' => 1]]);
    ff9cp_assert($absolutRow[0]['catalog_id'] === $manualAbsolut && $absolutRow[1]['catalog_id'] === $oldJameson, 'reading_rows_scoped');
    // A product not yet in the catalog is created once, as a field product, and reused by the next reference.
    $new = V::addFieldReference($request, ['name' => 'PLYMOUTH GIN 70CL', 'ref' => 'Z9', 'capacity' => '70', 'cardex_id' => 1], $photo(), 7);
    ff9cp_assert($new['created'] && C::get($new['product_id'])['origin'] === 'field' && C::get($new['product_id'])['project_ids'] === [77] && C::resolveProductIdScoped(9, '', 'Gin Plymouth 70 cl', '70') === $new['product_id'], 'field_product_created');
    // Share Space side: the mapped competitor gets the photo; an unmapped label creates and maps one product.
    $share = ['question_type' => 'share_space_matrix'] + $request;
    $tull = V::addFieldReference($share, ['side' => 'competitor', 'label' => 'Tullamore'], $photo(), 7);
    ff9cp_assert($tull['product_id'] === $competitor && !$tull['created'], 'share_mapped_side');
    $teeling = V::addFieldReference($share, ['side' => 'competitor', 'label' => 'Teeling'], $photo(), 7);
    ff9cp_assert($teeling['created'] && C::get($teeling['product_id'])['kind'] === 'competitor' && C::productIdsForLabel(9, 'Teeling') === [$teeling['product_id']], 'share_label_created_and_mapped');
    V::saveClientSettings(9, ['enabled' => 1, 'share_space' => 1, 'monthly_limit' => 0, 'reference_images' => 12, 'field_references' => 0]);
    try { V::addFieldReference($request, ['name' => 'WHISKY CHIVAS REGAL 18 ANOS 70CL', 'ref' => '8018', 'capacity' => '70', 'cardex_id' => 1], $photo(), 7); ff9cp_fail('field_references_off'); } catch (\RuntimeException $e) {}
    foreach (glob(sys_get_temp_dir() . '/ff9cp-ref-' . getmypid() . '-*') ?: [] as $file) @unlink($file);
    $fieldReference = true;

    // 8. Static contracts.
    $read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
    $service = $read('src/Catalog/ProductCatalogService.php');
    $vision = $read('src/Vision/FrontsVisionService.php');
    $admin = $read('src/Admin/ProductCatalog.php');
    $renderer = $read('src/Forms/FormRenderer.php');
    $a = strpos($vision, 'public static function ajaxReference(): void {');
    $body = $a === false ? '' : substr($vision, $a, 900);
    ff9cp_assert($body !== '' && strpos($body, '$userId = self::ajaxGuard();') !== false && strpos($body, 'self::rateLimit($userId, \'ff_vision_ref_rate_\'') !== false && strpos($body, '$scopeError = self::scopeError($request, $userId);') !== false, 'reference_ajax_guarded');
    ff9cp_assert(strpos($vision, "add_action('wp_ajax_' . self::AJAX_REFERENCE") !== false && strpos($vision, 'wp_ajax_nopriv') === false, 'logged_in_only');
    ff9cp_assert(strpos($admin, "self::guard('fieldflow_catalog_not_duplicate')") !== false && strpos($admin, "'lock_name' => true") !== false && strpos($admin, 'Possíveis duplicados') !== false, 'admin_guarded');
    ff9cp_assert(strpos($renderer, "fd.append('action','fieldflow_fronts_vision_reference')") !== false && strpos($renderer, 'data-vision-ref-camera') !== false && strpos($renderer, 'data-vision-references') !== false, 'front_runtime');
    $noSchema = stripos($service . $vision . $admin, 'CREATE TABLE') === false && stripos($service . $admin, 'ALTER TABLE') === false && strpos($service, 'wp_insert_attachment') === false;
    ff9cp_assert($noSchema, 'no_schema');
    $historical = strpos($service, "public const CONTRACT = 'fieldflow-visual-product-catalog-v1';") !== false
        && strpos($service, 'public static function resolveProductId(int $clientId, string $reference, string $name, $capacity = null): int {') !== false
        && strpos($service, "public const REUSE_CONTRACT = 'fieldflow-catalog-cross-client-reuse-v1';") !== false
        && strpos($vision, "public const CONTRACT = 'fieldflow-fronts-vision-reading-v1';") !== false
        && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false;
    ff9cp_assert($historical, 'historical_continuity');

    echo 'CATALOG GENERAL IDENTITY 9CP RUNTIME OK | identity=1 | reorganised=1 | idempotent=1 | suggestions=1 | conflict_guard=1 | manual_preserved=1 | field_reference=1 | guarded=1 | no_schema=1 | historical_continuity=1' . "\n";
}
