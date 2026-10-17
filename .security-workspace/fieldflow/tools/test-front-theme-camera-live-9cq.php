<?php
// 9CQ runtime test: insignia name variants in the catalog, camera on the question switch, client modes, live counting
// without a stored photo and theme isolation (FieldFlow styles after the theme, template colours in every state).
// Runs the real ProductCatalogService and FrontsVisionService against an in-memory WordPress stub (no database).
namespace {
    $root = dirname(__DIR__);
    define('ABSPATH', '/tmp/');
    define('ARRAY_A', 'ARRAY_A');
    define('HOUR_IN_SECONDS', 3600);
    $GLOBALS['ff9cq'] = ['posts' => [], 'meta' => [], 'next' => 100, 'options' => [], 'attachments' => [501 => true, 502 => true, 503 => true], 'transients' => [], 'scheduled' => [], 'user' => 7];
    function ff9cq_fail(string $reason): void { fwrite(STDERR, "FRONT THEME CAMERA LIVE 9CQ RUNTIME FAIL: {$reason}\n"); exit(1); }
    function ff9cq_assert(bool $ok, string $reason): void { if (!$ok) ff9cq_fail($reason); }
    function wp_strip_all_tags($v) { return strip_tags((string)$v); }
    function remove_accents($v) { return strtr((string)$v, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','É'=>'E','Ó'=>'O','Ç'=>'C']); }
    function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$v))); }
    function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$v)); }
    function absint($v) { return abs((int)$v); }
    function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }
    function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
    function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
    function current_time($t) { return $t === 'mysql' ? '2026-10-08 10:00:00' : date($t, strtotime('2026-10-08 10:00:00')); }
    function update_option($k, $v, $a = null) { $GLOBALS['ff9cq']['options'][$k] = $v; return true; }
    function get_option($k, $d = false) { return $GLOBALS['ff9cq']['options'][$k] ?? $d; }
    function get_transient($k) { return $GLOBALS['ff9cq']['transients'][$k] ?? false; }
    function set_transient($k, $v, $t = 0) { $GLOBALS['ff9cq']['transients'][$k] = $v; return true; }
    function wp_next_scheduled($hook, $args = []) { return isset($GLOBALS['ff9cq']['scheduled'][$hook]) ? 1 : false; }
    function wp_schedule_single_event($time, $hook, $args = []) { $GLOBALS['ff9cq']['scheduled'][$hook] = $args; return true; }
    function post_type_exists($t) { return !empty($GLOBALS['ff9cq']['types'][$t]); }
    function register_post_type($t, $args) { $GLOBALS['ff9cq']['types'][$t] = $args; }
    function is_wp_error($v) { return false; }
    function wp_attachment_is_image($id) { return !empty($GLOBALS['ff9cq']['attachments'][(int)$id]); }
    function wp_get_attachment_image_src($id, $size) { return wp_attachment_is_image($id) ? ['https://example.test/img-' . (int)$id . '-' . $size . '.jpg', 150, 150] : false; }
    function wp_insert_post($data, $err = false) { $id = $GLOBALS['ff9cq']['next']++; $GLOBALS['ff9cq']['posts'][$id] = (object)['ID' => $id, 'post_type' => $data['post_type'], 'post_status' => $data['post_status'] ?? 'publish', 'post_title' => wp_unslash($data['post_title'] ?? ''), 'post_date' => '2026-10-08 10:00:00']; return $id; }
    function wp_insert_attachment($data, $file, $parent = 0, $err = false) { $id = wp_insert_post(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => $data['post_title']]); $GLOBALS['ff9cq']['attachments'][$id] = $file; return $id; }
    function wp_generate_attachment_metadata($id, $file) { return ['file' => basename($file), 'sizes' => ['thumbnail' => []]]; }
    function wp_update_attachment_metadata($id, $data) { $GLOBALS['ff9cq']['meta'][(int)$id]['_wp_attachment_metadata'] = [$data]; return true; }
    function wp_update_post($data) { $p = $GLOBALS['ff9cq']['posts'][(int)$data['ID']] ?? null; if ($p && isset($data['post_title'])) $p->post_title = wp_unslash($data['post_title']); return $p ? $p->ID : 0; }
    function wp_trash_post($id) { if (isset($GLOBALS['ff9cq']['posts'][$id])) $GLOBALS['ff9cq']['posts'][$id]->post_status = 'trash'; }
    function get_post($id) { return $GLOBALS['ff9cq']['posts'][(int)$id] ?? null; }
    function get_post_meta($id, $key = '', $single = false) { $values = $GLOBALS['ff9cq']['meta'][(int)$id][$key] ?? []; return $single ? ($values[0] ?? '') : $values; }
    function update_post_meta($id, $key, $value) { $GLOBALS['ff9cq']['meta'][(int)$id][$key] = [wp_unslash($value)]; return true; }
    function add_post_meta($id, $key, $value) { $GLOBALS['ff9cq']['meta'][(int)$id][$key][] = wp_unslash($value); return true; }
    function delete_post_meta($id, $key, $value = '') {
        if ($value === '') { unset($GLOBALS['ff9cq']['meta'][(int)$id][$key]); return true; }
        $GLOBALS['ff9cq']['meta'][(int)$id][$key] = array_values(array_filter($GLOBALS['ff9cq']['meta'][(int)$id][$key] ?? [], static function($v) use ($value) { return (string)$v !== (string)$value; }));
        return true;
    }
    function ff9cq_clause(int $id, array $c): bool {
        if (isset($c['relation']) || isset($c[0])) {
            $rel = strtoupper($c['relation'] ?? 'AND'); $results = [];
            foreach ($c as $k => $sub) if ($k !== 'relation' && is_array($sub)) $results[] = ff9cq_clause($id, $sub);
            return $rel === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
        }
        $values = array_map('strval', $GLOBALS['ff9cq']['meta'][$id][$c['key']] ?? []);
        $cmp = strtoupper($c['compare'] ?? (is_array($c['value'] ?? null) ? 'IN' : '='));
        if ($cmp === 'NOT EXISTS') return !$values;
        if ($cmp === 'IN') return (bool)array_intersect($values, array_map('strval', (array)$c['value']));
        if ($cmp === '!=') return (bool)$values && !in_array((string)$c['value'], $values, true);
        return in_array((string)$c['value'], $values, true);
    }
    function get_posts($args) {
        $status = (array)($args['post_status'] ?? 'publish'); $out = [];
        foreach ($GLOBALS['ff9cq']['posts'] as $id => $post) {
            if ($post->post_type !== ($args['post_type'] ?? '') || !in_array($post->post_status, $status, true)) continue;
            if (!empty($args['meta_query']) && !ff9cq_clause($id, $args['meta_query'])) continue;
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
    class FF9CQWpdb {
        public $prefix = 'wp_'; public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts';
        public function prepare($sql, ...$args) { return ['sql' => $sql, 'args' => $args]; }
        public function get_var($q) {
            $sql = is_array($q) ? $q['sql'] : $q;
            if (stripos($sql, 'GET_LOCK(') !== false || stripos($sql, 'RELEASE_LOCK(') !== false) return 1;
            if (strpos($sql, 'routespro_projects') !== false) return ['77' => 9, '78' => 9][(string)$q['args'][0]] ?? 0;
            return null;
        }
        public function query($q) { return 1; }
        public function get_results($q, $mode = null) {
            $sql = is_array($q) ? $q['sql'] : $q;
            if (strpos($sql, 'routespro_product_cardex_items') !== false) return $GLOBALS['ff9cq']['cardex_items'][(int)$q['args'][0]] ?? [];
            return [];
        }
    }
    $GLOBALS['wpdb'] = new FF9CQWpdb();
}

namespace RoutesPro\Forms {
    class ProductCardex {
        public static function install(): void {}
        public static function list_cardex(): array { return array_values($GLOBALS['ff9cq']['cardex']); }
        public static function get_cardex(int $id): ?array { return $GLOBALS['ff9cq']['cardex'][$id] ?? null; }
        public static function table_items(): string { return 'wp_routespro_product_cardex_items'; }
    }
}

namespace RoutesPro\Support {
    class Config {
        public static $provider = 'azure';
        public static function get($k, $d = null) { return ['azure_openai_vision_deployment' => 'gpt-4o-vision', 'azure_openai_deployment' => 'gpt-4o-mini'][$k] ?? $d; }
        public static function aiProviderForModule($m = 'general', $o = null) { return self::$provider; }
        public static function aiProviderReady($p, $o = null) { return $p !== 'none'; }
    }
}

namespace {
    use RoutesPro\Catalog\ProductCatalogService as C;
    use RoutesPro\Vision\FrontsVisionService as V;
    use RoutesPro\Front\ThemeIsolation as T;
    define('FF9CQ_JPEG', base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gNzAK/9sAQwAKBwcIBwYKCAgICwoKCw4YEA4NDQ4dFRYRGCMfJSQiHyIhJis3LyYpNCkhIjBBMTQ5Oz4+PiUuRElDPEg3PT47/9sAQwEKCwsODQ4cEBAcOygiKDs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7/8AAEQgABAAEAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A52iiiuM+fP/Z'));
    function add_action($hook, $cb, $priority = 10, $args = 1) { $GLOBALS['ff9cq']['actions'][$hook][] = [$cb, $priority]; }
    function current_user_can($cap) { return !empty($GLOBALS['ff9cq']['manager']); }
    function esc_url_raw($v) { return (string)$v; }
    function get_current_user_id() { return (int)$GLOBALS['ff9cq']['user']; }
    class FF9CQStyle { public $handle; public $deps = []; public function __construct($h, $d = []) { $this->handle = $h; $this->deps = $d; } }
    class FF9CQStyles { public $queue = []; public $registered = []; public $inline = []; }
    $GLOBALS['ff9cq']['styles'] = new FF9CQStyles();
    function wp_styles() { return $GLOBALS['ff9cq']['styles']; }
    function wp_register_style($h, $src, $deps = [], $ver = null) { $GLOBALS['ff9cq']['styles']->registered[$h] = new FF9CQStyle($h, $deps); return true; }
    function wp_enqueue_style($h) { $GLOBALS['ff9cq']['styles']->queue[] = $h; }
    function wp_add_inline_style($h, $css) { $GLOBALS['ff9cq']['styles']->inline[$h] = $css; return true; }
    require $root . '/src/Catalog/ProductCatalogService.php';
    require $root . '/src/Vision/VisionUsageGuard.php';
    require $root . '/src/Vision/FrontsVisionService.php';
    require $root . '/src/Front/ThemeIsolation.php';

    // 1. Catalog: the same product written differently by each insignia (examples from the Pernod briefing).
    $line = static function (int $id, string $ref, string $name, $capacity, string $category = 'RTD'): array {
        return ['id' => $id, 'category' => $category, 'reference' => $ref, 'product_name' => $name, 'capacity_cl' => $capacity, 'meta_json' => json_encode(['managed_by' => 'briefing_weekly'])];
    };
    $GLOBALS['ff9cq']['cardex'] = [
        1 => ['id' => 1, 'client_id' => 9, 'project_id' => 77, 'insignia' => 'Continente', 'name' => 'Continente'],
        2 => ['id' => 2, 'client_id' => 9, 'project_id' => 77, 'insignia' => 'Pingo Doce', 'name' => 'Pingo Doce'],
        3 => ['id' => 3, 'client_id' => 9, 'project_id' => 77, 'insignia' => 'Auchan', 'name' => 'Auchan'],
        4 => ['id' => 4, 'client_id' => 9, 'project_id' => 77, 'insignia' => 'Intermarché', 'name' => 'Intermarché'],
        5 => ['id' => 5, 'client_id' => 9, 'project_id' => 77, 'insignia' => 'El Corte Inglés', 'name' => 'ECI'],
    ];
    $GLOBALS['ff9cq']['cardex_items'] = [
        1 => [$line(11, '9001', 'ABSOLUT PASSIONFRUIT MART.LATA 25CL SDR', '25'), $line(12, '9002', 'BEBIDA ESPIRITUOSA JAMESON ORANGE 70 CL', '70', 'WHISKEY'), $line(13, '9003', 'WHISKY JAMESON 70CL', '70', 'WHISKEY'), $line(14, '9004', 'JW BLK LABEL 70CL', '70', 'WHISKEY'), $line(15, '9005', 'GIN BEEFEATER 70CL', '70', 'GIN'), $line(16, '', 'WHISKY JAMESON STD 1L', '100', 'WHISKEY'), $line(17, '', 'WHISKY JAMESON 1L', '100', 'WHISKEY')],
        2 => [$line(21, '1001', 'ABSOLUT PASSIONFRUIT MARTINI LATA 25 CL', '0.25'), $line(22, '1002', 'JAMESON ORANGE 700', '', 'WHISKEY'), $line(23, '1003', 'JAMESON 70CL', '70', 'WHISKEY'), $line(24, '1004', 'JW BLACK LABEL 70CL', '70', 'WHISKEY'), $line(25, '1005', 'BEEFEATER PINK 70CL', '70', 'GIN')],
        3 => [$line(31, 'A1', 'ABSOLUT PASSIONFRUIT MARTINI RTD 25 cl', '25'), $line(32, 'A2', 'WHISKEY JAMESON STD 70CL', '70', 'WHISKEY'), $line(33, 'A3', 'CHIVAS 12 ANOS 70CL', '70', 'WHISKEY')],
        4 => [$line(41, 'I1', 'ABSOLUT PASSIONFRUIT MARTINI LATA 25 CL', '25'), $line(42, 'I2', 'CHIVAS REGAL 12Y 70CL', '70', 'WHISKEY')],
        5 => [$line(51, 'E1', 'ABSOLUT PASSIONFRUIT MARTINI RTD 25 cl', '25')],
    ];
    ff9cq_assert(C::capacityFromName('JAMESON ORANGE 700') === '70' && C::capacityFromName('ABSOLUT 1000') === '100' && C::capacityFromName('GLENLIVET 25') === '' && C::capacityFromName('CHIVAS 12') === '', 'bare_capacity');
    ff9cq_assert(C::nameSignature('BEBIDA ESPIRITUOSA JAMESON ORANGE 70 CL', '70') === C::nameSignature('JAMESON ORANGE 700', '70') && C::nameSignature('ABSOLUT PASSIONFRUIT MARTINI LATA 25 CL', '25') === C::nameSignature('ABSOLUT PASSIONFRUIT MARTINI RTD 25 cl', '25'), 'packaging_words');
    $tokens = static function (string $name): array { return C::signatureTokens($name, C::normalizeCapacity(null, $name)); };
    ff9cq_assert(C::variantScore($tokens('ABSOLUT PASSIONFRUIT MARTINI LATA 25 CL'), $tokens('ABSOLUT PASSIONFRUIT MART.LATA 25CL SDR')) > 0 && C::variantScore($tokens('JW BLK LABEL 70CL'), $tokens('JW BLACK LABEL 70CL')) > 0, 'abbreviations_and_codes');
    ff9cq_assert(C::variantScore($tokens('JAMESON 70CL'), $tokens('JAMESON ORANGE 70CL')) == 0 && C::variantScore($tokens('BEEFEATER 70CL'), $tokens('BEEFEATER PINK 70CL')) == 0 && C::variantScore($tokens('JW RED LABEL 70CL'), $tokens('JW BLACK LABEL 70CL')) == 0 && C::variantScore($tokens('CHIVAS 12 ANOS 70CL'), $tokens('CHIVAS 18 ANOS 70CL')) == 0, 'variants_kept_apart');
    $stats = C::syncFromCardex();
    $resolve = static function (string $ref, string $name, $capacity, string $scope): int { return C::resolveProductIdScoped(9, $ref, $name, $capacity, $scope); };
    $absolut = $resolve('9001', 'ABSOLUT PASSIONFRUIT MART.LATA 25CL SDR', '25', 'continente');
    ff9cq_assert($absolut > 0 && C::get($absolut)['cardex_ids'] === [1, 2, 3, 4, 5] && $resolve('E1', 'x', '25', 'el_corte_ingles') === $absolut, 'five_insignias_one_product ' . json_encode(C::get($absolut)['cardex_ids'] ?? null));
    $orange = $resolve('9002', 'x', '70', 'continente');
    ff9cq_assert($orange > 0 && $orange === $resolve('1002', 'y', null, 'pingo_doce') && $orange !== $resolve('9003', 'z', '70', 'continente'), 'jameson_orange_700');
    $jameson = $resolve('9003', 'WHISKY JAMESON 70CL', '70', 'continente');
    ff9cq_assert($jameson === $resolve('1003', 'x', '70', 'pingo_doce') && $jameson === $resolve('A2', 'x', '70', 'auchan'), 'std_code_joined');
    // Two lines of the same insignia are two products even without article codes (STD is a chain code, but both are listed).
    ff9cq_assert($resolve('', 'WHISKY JAMESON STD 1L', '100', 'continente') > 0 && $resolve('', 'WHISKY JAMESON STD 1L', '100', 'continente') !== $resolve('', 'WHISKY JAMESON 1L', '100', 'continente'), 'same_insignia_kept_apart');
    ff9cq_assert($resolve('9004', 'x', '70', 'continente') === $resolve('1004', 'x', '70', 'pingo_doce'), 'blk_black');
    ff9cq_assert($resolve('9005', 'x', '70', 'continente') !== $resolve('1005', 'x', '70', 'pingo_doce') && $resolve('A3', 'x', '70', 'auchan') !== $resolve('I2', 'x', '70', 'intermarche'), 'pink_and_regal_apart');
    ff9cq_assert($stats['items'] === 18 && $stats['groups'] === 10 && $stats['variants'] === 3 && count(C::clientIndex(9)) === 10, 'catalog_size ' . json_encode($stats));
    $again = C::syncFromCardex();
    ff9cq_assert($again['created'] === 0 && $again['merged'] === 0 && $again['groups'] === 10, 'idempotent ' . json_encode($again));
    ff9cq_assert(C::IDENTITY_VERSION === 3 && (int)get_option('fieldflow_catalog_identity_version') === 3 && C::identityCurrentFor(9), 'identity_version');
    $catalog = true;

    // 2. Camera on the question switch; client modes; reasons for managers.
    V::saveClientSettings(9, ['mode'=>'questions','monthly_budget_cents'=>300,'reservation_cents'=>100,'daily_limit'=>3,'monthly_limit'=>3]);
    $fronts = ['type' => 'product_matrix', 'camera_reading' => true];
    $share = ['type' => 'share_space_matrix'];
    ff9cq_assert(V::clientSettings(9)['mode'] === 'questions' && V::questionEnabled($fronts, ['client_id' => 9]) && !V::questionEnabled(['type' => 'product_matrix'], ['client_id' => 9]) && !V::questionEnabled($share, ['client_id' => 9]), 'question_switch_alone');
    update_option(V::OPTION, ['clients' => [21 => ['enabled' => true, 'share_space' => true, 'monthly_limit' => 300, 'reference_images' => 12]]]);
    ff9cq_assert(V::clientSettings(21)['mode'] === 'on' && !V::questionEnabled($share, ['client_id' => 21]) && V::clientEnabled(21), 'legacy_mode_preserved_but_cost_blocked');
    V::saveClientSettings(21, ['mode'=>'on','share_space'=>true,'monthly_budget_cents'=>100,'reservation_cents'=>100,'daily_limit'=>1,'monthly_limit'=>1]);
    ff9cq_assert(V::questionEnabled($share, ['client_id'=>21]), 'legacy_explicit_budget_allows_camera');
    V::saveClientSettings(9, ['mode' => 'off']);
    ff9cq_assert(!V::questionEnabled($fronts, ['client_id' => 9]) && strpos(V::questionBlockReason($fronts, ['client_id' => 9]), 'desligada') !== false && !V::fieldReferencesEnabled(9), 'client_off');
    V::saveClientSettings(9, ['mode' => 'questions']);
    \RoutesPro\Support\Config::$provider = 'none';
    ff9cq_assert(strpos(V::questionBlockReason($fronts, ['client_id' => 9]), 'fornecedor') !== false && strpos(V::questionBlockReason($fronts, ['client_id' => 0]), 'cliente') !== false, 'reasons');
    \RoutesPro\Support\Config::$provider = 'azure';
    V::saveClientSettings(9, ['mode' => 'questions', 'enabled' => 1, 'monthly_budget_cents'=>300,'reservation_cents'=>100,'daily_limit'=>3,'monthly_limit'=>3]);
    ff9cq_assert(V::clientSettings(9)['mode'] === 'questions' && !V::clientEnabled(9), 'mode_wins_over_enabled');
    $modes = true;

    // 3. Live counting: the frame is read and no photo is kept.
    $frame = sys_get_temp_dir() . '/ff9cq-live-' . getmypid() . '.jpg';
    file_put_contents($frame, FF9CQ_JPEG);
    V::$aiCaller = static function (string $module, string $prompt, array $params): string {
        return (string)wp_json_encode(['observations'=>[
            ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','visible_capacity'=>'70cl','box'=>[100,100,750,260],'confidence'=>.98,'front_row'=>true],
            ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','visible_capacity'=>'70cl','box'=>[100,310,750,470],'confidence'=>.98,'front_row'=>true],
            ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','visible_capacity'=>'70cl','box'=>[100,520,750,680],'confidence'=>.98,'front_row'=>true],
        ]]);
    };
    $request = ['client_id' => 9, 'location_id' => 345, 'question_key' => 'frentes', 'question_type' => 'product_matrix', 'items' => [['key' => 'r0', 'name' => 'WHISKY JAMESON 70CL', 'ref' => '9003', 'capacity' => '70', 'cardex_id' => 1]]];
    $live = V::read($request, ['path' => $frame, 'url' => '', 'capture' => 'live', 'live' => true], 7);
    $reading = V::reading($live['reading_id']);
    ff9cq_assert($live['items'][0]['fronts'] === 3 && $live['items'][0]['catalog_id'] === $jameson && $reading['capture'] === 'live' && $reading['photo_url'] === '' && (string)get_post_meta($live['reading_id'], '_ff_photo_path', true) === '', 'live_without_photo');
    $photo = V::read($request, ['path' => $frame, 'url' => '', 'capture' => 'gallery', 'live'=>true], 7);
    ff9cq_assert(V::reading($photo['reading_id'])['capture'] === 'gallery' && V::reading($photo['reading_id'])['photo_url'] === '', 'gallery_without_public_photo');
    try { V::read(['question_type' => 'share_space_matrix', 'items' => [['key' => 'c', 'pr_label' => 'Jameson', 'competitor_label' => 'X']]] + $request, ['path' => $frame], 7); ff9cq_fail('share_needs_on'); } catch (\RuntimeException $e) {}
    @unlink($frame);
    $live = true;

    // 4. Theme isolation: FieldFlow styles after the theme, no dependency loop, template colours in every state.
    $styles = wp_styles();
    foreach (['hello-elementor' => [], 'elementor-frontend' => [], 'routespro-form-renderer' => [], 'fieldflow-forms-runtime-v3' => ['routespro-form-renderer'], 'plugin-depends-on-ff' => ['routespro-form-renderer']] as $handle => $deps) { wp_register_style($handle, false, $deps); $styles->queue[] = $handle; }
    T::enqueue();
    $deps = $styles->registered['routespro-form-renderer']->deps;
    ff9cq_assert(in_array('hello-elementor', $deps, true) && in_array('elementor-frontend', $deps, true) && !in_array('plugin-depends-on-ff', $deps, true), 'fieldflow_after_theme ' . json_encode($deps));
    ff9cq_assert(in_array(T::HANDLE, $styles->queue, true) && in_array('routespro-form-renderer', $styles->registered[T::HANDLE]->deps, true) && strpos($styles->inline[T::HANDLE] ?? '', 'fieldflow-theme-isolation-v1') !== false, 'guard_last');
    // A FieldFlow style enqueued while the page renders (Elementor shortcode) is ordered at wp_footer too.
    wp_register_style('fieldflow-late-panel', false, []); $styles->queue[] = 'fieldflow-late-panel';
    T::enqueue();
    ff9cq_assert(in_array('hello-elementor', $styles->registered['fieldflow-late-panel']->deps, true) && in_array('fieldflow-late-panel', $styles->registered[T::HANDLE]->deps, true) && count(array_keys($styles->queue, T::HANDLE, true)) === 1, 'late_styles');
    T::registerHooks();
    ff9cq_assert(($GLOBALS['ff9cq']['actions']['wp_enqueue_scripts'][0][1] ?? 0) === 9999 && ($GLOBALS['ff9cq']['actions']['wp_footer'][0][1] ?? 0) === 5, 'hooks');
    $css = T::css();
    foreach (['.rp-form-ui button.routespro-form-btn:is(:hover,:focus,:active)', '.rp-form-ui button.routespro-form-btn.secondary:is(:hover,:focus,:active)', 'button.rp-btn:is(:hover,:focus,:active)', 'a:not([class])', 'var(--rp-form-primary'] as $needle) ff9cq_assert(strpos($css, $needle) !== false, 'guard_rule ' . $needle);
    ff9cq_assert(stripos($css, '#c36') === false && stripos($css, 'magenta') === false, 'no_theme_colour');
    $theme = true;

    // 5. Static contracts of the front runtime and back office.
    $read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
    $renderer = $read('src/Forms/FormRenderer.php');
    $vision = $read('src/Vision/FrontsVisionService.php');
    $admin = $read('src/Admin/FrontsVisionAdmin.php');
    ff9cq_assert(strpos($renderer, 'function rpVisionLiveStart(){') !== false && strpos($renderer, 'data-vision-live') !== false && strpos($renderer, "fd.append('capture',capture||'camera')") !== false && strpos($renderer, 'data-vision-shoot="camera"') !== false && strpos($renderer, 'data-vision-shoot="gallery"') !== false, 'three_options');
    ff9cq_assert(strpos($renderer, 'private static function fronts_vision_admin_hint(array $q, array $context, string $attrs): string {') !== false && strpos($renderer, 'function rpFrontsDecorateDynamicRow($row,$box){') !== false && substr_count($renderer, 'routespro-category-chevron" aria-hidden="true"></span>') === 0, 'front_fixes');
    ff9cq_assert(strpos($vision, "self::livePhoto(\$_FILES['photo'] ?? null)") !== false && strpos($vision, 'is_uploaded_file($path)') !== false && strpos($vision, 'wp_ajax_nopriv') === false, 'live_guarded');
    ff9cq_assert(strpos($admin, "'questions' => 'Perguntas autorizadas'") !== false && strpos($admin, "check_admin_referer('fieldflow_vision_settings', 'fieldflow_vision_nonce');") !== false, 'admin_modes');
    $noSchema = stripos($vision . $read('src/Catalog/ProductCatalogService.php') . $read('src/Front/ThemeIsolation.php'), 'CREATE TABLE') === false;
    ff9cq_assert($noSchema, 'no_schema');
    $historical = strpos($vision, "public const REFERENCE_CONTRACT = 'fieldflow-fronts-field-reference-v1';") !== false && strpos($read('src/Catalog/ProductCatalogService.php'), '// 9CP marker: public const IDENTITY_VERSION = 2;') !== false && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false;
    ff9cq_assert($historical, 'historical_continuity');

    echo 'FRONT THEME CAMERA LIVE 9CQ RUNTIME OK | insignia_variants=1 | question_switch=1 | client_modes=1 | live_without_photo=1 | theme_isolation=1 | front_fixes=1 | guarded=1 | no_schema=1 | historical_continuity=1' . "\n";
}
