<?php
/** 9D3 isolated catalog category selection, persistence and tenant isolation. No WordPress writes. */
namespace {
    define('ABSPATH', '/tmp/wp/');
    $testOptions = [];
    function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
    function wp_strip_all_tags($value) { return strip_tags((string)$value); }
    function absint($value) { return abs((int)$value); }
    function get_option($key, $default = []) { global $testOptions; return $testOptions[$key] ?? $default; }
    function update_option($key, $value, $autoload = false) { global $testOptions; $testOptions[$key] = $value; return true; }
    function ff9d3_assert($condition, $message) { if (!$condition) { fwrite(STDERR, "9D3 FAIL: $message\n"); exit(1); } }
    class CategoryDb9D3 {
        public $prefix = 'wp_';
        public function prepare($sql, ...$args) {
            foreach ($args as $arg) $sql = preg_replace('/%[dsf]/', is_string($arg) ? "'".addslashes($arg)."'" : (string)$arg, $sql, 1);
            return $sql;
        }
        public function get_var($sql) { if (strpos($sql, 'SHOW TABLES') !== false) {preg_match("/'([^']+)'/", $sql, $m); return $m[1] ?? ''; } return null; }
        public function get_col($sql) {
            if (strpos($sql, 'c.client_id=10') !== false) return ['GIN', 'WHISKEY'];
            if (strpos($sql, 'c.client_id=20') !== false) return ['VODKA'];
            return [];
        }
    }
    $wpdb = new CategoryDb9D3();
}
namespace RoutesPro\Catalog {
    class ProductCatalogService {
        public static $products = [];
        public static function clientIndex(int $clientId): array { return self::$products[$clientId] ?? []; }
        public static function normalizeText(string $text): string {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
            return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', strtolower($text))));
        }
    }
}
namespace RoutesPro\Forms {
    class ProductCardex {
        public static function table_items(): string { return 'wp_cardex_items'; }
        public static function table_cardex(): string { return 'wp_cardex'; }
    }
    class ContextQuestions {}
}
namespace {
    require dirname(__DIR__).'/src/Briefings/BriefingCategoryMappingService.php';
    use RoutesPro\Briefings\BriefingCategoryMappingService as Categories;
    use RoutesPro\Catalog\ProductCatalogService as Catalog;
    Catalog::$products = [
        10 => [101 => ['active'=>1,'category'=>'WHISKEY'],102 => ['active'=>0,'category'=>'ARCHIVED']],
        20 => [201 => ['active'=>1,'category'=>'Vodka']],
    ];
    ff9d3_assert(Categories::CATALOG_CATEGORY_PICKER_9D3_CONTRACT === 'fieldflow-catalog-category-picker-9d3-v1', 'contract');
    $existing = Categories::categories(10);
    ff9d3_assert(in_array('WHISKEY', $existing, true) && in_array('GIN', $existing, true), 'catalog + cardex categories');
    ff9d3_assert(!in_array('Vodka', $existing, true) && !in_array('ARCHIVED', $existing, true), 'tenant isolation and inactive products excluded from automatic mapping');
    ff9d3_assert(in_array('ARCHIVED', Categories::catalogCategories(10), true), 'inactive-only existing label shown in catalog dropdown');
    ff9d3_assert(Categories::catalogCategorySelection(10, 'gin') === 'GIN', 'existing category case insensitive');
    ff9d3_assert(Categories::catalogCategorySelection(10, '__new__', 'gin') === 'GIN', 'new category deduplicates existing');
    ff9d3_assert(Categories::catalogCategorySelection(10, '') === '', 'clearing category is explicit');
    ff9d3_assert(Categories::catalogCategorySelection(10, 'ARCHIVED', '', 'ARCHIVED') === 'ARCHIVED', 'legacy stored category preserved');
    ff9d3_assert(Categories::catalogCategorySelection(10, '__new__', '  Tequila  ') === 'Tequila', 'new category clean');
    ff9d3_assert(Categories::rememberCatalogCategory(10, 'Tequila'), 'new category persisted');
    ff9d3_assert(in_array('Tequila', Categories::categories(10), true), 'saved category appears in dropdown');
    ff9d3_assert(Categories::rememberCatalogCategory(10, 'TEQUILA'), 'duplicate category accepted without double registration');
    ff9d3_assert(count(get_option('fieldflow_catalog_categories_9d3_client_10')) === 1, 'de-duplicate stored category');
    ff9d3_assert(Categories::catalogCategorySelection(10, 'TEQUILA') === 'Tequila', 'canonical category reused');
    ff9d3_assert(!in_array('Tequila', Categories::categories(20), true), 'tenant isolation after save');
    ff9d3_assert(Categories::rememberCatalogCategory(20, 'Cerveja'), 'second client own categories');
    ff9d3_assert(!in_array('Cerveja', Categories::categories(10), true), 'tenant isolation reverse');
    foreach ([
        fn() => Categories::catalogCategorySelection(10, 'Vodka'),
        fn() => Categories::catalogCategorySelection(10, '__new__', ''),
        fn() => Categories::catalogCategorySelection(10, '__new__', ' '),
        fn() => Categories::catalogCategorySelection(10, '__new__', str_repeat('T', 120)),
        fn() => Categories::catalogCategorySelection(0, 'GIN'),
    ] as $i => $attempt) {
        $rejected = false;
        try { $attempt(); } catch (\RuntimeException $e) { $rejected = true; }
        ff9d3_assert($rejected, 'malformed/forged category rejected #' . $i);
    }
    $admin = file_get_contents(dirname(__DIR__).'/src/Admin/ProductCatalog.php');
    ff9d3_assert(strpos($admin, 'name="category_choice"') !== false && strpos($admin, 'name="category_new"') !== false, 'dropdown and new input rendered');
    ff9d3_assert(strpos($admin, 'CategoryMapping::catalogCategorySelection(') !== false, 'server checks selection');
    ff9d3_assert(strpos($admin, '$ownerClient = $existing ? (int)$existing[\'client_id\'] : (int)$data[\'client_id\'];') !== false, 'server gets true product client');
    $savedIndex = strpos($admin, '$id = Catalog::save($productId, $data);');
    $rememberIndex = strpos($admin, 'CategoryMapping::rememberCatalogCategory($ownerClient, $data[\'category\']);');
    ff9d3_assert($savedIndex !== false && $rememberIndex !== false && $savedIndex < $rememberIndex, 'only persist category after product saves');
    ff9d3_assert(strpos($admin, 'categoryInput.required=active') !== false && strpos($admin, 'categoryInput.disabled=!active') !== false, 'new category visibility and required state');
    echo "CATALOG CATEGORY PICKER 9D3 PHP PASS | tenant isolation | new category persisted | canonical reuse | forged choice rejected | legacy preserved\n";
}
