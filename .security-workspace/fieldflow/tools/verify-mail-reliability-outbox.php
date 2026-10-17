<?php
/** Build-time successor verifier for Mail Reliability / Outbox Foundation. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$failures = [];
$assert = static function(bool $ok, string $label) use (&$failures): void { if (!$ok) $failures[] = $label; };

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$service = $read('src/Services/MailDispatchService.php');
$repo = $read('src/Services/MailOutboxRepository.php');
$migrations = $read('src/Support/Migrations.php');
$schema = $read('src/Support/SchemaManifest.php');
$runtime = $read('src/Architecture/RuntimeRegistry.php');
$emails = $read('src/Admin/Emails.php');
$reports = $read('src/Admin/ReportAutomations.php');
$billing = $read('src/Billing/ProductionHardening.php');
$performance = $read('src/Performance/Module.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9I-MAIL-RELIABILITY-OUTBOX-FOUNDATION.md');

$version = '';
if (preg_match('/Version:\s*([0-9.]+)/', $plugin, $m)) $version = (string)$m[1];
$stableTag = '';
if (preg_match('/Stable tag:\s*([0-9.]+)/', $readme, $m)) $stableTag = (string)$m[1];
$sourceRelease = '';
$stableRelease = '';
if (preg_match("/SOURCE_RELEASE = '([^']+)'/", $phase6, $m)) $sourceRelease = (string)$m[1];
if (preg_match("/STABLE_RELEASE = '([^']+)'/", $phase6, $m)) $stableRelease = (string)$m[1];
$assert($version !== '' && version_compare($version, '2.2.530', '>=') && strpos($plugin, "FIELDFLOW_VERSION', '" . $version . "'") !== false, 'release_identity');
$assert($stableTag === $version, 'readme_identity');
$assert($stableRelease === $version && $sourceRelease !== '' && version_compare($sourceRelease, $stableRelease, '<'), 'phase6_continuity');
$assert(strpos($service, "OUTBOX_CONTRACT = 'fieldflow-mail-reliability-outbox-v1'") !== false && strpos($service, "OUTBOX_BUILD = '2.2.525'") !== false, 'outbox_contract');
$assert(strpos($service, "public const BUILD = '2.2.524'") !== false && strpos($service, 'wp_mail($to, $subject, $message, $headers, $attachments)') !== false, 'foundation_continuity');
$assert(strpos($service, 'processOutbox') !== false && strpos($service, 'recoverStaleLocks') !== false && strpos($service, 'idempotency_key') !== false, 'reliability_engine');
$assert(strpos($repo, "TABLE_SUFFIX = 'mail_outbox'") !== false && strpos($repo, 'function claim(') !== false && strpos($repo, 'function findByIdempotencyKey(') !== false, 'outbox_repository');
$schemaVersion = '';
if (preg_match("/public const LATEST_SCHEMA_VERSION = '([^']+)'/", $migrations, $m)) $schemaVersion = (string)$m[1];
$assert($schemaVersion !== '' && version_compare($schemaVersion, '2.2.525', '>=') && strpos($migrations, 'migrate22525') !== false && strpos($migrations, 'mail_outbox') !== false, 'schema_migration');
$assert(strpos($schema, "'mail_outbox'") !== false && strpos($schema, 'idempotency_key') !== false && strpos($schema, 'next_attempt_at') !== false, 'schema_manifest');
$assert(strpos($runtime, 'MailDispatchService::registerHooks') !== false, 'runtime_hooks');
$assert(strpos($emails, "tab==='outbox'") !== false && strpos($emails, 'renderOutbox') !== false && strpos($emails, 'Mail Reliability Outbox') !== false, 'mail_center_outbox');
$assert(strpos($reports, "'idempotency_key']='order_suggestion|") !== false && strpos($reports, "!empty(\$mailDispatch['accepted'])") !== false && strpos($reports, "'queued'") !== false, 'order_suggestion_outbox_ownership');
$assert(strpos($reports, "'idempotency_key'] = 'scheduled_report|") !== false, 'scheduled_report_idempotency');
$assert(strpos($billing, "'idempotency_key'=>'billing_transactional|'") !== false, 'billing_idempotency');
$assert(strpos($performance, "'idempotency_key'=>'performance_automation|'") !== false, 'performance_idempotency');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert(strpos($gate, 'checkMailReliabilityOutboxContract') !== false, 'release_gate_contract');
$assert(strpos($validator, 'mail_reliability_outbox_ok') !== false, 'package_validator_contract');
$assert(strpos($manager, 'Mail Reliability Outbox') !== false, 'release_manager_visibility');
$assert(strpos($doc, 'fieldflow-mail-reliability-outbox-v1') !== false && strpos($doc, '`SOURCE_RELEASE` is `2.2.524` and `STABLE_RELEASE` is `2.2.525`') !== false && strpos($doc, 'routespro_mail_outbox') !== false, 'phase_documentation');

if ($failures) {
    fwrite(STDERR, "MAIL RELIABILITY OUTBOX CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "MAIL RELIABILITY OUTBOX CONTRACT OK\n";
echo "release=" . $version . "\n";
echo "successor=>=2.2.530\n";
echo "contract=fieldflow-mail-reliability-outbox-v1\n";
echo "transport=wp_mail\n";
echo "outbox=persistent\n";
echo "idempotency=caller_owned\n";
echo "retry=bounded_atomic_claims\n";
echo "order_suggestion_retry=single_transport_owner\n";
echo "schema_change=additive_mail_outbox\n";
echo "submission_build=2.2.485\n";
