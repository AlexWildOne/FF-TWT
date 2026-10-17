<?php
/** Build-time verifier for FieldFlow 2.2.530 Operational Action Hardening. */
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
$source = $read('frontend/src/react-front/module-controls.ts');
$css = $read('assets/fieldflow-react-front.css');
$asset = $read('assets/fieldflow-react-front-2.2.530.js');
$tests = $read('frontend/tests/react-front.test.mjs');
$doc = $read('PHASE-8K-OPERATIONAL-ACTION-HARDENING.md');
$submission = $read('src/Forms/SubmissionPipeline.php');
$implementationService = $read('src/Implementations/ImplementationService.php');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkOperationalActionHardeningContract', 'fieldflow-react-operational-actions-hardening-v1']), 'release_gate_contract');
$assert($contains($source, [
    'fieldflow-react-operational-actions-hardening-v1',
    'fieldFlowOperationalModuleReconcileImplementationVisibility',
    "if (props.module === 'implementations')",
    'queueMicrotask(() => fieldFlowOperationalModuleReconcileImplementationVisibility(moduleRoot))',
    "'data-action-contract': FIELD_FLOW_REACT_OPERATIONAL_ACTIONS_CONTRACT",
]), 'react_action_proxy_hardening');
$assert(!$contains($source, ["onInput: (event: unknown) => fieldFlowOperationalModuleWrite", "onChange: (event: unknown) => fieldFlowOperationalModuleWrite"])
    || strpos($source, "element.createElement('input'") !== false, 'input_handler_shape');
$assert(!preg_match("/element\\.createElement\\('input',[\\s\\S]{0,700}?onInput:[\\s\\S]{0,300}?onChange:/", $source), 'no_duplicate_visible_input_dispatch');
$assert($contains($css, [
    'Phase 8K, 2.2.498',
    'fieldflow-react-operational-actions-hardening-v1',
    '.ff-react-operational-module-implementations .ff-react-module-chips',
    '.ff-operational-surface-implementations .ffi-front-filters',
    'flex-wrap:nowrap!important',
    'overflow-x:auto!important',
]), 'implementation_tabs_layout');
$assert($contains($asset, ['FieldFlow 2.2.530', 'fieldflow-react-operational-actions-hardening-v1', 'fieldFlowOperationalModuleReconcileImplementationVisibility', 'data-action-contract']), 'compiled_action_hardening');
$assert($contains($tests, ['Operational action hardening keeps Implementações chips stable and reconciles combined visibility', 'Operational React inputs do not double-dispatch the same visible edit']), 'regression_tests');
$assert($contains($validator, ['operational_action_hardening_ok', 'O Operational Action Hardening não está integralmente ligado']), 'package_validator_guard');
$assert($contains($manager, ['Operational Action Hardening', "packageResult['operational_action_hardening_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-react-operational-actions-hardening-v1', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.', '`SOURCE_RELEASE` is `2.2.495` and `STABLE_RELEASE` is `2.2.496`']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_pipeline_build_preserved');
$assert($contains($implementationService, ['public static function publish', 'public static function saveMappings', 'public static function updateItems']), 'implementation_business_runtime_preserved');

if ($failures) {
    fwrite(STDERR, "OPERATIONAL ACTION HARDENING CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "OPERATIONAL ACTION HARDENING CONTRACT OK\n";
echo "release=2.2.507\n";
echo "contract=fieldflow-react-operational-actions-hardening-v1\n";
echo "implementation_filters=stable_horizontal_scroll\n";
echo "combined_visibility=search_plus_status\n";
echo "visible_input_dispatch=single\n";
echo "interaction_owner=native_controls\n";
echo "core_rewrite=none\n";
