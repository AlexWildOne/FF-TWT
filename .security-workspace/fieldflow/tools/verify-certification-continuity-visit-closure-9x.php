<?php
/** Build-time successor verifier for FieldFlow Certification Continuity Visit Closure 9X. */
$root = dirname(__DIR__);
$failures = [];
$passes = [];
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $ok, string $label) use (&$failures, &$passes): void {
    if ($ok) $passes[] = $label; else $failures[] = $label;
};

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$events = $read('src/Rest/EventsController.php');
$mail = $read('src/Admin/ReportAutomations.php');
$opsVerifier = $read('tools/verify-operational-intelligence-hardening.php');
$mailVerifier = $read('tools/verify-mail-reliability-outbox.php');
$doc = $read('PHASE-9X-CERTIFICATION-CONTINUITY-VISIT-CLOSURE.md');
$migrations = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');

$version = '';
if (preg_match('/Version:\s*([0-9.]+)/', $plugin, $m)) $version = (string)$m[1];
$stableTag = '';
if (preg_match('/Stable tag:\s*([0-9.]+)/', $readme, $m)) $stableTag = (string)$m[1];
$sourceRelease = '';
$stableRelease = '';
if (preg_match("/SOURCE_RELEASE = '([^']+)'/", $phase, $m)) $sourceRelease = (string)$m[1];
if (preg_match("/STABLE_RELEASE = '([^']+)'/", $phase, $m)) $stableRelease = (string)$m[1];

$assert($version !== '' && version_compare($version, '2.2.540', '>=') && $stableTag === $version, 'successor_identity');
$assert($stableRelease === $version && $sourceRelease !== '' && version_compare($sourceRelease, $stableRelease, '<'), 'phase6_successor_chain');
$assert(strpos($gate, 'fieldflow-certification-continuity-visit-closure-v1') !== false, 'release_gate_9x_contract');
$assert(strpos($gate, "'src/Admin/ReportAutomations.php' => ['queueDailyCloseForRoute','scheduleDailyCloseRetry','dispatchDailyCloseForRoute','claimDispatch']") !== false, 'operational_gate_capability_based');
$assert(strpos($gate, 'post-commit reliability fallback') === false, 'obsolete_comment_marker_removed');
$assert(strpos($validator, 'operational_intelligence_hardening_ok') !== false && strpos($validator, 'certification_continuity_visit_closure_9x_ok') !== false, 'package_validator_gate_parity');
$assert(strpos($validator, 'Operational Intelligence & Field Reliability is not package-safe') !== false, 'package_validator_preinstall_block');
$assert(strpos($events, "\$wpdb->query('COMMIT');") !== false && strpos($events, "route_status'] ?? '')) === 'completed'") !== false && strpos($events, 'operationally_complete') !== false && strpos($events, 'ReportAutomations::queueDailyCloseForRoute') !== false, 'post_commit_route_capability');
$assert(strpos($mail, 'queueDailyCloseForRoute') !== false && strpos($mail, 'scheduleDailyCloseRetry') !== false && strpos($mail, 'dispatchDailyCloseForRoute') !== false && strpos($mail, 'claimDispatch') !== false, 'daily_close_durable_idempotent_capability');
$assert(strpos($opsVerifier, 'successor=>=2.2.530') !== false && strpos($opsVerifier, "route_status'] ?? '')) === 'completed'") !== false, 'operational_verifier_successor_safe');
$assert(strpos($mailVerifier, 'successor=>=2.2.530') !== false && strpos($mailVerifier, 'version_compare($version, \'2.2.530\', \'>=\')') !== false, 'mail_verifier_successor_safe');
$assert(strpos($manager, 'Certification Continuity Visit Closure 9X') !== false && strpos($manager, "packageResult['certification_continuity_visit_closure_9x_ok']") !== false, 'release_manager_visibility');
$assert(strpos($doc, 'fieldflow-certification-continuity-visit-closure-v1') !== false && strpos($doc, '`SOURCE_RELEASE` is `2.2.539` and `STABLE_RELEASE` is `2.2.540`') !== false, 'phase_documentation');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema_identity_preserved');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');

if ($failures) {
    fwrite(STDERR, "CERTIFICATION CONTINUITY VISIT CLOSURE 9X CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "CERTIFICATION CONTINUITY VISIT CLOSURE 9X CONTRACT OK\n";
echo "successor=>=2.2.540\n";
echo "operational_gate=capability_based\n";
echo "package_gate_parity=enabled\n";
echo "mail_outbox_verifier=successor_safe\n";
echo "schema=2.2.529\n";
echo "submission_build=2.2.485\n";
