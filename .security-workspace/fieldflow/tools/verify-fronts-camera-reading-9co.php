<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.612', "define('FIELDFLOW_VERSION', '2.2.612')", "9CO predecessor marker: define('FIELDFLOW_VERSION', '2.2.611')"],
    'src/Vision/FrontsVisionService.php' => ["public const CONTRACT = 'fieldflow-fronts-vision-reading-v1';", "public const MODULE = 'fronts_vision';", "check_ajax_referer(self::NONCE, 'nonce', false)", 'self::rateLimit($userId);', "\\RoutesPro\\Services\\AIManager::complete(\$module, \$prompt, \$params)"],
    'src/Services/AI.php' => ['trait ProviderVisionPayload {', "'type' => 'image_url'", "'type' => 'input_image'", "'inline_data' => ['mime_type'", 'fieldflow-gemini-model-availability-v1', "public const DEFAULT_MODEL = 'gemini-3.5-flash-lite';", 'private const UNAVAILABLE_FALLBACK = ['],
    'src/Forms/FormRenderer.php' => ['private static function fronts_vision_attrs(array $q, array $context): string {', 'function rpVisionFill(){', 'data-fronts-camera-card', "fd.append('action','fieldflow_fronts_vision_read')"],
    'src/Admin/FrontsVisionAdmin.php' => ["public const CONTRACT = 'fieldflow-fronts-vision-admin-v1';", "check_admin_referer('fieldflow_vision_settings', 'fieldflow_vision_nonce');"],
    'src/Admin/AIControlCenter.php' => ["'fronts_vision'=>['Leitura de frentes'", 'azure_openai_vision_deployment'],
    'assets/routespro-form-builder.js' => ['camera_reading: !!q.camera_reading,', 'input[data-fb="camera_reading"]'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.611'", "public const STABLE_RELEASE = '2.2.612'", "// public const SOURCE_RELEASE = '2.2.610'", "// public const STABLE_RELEASE = '2.2.611'"],
    'src/Support/ReleaseGate.php' => ['FRONTS_CAMERA_READING_9CO_CONTRACT', 'checkFrontsCameraReading9COContract'],
    'src/Support/ReleasePackageValidator.php' => ['fronts_camera_reading_9co_ok', 'Fronts Camera Reading 9CO'],
    'src/Admin/ReleaseManager.php' => ["packageResult['fronts_camera_reading_9co_ok']", 'LEITURA POR CÂMARA OK'],
    'PHASE-9CO-FRONTS-CAMERA-READING.md' => ['fieldflow-fronts-vision-reading-v1', '`SOURCE_RELEASE` is `2.2.611` and `STABLE_RELEASE` is `2.2.612`', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $raw = is_readable($root . '/' . $relative) ? (string)file_get_contents($root . '/' . $relative) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
if (strpos((string)@file_get_contents($root . '/src/Vision/FrontsVisionService.php'), 'wp_ajax_nopriv') !== false) $errors[] = 'nopriv';
if ($errors) { fwrite(STDERR, "FRONTS CAMERA READING 9CO CONTRACT FAIL\n" . implode("\n", $errors) . "\n"); exit(1); }
echo "FRONTS CAMERA READING 9CO CONTRACT OK successor=>=2.2.612\n";
