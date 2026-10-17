<?php
/** Build-time verifier for FieldFlow 2.2.530 continuity verification of the 2.2.524 Mail Dispatch Foundation. */
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
$loader = $read('src/Support/Loader.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$experience = $read('assets/fieldflow-operational-experience.js');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-9H-MAIL-DISPATCH-CERTIFICATION-CONTINUITY.md');

$assert(strpos($plugin, 'Version: 2.2.530') !== false && strpos($plugin, "FIELDFLOW_VERSION', '2.2.530'") !== false, 'release_identity');
$assert(strpos($readme, 'Stable tag: 2.2.530') !== false, 'readme_identity');
$assert(strpos($phase6, "VERSION = '1.0.87'") !== false && strpos($phase6, "SOURCE_RELEASE = '2.2.529'") !== false && strpos($phase6, "STABLE_RELEASE = '2.2.530'") !== false, 'phase6_continuity');
$assert(strpos($service, "fieldflow-mail-dispatch-foundation-v1") !== false, 'mail_contract');
$assert(strpos($service, "public const BUILD = '2.2.524'") !== false, 'mail_build');
$assert(strpos($service, 'public static function dispatch(') !== false && strpos($service, 'wp_mail($to, $subject, $message, $headers, $attachments)') !== false, 'canonical_transport');
$assert(strpos($service, "fieldflow_mail_dispatch_after") !== false && strpos($service, "fieldflow_mail_dispatch_context") !== false, 'mail_hooks');
$assert(strpos($loader, 'src/Services/MailDispatchService.php') !== false, 'loader_manifest');
$assert(strpos($experience, "const BUILD='2.2.524'") !== false, 'operational_runtime_identity');
$assert(strpos($experience, "CLIENT_PORTAL_SHELL_STABILITY_CONTRACT='fieldflow-client-portal-shell-stability-v1'") !== false, 'durable_shell_marker');
$assert(strpos($experience, "const BUILD='2.2.523'") !== false && strpos($experience, "const BUILD='2.2.522'") !== false, 'historical_package_markers');
$assert(strpos($gate, "CLIENT_PORTAL_SHELL_STABILITY_CONTRACT='fieldflow-client-portal-shell-stability-v1'") !== false, 'release_gate_durable_shell');
$assert(strpos($gate, 'checkMailDispatchFoundationContract') !== false, 'release_gate_mail');
$assert(strpos($validator, 'mail_dispatch_certification_continuity_ok') !== false, 'package_validator_mail');
$assert(strpos($manager, 'Mail Dispatch Foundation') !== false, 'release_manager_visibility');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert(strpos($doc, '`SOURCE_RELEASE` is `2.2.523` and `STABLE_RELEASE` is `2.2.524`') !== false && strpos($doc, 'No database schema change.') !== false, 'phase_documentation');

$callers = [
    'src/Observability/AlertService.php','src/Performance/Module.php','src/Services/Notify.php',
    'src/Billing/ProductionHardening.php','src/Billing/BillingService.php','src/Shopping/ShoppingService.php',
    'src/Admin/ReportAutomations.php','src/Admin/Emails.php','src/Admin/ajax.php'
];
$direct = [];
foreach ($callers as $relative) {
    $content = $read($relative);
    $assert(strpos($content, 'MailDispatchService::') !== false, 'canonical_caller:' . $relative);
    if (preg_match('/\bwp_mail\s*\(/', $content)) $direct[] = $relative;
}
$assert(!$direct, 'no_direct_wp_mail_callers');

if ($failures) {
    fwrite(STDERR, "MAIL DISPATCH + CERTIFICATION CONTINUITY CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "MAIL DISPATCH + CERTIFICATION CONTINUITY CONTRACT OK\n";
echo "release=2.2.530\n";
echo "contract=fieldflow-mail-dispatch-foundation-v1\n";
echo "transport=wp_mail\n";
echo "direct_wp_mail_callers=0\n";
echo "client_shell_gate=durable_contract\n";
echo "schema_change=no\n";
echo "submission_build=2.2.485\n";
