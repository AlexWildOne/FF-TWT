<?php
/** Build-time verifier for FieldFlow 2.2.530 Typed Draft & Media Core. */
$root = dirname(__DIR__);
$checks = [];
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $condition, string $name) use (&$checks): void {
    $checks[$name] = $condition ? 'PASS' : 'FAIL';
};
$containsAll = static function(string $haystack, array $needles): bool {
    foreach ($needles as $needle) if (strpos($haystack, $needle) === false) return false;
    return true;
};

$fieldflow = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$draft = $read('frontend/src/drafts/draft-core.ts');
$bootstrap = $read('frontend/src/bootstrap.ts');
$tests = $read('frontend/tests/frontend-build.test.mjs');
$form = $read('src/Forms/FormRenderer.php');
$asset = $read('assets/fieldflow-frontend-core-2.2.530.js');
$doc = $read('PHASE-7ZU-TYPED-DRAFT-MEDIA-CORE.md');

$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'checkTypedDraftMediaCoreContract', 'fieldflow-typed-draft-media-core-v1']), 'release_gate_contract');
$assert($containsAll($draft, ["FIELD_FLOW_TYPED_DRAFT_CONTRACT = 'fieldflow-typed-draft-media-core-v1'", "FIELD_FLOW_DRAFT_DB_NAME = 'fieldflow-form-drafts'", 'FIELD_FLOW_DRAFT_DB_VERSION = 2', 'fieldFlowDraftStoreSnapshot', 'fieldFlowDraftStoreFiles', 'fieldFlowDraftCleanupExpiredSnapshots', 'fieldFlowDraftNewestLocal']), 'typed_draft_source_contract');
$assert($containsAll($bootstrap, ['draftCore: fieldFlowTypedDraftCoreApi']), 'typed_draft_bootstrap');
$assert($containsAll($tests, ['typed draft core preserves snapshot, media and reconciliation semantics', "drafts.dbName, 'fieldflow-form-drafts'", 'drafts.state.newestLocal']), 'typed_draft_behavioural_tests');
$assert($containsAll($form, ['fieldflow-typed-draft-media-core-v1', 'rpTypedDraftPersistence', 'persistence.storeSnapshot', 'persistence.storeFiles', 'persistence.readFiles', 'core.state.contextValid', 'core.state.newestLocal', 'draftExpectedFileCount']), 'form_renderer_progressive_integration');
$assert($containsAll($asset, ['FieldFlow 2.2.530', 'fieldflow-typed-draft-media-core-v1', 'fieldflow-form-drafts', 'fieldFlowTypedDraftCoreApi', 'cleanupExpiredSnapshots']), 'typed_draft_compiled_bundle');
$assert($containsAll($validator, ['typed_draft_media_core_ok', 'O Typed Draft & Media Core não está integralmente compilado e ligado ao FormRenderer da release.']) && $containsAll($manager, ['Typed Draft & Media', "packageResult['typed_draft_media_core_ok']"]), 'release_manager_typed_draft_gate');
$assert($containsAll($doc, ['fieldflow-typed-draft-media-core-v1', 'fieldflow-form-drafts', 'schema v2', 'Release Manager', 'No database migration']), 'release_documentation_contract');

$failed = array_keys(array_filter($checks, static fn($status) => $status !== 'PASS'));
foreach ($checks as $name => $status) echo $status . ' ' . $name . PHP_EOL;
echo $failed ? ('FAILED ' . implode(',', $failed) . PHP_EOL) : ('TYPED DRAFT MEDIA CORE CONTRACT OK' . PHP_EOL);
exit($failed ? 1 : 0);
