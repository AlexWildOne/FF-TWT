<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.611', "define('FIELDFLOW_VERSION', '2.2.611')", "9CN predecessor marker: define('FIELDFLOW_VERSION', '2.2.610')"],
    'src/Catalog/ProductCatalogService.php' => ["public const REUSE_CONTRACT = 'fieldflow-catalog-cross-client-reuse-v1';", 'public static function copyFromClient(int $targetClientId, array $sourceProductIds, string $kind = \'keep\'): array {', 'public static function refreshImagesFromSource(int $productId): int {', "\$filters['search'] ?? \$filters['s'] ?? ''"],
    'src/Admin/ProductCatalog.php' => ["add_action('admin_post_fieldflow_catalog_reuse', [self::class, 'handle_reuse']);", "self::guard('fieldflow_catalog_reuse');", 'Reutilizar de outro cliente', 'Atualizar imagens da origem'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.610'", "public const STABLE_RELEASE = '2.2.611'", "// public const SOURCE_RELEASE = '2.2.609'", "// public const STABLE_RELEASE = '2.2.610'"],
    'src/Support/ReleaseGate.php' => ['CATALOG_CROSS_CLIENT_REUSE_9CN_CONTRACT', 'checkCatalogCrossClientReuse9CNContract'],
    'src/Support/ReleasePackageValidator.php' => ['catalog_cross_client_reuse_9cn_ok', 'Catalog Cross-Client Reuse 9CN'],
    'src/Admin/ReleaseManager.php' => ["packageResult['catalog_cross_client_reuse_9cn_ok']", 'CATÁLOGO REUTILIZÁVEL OK'],
    'PHASE-9CN-CATALOG-CROSS-CLIENT-REUSE.md' => ['fieldflow-catalog-cross-client-reuse-v1', '`SOURCE_RELEASE` is `2.2.610` and `STABLE_RELEASE` is `2.2.611`', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $raw = is_readable($root . '/' . $relative) ? (string)file_get_contents($root . '/' . $relative) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
if ($errors) { fwrite(STDERR, "CATALOG CROSS CLIENT REUSE 9CN CONTRACT FAIL\n" . implode("\n", $errors) . "\n"); exit(1); }
echo "CATALOG CROSS CLIENT REUSE 9CN CONTRACT OK successor=>=2.2.611\n";
