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
$plugin = $read('fieldflow.php');
preg_match("/define\\('FIELDFLOW_VERSION',\\s*'([^']+)'\\)/", $plugin, $m);
$version = $m[1] ?? '';
$foundation = $read('src/Front/ReactFrontFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$form = $read('src/Forms/FormRenderer.php');
$types = $read('frontend/src/react-front/types.ts');
$wizard = $read('frontend/src/react-front/form-wizard.ts');
$bootstrap = $read('frontend/src/react-front/bootstrap.ts');
$build = $read('frontend/tools/build.mjs');
$tests = $read('frontend/tests/react-front.test.mjs');
$css = $read('assets/fieldflow-react-front.css');
$asset = $read('assets/fieldflow-react-front-' . $version . '.js');
$loader = $read('src/Support/Loader.php');
$package = $read('frontend/package.json');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-8D-REACT-FRONT-FORM-WIZARD.md');

$assert($version === '2.2.530', 'release_identity');
$assert($contains($foundation, ["CONTRACT = 'fieldflow-react-front-foundation-v1'", "FORM_WIZARD_CONTRACT = 'fieldflow-react-form-wizard-progress-v1'", "FORM_WIZARD_ISLAND = 'form-wizard-progress'", 'FIELDFLOW_REACT_FRONT', "'submission_owner' => 'legacy_typed_runtime'", "'offline_owner' => 'fieldflow_offline_runtime'"]), 'front_foundation_contract');
$assert($contains($responsive, ['fieldflow-react-front-' . $version . '.js', "[self::FRONTEND_CORE_SCRIPT_HANDLE, 'wp-element']", 'ReactFrontFoundation::FORM_WIZARD_ISLAND']), 'front_asset_reference');
$assert($contains($form, ['data-fieldflow-react-front-island="form-wizard-progress"', 'data-fieldflow-react-front-fallback="form-wizard-progress"', 'routespro-form-progress-fill', 'data-rp-progress-label', "ReactFrontFoundation::islandEnabled('form-wizard-progress')"]), 'form_renderer_progressive_mount');
$assert($contains($types, ['FieldFlowFormWizardProgressProps', 'FieldFlowReactFrontApi', 'FieldFlowReactFront']), 'react_front_types');
$assert($contains($wizard, ['fieldflow-react-front-foundation-v1', 'fieldflow-react-form-wizard-progress-v1', 'MutationObserver', 'data-fieldflow-react-front-fallback-hidden', 'form-wizard-progress']), 'react_front_wizard_source');
$assert($contains($bootstrap, ['FieldFlowReactFront', 'wordpress-wp-element', 'fieldFlowReactFrontMountAll']), 'react_front_bootstrap');
$assert($contains($build, ['reactFrontContract', 'tsconfig.react-front.json', 'fieldflow-react-front-${version}.js']), 'react_front_build');
$assert($contains($tests, ['React form wizard progress mounts progressively without owning submission', 'React front bundle uses wp-element']), 'react_front_tests');
$assert($contains($css, ['ff-react-front-form-progress', 'data-fieldflow-react-front-fallback-hidden']), 'react_front_css');
$assert($contains($asset, ['FieldFlow ' . $version, 'fieldflow-react-front-foundation-v1', 'fieldflow-react-form-wizard-progress-v1', 'wordpress-wp-element']), 'compiled_front_asset');
$assert(strpos($loader, 'src/Front/ReactFrontFoundation.php') !== false, 'loader_registration');
$assert(strpos($package, '"react"') === false && strpos($package, '"react-dom"') === false, 'no_bundled_react');
$assert($contains($gate, ['checkReactFrontFormWizardContract', 'React Front Form Wizard']), 'release_gate');
$assert($contains($validator, ['react_front_form_wizard_ok', 'A React Front Foundation do formulário não está integralmente ligada']), 'package_validator');
$assert($contains($manager, ['React Front', "packageResult['react_front_form_wizard_ok']"]), 'release_manager_kpi');
$assert($contains($doc, ['PHP fallback', 'No database migration', 'No REST contract change', 'No IndexedDB schema change', 'No FlutterFlow change']), 'documentation_safety');

if ($fail) {
    fwrite(STDERR, 'REACT FRONT FORM WIZARD CONTRACT FAILED: ' . implode(', ', $fail) . PHP_EOL);
    exit(1);
}
echo 'REACT FRONT FORM WIZARD CONTRACT OK' . PHP_EOL;
echo 'submission_owner=legacy_typed_runtime' . PHP_EOL;
echo 'offline_owner=fieldflow_offline_runtime' . PHP_EOL;
echo 'php_fallback=preserved' . PHP_EOL;
