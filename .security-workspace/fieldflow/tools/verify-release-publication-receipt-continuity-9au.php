<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$checks = [];
$assert = static function(bool $ok, string $label) use (&$checks): void { $checks[$label] = $ok; };
$receipt = $read('src/Cloud/ReleasePublicationReceipt.php');
$manager = $read('src/Admin/ReleaseManager.php');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$doc = $read('PHASE-9AU-RELEASE-MANAGER-PUBLICATION-RECEIPT-CONTINUITY.md');
$migrations = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');

$assert(strpos($receipt, "CONTRACT = 'fieldflow-release-manager-publication-receipt-v1'") !== false, 'receipt_contract');
$assert(strpos($receipt, "OPTION = 'fieldflow_release_manager_last_state'") !== false, 'local_option');
$assert(strpos($receipt, 'auditCandidates') !== false && strpos($receipt, "'action_name'=>'release.published'") !== false, 'audit_recovery');
$assert(strpos($receipt, 'healLocalOption') !== false, 'receipt_self_heal');
$assert(strpos($manager, 'ReleasePublicationReceipt::persist($state)') !== false, 'manager_persist');
$assert(strpos($manager, "ReleasePublicationReceipt::current(defined('FIELDFLOW_VERSION')") !== false, 'manager_read');
$assert(strpos($phase6, 'ReleasePublicationReceipt::current(self::STABLE_RELEASE)') !== false, 'phase6_receipt');
$assert(strpos($phase6, "'wordpress_evidence'") !== false && strpos($phase6, "'catalog_sha_match'") !== false, 'phase6_corroboration');
$assert(strpos($phase6, "SOURCE_RELEASE = '2.2.564'") !== false && strpos($phase6, "STABLE_RELEASE = '2.2.565'") !== false, 'phase6_successor');
$assert(strpos($gate, "RELEASE_MANAGER_PUBLICATION_RECEIPT_9AU_CONTRACT = 'fieldflow-release-manager-publication-receipt-v1'") !== false, 'release_gate_contract');
$assert(strpos($validator, 'release_manager_publication_receipt_9au_ok') !== false, 'package_validator_contract');
$assert(strpos($doc, 'No bypass') !== false, 'no_bypass');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema_unchanged');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_unchanged');

$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, 'FAILED ' . implode(',', $failed) . PHP_EOL);
    exit(1);
}
echo "RELEASE MANAGER PUBLICATION RECEIPT 9AU CONTRACT OK\n";
