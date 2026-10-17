<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.615', "define('FIELDFLOW_VERSION', '2.2.615')", "9CR predecessor marker: define('FIELDFLOW_VERSION', '2.2.614')"],
    'src/Forms/FormRenderer.php' => [
        "var RP_MEDIA_RESILIENCE_CONTRACT='fieldflow-media-capture-resilience-v1';",
        "function rpMediaOpener(){var saved=rpMediaGet('opener');if(saved==='click'||saved==='picker')return saved;return rpMediaIsNativeApp()?'click':'picker';}",
        'function rpMediaCheckRetap(zone,source,origin){',
        "rpMediaSet('camera','native');",
        "document.addEventListener('pointerup',rpMediaReviveTrigger,true);",
        'rpMediaNoteChange(this,$zone);',
        'offline.submitForm.__rpMediaWatch',
        'rpCheckpointExternalMediaHandoff($(this),source);',
    ],
    'src/Forms/Forms.php' => ["data-rp-media-diag-endpoint=\"<?php echo esc_url(rest_url('fieldflow-mobile/v1/diagnostics/client-event')); ?>\"", "data-rp-media-diag-nonce=\"<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>\""],
    'src/Mobile/StabilityController.php' => ["'/diagnostics/client-event'", "'media_error'"],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.614'", "public const STABLE_RELEASE = '2.2.615'", "// public const SOURCE_RELEASE = '2.2.613'", "// public const STABLE_RELEASE = '2.2.614'"],
    'src/Support/ReleaseGate.php' => ['MEDIA_CAPTURE_RESILIENCE_9CR_CONTRACT', 'checkMediaCaptureResilience9CRContract', 'FRONT_THEME_CAMERA_LIVE_9CQ_CONTRACT'],
    'src/Support/ReleasePackageValidator.php' => ['media_capture_resilience_9cr_ok', 'Media Capture Resilience 9CR', 'front_theme_camera_live_9cq_ok'],
    'src/Admin/ReleaseManager.php' => ["packageResult['media_capture_resilience_9cr_ok']", 'FOTOGRAFIAS RESILIENTES OK'],
    'PHASE-9CR-MEDIA-CAPTURE-RESILIENCE.md' => ['fieldflow-media-capture-resilience-v1', '`SOURCE_RELEASE` is `2.2.614` and `STABLE_RELEASE` is `2.2.615`', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
foreach ($need as $relative => $markers) {
    $raw = is_readable($root . '/' . $relative) ? (string)file_get_contents($root . '/' . $relative) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
$renderer = (string)@file_get_contents($root . '/src/Forms/FormRenderer.php');
if (strpos($renderer, "try{if(typeof input.showPicker==='function'){input.showPicker();return;}}catch(_){ }") !== false) $errors[] = 'legacy_opener';
if ($errors) { fwrite(STDERR, "MEDIA CAPTURE RESILIENCE 9CR CONTRACT FAIL\n" . implode("\n", $errors) . "\n"); exit(1); }
echo "MEDIA CAPTURE RESILIENCE 9CR CONTRACT OK successor=>=2.2.615\n";
