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
$types=$read('frontend/src/react-front/types.ts'); $wizard=$read('frontend/src/react-front/form-wizard.ts'); $fields=$read('frontend/src/react-front/simple-fields.ts');
$build=$read('frontend/tools/build.mjs'); $tests=$read('frontend/tests/react-front.test.mjs'); $css=$read('assets/fieldflow-react-front.css');
$asset=$read('assets/fieldflow-react-front-2.2.530.js'); $package=$read('frontend/package.json'); $gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php'); $manager=$read('src/Admin/ReleaseManager.php'); $doc=$read('PHASE-8F-REACT-FORM-SIMPLE-CONTROLS.md');

$assert($contains($plugin,['Version: 2.2.530',"FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme,'Stable tag: 2.2.530')!==false,'release_identity');
$assert($contains($phase,["VERSION = '1.0.87'","SOURCE_RELEASE = '2.2.529'","STABLE_RELEASE = '2.2.530'"]),'phase6_continuity');
$assert($contains($foundation,["SIMPLE_FIELD_CONTROL_CONTRACT = 'fieldflow-react-simple-field-control-v1'",'simpleFieldTypeEnabled',"'submission_owner' => 'legacy_typed_runtime'", "'offline_owner' => 'fieldflow_offline_runtime'"]),'front_foundation_control');
$assert($contains($responsive,['fieldflow-react-front-2.2.530.js','SIMPLE_FIELD_CONTROL_CONTRACT']), 'front_asset_enqueue');
$assert($contains($form,['data-fieldflow-react-front-island="simple-field-assist"','controlContract','SIMPLE_FIELD_CONTROL_CONTRACT',"'unit' => \$unit"]), 'form_control_mount');
$assert($contains($types,['FieldFlowSimpleFieldOption','controlContract?: string','value: string','options: FieldFlowSimpleFieldOption[]']), 'simple_control_types');
$assert($contains($wizard,['fieldflow-react-simple-field-control-v1','FieldFlowSimpleFieldAssistIsland']), 'simple_control_dispatch');
$assert($contains($fields,['fieldFlowSimpleFieldWrite','data-fieldflow-react-simple-native','data-fieldflow-react-simple-control-input',"dispatchEvent(new Event('input'","dispatchEvent(new Event('change'",'redirectInvalid','fieldflow:forms-runtime-v3-snapshot']), 'simple_control_bridge');
$assert($contains($build,['reactSimpleFieldControlContract','fieldflow-react-simple-field-control-v1']), 'build_identity');
$assert($contains($tests,['React simple field control owns visible UI while canonical input owns value and submit semantics','fieldflow-react-simple-field-control-v1']), 'behavioural_tests');
$assert($contains($css,['data-fieldflow-react-simple-native','ff-react-front-simple-control','fieldflow-react-simple-field-control-v1']), 'simple_control_css');
$assert($contains($asset,['FieldFlow 2.2.530','fieldflow-react-simple-field-assist-v1','fieldflow-react-simple-field-control-v1','wordpress-wp-element']), 'compiled_simple_controls');
$assert(strpos($package,'"react"')===false && strpos($package,'"react-dom"')===false,'no_bundled_react');
$assert($contains($gate,['checkReactSimpleFieldControlsContract','React Form Simple Controls']), 'release_gate');
$assert($contains($validator,['react_simple_field_controls_ok','Os controlos simples React do formulário não estão integralmente ligados']), 'package_validator');
$assert($contains($manager,['React Simple Fields',"packageResult['react_simple_field_controls_ok']"]), 'release_manager_kpi');
$assert($contains($doc,['fieldflow-react-simple-field-control-v1','canonical native input','No REST contract change','No IndexedDB schema change','No FlutterFlow change']), 'documentation_safety');

if ($fail) {
    fwrite(STDERR, 'REACT FORM SIMPLE CONTROLS CONTRACT FAILED: ' . implode(', ', $fail) . PHP_EOL);
    exit(1);
}
echo 'REACT FORM SIMPLE CONTROLS CONTRACT OK' . PHP_EOL;
echo 'visible_ui_owner=react' . PHP_EOL;
echo 'canonical_value_owner=native_input' . PHP_EOL;
echo 'conditional_owner=fieldflow-typed-form-state-conditional-core-v1' . PHP_EOL;
echo 'submission_owner=legacy_typed_runtime' . PHP_EOL;
echo 'offline_owner=fieldflow_offline_runtime' . PHP_EOL;
