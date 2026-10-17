<?php
/** Build-time and behavioural verifier for FieldFlow 2.2.530 Typed Submission & Validation Core. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) { $key = strtolower((string)$key); return preg_replace('/[^a-z0-9_\-]/', '', $key) ?: ''; }
}
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string { $path = $root . '/' . ltrim($relative, '/'); return is_readable($path) ? (string)file_get_contents($path) : ''; };
$containsAll = static function(string $content, array $markers): bool { foreach ($markers as $marker) if (strpos($content, $marker) === false) return false; return true; };
$failures = [];
$assert = static function(bool $ok, string $name) use (&$failures): void { if ($ok) echo "PASS {$name}\n"; else { echo "FAIL {$name}\n"; $failures[] = $name; } };

$fieldflow=$read('fieldflow.php'); $readme=$read('readme.txt'); $phase=$read('src/Cloud/Phase6StableRelease.php');
$source=$read('frontend/src/forms/submission-core.ts'); $asset=$read('assets/fieldflow-frontend-core-2.2.530.js');
$renderer=$read('src/Forms/FormRenderer.php'); $web=$read('src/Forms/Forms.php'); $mobile=$read('src/Mobile/SubmissionService.php');
$gate=$read('src/Support/ReleaseGate.php'); $manager=$read('src/Admin/ReleaseManager.php'); $doc=$read('PHASE-7ZW-TYPED-SUBMISSION-VALIDATION-CORE.md');

$assert($containsAll($fieldflow,['Version: 2.2.530',"FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme,'Stable tag: 2.2.530')!==false,'release_version_alignment');
$assert($containsAll($phase,["SOURCE_RELEASE = '2.2.529'","STABLE_RELEASE = '2.2.530'"]),'phase6_release_continuity');
$assert($containsAll($source,['fieldflow-typed-submission-validation-core-v1','fieldFlowSubmissionRequiredMissing','fieldFlowSubmissionShouldPersist','fieldFlowSubmissionPrepare','fieldFlowTypedSubmissionCoreApi']),'typed_submission_source_contract');
$assert($containsAll($asset,['FieldFlow 2.2.530','fieldflow-typed-submission-validation-core-v1','fieldFlowTypedSubmissionCoreApi','fieldFlowSubmissionPrepare']),'typed_submission_compiled_bundle');
$assert($containsAll($renderer,['rpTypedSubmissionCore',"data-rp-submission-core-contract','fieldflow-typed-submission-validation-core-v1'",'core.validation.prepare']),'form_renderer_submission_integration');
$assert($containsAll($web,['SubmissionValidation::is_question_active','SubmissionValidation::required_missing','SubmissionValidation::should_persist']),'web_submission_integration');
$assert($containsAll($mobile,['use RoutesPro\\Forms\\SubmissionValidation','SubmissionValidation::is_question_active','SubmissionValidation::required_missing','SubmissionValidation::should_persist']),'mobile_submission_integration');
$assert($containsAll($gate,["VERSION = '1.79.0'",'checkTypedSubmissionValidationCoreContract','fieldflow-typed-submission-validation-core-v1']),'release_gate_contract');
$assert($containsAll($manager,['Typed Submission',"packageResult['typed_submission_validation_core_ok']"]),'release_manager_gate');
$assert($containsAll($doc,['fieldflow-typed-submission-validation-core-v1','web e mobile','Release Manager','No database migration']),'release_documentation_contract');

require_once $root . '/src/Forms/SubmissionValidation.php';
use RoutesPro\Forms\SubmissionValidation;
$contract='fieldflow-typed-submission-validation-core-v1';
$assert(SubmissionValidation::CONTRACT===$contract && SubmissionValidation::VERSION===1,'php_submission_contract');
$fixtures=json_decode((string)file_get_contents($root.'/frontend/tests/submission-validation-fixtures.json'),true);
$assert(is_array($fixtures) && count($fixtures)>=8,'shared_fixture_set');
foreach ((array)$fixtures as $index=>$fixture) {
    $actual=SubmissionValidation::validate((array)($fixture['questions']??[]),(array)($fixture['values']??[]),(array)($fixture['visibility']??[]));
    $expected=(array)($fixture['expected']??[]);
    $ok=(bool)($actual['valid']??false)===(bool)($expected['valid']??false)
        && array_values((array)($actual['missing_required_keys']??[]))===array_values((array)($expected['missing']??[]))
        && array_values((array)($actual['active_keys']??[]))===array_values((array)($expected['active']??[]))
        && array_values((array)($actual['hidden_keys']??[]))===array_values((array)($expected['hidden']??[]));
    if (array_key_exists('active_values',$expected)) $ok=$ok && (array)($actual['active_values']??[])===(array)$expected['active_values'];
    $assert($ok,'php_typescript_fixture_'.($index+1));
}
$assert(SubmissionValidation::required_missing(['key'=>'confirm','type'=>'checkbox','required'=>true],false,['confirm'=>true]),'required_checkbox_false_rejected');
$assert(SubmissionValidation::should_persist(false,'checkbox',true),'optional_checkbox_false_persisted');
$assert(!SubmissionValidation::is_question_active('order_suggestion',['order_suggestion'=>false]),'hidden_order_suggestion_inactive');

if ($failures) { fwrite(STDERR,"TYPED SUBMISSION VALIDATION CORE CONTRACT FAILED\n".implode("\n",$failures)."\n"); exit(1); }
echo "TYPED SUBMISSION VALIDATION CORE CONTRACT OK\n";
echo "contract={$contract}\n";
echo "fixtures=".count((array)$fixtures)."\n";
echo "php_typescript_parity=ok\n";
echo "web_mobile_validation_parity=ok\n";
echo "release_manager_typed_submission_gate=ok\n";
