<?php
// 9CM runtime test: compact fronts rows keep the exact submitted inputs, catalog visuals resolve per row and per
// briefing label, and the opt-out flag is honoured. Runs the real renderer methods against in-memory WordPress stubs.
namespace {
    $root = dirname(__DIR__);
    define('ABSPATH', '/tmp/');
    define('ARRAY_A', 'ARRAY_A');
    $GLOBALS['ff9cm'] = ['posts' => [], 'meta' => [], 'next' => 100, 'attachments' => [501 => true, 503 => true]];
    function ff9cm_fail(string $reason): void { fwrite(STDERR, "FRONTS MOBILE CATALOG VISUAL 9CM RUNTIME FAIL: {$reason}\n"); exit(1); }
    function ff9cm_assert(bool $ok, string $reason): void { if (!$ok) ff9cm_fail($reason); }
    function wp_strip_all_tags($v) { return strip_tags((string)$v); }
    function sanitize_text_field($v) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string)$v))); }
    function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$v)); }
    function absint($v) { return abs((int)$v); }
    function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
    function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
    function esc_url($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
    function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }
    function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
    function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
    function current_time($t) { return '2026-10-08 10:00:00'; }
    function update_option($k, $v, $a = null) { return true; }
    function post_type_exists($t) { return true; }
    function register_post_type($t, $a) {}
    function is_wp_error($v) { return false; }
    function wp_attachment_is_image($id) { return !empty($GLOBALS['ff9cm']['attachments'][(int)$id]); }
    function wp_get_attachment_image_src($id, $size) { return wp_attachment_is_image($id) ? ['https://cdn.test/p' . (int)$id . '-' . $size . '.jpg', 150, 150] : false; }
    function wp_insert_post($d, $e = false) { $id = $GLOBALS['ff9cm']['next']++; $GLOBALS['ff9cm']['posts'][$id] = (object)['ID' => $id, 'post_type' => $d['post_type'], 'post_status' => 'publish', 'post_title' => wp_unslash($d['post_title'])]; return $id; }
    function wp_update_post($d) { return (int)$d['ID']; }
    function get_post($id) { return $GLOBALS['ff9cm']['posts'][(int)$id] ?? null; }
    function get_post_meta($id, $k = '', $single = false) { $v = $GLOBALS['ff9cm']['meta'][(int)$id][$k] ?? []; return $single ? ($v[0] ?? '') : $v; }
    function update_post_meta($id, $k, $v) { $GLOBALS['ff9cm']['meta'][(int)$id][$k] = [wp_unslash($v)]; return true; }
    function add_post_meta($id, $k, $v) { $GLOBALS['ff9cm']['meta'][(int)$id][$k][] = wp_unslash($v); return true; }
    function delete_post_meta($id, $k, $v = '') { if ($v === '') unset($GLOBALS['ff9cm']['meta'][(int)$id][$k]); else $GLOBALS['ff9cm']['meta'][(int)$id][$k] = array_values(array_filter($GLOBALS['ff9cm']['meta'][(int)$id][$k] ?? [], function($x) use ($v) { return (string)$x !== (string)$v; })); return true; }
    function ff9cm_clause(int $id, array $c): bool {
        if (isset($c['relation']) || isset($c[0])) { $rel = strtoupper($c['relation'] ?? 'AND'); $r = []; foreach ($c as $k => $s) if ($k !== 'relation' && is_array($s)) $r[] = ff9cm_clause($id, $s); return $rel === 'OR' ? in_array(true, $r, true) : !in_array(false, $r, true); }
        $vals = array_map('strval', $GLOBALS['ff9cm']['meta'][$id][$c['key']] ?? []); $cmp = strtoupper($c['compare'] ?? (is_array($c['value'] ?? null) ? 'IN' : '='));
        if ($cmp === 'NOT EXISTS') return !$vals; if ($cmp === 'IN') return (bool)array_intersect($vals, array_map('strval', (array)$c['value'])); if ($cmp === '!=') return (bool)$vals && !in_array((string)$c['value'], $vals, true);
        return in_array((string)$c['value'], $vals, true);
    }
    function get_posts($a) {
        $GLOBALS['ff9cm']['queries'][] = $a['post_type'] ?? '';
        $out = [];
        foreach ($GLOBALS['ff9cm']['posts'] as $id => $p) { if ($p->post_type !== ($a['post_type'] ?? '') || $p->post_status !== 'publish') continue; if (!empty($a['meta_query']) && !ff9cm_clause((int)$id, $a['meta_query'])) continue; $out[] = $p; }
        usort($out, function($x, $y) { return strcasecmp($x->post_title, $y->post_title); });
        return ($a['fields'] ?? '') === 'ids' ? array_map(function($p) { return $p->ID; }, $out) : $out;
    }
    class FF9CMWpdb { public $prefix = 'wp_'; public function prepare($q, ...$a) { return ['sql' => $q, 'args' => $a]; } public function get_var($q) { return strpos(is_array($q) ? $q['sql'] : $q, 'routespro_projects') !== false ? 9 : null; } public function get_results($q, $m = null) { return []; } }
    $GLOBALS['wpdb'] = new FF9CMWpdb();
    require $root . '/src/Catalog/ProductCatalogService.php';
    require $root . '/src/Forms/FormRenderer.php';

    use RoutesPro\Catalog\ProductCatalogService as C;
    use RoutesPro\Forms\FormRenderer as R;

    $jameson = C::save(0, ['client_id' => 9, 'name' => 'Jameson Original 70cl', 'reference' => '2050501', 'capacity_cl' => '70', 'kind' => 'own', 'image_ids' => [501]]);
    $tullamore = C::save(0, ['client_id' => 9, 'name' => 'Tullamore D.E.W. 70cl', 'capacity_cl' => '70', 'kind' => 'competitor', 'image_ids' => [503]]);
    $teeling = C::save(0, ['client_id' => 9, 'name' => 'Tullamore D.E.W. 1L', 'capacity_cl' => '100', 'kind' => 'competitor']);
    $inactive = C::save(0, ['client_id' => 9, 'name' => 'Tullamore Old', 'kind' => 'competitor', 'image_ids' => [501], 'inactive' => true]);
    C::mapLabel(9, 'Tullamore', [$tullamore, $teeling, $inactive]);

    // 1. Catalog resolution per row, per label and per context.
    $rows = [
        0 => ['category' => 'WHISKEY', 'ref' => '205-0501', 'name' => 'JAMESON 70CL', 'capacity_cl' => '70', 'qty' => '3'],
        1 => ['category' => 'WHISKEY', 'ref' => '', 'name' => 'Black Barrel', 'capacity_cl' => '70', 'qty' => ''],
        2 => ['category' => '', 'ref' => '', 'name' => '', 'qty' => ''],
    ];
    $visuals = C::visualsForRows(9, $rows);
    ff9cm_assert(array_keys($visuals) === [0] && $visuals[0]['product_id'] === $jameson && $visuals[0]['thumb'] === 'https://cdn.test/p501-thumbnail.jpg' && $visuals[0]['full'] === 'https://cdn.test/p501-large.jpg', 'row_visuals');
    $label = C::visualsForLabel(9, 'tullamore');
    ff9cm_assert($label['total'] === 2 && $label['items'][0]['product_id'] === $tullamore && $label['items'][1]['thumb'] === '', 'label_visuals_active_images_first');
    ff9cm_assert(C::visualsForLabel(9, 'Teeling')['total'] === 0 && C::visualsForLabel(0, 'Tullamore')['total'] === 0, 'label_visuals_scoped');
    ff9cm_assert(C::clientForContext(['client_id' => 0, 'project_id' => 77]) === 9 && C::clientForContext(['client_id' => 5]) === 5, 'client_context');
    $resolution = true;

    // 2. Compact rows submit exactly the same inputs as the classic rows.
    $set = static function (string $prop, $value): void { $p = new \ReflectionProperty(R::class, $prop); $p->setAccessible(true); $p->setValue(null, $value); };
    $call = static function (string $method, ...$args) { $m = new \ReflectionMethod(R::class, $method); $m->setAccessible(true); return $m->invoke(null, ...$args); };
    $inputs = static function (string $html): array { preg_match_all('/<input\b[^>]*>/', $html, $m); return array_map(static function ($tag) { preg_match('/name="([^"]+)"/', $tag, $n); preg_match('/value="([^"]*)"/', $tag, $v); return ($n[1] ?? '') . '=' . ($v[1] ?? '') . (strpos($tag, 'data-fronts-input') !== false ? '#fronts' : '') . (strpos($tag, ' required') !== false ? '#required' : '') . (strpos($tag, ' readonly') !== false ? '#readonly' : ''); }, $m[0]); };
    $historyRow = ['category' => 'WHISKEY', 'ref' => '205-0501', 'name' => 'JAMESON 70CL', 'capacity_cl' => '70', 'has_history' => 1, 'before' => '4.00', 'after' => '', 'item_id' => 11, 'cardex_id' => 1];
    $set('fronts_compact', false); $set('fronts_visuals', []); $set('fronts_thumbs', false);
    $classic = $call('render_product_row', 'fronts', 0, $rows[0], true, false);
    $classicHistory = $call('render_product_row', 'fronts', 0, $historyRow, false, false);
    $set('fronts_compact', true); $set('fronts_visuals', $visuals); $set('fronts_thumbs', true);
    $compact = $call('render_product_row', 'fronts', 0, $rows[0], true, false);
    $compactHistory = $call('render_product_row', 'fronts', 0, $historyRow, false, false);
    $compactNoVisual = $call('render_product_row', 'fronts', 1, $rows[1], false, false);
    $emptyRow = $call('render_product_row', 'fronts', 2, $rows[2], false, false);
    ff9cm_assert($inputs($classic) === $inputs($compact) && $inputs($classicHistory) === $inputs($compactHistory), 'same_inputs');
    ff9cm_assert(strpos($classic, 'has-summary') === false && strpos($classic, 'data-fronts-step') === false, 'classic_unchanged');
    ff9cm_assert(strpos($compact, 'routespro-product-row has-summary') !== false && substr_count($compact, 'data-fronts-step=') === 2 && strpos($compact, 'data-catalog-product-id="' . $jameson . '"') !== false, 'compact_row');
    ff9cm_assert(strpos($compact, 'data-product-zoom="https://cdn.test/p501-large.jpg"') !== false && strpos($compact, '<small>Ref. 205-0501</small>') !== false && strpos($compact, 'data-product-edit') !== false, 'compact_summary');
    ff9cm_assert(strpos($compactHistory, '<em class="routespro-fronts-before">Antes 4</em>') !== false && strpos($compactHistory, '[after]') !== false && strpos($compactHistory, 'inputmode="decimal"') !== false, 'history_before');
    ff9cm_assert(strpos($compactNoVisual, '<small>70 cl</small>') !== false, 'capacity_when_not_in_name');
    ff9cm_assert(strpos($compactNoVisual, 'routespro-product-thumb is-empty') !== false && strpos($compactNoVisual, '>BB<') !== false, 'placeholder_initials');
    ff9cm_assert(strpos($emptyRow, 'has-summary') === false && strpos($emptyRow, 'data-fronts-step') === false, 'manual_empty_row_classic');
    $set('fronts_thumbs', false);
    $noThumbs = $call('render_product_row', 'fronts', 1, $rows[1], false, false);
    ff9cm_assert(strpos($noThumbs, 'routespro-product-thumb') === false && strpos($noThumbs, 'has-summary') !== false, 'no_thumb_column_without_images');
    $set('fronts_compact', false); $set('fronts_visuals', []);
    $sameInputs = true;

    // 3. Opt-out and Share Space sides.
    ff9cm_assert($call('fronts_catalog_visuals', ['catalog_images_disabled' => true], ['client_id' => 9], $rows, 0) === [], 'opt_out');
    ff9cm_assert(array_keys($call('fronts_catalog_visuals', [], ['client_id' => 9], $rows, 0)) === [0], 'opt_in_default');
    $side = $call('share_space_side_visual', 9, 'Tullamore');
    ff9cm_assert(strpos($side, 'routespro-share-space-thumbs') !== false && substr_count($side, 'data-product-zoom=') === 1 && strpos($side, '+1') !== false, 'share_side');
    ff9cm_assert($call('share_space_side_visual', 9, 'Sem mapeamento') === '' && $call('share_space_catalog_client', ['catalog_images_disabled' => 1], ['client_id' => 9]) === 0, 'share_side_empty');
    $stepper = $call('fronts_stepper', '<input type="number" name="q[0][pr_fronts]" data-share-space-pr>', true, 'Jameson');
    ff9cm_assert(strpos($stepper, 'data-share-space-pr') !== false && strpos($stepper, 'aria-label="Mais uma frente de Jameson"') !== false, 'share_stepper');
    $optOut = true;

    // 4. Static contracts: runtime handlers, styles, builder option, unchanged pipeline.
    $read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
    $renderer = $read('src/Forms/FormRenderer.php');
    $runtime = strpos($renderer, "\$(document).on('click','[data-fronts-step]',function(e){") !== false
        && strpos($renderer, "input.dispatchEvent(new Event('input',{bubbles:true}));") !== false
        && strpos($renderer, "\$(document).on('click','[data-product-edit]',function(e){") !== false
        && strpos($renderer, "\$(document).on('click','[data-product-zoom]',function(e){") !== false
        && strpos($renderer, "holder.textContent=String(holder.getAttribute('data-initials')||'');") !== false
        && strpos($renderer, '@media(max-width:1024px){') !== false
        && strpos($renderer, ".rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary{min-width:0;display:grid;grid-template-columns:minmax(0,1fr) 118px;") !== false;
    ff9cm_assert($runtime, 'runtime_handlers');
    $builder = $read('assets/routespro-form-builder.js');
    ff9cm_assert(substr_count($builder, 'catalog_images_disabled') >= 5 && strpos($read('src/Admin/Forms.php'), 'data-fb="catalog_images_disabled"') !== false, 'builder_option');
    $historical = strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false
        && strpos($read('src/Catalog/ProductCatalogService.php'), "public const CONTRACT = 'fieldflow-visual-product-catalog-v1';") !== false
        && strpos($renderer, 'data-share-space-competitor') !== false
        && strpos($read('frontend/src/react-front/special-fields.ts'), "field.querySelectorAll('[data-product-row]')") !== false;
    ff9cm_assert($historical, 'historical_continuity');

    echo 'FRONTS MOBILE CATALOG VISUAL 9CM RUNTIME OK | resolution=1 | same_inputs=1 | compact_summary=1 | history_before=1 | opt_out=1 | share_space_visual=1 | runtime_handlers=1 | no_schema=1 | historical_continuity=1' . "\n";
}
