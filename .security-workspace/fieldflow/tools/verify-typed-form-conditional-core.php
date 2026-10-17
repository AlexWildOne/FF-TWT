<?php
/** Build-time and behavioural verifier for FieldFlow 2.2.530 Typed Form State + Conditional Engine. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string)$key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?: '';
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) { return trim((string)$value); }
}

$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$containsAll = static function(string $content, array $markers): bool {
    foreach ($markers as $marker) if (strpos($content, $marker) === false) return false;
    return true;
};
$failures = [];
$assert = static function(bool $ok, string $name) use (&$failures): void {
    if ($ok) echo "PASS {$name}\n";
    else { echo "FAIL {$name}\n"; $failures[] = $name; }
};

$fieldflow = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$manager = $read('src/Admin/ReleaseManager.php');
$renderer = $read('src/Forms/FormRenderer.php');
$runtimeV3 = $read('assets/forms-runtime-v3.js');
$source = $read('frontend/src/forms/form-core.ts');
$asset = $read('assets/fieldflow-frontend-core-2.2.530.js');
$contractDoc = $read('PHASE-7ZV-TYPED-FORM-STATE-CONDITIONAL-ENGINE.md');

$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert($containsAll($source, ['fieldflow-typed-form-state-conditional-core-v1', 'fieldFlowConditionalEvaluate', 'fieldFlowConditionalSnapshot', 'fieldFlowActiveRequiredKeys', 'fieldFlowFilterActiveValues']), 'typed_form_source_contract');
$assert($containsAll($asset, ['FieldFlow 2.2.530', 'fieldflow-typed-form-state-conditional-core-v1', 'fieldFlowTypedFormCoreApi', 'fieldFlowConditionalEvaluate']), 'typed_form_compiled_bundle');
$assert($containsAll($renderer, ['rpTypedFormCore', 'typedCore.conditions.evaluate', 'data-rp-conditional-core-contract']), 'form_renderer_progressive_integration');
$assert($containsAll($runtimeV3, ['typedFormCore()', 'core.conditions.evaluate', 'data-rp-conditional-core-contract']), 'runtime_v3_progressive_integration');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'fieldflow-typed-form-state-conditional-core-v1', 'checkTypedFormConditionalCoreContract']), 'release_gate_contract');
$assert($containsAll($manager, ['Typed Form State', "packageResult['typed_form_conditional_core_ok']"]), 'release_manager_typed_form_gate');
$assert($containsAll($contractDoc, ['fieldflow-typed-form-state-conditional-core-v1', 'paridade PHP/TypeScript', 'No database migration']), 'release_documentation_contract');

require_once $root . '/src/Forms/ConditionalFlow.php';
use RoutesPro\Forms\ConditionalFlow;

$contract = 'fieldflow-typed-form-state-conditional-core-v1';
$assert(ConditionalFlow::CONTRACT === $contract && ConditionalFlow::VERSION === 3, 'php_conditional_contract');

$fixtureFile = $root . '/frontend/tests/conditional-flow-fixtures.json';
$fixtures = json_decode((string)file_get_contents($fixtureFile), true);
$assert(is_array($fixtures) && count($fixtures) >= 8, 'shared_fixture_set');
foreach ((array)$fixtures as $index => $fixture) {
    $keys = array_values(array_map('strval', (array)($fixture['keys'] ?? [])));
    $rules = [];
    foreach ((array)($fixture['rules'] ?? []) as $rule) {
        if (!is_array($rule)) continue;
        $rules[] = [
            'source_key' => (string)($rule['source_key'] ?? $rule['source'] ?? ''),
            'target_key' => (string)($rule['target_key'] ?? $rule['target'] ?? ''),
            'expected_value' => (string)($rule['expected_value'] ?? $rule['value'] ?? ''),
            'operator' => 'equals',
        ];
    }
    $actual = ConditionalFlow::evaluate($keys, $rules, (array)($fixture['values'] ?? []));
    $expected = (array)($fixture['expected'] ?? []);
    $assert($actual === $expected, 'php_typescript_fixture_' . ($index + 1));
}

$schema = [
    'questions' => [
        ['key'=>'need_order','type'=>'radio','required'=>true,'conditions'=>['enabled'=>true,'rules'=>[['value'=>'Sim','go_to'=>'order_suggestion']]]],
        ['key'=>'order_suggestion','type'=>'order_suggestion','required'=>true],
        ['key'=>'notes','type'=>'text','required'=>true],
    ],
];
$payloadNo = ConditionalFlow::payload($schema, ['need_order'=>'Não']);
$metaNo = ConditionalFlow::submission_meta($schema, (array)($payloadNo['initial_visibility'] ?? []));
$orderNoOk = ($payloadNo['contract'] ?? '') === $contract
    && ($metaNo['contract'] ?? '') === $contract
    && in_array('order_suggestion', (array)($metaNo['hidden_question_keys'] ?? []), true)
    && !in_array('order_suggestion', (array)($metaNo['active_order_suggestion_keys'] ?? []), true);
$assert($orderNoOk, 'order_suggestion_conditional_state_no');

$payloadYes = ConditionalFlow::payload($schema, ['need_order'=>'Sim']);
$metaYes = ConditionalFlow::submission_meta($schema, (array)($payloadYes['initial_visibility'] ?? []));
$assert(in_array('order_suggestion', (array)($metaYes['active_order_suggestion_keys'] ?? []), true), 'order_suggestion_conditional_state_yes');

if ($failures) {
    fwrite(STDERR, "TYPED FORM STATE CONDITIONAL CORE CONTRACT FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "TYPED FORM STATE CONDITIONAL CORE CONTRACT OK\n";
echo "contract={$contract}\n";
echo "fixtures=" . count((array)$fixtures) . "\n";
echo "php_typescript_parity=ok\n";
echo "order_suggestion_conditional_state=ok\n";
echo "release_manager_typed_form_gate=ok\n";
