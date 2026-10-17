<?php
$root = dirname(__DIR__);
$fail = [];
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$contains = static function(string $content, array $needles): bool {
    foreach ($needles as $needle) if (strpos($content, $needle) === false) return false;
    return true;
};
$assert = static function(bool $ok, string $name) use (&$fail): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $fail[] = $name;
};

$plugin=$read('fieldflow.php'); $readme=$read('readme.txt'); $phase=$read('src/Cloud/Phase6StableRelease.php');
$foundation=$read('src/Front/ReactFrontFoundation.php'); $responsive=$read('src/Front/ResponsiveAssets.php'); $form=$read('src/Forms/FormRenderer.php');
$special=$read('frontend/src/react-front/special-fields.ts'); $types=$read('frontend/src/react-front/types.ts'); $wizard=$read('frontend/src/react-front/form-wizard.ts');
$build=$read('frontend/tools/build.mjs'); $tests=$read('frontend/tests/react-front.test.mjs'); $css=$read('assets/fieldflow-react-front.css');
$asset=$read('assets/fieldflow-react-front-2.2.530.js'); $package=$read('frontend/package.json'); $gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php'); $manager=$read('src/Admin/ReleaseManager.php'); $doc=$read('PHASE-8H-MODERNIZATION-STABLE-CLOSURE.md');
$submissionPipeline=$read('src/Forms/SubmissionPipeline.php');

$assert($contains($plugin,['Version: 2.2.530',"FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme,'Stable tag: 2.2.530')!==false,'release_identity');
$assert($contains($phase,["VERSION = '1.0.87'","SOURCE_RELEASE = '2.2.529'","STABLE_RELEASE = '2.2.530'"]),'phase6_continuity');
$assert($contains($foundation,["MODERNIZATION_CLOSURE_CONTRACT = 'fieldflow-modernization-stable-closure-v1'", "MEDIA_CONTROL_CONTRACT = 'fieldflow-react-media-control-v1'", "'media_control_contract' => self::MEDIA_CONTROL_CONTRACT", "'offline_owner' => 'fieldflow_offline_runtime'"]),'media_foundation');
$assert($contains($responsive,['fieldflow-react-front-2.2.530.js','ReactFrontFoundation::SPECIAL_FIELD_CONTRACT']),'front_asset_enqueue');
$assert($contains($form,['ReactFrontFoundation::MEDIA_CONTROL_CONTRACT','data-rp-upload-trigger="camera"','data-rp-upload-trigger="gallery"','data-rp-upload-file']),'native_media_bridge');
$assert($contains($wizard,['fieldflow-modernization-stable-closure-v1','fieldflow-react-media-control-v1','FieldFlowSpecialFieldIsland']),'front_dispatch');
$assert($contains($types,['mediaControlContract','savedMedia','pendingMedia','pendingRemoval','online']),'typed_media_state');
$assert($contains($special,['fieldFlowMediaClick','data-fieldflow-react-media-native','fieldflow:offline-form-synced','fieldflow:sync-completed','Tirar fotografia','Escolher imagens','Protegido localmente']),'react_media_proxy');
$assert($contains($build,['reactMediaControlContract','fieldflow-react-media-control-v1']),'build_identity');
$assert($contains($tests,['React media control compiles final closure contract without taking media transport ownership','React media surface proxies camera action to certified native upload trigger']),'behavioural_tests');
$assert($contains($css,['ff-react-media-control','ff-react-media-stats','data-fieldflow-react-media-native']),'media_css');
$assert($contains($asset,['FieldFlow 2.2.530','fieldflow-modernization-stable-closure-v1','fieldflow-react-media-control-v1','fieldFlowMediaClick','wordpress-wp-element']),'compiled_media_surface');
$assert(strpos($package,'"react"')===false && strpos($package,'"react-dom"')===false,'no_bundled_react');
$assert($contains($submissionPipeline,["BUILD = '2.2.485'"]),'submission_pipeline_component_build_preserved');
$assert($contains($gate,['checkModernizationStableClosureContract','Modernization Stable Closure']),'release_gate');
$assert($contains($validator,['modernization_stable_closure_ok','O Modernization Stable Closure não está integralmente ligado']),'package_validator');
$assert($contains($manager,['Modernization Closure',"packageResult['modernization_stable_closure_ok']"]),'release_manager_kpi');
$assert($contains($doc,['fieldflow-modernization-stable-closure-v1','Native media transport remains canonical','No IndexedDB schema change','No FlutterFlow change','SOURCE_RELEASE` is `2.2.492`','STABLE_RELEASE` is `2.2.493`']),'closure_documentation');

if ($fail) {
    fwrite(STDERR, 'MODERNIZATION STABLE CLOSURE CONTRACT FAILED: ' . implode(', ', $fail) . PHP_EOL);
    exit(1);
}
echo 'MODERNIZATION STABLE CLOSURE CONTRACT OK' . PHP_EOL;
echo 'closure=fieldflow-modernization-stable-closure-v1' . PHP_EOL;
echo 'media_contract=fieldflow-react-media-control-v1' . PHP_EOL;
echo 'media_owner=native_offline_runtime' . PHP_EOL;
echo 'submission_owner=submission_application_service' . PHP_EOL;
echo 'fallback_policy=intentionally_retained' . PHP_EOL;
