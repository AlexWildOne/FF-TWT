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
$types=$read('frontend/src/react-front/types.ts'); $wizard=$read('frontend/src/react-front/form-wizard.ts'); $choices=$read('frontend/src/react-front/choice-fields.ts'); $special=$read('frontend/src/react-front/special-fields.ts');
$build=$read('frontend/tools/build.mjs'); $tests=$read('frontend/tests/react-front.test.mjs'); $css=$read('assets/fieldflow-react-front.css');
$asset=$read('assets/fieldflow-react-front-2.2.530.js'); $package=$read('frontend/package.json'); $gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php'); $manager=$read('src/Admin/ReleaseManager.php'); $doc=$read('PHASE-8G-REACT-FORM-CHOICE-SPECIAL-WAVE.md');

$assert($contains($plugin,['Version: 2.2.530',"FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme,'Stable tag: 2.2.530')!==false,'release_identity');
$assert($contains($phase,["VERSION = '1.0.87'","SOURCE_RELEASE = '2.2.529'","STABLE_RELEASE = '2.2.530'"]),'phase6_continuity');
$assert($contains($foundation,["CHOICE_FIELD_CONTRACT = 'fieldflow-react-choice-field-control-v1'","SPECIAL_FIELD_CONTRACT = 'fieldflow-react-special-field-assist-v1'",'choiceFieldTypeEnabled','specialFieldTypeEnabled',"'conditional_owner' => 'fieldflow-typed-form-state-conditional-core-v1'"]),'front_foundation');
$assert($contains($responsive,['fieldflow-react-front-2.2.530.js','CHOICE_FIELD_CONTRACT','SPECIAL_FIELD_CONTRACT']),'front_asset_enqueue');
$assert($contains($form,['data-fieldflow-react-front-island="choice-field-control"','data-fieldflow-react-front-island="special-field-assist"','react_choice_field_root','react_special_field_root','ProductCardex::question_rows','ProductCardex::order_question_rows']),'form_mounts');
$assert($contains($types,['FieldFlowChoiceFieldProps','FieldFlowSpecialFieldProps','FieldFlowChoiceSnapshot','FieldFlowSpecialSnapshot']),'typed_props');
$assert($contains($wizard,['fieldflow-react-choice-field-control-v1','fieldflow-react-special-field-assist-v1','FieldFlowChoiceFieldIsland','FieldFlowSpecialFieldIsland']),'dispatch');
$assert($contains($choices,['fieldFlowChoiceWrite','data-fieldflow-react-choice-native',"dispatchEvent(new Event('input'", "dispatchEvent(new Event('change'",'fieldflow:forms-runtime-v3-snapshot','fieldflow:offline-form-synced']),'choice_native_bridge');
$assert($contains($special,['fieldFlowSpecialSnapshot','data-share-space-card','data-fronts-input','data-order-qty','data-rp-evidence-category','data-category-open-all','data-category-close-all']),'special_runtime_bridge');
$assert($contains($build,['reactChoiceFieldContract','reactSpecialFieldContract','fieldflow-react-choice-field-control-v1','fieldflow-react-special-field-assist-v1']),'build_identity');
$assert($contains($tests,['React choice and advanced form wave compiles conditional bridges and special-field assistants','React choice control writes the canonical radio group so conditional listeners keep ownership']),'behavioural_tests');
$assert($contains($css,['ff-react-choice-control','data-fieldflow-react-choice-native','ff-react-special-field']),'wave_css');
$assert($contains($asset,['FieldFlow 2.2.530','fieldflow-react-choice-field-control-v1','fieldflow-react-special-field-assist-v1','choice-field-control','special-field-assist','wordpress-wp-element']),'compiled_wave');
$assert(strpos($package,'"react"')===false && strpos($package,'"react-dom"')===false,'no_bundled_react');
$assert($contains($gate,['checkReactChoiceSpecialWaveContract','React Choice & Special Form Wave']),'release_gate');
$assert($contains($validator,['react_choice_special_wave_ok','A React Choice & Special Form Wave não está integralmente ligada']),'package_validator');
$assert($contains($manager,['React Choice + Special',"packageResult['react_choice_special_wave_ok']"]),'release_manager_kpi');
$assert($contains($doc,['fieldflow-react-choice-field-control-v1','fieldflow-react-special-field-assist-v1','ProductCardex remains canonical','No IndexedDB schema change','No FlutterFlow change']),'documentation_safety');

if ($fail) {
    fwrite(STDERR, 'REACT FORM CHOICE SPECIAL WAVE CONTRACT FAILED: ' . implode(', ', $fail) . PHP_EOL);
    exit(1);
}
echo 'REACT FORM CHOICE SPECIAL WAVE CONTRACT OK' . PHP_EOL;
echo 'conditional_owner=typed_native_bridge' . PHP_EOL;
echo 'special_business_logic_owner=legacy_typed_runtime' . PHP_EOL;
echo 'product_cardex_owner=php_canonical' . PHP_EOL;
echo 'offline_owner=fieldflow_offline_runtime' . PHP_EOL;
