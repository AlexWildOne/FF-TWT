<?php
/** Build-time verifier for FieldFlow 2.2.530 React Operational Modules. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$contains = static function(string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};
$failures = [];
$assert = static function(bool $ok, string $label) use (&$failures): void {
    if (!$ok) $failures[] = $label;
};

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$source = $read('frontend/src/react-front/module-controls.ts');
$formWizard = $read('frontend/src/react-front/form-wizard.ts');
$types = $read('frontend/src/react-front/types.ts');
$css = $read('assets/fieldflow-react-front.css');
$asset = $read('assets/fieldflow-react-front-2.2.530.js');
$tests = $read('frontend/tests/react-front.test.mjs');
$package = $read('frontend/package.json');
$submission = $read('src/Forms/SubmissionPipeline.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$gate = $read('src/Support/ReleaseGate.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-8J-REACT-OPERATIONAL-MODULES.md');

$renderers = [
    'briefings' => $read('src/Front/BriefingsShortcodeRenderer.php'),
    'implementations' => $read('src/Front/ImplementationsShortcodeRenderer.php'),
    'stocks' => $read('src/Front/StocksShortcodeRenderer.php'),
    'shopping' => $read('src/Front/ShoppingShortcodeRenderer.php'),
    'planner' => $read('src/Front/SelfRoutePlannerShortcodeRenderer.php'),
    'expenses' => $read('src/Front/ExpensesShortcodeRenderer.php'),
    'messages' => $read('src/Front/TeamInboxShortcodeRenderer.php'),
];

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($foundation, ["OPERATIONAL_MODULES_CONTRACT = 'fieldflow-react-operational-modules-v1'", "OPERATIONAL_MODULES_ISLAND = 'operational-module-controls'", 'operationalModuleMount']), 'foundation_contract');
$assert($contains($responsive, ['fieldflow-react-front-2.2.530.js', 'ReactFrontFoundation::OPERATIONAL_MODULES_CONTRACT', 'ReactFrontFoundation::OPERATIONAL_MODULES_ISLAND']), 'runtime_enqueue');
$assert($contains($source, ['fieldflow-react-operational-modules-v1', 'fieldFlowOperationalModuleWrite', 'fieldFlowOperationalModuleClickAction', 'data-fieldflow-react-operational-native', 'dispatchEvent(new Event(eventName, { bubbles: true }))', 'ffp-react-primary-filters', '.ffs-form-view .ffs-store-card']), 'shared_native_proxy');
$assert($contains($formWizard, ["FIELD_FLOW_OPERATIONAL_MODULES_ISLAND = 'operational-module-controls'", 'FieldFlowOperationalModuleControlsIsland']), 'island_mount_dispatch');
$assert($contains($types, ['FieldFlowOperationalModuleProps', "island: 'operational-module-controls'", 'FieldFlowOperationalModuleKey']), 'typed_contract');
foreach ($renderers as $module => $renderer) {
    $markers = ['operationalModuleMount', "'{$module}'"];
    if ($module === 'planner') $markers[] = 'ffp-react-primary-filters';
    $assert($contains($renderer, $markers), 'renderer_mount_' . $module);
}
$assert($contains($css, ['Phase 8J, 2.2.498', 'fieldflow-react-operational-modules-v1', 'data-fieldflow-react-operational-native', 'ff-react-module-search-hidden']), 'visual_fallback_contract');
$assert($contains($asset, ['FieldFlow 2.2.530', 'fieldflow-react-operational-modules-v1', 'operational-module-controls', 'data-fieldflow-react-operational-native', 'wordpress-wp-element']), 'compiled_operational_modules');
$assert($contains($tests, ['React operational modules compile a shared native-proxy contract for all priority surfaces', 'React operational module mounts preserve the seven canonical PHP module owners']), 'frontend_regression_tests');
$assert(strpos($package, '"react"') === false && strpos($package, '"react-dom"') === false, 'no_bundled_react');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_pipeline_build_preserved');
$assert($contains($validator, ['react_operational_modules_ok', 'Os React Operational Modules não estão integralmente ligados']), 'package_validator_guard');
$assert($contains($gate, ['checkReactOperationalModulesContract', 'React Operational Modules']), 'release_gate_guard');
$assert($contains($manager, ['React Operational Modules', "packageResult['react_operational_modules_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-react-operational-modules-v1', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.', '`SOURCE_RELEASE` is `2.2.494` and `STABLE_RELEASE` is `2.2.495`']), 'phase_documentation');

if ($failures) {
    fwrite(STDERR, "REACT OPERATIONAL MODULES CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "REACT OPERATIONAL MODULES CONTRACT OK\n";
echo "release=2.2.507\n";
echo "contract=fieldflow-react-operational-modules-v1\n";
echo "modules=briefings,implementations,stocks,shopping,planner,expenses,messages\n";
echo "interaction_owner=native_controls\n";
echo "react_runtime=wordpress-wp-element\n";
echo "core_rewrite=none\n";
