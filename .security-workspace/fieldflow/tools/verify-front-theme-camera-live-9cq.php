<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.614', "define('FIELDFLOW_VERSION', '2.2.614')", "9CQ predecessor marker: define('FIELDFLOW_VERSION', '2.2.613')"],
    'src/Front/ThemeIsolation.php' => ["public const CONTRACT = 'fieldflow-theme-isolation-v1';", "add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 9999);", 'if (self::dependsOn($styles, $dependency, $handle, [])) continue;'],
    'src/Vision/FrontsVisionService.php' => ["public const QUESTION_SWITCH_CONTRACT = 'fieldflow-fronts-vision-question-switch-v1';", "public const LIVE_CONTRACT = 'fieldflow-fronts-vision-live-count-v1';", 'public static function questionBlockReason(array $question, array $context, int $clientId = 0): string {', 'is_uploaded_file($path)', "check_ajax_referer(self::NONCE, 'nonce', false)"],
    'src/Catalog/ProductCatalogService.php' => ["public const IDENTITY_FUZZY_CONTRACT = 'fieldflow-catalog-insignia-name-variants-v1';", 'public const IDENTITY_VERSION = 3;', '// 9CP marker: public const IDENTITY_VERSION = 2;', 'public static function variantScore(array $a, array $b): float {'],
    'src/Admin/ProductCatalog.php' => ["add_action('admin_post_fieldflow_catalog_export', [self::class, 'handle_export']);", "check_admin_referer('fieldflow_catalog_export_' . \$clientId);", 'Exportar CSV'],
    'src/Admin/FrontsVisionAdmin.php' => ["'questions' => 'Nas perguntas com a opção ligada'", "check_admin_referer('fieldflow_vision_settings', 'fieldflow_vision_nonce');"],
    'src/Forms/FormRenderer.php' => ['function rpVisionLiveStart(){', "fd.append('capture',capture||'camera')", 'function rpFrontsDecorateDynamicRow($row,$box){', 'private static function fronts_vision_admin_hint(array $q, array $context, string $attrs): string {'],
    'src/Support/Loader.php' => ["'src/Front/ThemeIsolation.php'"],
    'src/Architecture/RuntimeRegistry.php' => ['\\RoutesPro\\Front\\ThemeIsolation::registerHooks();'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.613'", "public const STABLE_RELEASE = '2.2.614'", "// public const SOURCE_RELEASE = '2.2.612'", "// public const STABLE_RELEASE = '2.2.613'"],
    'src/Support/ReleaseGate.php' => ['FRONT_THEME_CAMERA_LIVE_9CQ_CONTRACT', 'checkFrontThemeCameraLive9CQContract'],
    'src/Support/ReleasePackageValidator.php' => ['front_theme_camera_live_9cq_ok', 'Front Theme Camera Live 9CQ'],
    'src/Admin/ReleaseManager.php' => ["packageResult['front_theme_camera_live_9cq_ok']", 'FRONT E CÂMARA AO VIVO OK'],
    'PHASE-9CQ-FRONT-THEME-CAMERA-LIVE.md' => ['fieldflow-theme-isolation-v1', '`SOURCE_RELEASE` is `2.2.613` and `STABLE_RELEASE` is `2.2.614`', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $raw = is_readable($root . '/' . $relative) ? (string)file_get_contents($root . '/' . $relative) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
if (strpos((string)@file_get_contents($root . '/src/Vision/FrontsVisionService.php'), 'wp_ajax_nopriv') !== false) $errors[] = 'nopriv';
if (stripos((string)@file_get_contents($root . '/src/Front/ThemeIsolation.php'), '#c36') !== false) $errors[] = 'theme_colour';
if ($errors) { fwrite(STDERR, "FRONT THEME CAMERA LIVE 9CQ CONTRACT FAIL\n" . implode("\n", $errors) . "\n"); exit(1); }
echo "FRONT THEME CAMERA LIVE 9CQ CONTRACT OK successor=>=2.2.614\n";
