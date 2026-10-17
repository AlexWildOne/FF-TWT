<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.609', "define('FIELDFLOW_VERSION', '2.2.609')", "9CL predecessor marker: define('FIELDFLOW_VERSION', '2.2.608')"],
    'src/Catalog/ProductCatalogService.php' => [
        "public const CONTRACT = 'fieldflow-visual-product-catalog-v1';",
        "public const MAPPING_CONTRACT = 'fieldflow-briefing-product-label-mapping-v1';",
        "public const POST_TYPE = 'ff_catalog_product';",
        "'public' => false,",
        "'show_in_rest' => false,",
        'public static function syncFromCardex(int $onlyClientId = 0): array {',
        'public static function briefingLabels(int $clientId, int $projectId = 0): array {',
        'public static function mapLabel(int $clientId, string $label, array $productIds): int {',
        'public static function merge(int $sourceId, int $targetId): void {',
        'public static function resolveProductId(int $clientId, string $reference, string $name, $capacity = null): int {',
        "['cardex_import', 'cardex', 'cardex_briefing', 'cardex_sync']",
    ],
    'src/Admin/ProductCatalog.php' => [
        "public const CONTRACT = 'fieldflow-visual-product-catalog-admin-v1';",
        "check_admin_referer(\$action, 'fieldflow_catalog_nonce');",
        "add_action('admin_post_fieldflow_catalog_map_label', [self::class, 'handle_map_label']);",
        'wp_enqueue_media()',
        'Mapeamento do briefing',
        'Criar produto de concorrência',
    ],
    'src/Admin/ProductCardex.php' => ["if (ProductCatalog::current_tab() !== 'cardex') { ProductCatalog::render(); return; }", "ProductCatalog::render_tabs('cardex');"],
    'src/Admin/Menu.php' => ["'Cardex e Catálogo', 'Cardex e Catálogo', 'routespro_manage', 'routespro-product-cardex'"],
    'src/Architecture/RuntimeRegistry.php' => ['\\RoutesPro\\Catalog\\ProductCatalogService::registerHooks();', "add_action('admin_init', ['RoutesPro\\\\Admin\\\\ProductCatalog', 'register_hooks']);"],
    'src/Support/Loader.php' => ["'src/Catalog/ProductCatalogService.php'", "'src/Admin/ProductCatalog.php'"],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.608'", "public const STABLE_RELEASE = '2.2.609'", "// public const SOURCE_RELEASE = '2.2.607'", "// public const STABLE_RELEASE = '2.2.608'"],
    'src/Support/ReleaseGate.php' => ['VISUAL_PRODUCT_CATALOG_9CL_CONTRACT', 'checkVisualProductCatalog9CLContract'],
    'src/Support/ReleasePackageValidator.php' => ['visual_product_catalog_9cl_ok', 'Visual Product Catalog 9CL'],
    'src/Admin/ReleaseManager.php' => ["packageResult['visual_product_catalog_9cl_ok']", 'CATÁLOGO VISUAL OK'],
    'PHASE-9CL-VISUAL-PRODUCT-CATALOG.md' => ['fieldflow-visual-product-catalog-v1', '`SOURCE_RELEASE` is `2.2.608` and `STABLE_RELEASE` is `2.2.609`', 'fieldflow-briefing-product-label-mapping-v1', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
$forbidden = [
    'src/Catalog/ProductCatalogService.php' => ['CREATE TABLE', 'ALTER TABLE', 'dbDelta('],
    'src/Admin/ProductCatalog.php' => ['CREATE TABLE', 'ALTER TABLE', 'dbDelta('],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
foreach ($forbidden as $relative => $markers) {
    $raw = (string)@file_get_contents($root . '/' . $relative);
    foreach ($markers as $marker) if (stripos($raw, $marker) !== false) $errors[] = $relative . ':forbidden:' . $marker;
}
if ($errors) {
    fwrite(STDERR, "VISUAL PRODUCT CATALOG 9CL CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "VISUAL PRODUCT CATALOG 9CL CONTRACT OK successor=>=2.2.609\n";
