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
$foundation=$read('src/Front/ReactFrontFoundation.php'); $responsive=$read('src/Front/ResponsiveAssets.php');
$app=$read('src/Front/AppShortcodeRenderer.php'); $shortcodes=$read('src/Shortcodes.php'); $form=$read('src/Forms/FormRenderer.php');
$types=$read('frontend/src/react-front/types.ts'); $wizard=$read('frontend/src/react-front/form-wizard.ts');
$shell=$read('frontend/src/react-front/operational-shell.ts'); $route=$read('frontend/src/react-front/route-toolbar.ts'); $fields=$read('frontend/src/react-front/simple-fields.ts');
$bootstrap=$read('frontend/src/react-front/bootstrap.ts'); $tsconfig=$read('frontend/tsconfig.react-front.json'); $build=$read('frontend/tools/build.mjs');
$tests=$read('frontend/tests/react-front.test.mjs'); $css=$read('assets/fieldflow-react-front.css');
$asset=$read('assets/fieldflow-react-front-2.2.530.js'); $package=$read('frontend/package.json');
$gate=$read('src/Support/ReleaseGate.php'); $validator=$read('src/Support/ReleasePackageValidator.php'); $manager=$read('src/Admin/ReleaseManager.php');
$doc=$read('PHASE-8E-REACT-FRONT-OPERATIONAL-WAVE.md');

$assert($contains($plugin,['Version: 2.2.530',"FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme,'Stable tag: 2.2.530')!==false,'release_identity');
$assert($contains($phase,["VERSION = '1.0.87'","SOURCE_RELEASE = '2.2.529'","STABLE_RELEASE = '2.2.530'"]),'phase6_continuity');
$assert($contains($foundation,["OPERATIONAL_SHELL_CONTRACT = 'fieldflow-react-operational-shell-v1'","MY_ROUTE_CONTRACT = 'fieldflow-react-my-route-toolbar-v1'","SIMPLE_FIELD_CONTRACT = 'fieldflow-react-simple-field-assist-v1'",'simpleFieldTypeEnabled',"'route_data_owner' => 'routespro_rest_runtime'"]),'front_foundation_wave');
$assert($contains($responsive,['fieldflow-react-front-2.2.530.js','OPERATIONAL_SHELL_CONTRACT','MY_ROUTE_CONTRACT','SIMPLE_FIELD_CONTRACT']), 'front_asset_enqueue');
$assert($contains($app,['data-fieldflow-react-front-island="operational-shell"','data-fieldflow-react-front-fallback="operational-shell"','react_front_panels']), 'operational_shell_mount');
$assert($contains($shortcodes,['data-fieldflow-react-front-island="my-route-toolbar"','data-fieldflow-react-front-fallback="my-route-toolbar"','routeRootId']), 'my_route_mount');
$assert($contains($form,['data-fieldflow-react-front-island="simple-field-assist"','simpleFieldTypeEnabled','inputId']), 'simple_field_mount');
$assert($contains($types,['FieldFlowOperationalShellProps','FieldFlowMyRouteToolbarProps','FieldFlowSimpleFieldProps']), 'front_types');
$assert($contains($wizard,['fieldflow-react-operational-shell-v1','fieldflow-react-my-route-toolbar-v1','fieldflow-react-simple-field-assist-v1','FieldFlowOperationalShellIsland','FieldFlowMyRouteToolbarIsland','FieldFlowSimpleFieldAssistIsland']), 'front_dispatch');
$assert($contains($shell,['navigator.onLine','data-react-contract','Atalhos operacionais']), 'operational_shell_source');
$assert($contains($route,['#rp-date','#rp-route-select',"dispatchEvent(new Event('change'",'fieldflow:route-updated']), 'my_route_source');
$assert($contains($fields,['fieldFlowSimpleFieldInput','Preenchido','Por preencher','Opcional']), 'simple_field_source');
$assert($contains($bootstrap,['fieldflow:lazy-panel-loaded','fieldflow:form-rendered','MutationObserver','addedNodes']), 'dynamic_mounts');
$assert($contains($tsconfig,['src/react-front/operational-shell.ts','src/react-front/route-toolbar.ts','src/react-front/simple-fields.ts','"strict": true']), 'strict_build_scope');
$assert($contains($build,['reactOperationalShellContract','reactMyRouteContract','reactSimpleFieldContract']), 'build_identity');
$assert($contains($tests,['React operational front wave compiles shell, route toolbar and simple-field assist contracts','React operational shell mounts progressively over the legacy navigation']), 'behavioural_tests');
$assert($contains($css,['ff-react-front-operational-shell','ff-react-front-route-toolbar','ff-react-front-field-assist']), 'front_wave_css');
$assert($contains($asset,['FieldFlow 2.2.530','fieldflow-react-operational-shell-v1','fieldflow-react-my-route-toolbar-v1','fieldflow-react-simple-field-assist-v1','wordpress-wp-element']), 'compiled_front_wave');
$assert(strpos($package,'"react"')===false && strpos($package,'"react-dom"')===false,'no_bundled_react');
$assert($contains($gate,['checkReactFrontOperationalWaveContract','React Front Operational Wave']), 'release_gate');
$assert($contains($validator,['react_front_operational_wave_ok','A React Front Operational Wave não está integralmente ligada']), 'package_validator');
$assert($contains($manager,['React Front Wave',"packageResult['react_front_operational_wave_ok']"]), 'release_manager_kpi');
$assert($contains($doc,['fieldflow-react-front-operational-wave-v1','PHP fallback','No database migration','No REST contract change','No IndexedDB schema change','No FlutterFlow change']), 'documentation_safety');

if ($fail) {
    fwrite(STDERR, 'REACT FRONT OPERATIONAL WAVE CONTRACT FAILED: ' . implode(', ', $fail) . PHP_EOL);
    exit(1);
}
echo 'REACT FRONT OPERATIONAL WAVE CONTRACT OK' . PHP_EOL;
echo 'route_data_owner=routespro_rest_runtime' . PHP_EOL;
echo 'submission_owner=legacy_typed_runtime' . PHP_EOL;
echo 'offline_owner=fieldflow_offline_runtime' . PHP_EOL;
echo 'php_fallback=preserved' . PHP_EOL;
