<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.613', "define('FIELDFLOW_VERSION', '2.2.613')", "9CP predecessor marker: define('FIELDFLOW_VERSION', '2.2.612')"],
    'src/Catalog/ProductCatalogService.php' => ["public const IDENTITY_CONTRACT = 'fieldflow-catalog-general-identity-v1';", 'public const IDENTITY_VERSION = 2;', "public static function identityKeys(string \$reference, string \$name, \$capacity = null, string \$ean = '', string \$scope = ''): array {", 'public static function duplicateSuggestions(int $clientId, int $limit = 40): array {', 'public static function resolveProductId(int $clientId, string $reference, string $name, $capacity = null): int {', "public const CONTRACT = 'fieldflow-visual-product-catalog-v1';"],
    'src/Admin/ProductCatalog.php' => ["add_action('admin_post_fieldflow_catalog_not_duplicate', [self::class, 'handle_not_duplicate']);", "self::guard('fieldflow_catalog_not_duplicate');", "'lock_name' => true,", 'Possíveis duplicados'],
    'src/Vision/FrontsVisionService.php' => ["public const REFERENCE_CONTRACT = 'fieldflow-fronts-field-reference-v1';", "add_action('wp_ajax_' . self::AJAX_REFERENCE, [self::class, 'ajaxReference']);", "check_ajax_referer(self::NONCE, 'nonce', false)", "public const CONTRACT = 'fieldflow-fronts-vision-reading-v1';"],
    'src/Admin/FrontsVisionAdmin.php' => ['[field_references]', "check_admin_referer('fieldflow_vision_settings', 'fieldflow_vision_nonce');"],
    'src/Forms/FormRenderer.php' => ["fd.append('action','fieldflow_fronts_vision_reference')", 'data-vision-ref-camera', 'function rpVisionFill(){', 'private static function fronts_vision_attrs(array $q, array $context): string {'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.612'", "public const STABLE_RELEASE = '2.2.613'", "// public const SOURCE_RELEASE = '2.2.611'", "// public const STABLE_RELEASE = '2.2.612'"],
    'src/Support/ReleaseGate.php' => ['CATALOG_GENERAL_IDENTITY_9CP_CONTRACT', 'checkCatalogGeneralIdentity9CPContract'],
    'src/Support/ReleasePackageValidator.php' => ['catalog_general_identity_9cp_ok', 'Catalog General Identity 9CP'],
    'src/Admin/ReleaseManager.php' => ["packageResult['catalog_general_identity_9cp_ok']", 'CATÁLOGO GERAL OK'],
    'PHASE-9CP-CATALOG-GENERAL-IDENTITY.md' => ['fieldflow-catalog-general-identity-v1', '`SOURCE_RELEASE` is `2.2.612` and `STABLE_RELEASE` is `2.2.613`', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $raw = is_readable($root . '/' . $relative) ? (string)file_get_contents($root . '/' . $relative) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
$service = (string)@file_get_contents($root . '/src/Catalog/ProductCatalogService.php');
if (strpos($service, 'wp_insert_attachment') !== false || stripos($service, 'CREATE TABLE') !== false) $errors[] = 'catalog_storage';
if (strpos((string)@file_get_contents($root . '/src/Vision/FrontsVisionService.php'), 'wp_ajax_nopriv') !== false) $errors[] = 'nopriv';
if ($errors) { fwrite(STDERR, "CATALOG GENERAL IDENTITY 9CP CONTRACT FAIL\n" . implode("\n", $errors) . "\n"); exit(1); }
echo "CATALOG GENERAL IDENTITY 9CP CONTRACT OK successor=>=2.2.613\n";
