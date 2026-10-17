<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
};
$plugin = $read('fieldflow.php');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$semantics = $read('src/Forms/AnswerSemantics.php');
$forms = $read('src/Forms/Forms.php');
$mobile = $read('src/Mobile/SubmissionService.php');
$submissionTs = $read('frontend/src/forms/submission-core.ts');
$semanticsTs = $read('frontend/src/forms/answer-semantics-core.ts');
$renderer = $read('src/Forms/FormRenderer.php');
$doc = $read('PHASE-9V-CANONICAL-ANSWER-SEMANTICS.md');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$migrations = $read('src/Support/Migrations.php');
$pipeline = $read('src/Forms/SubmissionPipeline.php');

$assert((bool)preg_match("/FIELDFLOW_VERSION'\s*,\s*'([^']+)'/", $plugin, $m), 'release_identity_missing');
$release = (string)$m[1];
$assert(version_compare($release, '2.2.538', '>='), 'release_identity_too_old');
$assert(strpos($semantics, "CONTRACT = 'fieldflow-canonical-answer-semantics-v1'") !== false, 'server_contract');
$assert(strpos($semantics, 'number_below_min') !== false && strpos($semantics, 'number_above_max') !== false, 'numeric_bounds');
$assert(strpos($semantics, 'choice_not_allowed') !== false && strpos($semantics, 'structured_value_invalid') !== false, 'choice_structured_guards');
$assert(strpos($forms, 'AnswerSemantics::normalize($question, $value, true)') !== false, 'web_integration');
$assert(strpos($mobile, 'AnswerSemantics::normalize($question, $value, true)') !== false, 'mobile_integration');
$assert(strpos($semanticsTs, "FIELD_FLOW_ANSWER_SEMANTICS_CONTRACT = 'fieldflow-canonical-answer-semantics-v1'") !== false, 'typescript_contract');
$assert(strpos($submissionTs, 'invalidSemanticKeys') !== false && strpos($submissionTs, 'fieldFlowAnswerSemanticNormalize') !== false, 'typed_submission_integration');
$assert(strpos($renderer, 'prepared.invalidSemanticKeys') !== false, 'browser_preflight_integration');
$assert(strpos($doc, '`SOURCE_RELEASE` is `2.2.537` and `STABLE_RELEASE` is `2.2.538`') !== false, 'phase_documentation');
$assert(strpos($gate, "CANONICAL_ANSWER_SEMANTICS_9V_CONTRACT = 'fieldflow-canonical-answer-semantics-v1'") !== false, 'release_gate_contract');
$assert(strpos($validator, "'canonical_answer_semantics_9v_ok'=>false") !== false, 'package_validator_contract');
$assert(strpos($manager, 'Canonical Answer Semantics 9V') !== false && strpos($manager, "packageResult['canonical_answer_semantics_9v_ok']") !== false, 'release_manager_kpi');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema_identity');
$assert(strpos($pipeline, "public const BUILD = '2.2.485'") !== false, 'submission_identity');
$assert((bool)preg_match("/SOURCE_RELEASE\s*=\s*'([^']+)'/", $phase6, $sourceMatch), 'phase6_source');
$assert((bool)preg_match("/STABLE_RELEASE\s*=\s*'([^']+)'/", $phase6, $stableMatch), 'phase6_stable');
$assert((string)$stableMatch[1] === $release, 'phase6_runtime_match');

echo "CANONICAL ANSWER SEMANTICS 9V CONTRACT OK\n";
echo "release={$release}\n";
echo "successor=>=2.2.538\n";
echo "schema=2.2.529\n";
echo "submission_build=2.2.485\n";
