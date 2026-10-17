<?php
$root = dirname(__DIR__);
$checks = [
    'release_identity' => [
        'file' => 'fieldflow.php',
        'needles' => ['isWordPressMediaProcessingRequest', 'WordPress Media Library isolation'],
    ],
    'plugin_media_detector' => [
        'file' => 'src/Support/Plugin.php',
        'needles' => ["WORDPRESS_MEDIA_ISOLATION_CONTRACT = 'fieldflow-wordpress-media-isolation-v1'", 'async-upload.php', 'upload-attachment', 'image-editor', '/wp/v2/media'],
    ],
    'upload_guard_scope' => [
        'file' => 'src/Security/UploadGuard.php',
        'needles' => ["IMAGE_SIZE_POLICY = 'no-fixed-image-limit'", "if (!self::isFieldFlowRequest()) return;", 'isWordPressMediaProcessingRequest', 'routespro_form_id'],
    ],
    'release_gate' => [
        'file' => 'src/Support/ReleaseGate.php',
        'needles' => ["WORDPRESS_MEDIA_ISOLATION_9AR_CONTRACT = 'fieldflow-wordpress-media-isolation-v1'", 'checkWordPressMediaIsolation9ARContract'],
    ],
    'package_validator' => [
        'file' => 'src/Support/ReleasePackageValidator.php',
        'needles' => ['wordpress_media_isolation_9ar_ok', 'fieldflow-wordpress-media-isolation-v1'],
    ],
    'release_manager' => [
        'file' => 'src/Admin/ReleaseManager.php',
        'needles' => ['WordPress Media Isolation 9AR', "packageResult['wordpress_media_isolation_9ar_ok']"],
    ],
    'schema_frozen' => [
        'file' => 'src/Support/Migrations.php',
        'needles' => ["LATEST_SCHEMA_VERSION = '2.2.529'"],
    ],
    'submission_frozen' => [
        'file' => 'src/Forms/SubmissionPipeline.php',
        'needles' => ["public const BUILD = '2.2.485'"],
    ],
];
$failed = [];
foreach ($checks as $name => $spec) {
    $path = $root . '/' . $spec['file'];
    $raw = is_readable($path) ? file_get_contents($path) : '';
    if ($raw === '') { $failed[] = $name . ':file'; continue; }
    foreach ($spec['needles'] as $needle) if (strpos($raw, $needle) === false) $failed[] = $name . ':' . $needle;
}
$guard = file_get_contents($root . '/src/Security/UploadGuard.php');
if (strpos($guard, "['routespro_form_id','form_id'") !== false) $failed[] = 'upload_guard:generic_form_id_scope';
if ($failed) {
    fwrite(STDERR, "WORDPRESS MEDIA ISOLATION 9AR CONTRACT FAILED\n" . implode("\n", $failed) . "\n");
    exit(1);
}
$pluginRaw = file_get_contents($root . '/fieldflow.php');
$release = '';
if (preg_match("/define\('FIELDFLOW_VERSION',\s*'([^']+)'\)/", (string)$pluginRaw, $m)) $release = (string)$m[1];
if ($release === '' || version_compare($release, '2.2.562', '<')) {
    fwrite(STDERR, "WORDPRESS MEDIA ISOLATION 9AR CONTRACT FAILED\nrelease_identity:requires>=2.2.562\n");
    exit(1);
}
echo "WORDPRESS MEDIA ISOLATION 9AR CONTRACT OK\n";
echo "release=" . $release . "\n";
echo "contract=fieldflow-wordpress-media-isolation-v1\n";
echo "core_media=lightweight_bootstrap\n";
echo "upload_guard=fieldflow_only\n";
