<?php
/** Build-time verifier for FieldFlow 2.2.530 Client Portal Shell Stability. */
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
$ui = $read('assets/fieldflow-ui-runtime.js');
$shell = $read('assets/fieldflow-premium-shell-v2.js');
$css = $read('assets/fieldflow-premium-shell-v2.css');
$experience = $read('assets/fieldflow-operational-experience.js');
$submission = $read('src/Forms/SubmissionPipeline.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9A-CLIENT-PORTAL-SHELL-STABILITY.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($client, [
    'fieldflow-client-portal-shell-stability-v1', 'data-ff2-layout="server"', 'data-ff2-rail="server"',
    'function switchPanel(key,options={})', "source:'client-shell'", "source:'client-shell-module'",
    "panel.hidden=!active", "b.setAttribute('aria-selected'", "root.dataset.activePanel=key"
]), 'client_shell_contract');
$assert(strpos($client, "setTimeout(()=>target.scrollIntoView({behavior:'smooth'") === false, 'no_client_tab_smooth_scroll');
$assert($contains($ui, [
    "CLIENT_SHELL_CONTRACT='fieldflow-client-portal-shell-stability-v1'", 'const clientTab=', "source!=='client-shell-module'", "source!=='lazy-loader'", 'syncClientLazyScope', "placeholder.dataset.ffPanel||''"
]), 'ui_runtime_dedupe_scope');
$assert($contains($shell, [
    "SERVER_SHELL_CONTRACT = 'fieldflow-client-portal-shell-stability-v1'", 'data-ff2-layout="server"', 'data-ff2-rail="server"', 'if (!serverLayout)', 'data-ff2-rail-ready'
]), 'server_shaped_rail');
$assert($contains($css, ['Phase 9A, Client Portal Shell Stability', 'fieldflow-client-portal-shell-stability-v1', 'data-ff2-layout="server"', 'data-ff2-rail="server"']), 'shell_css_contract');
$assert($contains($experience, ["const BUILD='2.2.517'", "hero=root.querySelector('.rp-shell > .rp-hero')", "type!=='client'"]), 'command_deck_no_client_scroll');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert($contains($gate, ['checkClientPortalShellStabilityContract', 'Client Portal Shell Stability']), 'release_gate_guard');
$assert($contains($validator, ['client_portal_shell_stability_ok', 'O Client Portal Shell Stability não está integralmente ligado']), 'package_validator_guard');
$assert($contains($manager, ['Client Portal Stability', "packageResult['client_portal_shell_stability_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-client-portal-shell-stability-v1', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.', '`SOURCE_RELEASE` is `2.2.514` and `STABLE_RELEASE` is `2.2.515`']), 'phase_documentation');

if ($failures) {
    fwrite(STDERR, "CLIENT PORTAL SHELL STABILITY CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "CLIENT PORTAL SHELL STABILITY CONTRACT OK\n";
echo "release=2.2.530\n";
echo "contract=fieldflow-client-portal-shell-stability-v1\n";
echo "first_paint_layout=server_shaped\n";
echo "panel_event_owner=client_shell\n";
echo "lazy_scope=current_client_project\n";
echo "submission_build=2.2.485\n";
