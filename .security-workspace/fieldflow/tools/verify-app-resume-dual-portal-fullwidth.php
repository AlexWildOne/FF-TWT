<?php
/** Build-time verifier for FieldFlow 2.2.530 App Resume Stability & Dual Portal True Full Width. */
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
$client = $read('src/Front/ClientPortalShortcodeRenderer.php');
$app = $read('src/Front/AppShortcodeRenderer.php');
$briefings = $read('src/Front/BriefingsShortcodeRenderer.php');
$performance = $read('src/Front/PerformanceShortcodeRenderer.php');
$teamInbox = $read('src/Front/TeamInboxShortcodeRenderer.php');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$ui = $read('assets/fieldflow-ui-runtime.js');
$premiumCss = $read('assets/fieldflow-premium-shell-v2.css');
$webview = $read('src/Mobile/WebViewBridge.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9D-APP-RESUME-DUAL-PORTAL-TRUE-FULLWIDTH.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($foundation, ["DUAL_PORTAL_LAYOUT_CONTRACT = 'fieldflow-dual-portal-true-fullwidth-v1'", "APP_RESUME_PAINT_GUARD_CONTRACT = 'fieldflow-app-resume-paint-guard-v1'", 'dual_portal_layout_contract', 'app_resume_paint_guard_contract']), 'shared_contracts');
$assert($contains($client, ['data-fieldflow-portal="client"', 'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"', 'data-fieldflow-resume-contract="fieldflow-app-resume-paint-guard-v1"']), 'client_layout_contract');
$assert($contains($app, ['data-fieldflow-portal="operative"', 'data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1"', 'data-fieldflow-resume-contract="fieldflow-app-resume-paint-guard-v1"', '2.2.519, Analytics follows the same true-full-width shell']), 'operative_layout_contract');
$assert(strpos($app, 'margin-left:calc(50% - 50vw + 6px)') === false, 'operative_analytics_breakout_removed');
foreach (['max-width:min(1520px,96vw)','max-width:min(1540px,96vw)','max-width:min(1680px,calc(100vw - 18px))'] as $legacy) {
    $assert(strpos($app, $legacy) === false && strpos($client, $legacy) === false, 'legacy_shell_width_removed:' . $legacy);
}
$assert(strpos($briefings, '.ff-briefings{width:100%;max-width:none;margin:0;') !== false, 'briefings_fullwidth');
$assert(strpos($performance, '.ffp-front{width:100%;max-width:none;margin:0;') !== false, 'performance_fullwidth');
$assert(strpos($teamInbox, '.rp-team-inbox{width:100%;max-width:none;margin:0;') !== false, 'team_inbox_fullwidth');
$assert($contains($ui, ["DUAL_PORTAL_LAYOUT_CONTRACT='fieldflow-dual-portal-true-fullwidth-v1'", "APP_RESUME_PAINT_GUARD_CONTRACT='fieldflow-app-resume-paint-guard-v1'", 'fieldflow:operative-panel-layout', 'fieldflow:portal-panel-layout', 'const portalPanelSize=new WeakMap()', 'ff-app-resume-guard-active']), 'shared_layout_runtime');
$assert($contains($premiumCss, ['Phase 9D, App Resume Stability + Dual Portal True Full Width', 'fieldflow-dual-portal-true-fullwidth-v1', 'fieldflow-app-resume-paint-guard-v1', '.ff-portal-layout-aligned', '.ff-app-resume-guard', 'max-width:none!important']), 'dual_portal_css');
$assert($contains($webview, ['fieldflow-resume-paint-guard', "RESUME_PAINT_GUARD_CONTRACT='fieldflow-app-resume-paint-guard-v1'", 'function armResumePaintGuard(source)', 'function releaseResumePaintGuard(token,source)', "document.addEventListener('visibilitychange'", "window.addEventListener('pagehide'", "window.addEventListener('pageshow'", "stabilizeResumePaint('fieldflow:app-resume')", 'fieldflow:resume-paint-ready']), 'resume_paint_guard');
$assert($contains($responsive, ['dualPortalLayoutContract', 'appResumePaintGuardContract', 'ReactFrontFoundation::DUAL_PORTAL_LAYOUT_CONTRACT', 'ReactFrontFoundation::APP_RESUME_PAINT_GUARD_CONTRACT', 'fieldflow-react-front-2.2.530.js']), 'runtime_config');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert($contains($gate, ['checkAppResumeDualPortalFullwidthContract', 'App Resume Stability & Dual Portal True Full Width']), 'release_gate_guard');
$assert($contains($validator, ['app_resume_dual_portal_fullwidth_ok', 'O App Resume Paint Guard ou o True Full Width dos portais Cliente/Operativo não estão integralmente ligados.']), 'package_validator_guard');
$assert($contains($manager, ['Dual Portal Full Width', "packageResult['app_resume_dual_portal_fullwidth_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-app-resume-dual-portal-true-fullwidth-v1', '`SOURCE_RELEASE` is `2.2.518` and `STABLE_RELEASE` is `2.2.519`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.', '`SubmissionPipeline::BUILD` remains `2.2.485`.']), 'phase_documentation');

if ($failures) {
    fwrite(STDERR, "APP RESUME DUAL PORTAL FULLWIDTH CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "APP RESUME DUAL PORTAL FULLWIDTH CONTRACT OK\n";
echo "release=2.2.530\n";
echo "contract=fieldflow-app-resume-dual-portal-true-fullwidth-v1\n";
echo "layout=client,operative:true_full_width\n";
echo "resume_guard=visibility,pagehide,pageshow,native_resume\n";
echo "resize=client,operative:width_only\n";
echo "submission_build=2.2.485\n";
