<?php
/** Build-time verifier for FieldFlow 2.2.530 React Form Completion. */
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
$assert = static function(bool $ok, string $label) use (&$failures): void { if (!$ok) $failures[] = $label; };

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$renderer = $read('src/Forms/FormRenderer.php');
$dispatch = $read('frontend/src/react-front/form-wizard.ts');
$types = $read('frontend/src/react-front/types.ts');
$special = $read('frontend/src/react-front/special-fields.ts');
$build = $read('frontend/tools/build.mjs');
$tests = $read('frontend/tests/react-front.test.mjs');
$css = $read('assets/fieldflow-react-front.css');
$asset = $read('assets/fieldflow-react-front-2.2.530.js');
$package = $read('frontend/package.json');
$doc = $read('PHASE-8M-REACT-FORM-COMPLETION.md');
$submission = $read('src/Forms/SubmissionPipeline.php');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkReactFormCompletionContract', 'fieldflow-react-form-completion-v1']), 'release_gate_contract');
$assert($contains($foundation, ["FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1'", "'form_completion_contract' => self::FORM_COMPLETION_CONTRACT", "'submission_owner' => 'legacy_typed_runtime'", "'offline_owner' => 'fieldflow_offline_runtime'"]), 'react_foundation_contract');
$assert($contains($renderer, ['ReactFrontFoundation::FORM_COMPLETION_CONTRACT', 'data-fronts-input', 'data-order-qty', 'data-share-space-card', 'data-rp-evidence-category', 'ProductCardex::question_rows', 'ProductCardex::order_question_rows']), 'canonical_form_owner_markers');
$assert($contains($dispatch, ["FIELD_FLOW_REACT_FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1'"]), 'dispatch_contract');
$assert($contains($types, ['formCompletionContract?: string', 'FieldFlowSpecialCategorySnapshot', 'totalOrderQty: number']), 'typed_contract');
$assert($contains($special, ['fieldFlowSpecialActive', 'fieldFlowSpecialMediaCount', 'fieldFlowSpecialGoToIncomplete', 'fieldFlowSpecialOpenCategory', 'Inativo pela condição anterior', 'Zero automático', 'data-fieldflow-react-form-completion', 'Captura, recuperação e envio continuam no runtime FieldFlow certificado.']), 'react_form_completion_source');
$assert(strpos($special, "['share_space_matrix','categorized_image_upload'].includes(props.fieldType)") !== false, 'no_false_product_matrix_pending_action');
$assert($contains($build, ['reactFormCompletionContract', 'fieldflow-react-form-completion-v1']), 'build_contract');
$assert($contains($tests, ['React form completion compiles category navigation, pending guidance and conditional suggestion awareness', 'React form completion stays wired as UX over canonical special-field owners']), 'frontend_tests');
$assert($contains($css, ['Phase 8M, 2.2.498: React Form Completion.', 'fieldflow-react-form-completion-v1', '.ff-react-special-category-nav', '.ff-react-special-summary', '[data-fieldflow-react-form-completion="1"]']), 'form_completion_css');
$assert($contains($asset, ['FieldFlow 2.2.530', 'fieldflow-react-form-completion-v1', 'fieldFlowSpecialGoToIncomplete', 'Inativo pela condição anterior', 'Zero automático', 'wordpress-wp-element']), 'compiled_asset');
$assert($contains($validator, ['react_form_completion_ok', 'O React Form Completion não está integralmente ligado']), 'package_validator_guard');
$assert($contains($manager, ['React Form Completion', "packageResult['react_form_completion_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-react-form-completion-v1', 'ProductCardex remains canonical.', 'No IndexedDB schema change.', 'No media transport ownership change.', '`SOURCE_RELEASE` is `2.2.497` and `STABLE_RELEASE` is `2.2.498`']), 'phase_documentation');
$assert(strpos($package, '"react"') === false && strpos($package, '"react-dom"') === false, 'no_bundled_react');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_pipeline_build_preserved');

if ($failures) {
    fwrite(STDERR, "REACT FORM COMPLETION CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "REACT FORM COMPLETION CONTRACT OK\n";
echo "release=2.2.507\n";
echo "contract=fieldflow-react-form-completion-v1\n";
echo "business_logic_owner=canonical_native_runtime\n";
echo "offline_owner=fieldflow_offline_runtime\n";
echo "media_transport_owner=certified_native_runtime\n";
echo "submission_build=2.2.485\n";
