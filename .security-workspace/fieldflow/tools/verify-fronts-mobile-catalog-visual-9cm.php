<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.610', "define('FIELDFLOW_VERSION', '2.2.610')", "9CM predecessor marker: define('FIELDFLOW_VERSION', '2.2.609')"],
    'src/Forms/FormRenderer.php' => [
        "public const FRONTS_VISUAL_CONTRACT = 'fieldflow-fronts-mobile-catalog-visual-v1';",
        'private static function fronts_stepper(string $input, bool $enabled, string $name = \'\'): string {',
        'private static function fronts_row_summary(',
        "self::\$fronts_visuals = self::fronts_catalog_visuals(\$q, \$context, \$rows, (int)\$resolved_cardex_id);",
        "self::share_space_side_visual(\$share_catalog_client, \$prLabel)",
        "\$(document).on('click','[data-fronts-step]',function(e){",
        "\$(document).on('click','[data-product-zoom]',function(e){",
        '.rp-form-ui .routespro-product-matrix.is-compact-fronts .routespro-product-row.has-summary{',
        'data-fronts-input',
        'data-share-space-pr',
        'data-share-space-competitor',
    ],
    'src/Catalog/ProductCatalogService.php' => ['public static function visualsForRows(int $clientId, array $rows): array {', 'public static function visualsForLabel(int $clientId, string $label, int $limit = 3): array {', 'public static function clientForContext(array $context, int $cardexId = 0): int {'],
    'assets/routespro-form-builder.js' => ['catalog_images_disabled: !!q.catalog_images_disabled,', "input[data-fb=\"catalog_images_disabled\"]"],
    'src/Admin/Forms.php' => ['data-fb="catalog_images_disabled"'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.609'", "public const STABLE_RELEASE = '2.2.610'", "// public const SOURCE_RELEASE = '2.2.608'", "// public const STABLE_RELEASE = '2.2.609'"],
    'src/Support/ReleaseGate.php' => ['FRONTS_MOBILE_CATALOG_VISUAL_9CM_CONTRACT', 'checkFrontsMobileCatalogVisual9CMContract'],
    'src/Support/ReleasePackageValidator.php' => ['fronts_mobile_catalog_visual_9cm_ok', 'Fronts Mobile Layout & Catalog Visuals 9CM'],
    'src/Admin/ReleaseManager.php' => ["packageResult['fronts_mobile_catalog_visual_9cm_ok']", 'FRENTES COMPACTAS OK'],
    'PHASE-9CM-FRONTS-MOBILE-CATALOG-VISUAL.md' => ['fieldflow-fronts-mobile-catalog-visual-v1', '`SOURCE_RELEASE` is `2.2.609` and `STABLE_RELEASE` is `2.2.610`', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
if ($errors) {
    fwrite(STDERR, "FRONTS MOBILE CATALOG VISUAL 9CM CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "FRONTS MOBILE CATALOG VISUAL 9CM CONTRACT OK successor=>=2.2.610\n";
