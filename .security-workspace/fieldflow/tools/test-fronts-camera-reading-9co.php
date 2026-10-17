<?php
// 9CO runtime test: camera fronts reading (provider multimodal payloads, reading flow, limits, confirmation, history).
// Runs the real ProductCatalogService against an in-memory WordPress post/meta stub (no database, no bootstrap).
namespace {
    $root = dirname(__DIR__);
    define('ABSPATH', '/tmp/');
    define('ARRAY_A', 'ARRAY_A');
    $GLOBALS['ff9co'] = ['posts' => [], 'meta' => [], 'next' => 100, 'options' => [], 'attachments' => [501 => true, 502 => true, 503 => true], 'user' => 7, 'transients' => []];
    function ff9co_fail(string $reason): void { fwrite(STDERR, "FRONTS CAMERA READING 9CO RUNTIME FAIL: {$reason}\n"); exit(1); }
    function ff9co_assert(bool $ok, string $reason): void { if (!$ok) ff9co_fail($reason); }
    function wp_strip_all_tags($v) { return strip_tags((string)$v); }
    function remove_accents($v) { return strtr((string)$v, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','É'=>'E','Ó'=>'O','Ç'=>'C']); }
    function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$v))); }
    function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$v)); }
    function absint($v) { return abs((int)$v); }
    function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }
    function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
    function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
    function current_time($t) { return $t === 'mysql' ? '2026-10-08 10:00:00' : date($t, strtotime('2026-10-08 10:00:00')); }
    function update_option($k, $v, $a = null) { $GLOBALS['ff9co']['options'][$k] = $v; return true; }
    function get_option($k, $d = false) { return $GLOBALS['ff9co']['options'][$k] ?? $d; }
    function post_type_exists($t) { return !empty($GLOBALS['ff9co']['types'][$t]); }
    function register_post_type($t, $args) { $GLOBALS['ff9co']['types'][$t] = $args; }
    function is_wp_error($v) { return false; }
    function wp_attachment_is_image($id) { return !empty($GLOBALS['ff9co']['attachments'][(int)$id]); }
    function wp_get_attachment_image_src($id, $size) { return wp_attachment_is_image($id) ? ['https://example.test/img-' . (int)$id . '-' . $size . '.jpg', 150, 150] : false; }
    function wp_insert_post($data, $err = false) { $id = $GLOBALS['ff9co']['next']++; $GLOBALS['ff9co']['posts'][$id] = (object)['ID' => $id, 'post_type' => $data['post_type'], 'post_status' => $data['post_status'] ?? 'publish', 'post_title' => wp_unslash($data['post_title'] ?? ''), 'post_date' => '2026-10-08 10:00:00']; return $id; }
    function wp_update_post($data) { $p = $GLOBALS['ff9co']['posts'][(int)$data['ID']] ?? null; if ($p && isset($data['post_title'])) $p->post_title = wp_unslash($data['post_title']); return $p ? $p->ID : 0; }
    function wp_trash_post($id) { if (isset($GLOBALS['ff9co']['posts'][$id])) $GLOBALS['ff9co']['posts'][$id]->post_status = 'trash'; }
    function get_post($id) { return $GLOBALS['ff9co']['posts'][(int)$id] ?? null; }
    function get_the_title($id) { return (string)(get_post($id)->post_title ?? ''); }
    function get_post_meta($id, $key = '', $single = false) { $values = $GLOBALS['ff9co']['meta'][(int)$id][$key] ?? []; return $single ? ($values[0] ?? '') : $values; }
    function update_post_meta($id, $key, $value) { $GLOBALS['ff9co']['meta'][(int)$id][$key] = [wp_unslash($value)]; return true; }
    function add_post_meta($id, $key, $value) { $GLOBALS['ff9co']['meta'][(int)$id][$key][] = wp_unslash($value); return true; }
    function delete_post_meta($id, $key, $value = '') {
        if ($value === '') { unset($GLOBALS['ff9co']['meta'][(int)$id][$key]); return true; }
        $GLOBALS['ff9co']['meta'][(int)$id][$key] = array_values(array_filter($GLOBALS['ff9co']['meta'][(int)$id][$key] ?? [], static function($v) use ($value) { return (string)$v !== (string)$value; }));
        return true;
    }
    function ff9co_clause(int $id, array $c): bool {
        if (isset($c['relation']) || isset($c[0])) {
            $rel = strtoupper($c['relation'] ?? 'AND'); $results = [];
            foreach ($c as $k => $sub) if ($k !== 'relation' && is_array($sub)) $results[] = ff9co_clause($id, $sub);
            return $rel === 'OR' ? in_array(true, $results, true) : !in_array(false, $results, true);
        }
        $values = array_map('strval', $GLOBALS['ff9co']['meta'][$id][$c['key']] ?? []);
        $cmp = strtoupper($c['compare'] ?? (is_array($c['value'] ?? null) ? 'IN' : '='));
        if ($cmp === 'NOT EXISTS') return !$values;
        if ($cmp === 'IN') return (bool)array_intersect($values, array_map('strval', (array)$c['value']));
        if ($cmp === '!=') return (bool)$values && !in_array((string)$c['value'], $values, true);
        return in_array((string)$c['value'], $values, true);
    }
    function get_posts($args) {
        $status = (array)($args['post_status'] ?? 'publish'); $out = [];
        foreach ($GLOBALS['ff9co']['posts'] as $id => $post) {
            if ($post->post_type !== ($args['post_type'] ?? '') || !in_array($post->post_status, $status, true)) continue;
            if (!empty($args['meta_query']) && !ff9co_clause($id, $args['meta_query'])) continue;
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
            $this->posts = array_slice($all, (($args["paged"] ?? 1) - 1) * $per, $per);
        }
    }
    class FF9COWpdb {
        public $lockEnabled = true;
        public $prefix = 'wp_'; public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts';
        public function prepare($q, ...$a) { return ['sql' => $q, 'args' => $a]; }
        public function get_var($q) {
            $sql = is_array($q) ? $q['sql'] : (string)$q;
            if (stripos($sql, 'GET_LOCK(') !== false) return $this->lockEnabled ? 1 : 0;
            if (stripos($sql, 'RELEASE_LOCK(') !== false) return 1;
            return null;
        }
        public function query($q) { return 1; }
        public function get_results($q, $m = null) { return []; }
        public function get_row($q, $m = null) {
            if (!is_array($q) || stripos($q['sql'], 'routespro_form_submissions') === false) return null;
            $id = (int)($q['args'][0] ?? 0);
            return in_array($id, [900,901], true) ? ['id'=>$id, 'client_id'=>9, 'project_id'=>77, 'location_id'=>345, 'form_id'=>22, 'route_id'=>31, 'route_stop_id'=>44, 'user_id'=>$id===900?7:8, 'report_day'=>'2026-10-08', 'submitted_at'=>'2026-10-08 10:00:00'] : null;
        }
    }
    $GLOBALS['wpdb'] = new FF9COWpdb();
    define('FF9CO_JPEG', base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gNzAK/9sAQwAKBwcIBwYKCAgICwoKCw4YEA4NDQ4dFRYRGCMfJSQiHyIhJis3LyYpNCkhIjBBMTQ5Oz4+PiUuRElDPEg3PT47/9sAQwEKCwsODQ4cEBAcOygiKDs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7/8AAEQgABAAEAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A52iiiuM+fP/Z'));
    $GLOBALS['ff9co']['photo'] = sys_get_temp_dir() . '/ff9co-shelf-' . getmypid() . '.jpg';
    $GLOBALS['ff9co']['ref'] = sys_get_temp_dir() . '/ff9co-ref-' . getmypid() . '.jpg';
    file_put_contents($GLOBALS['ff9co']['photo'], FF9CO_JPEG);
    file_put_contents($GLOBALS['ff9co']['ref'], FF9CO_JPEG);
    function get_attached_file($id) { return $GLOBALS['ff9co']['ref']; }
    function wp_upload_dir() { return ['basedir' => sys_get_temp_dir(), 'baseurl' => 'https://ff.test/uploads']; }
    function trailingslashit($v) { return rtrim((string)$v, '/') . '/'; }
    function esc_url_raw($v) { return (string)$v; }
    function get_transient($k) { return $GLOBALS['ff9co']['transients'][$k] ?? false; }
    function set_transient($k, $v, $t = 0) { $GLOBALS['ff9co']['transients'][$k] = $v; return true; }
    function get_current_user_id() { return (int)$GLOBALS['ff9co']['user']; }
    function is_wp_error_stub() {}
}
namespace RoutesPro\Forms {
    class Forms { public static function table_submissions(): string {return 'wp_routespro_form_submissions';} }
}
namespace RoutesPro\Support {
    class Config {
        public static $o = ['azure_openai_endpoint' => 'https://ffres.openai.azure.com', 'azure_openai_deployment' => 'gpt-4o-mini-text', 'azure_openai_key' => 'k', 'azure_openai_vision_deployment' => 'gpt-4o-vision', 'google_ai_key' => 'g', 'openai_api_key' => 'o'];
        public static $provider = 'azure';
        public static function get($k, $d = null) { return self::$o[$k] ?? $d; }
        public static function aiProviderForModule($m = 'general', $o = null) { return self::$provider; }
        public static function aiProviderReady($p, $o = null) { return $p !== 'none'; }
    }
}
namespace RoutesPro\Security {
    class OutboundHttp {
        public static $calls = [];
        public static $reply = '';
        public static function post($url, $args = [], $policy = []) { self::$calls[] = ['url' => $url, 'payload' => json_decode($args['body'], true), 'headers' => $args['headers']]; return ['status' => 200, 'body' => self::$reply]; }
        public static function configuredPolicy($endpoint, $purpose, $extra = []) { return []; }
    }
}
namespace {
    function wp_remote_retrieve_response_code($r) { return $r['status']; }
    function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
    function wp_remote_retrieve_body($r) { return $r['body']; }
    require $root . '/src/Catalog/ProductCatalogService.php';
    require $root . '/src/Services/AI.php';
    require $root . '/src/Vision/VisionUsageGuard.php';
    require $root . '/src/Vision/FrontsVisionService.php';
    use RoutesPro\Catalog\ProductCatalogService as C;
    use RoutesPro\Vision\FrontsVisionService as V;
    use RoutesPro\Security\OutboundHttp as H;

    // 1. Providers: images become a multimodal payload; without images the payload is unchanged.
    $image = ['mime' => 'image/jpeg', 'data' => base64_encode(FF9CO_JPEG), 'label' => 'Fotografia da prateleira a contar:', 'detail' => 'high'];
    H::$reply = json_encode(['choices' => [['message' => ['content' => 'ok']]]]);
    $azure = new \RoutesPro\Services\AzureOpenAIProvider();
    $azure->complete('texto', []);
    $plain = H::$calls[0]['payload'];
    ff9co_assert($plain['model'] === 'gpt-4o-mini-text' && $plain['messages'][1]['content'] === 'texto' && strpos(H::$calls[0]['url'], '/openai/v1/chat/completions') !== false, 'azure_text_unchanged');
    H::$calls = [];
    (new \RoutesPro\Services\AzureOpenAIProvider())->complete('contar', ['images' => [$image, ['mime' => 'image/gif', 'data' => 'AAAA'], ['mime' => 'image/png', 'data' => 'not base64!']], 'deployment' => 'gpt-4o-vision']);
    $vision = H::$calls[0]['payload'];
    $content = $vision['messages'][1]['content'];
    ff9co_assert($vision['model'] === 'gpt-4o-vision' && is_array($content) && $content[0] === ['type' => 'text', 'text' => 'contar'] && $content[1]['text'] === 'Fotografia da prateleira a contar:' && $content[2]['type'] === 'image_url' && strpos($content[2]['image_url']['url'], 'data:image/jpeg;base64,/9j/') === 0 && $content[2]['image_url']['detail'] === 'high' && count($content) === 3, 'azure_vision_payload');
    $ref = new \ReflectionMethod(\RoutesPro\Services\AzureOpenAIProvider::class, 'responsesVisionContent'); $ref->setAccessible(true);
    $responses = $ref->invoke(new \RoutesPro\Services\AzureOpenAIProvider(), 'contar', [$image]);
    ff9co_assert($responses[0]['type'] === 'input_text' && $responses[2]['type'] === 'input_image' && strpos($responses[2]['image_url'], 'data:image/jpeg;base64,') === 0, 'azure_responses_payload');
    H::$calls = []; H::$reply = json_encode(['candidates' => [['content' => ['parts' => [['text' => '{}']]]]]]);
    (new \RoutesPro\Services\GoogleAIProvider())->complete('texto', []);
    (new \RoutesPro\Services\GoogleAIProvider())->complete('contar', ['images' => [$image], 'json' => true]);
    $gPlain = H::$calls[0]['payload']; $gVision = H::$calls[1]['payload'];
    ff9co_assert($gPlain['contents'][0]['parts'] === [['text' => 'texto']] && !isset($gPlain['generationConfig']['responseMimeType']), 'gemini_text_unchanged');
    ff9co_assert($gVision['contents'][0]['parts'][2]['inline_data']['mime_type'] === 'image/jpeg' && $gVision['generationConfig']['responseMimeType'] === 'application/json' && H::$calls[1]['headers']['x-goog-api-key'] === 'g', 'gemini_vision_payload');
    H::$calls = []; H::$reply = json_encode(['choices' => [['message' => ['content' => 'ok']]]]);
    (new \RoutesPro\Services\OpenAIProvider())->complete('contar', ['images' => [$image]]);
    ff9co_assert(H::$calls[0]['payload']['messages'][1]['content'][2]['type'] === 'image_url', 'openai_vision_payload');
    $providers = true;

    // 2. Catalog with reference images; pilot client 9 enabled, client 12 not.
    $jameson = C::save(0, ['client_id' => 9, 'name' => 'Jameson Original 70cl', 'reference' => '2050501', 'capacity_cl' => '70', 'image_ids' => [501]]);
    $tullamore = C::save(0, ['client_id' => 9, 'name' => 'Tullamore D.E.W. 70cl', 'capacity_cl' => '70', 'kind' => 'competitor', 'image_ids' => [503]]);
    C::mapLabel(9, 'Jameson', [$jameson]);
    C::mapLabel(9, 'Tullamore', [$tullamore]);
    V::saveClientSettings(9, ['enabled' => 1, 'share_space' => 1, 'monthly_limit' => 2, 'daily_limit'=>2, 'monthly_budget_cents'=>50, 'reservation_cents'=>25, 'reference_images' => 12]);
    // 9CQ: the question switch alone shows the camera; a client is only excluded when set to "off".
    V::saveClientSettings(12, ['mode' => 'off']);
    ff9co_assert(V::clientEnabled(9) && !V::clientEnabled(12) && V::clientSettings(12)['enabled'] === false, 'off_by_default');
    ff9co_assert(V::questionEnabled(['type' => 'product_matrix', 'camera_reading' => true], ['client_id' => 9]) && !V::questionEnabled(['type' => 'product_matrix'], ['client_id' => 9]) && !V::questionEnabled(['type' => 'product_matrix', 'camera_reading' => true], ['client_id' => 12]) && V::questionEnabled(['type' => 'share_space_matrix'], ['client_id' => 9]), 'question_switches');
    ff9co_assert(V::providerStatus()['model'] === 'gpt-4o-vision' && V::providerStatus()['boxes'] === true, 'azure_vision_deployment');
    $pilot = true;

    // 3. Reading flow (9CY): the AI sees the shelf only, then PHP resolves SKUs.
    $captured = [];
    V::$aiCaller = static function (string $module, string $prompt, array $params) use (&$captured): string {
        $captured[] = ['module'=>$module, 'prompt'=>$prompt, 'params'=>$params];
        return (string)wp_json_encode(['observations'=>[
            ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','visible_capacity'=>'70CL','box'=>[100,50,650,180],'confidence'=>.98,'front_row'=>true],
            ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','visible_capacity'=>'70CL','box'=>[100,190,650,320],'confidence'=>.98,'front_row'=>true],
            ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','visible_capacity'=>'70CL','box'=>[100,330,650,460],'confidence'=>.98,'front_row'=>true],
            ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','visible_capacity'=>'70CL','box'=>[100,470,650,600],'confidence'=>.98,'front_row'=>true],
            ['visible_text'=>'JAMESON BLACK BARREL','visible_brand'=>'JAMESON','visible_capacity'=>'70CL','box'=>[100,610,650,740],'confidence'=>.99,'front_row'=>true],
            ['visible_text'=>'JAMESON BLACK BARREL','visible_brand'=>'JAMESON','visible_capacity'=>'70CL','box'=>[100,750,650,900],'confidence'=>.99,'front_row'=>true],
        ]]);
    };
    $request = ['client_id' => 9, 'project_id' => 77, 'location_id' => 345, 'route_id'=>31, 'stop_id'=>44, 'form_id'=>22, 'date' => '2026-10-08', 'question_key' => 'frentes', 'question_type' => 'product_matrix', 'scope_label' => 'WHISKEY', 'items' => [
        ['key' => 'r0', 'name' => 'JAMESON 70CL', 'ref' => '205-0501', 'capacity' => '70'],
        ['key' => 'r1', 'name' => 'JAMESON BLACK BARREL 70CL', 'ref' => '2050502', 'capacity' => '70'],
        ['key' => 'r2', 'name' => '', 'ref' => 'X'],
    ]];
    $result = V::read($request, ['path' => $GLOBALS['ff9co']['photo'], 'url' => 'https://ff.test/uploads/ff-vision-a.jpg'], 7);
    $call = $captured[0];
    ff9co_assert($call['module'] === 'fronts_vision' && $call['params']['deployment'] === 'gpt-4o-vision' && $call['params']['client_id'] === 9 && $call['params']['feature_key'] === 'fronts_vision', 'module_and_attribution');
    ff9co_assert(count($call['params']['images']) === 1
        && $call['params']['images'][0]['detail'] === 'high', 'single_shelf_image');
    ff9co_assert(strpos($call['prompt'],'JAMESON') === false
        && strpos($call['prompt'],'BLACK BARREL') === false
        && strpos($call['prompt'],'visible_text') !== false, 'no_sku_priming');
    ff9co_assert($result['items'][0]['key'] === 'r0' && $result['items'][0]['fronts'] === 4
        && $result['items'][1]['fronts'] === null && $result['items'][1]['model_fronts'] === 2
        && $result['items'][1]['review_reason'] === 'variant_manual_verification'
        && $result['items'][1]['boxes'] === []
        && count($result['items']) === 2 && $result['unidentified'] === 0, 'blind_matched_result');
    $reading = V::reading($result['reading_id']);
    ff9co_assert($reading['location_id'] === 345 && $reading['status'] === 'detected'
        && $reading['provider'] === 'azure'
        && $reading['photo_url'] === 'https://ff.test/uploads/ff-vision-a.jpg'
        && strpos($reading['detected']['notes'],'lista de produtos') !== false, 'stored_per_store');
    $missing = V::parseResult('{"items":[{"code":"P1","fronts":0}]}', [
        ['code'=>'P1','key'=>'r0','name'=>'Jameson'],
        ['code'=>'P2','key'=>'r1','name'=>'Absolut'],
    ]);
    ff9co_assert($missing['items'][0]['fronts'] === 0 && $missing['items'][0]['needs_review'] === true
        && $missing['items'][1]['fronts'] === null && $missing['items'][1]['needs_review'] === true
        && $missing['items'][1]['recognition_status'] === 'unrecognised', 'unrecognised_not_zero'); // 9CU low-confidence zero remains explicit but needs human review.
    ff9co_assert($call['params']['max_attempts'] === 1 && $call['params']['disable_model_fallback'] === true
        && $call['params']['force_provider'] === 'azure', 'single_billable_attempt');
    $flow = true;

    // 4. Confirmation: only the author confirms; corrections are counted.
    $GLOBALS['ff9co']['user'] = 8;
    try { V::confirm($result['reading_id'], [['key' => 'r0', 'fronts' => 5]], 8); ff9co_fail('confirm_other_user'); } catch (\RuntimeException $e) {}
    try { V::confirm($result['reading_id'], [['key' => 'r0', 'fronts' => 5], ['key' => 'r1', 'fronts' => 2], ['key' => 'zz', 'fronts' => 9]], 7); ff9co_fail('extra_product_confirmation'); } catch (\RuntimeException $e) {}
    $confirm = V::confirm($result['reading_id'], [['key' => 'r0', 'fronts' => 5], ['key' => 'r1', 'fronts' => 2]], 7);
    $reading = V::reading($result['reading_id']);
    ff9co_assert($confirm['confirmed'] === 2 && $confirm['corrections'] === 2 && $reading['status'] === 'confirmed' && $reading['confirmed'][0] === ['key' => 'r0', 'fronts' => 5, 'detected' => 4], 'confirmation');
    $linkedLater = true;
    ff9co_assert(V::confirm($result['reading_id'], [['key' => 'r0', 'fronts' => 5], ['key' => 'r1', 'fronts' => 2]], 7)['already_confirmed'] === true, 'confirm_retry_idempotent');
    try { V::confirm($result['reading_id'], [['key' => 'r0', 'fronts' => 6], ['key' => 'r1', 'fronts' => 2]], 7); ff9co_fail('double_confirm_conflict'); } catch (\RuntimeException $e) {}
    $confirmation = true;

    // 5. Share Space: both sides through the briefing label mapping, label fallback without mapping.
    $captured = [];
    V::$aiCaller = static function (string $module, string $prompt, array $params) use (&$captured): string {
        $captured[] = $prompt;
        return (string)wp_json_encode(['observations'=>[
            ['visible_text'=>'Jameson','visible_brand'=>'Jameson','box'=>[100,100,650,200],'confidence'=>.99,'front_row'=>true],
            ['visible_text'=>'Tullamore','visible_brand'=>'Tullamore','box'=>[100,300,650,410],'confidence'=>.99,'front_row'=>true],
            ['visible_text'=>'Teeling','visible_brand'=>'Teeling','box'=>[100,500,650,610],'confidence'=>.99,'front_row'=>true],
        ]]);
    };
    $share = V::read(['client_id' => 9, 'location_id' => 345, 'question_key' => 'share', 'question_type' => 'share_space_matrix', 'items' => [
        ['key' => 'jameson_vs_tullamore', 'pr_label' => 'Jameson', 'competitor_label' => 'Tullamore'],
        ['key' => 'bb_vs_teeling', 'pr_label' => '', 'competitor_label' => 'Teeling'],
    ]], ['path' => $GLOBALS['ff9co']['photo'], 'url' => ''], 7);
    $sides = array_map(static function ($i) { return $i['card'] . '|' . $i['side'] . '|' . $i['catalog_id']; }, $share['items']);
    ff9co_assert($sides === ['jameson_vs_tullamore|pr|' . $jameson, 'jameson_vs_tullamore|competitor|' . $tullamore, 'bb_vs_teeling|competitor|0'] && strpos($captured[0], 'Tullamore') === false, 'share_space_items');
    $shareSpace = true;
    ff9co_assert(V::linkSubmission(900, ['location_id' => 345], []) === 1 && V::linkSubmission(901, ['location_id' => 345], []) === 0 && V::reading($share['reading_id'])['submission_id'] === 0, 'link_submission_same_store_day');

    // 6. Limits and robustness.
    try { V::read($request, ['path' => $GLOBALS['ff9co']['photo'], 'url' => ''], 7); ff9co_fail('monthly_limit'); } catch (\RuntimeException $e) { ff9co_assert(stripos($e->getMessage(), 'limite mensal') !== false, 'monthly_limit_message'); }
    try { V::read(['client_id' => 12] + $request, ['path' => $GLOBALS['ff9co']['photo']], 7); ff9co_fail('client_off'); } catch (\RuntimeException $e) {}
    V::saveClientSettings(9, ['enabled'=>1,'share_space'=>1,'monthly_limit'=>10,'daily_limit'=>10,'monthly_budget_cents'=>1000,'reservation_cents'=>100]);
    $budgetBefore=\RoutesPro\Vision\VisionUsageGuard::summary(9)['monthly_requests'];
    $GLOBALS['wpdb']->lockEnabled = false;
    try { V::read($request, ['path' => $GLOBALS['ff9co']['photo'], 'url' => ''], 7); ff9co_fail('advisory_lock_must_fail_closed'); }
    catch (\RuntimeException $e) { ff9co_assert(strpos($e->getMessage(), 'quota') !== false, 'advisory_lock_error'); }
    $GLOBALS['wpdb']->lockEnabled = true;
    ff9co_assert(\RoutesPro\Vision\VisionUsageGuard::summary(9)['monthly_requests'] === $budgetBefore, 'lock_failure_not_billable');
    V::saveClientSettings(9, ['enabled' => 1, 'share_space' => 1, 'monthly_limit' => 0, 'reference_images' => 0]);
    try { V::read($request, ['path' => __FILE__], 7); ff9co_fail('photo_validation'); } catch (\RuntimeException $e) {}
    try { V::parseResult('sem json', [['key' => 'a', 'code' => 'P1', 'name' => 'x']]); ff9co_fail('parse_invalid'); } catch (\RuntimeException $e) {}
    $boxes = V::parseResult('{"items":[{"code":"P1","fronts":-3,"boxes":[[10,20,300,120],[5,5,5,5],[1,2,3]]}]}', [['key' => 'a', 'code' => 'P1', 'name' => 'x']]);
    ff9co_assert($boxes['items'][0]['fronts'] === null && $boxes['items'][0]['needs_review'] === true && $boxes['items'][0]['boxes'] === [[10, 20, 300, 120]], 'parse_clamps_and_boxes');
    \RoutesPro\Support\Config::$provider = 'none';
    ff9co_assert(!V::questionEnabled(['type' => 'product_matrix', 'camera_reading' => true], ['client_id' => 9]), 'no_provider_no_button');
    $limits = true;

    // 7. Static contracts.
    $read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
    $service = $read('src/Vision/FrontsVisionService.php');
    $renderer = $read('src/Forms/FormRenderer.php');
    ff9co_assert(strpos($service, "if (!is_user_logged_in()) self::fail(") !== false && strpos($service, "check_ajax_referer(self::NONCE, 'nonce', false)") !== false && strpos($service, '$scopeError = self::scopeError($request, $userId);') !== false && strpos($service, 'self::rateLimit($userId);') !== false, 'ajax_guarded');
    ff9co_assert(strpos($service, "add_action('wp_ajax_' . self::AJAX_READ") !== false && strpos($service, 'wp_ajax_nopriv') === false, 'logged_in_only');
    ff9co_assert(strpos($renderer, "data-fronts-camera=\"'.esc_attr(\$key).'\"") !== false && strpos($renderer, 'function rpVisionFill(){') !== false && strpos($renderer, "fd.append('action','fieldflow_fronts_vision_read')") !== false && strpos($renderer, 'data-vision-fill') !== false, 'front_runtime');
    $admin = $read('src/Admin/FrontsVisionAdmin.php');
    ff9co_assert(strpos($admin, "check_admin_referer('fieldflow_vision_settings', 'fieldflow_vision_nonce');") !== false && strpos($admin, "check_admin_referer('fieldflow_vision_test', 'fieldflow_vision_nonce');") !== false, 'admin_guarded');
    ff9co_assert(stripos($service . $admin, 'CREATE TABLE') === false, 'no_schema');
    ff9co_assert(strpos($renderer, "public const FRONTS_VISUAL_CONTRACT = 'fieldflow-fronts-mobile-catalog-visual-v1';") !== false && strpos($read('src/Catalog/ProductCatalogService.php'), "public const REUSE_CONTRACT = 'fieldflow-catalog-cross-client-reuse-v1';") !== false && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false, 'historical_continuity');
    @unlink($GLOBALS['ff9co']['photo']); @unlink($GLOBALS['ff9co']['ref']);

    echo 'FRONTS CAMERA READING 9CO RUNTIME OK | multimodal_providers=1 | off_by_default=1 | reading_flow=1 | confirmation=1 | share_space=1 | limits=1 | guarded=1 | no_schema=1 | historical_continuity=1' . "\n";
}
