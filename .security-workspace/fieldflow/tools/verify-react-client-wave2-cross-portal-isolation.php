<?php
/** Build-time verifier for FieldFlow 2.2.530 React Client Wave 2 & Cross-Portal Render Isolation. */
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
$ui = $read('assets/fieldflow-ui-runtime.js');
$analytics = $read('assets/fieldflow-premium-analytics.js');
$experience = $read('assets/fieldflow-operational-experience.js');
$premiumCss = $read('assets/fieldflow-premium-shell-v2.css');
$reactCss = $read('assets/fieldflow-react-front.css');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$moduleControls = $read('frontend/src/react-front/module-controls.ts');
$build = $read('frontend/tools/build.mjs');
$asset = $read('assets/fieldflow-react-front-2.2.530.js');
$submission = $read('src/Forms/SubmissionPipeline.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9C2-REACT-CLIENT-WAVE2-CROSS-PORTAL-ISOLATION.md');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($client, ['data-fieldflow-portal="client"', 'fieldflow-cross-portal-render-isolation-v1', 'fieldflow-react-client-wave2-v1', "portal:'client'"]), 'client_portal_identity');
$assert($contains($app, ['data-fieldflow-portal="operative"', 'fieldflow-cross-portal-render-isolation-v1', "portal:'operative'"]), 'operative_portal_identity');
$assert($contains($ui, ['CROSS_PORTAL_ISOLATION_CONTRACT', 'function portalShellFor(node,portal)', "portal!=='client'", 'fieldflow:client-panel-layout']), 'ui_runtime_isolation');
$assert(strpos($ui, "placeholder.dispatchEvent(new CustomEvent('routespro:panel-open',{bubbles:true,detail}));\n      window.dispatchEvent(new Event('resize'));" ) === false, 'client_global_resize_removed');
$assert($contains($analytics, ['CROSS_PORTAL_ISOLATION_CONTRACT', 'function eventScope(event)', "portal==='client'", "portal==='operative'"]), 'analytics_scope_isolation');
$assert($contains($experience, ['CROSS_PORTAL_ISOLATION_CONTRACT', 'function eventRoot(event)', "portal==='client'", "portal==='operative'"]), 'operational_scope_isolation');
$assert(strpos($experience, "event.target?.closest?.(ROOTS)||document.querySelector(ROOTS)") === false, 'ambiguous_portal_fallback_removed');
$assert($contains($premiumCss, ['Phase 9C Wave 2 + Cross-Portal Render Isolation', 'fieldflow-react-client-wave2-v1', 'fieldflow-cross-portal-render-isolation-v1', '-webkit-mask-image:none!important', 'backdrop-filter:none!important']), 'webview_compositor_hardening');
$assert($contains($reactCss, ['Phase 9C, React Client Wave 2 + Cross-Portal Render Isolation', 'data-client-wave2="fieldflow-react-client-wave2-v1"', 'data-cross-portal-isolation="fieldflow-cross-portal-render-isolation-v1"']), 'react_wave2_css');
$assert($contains($foundation, ["CLIENT_WAVE2_CONTRACT = 'fieldflow-react-client-wave2-v1'", "CROSS_PORTAL_ISOLATION_CONTRACT = 'fieldflow-cross-portal-render-isolation-v1'", "['briefings','implementations','planner','expenses']", 'client_wave2_contract']), 'react_wave2_php_contract');
$assert($contains($responsive, ['clientWave2Contract', 'crossPortalIsolationContract', 'ReactFrontFoundation::CLIENT_WAVE2_CONTRACT', 'ReactFrontFoundation::CROSS_PORTAL_ISOLATION_CONTRACT', 'fieldflow-react-front-2.2.530.js']), 'react_wave2_enqueue');
$assert($contains($moduleControls, ["FIELD_FLOW_REACT_CLIENT_WAVE2_CONTRACT = 'fieldflow-react-client-wave2-v1'", "FIELD_FLOW_CROSS_PORTAL_RENDER_ISOLATION_CONTRACT = 'fieldflow-cross-portal-render-isolation-v1'", "['briefings', 'implementations', 'planner', 'expenses'].includes(props.module)", "portalScope === 'client'", "clientPortal.addEventListener('fieldflow:client-panel-lifecycle'", "'data-client-wave2'", 'React Client Wave 2']), 'react_wave2_ts_ownership');
$assert($contains($build, ['reactClientWave2Contract', 'crossPortalIsolationContract', 'data-client-wave2', 'data-portal-scope']), 'frontend_build_contract');
$assert($contains($asset, ['FieldFlow 2.2.530', 'fieldflow-react-client-wave2-v1', 'fieldflow-cross-portal-render-isolation-v1', 'data-client-wave2', 'data-portal-scope']), 'compiled_react_wave2_asset');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert($contains($gate, ['checkReactClientWave2CrossPortalIsolationContract', 'React Client Wave 2 & Cross-Portal Isolation']), 'release_gate_guard');
$assert($contains($validator, ['react_client_wave2_cross_portal_isolation_ok', 'O React Client Wave 2 ou o isolamento entre portais não estão integralmente ligados.']), 'package_validator_guard');
$assert($contains($manager, ['React Client Wave 2', "packageResult['react_client_wave2_cross_portal_isolation_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-react-client-wave2-cross-portal-isolation-v1', '`SOURCE_RELEASE` is `2.2.517` and `STABLE_RELEASE` is `2.2.518`', 'No P1-P4 routing or optimisation-rule change.', 'No database schema change.', '`SubmissionPipeline::BUILD` remains `2.2.485`.']), 'phase_documentation');

if ($failures) {
    fwrite(STDERR, "REACT CLIENT WAVE 2 CROSS-PORTAL ISOLATION CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "REACT CLIENT WAVE 2 CROSS-PORTAL ISOLATION CONTRACT OK\n";
echo "release=2.2.530\n";
echo "contract=fieldflow-react-client-wave2-cross-portal-isolation-v1\n";
echo "react_client_wave2=briefings,implementations,planner,expenses\n";
echo "portal_isolation=client,operative\n";
echo "android_webview_compositor=mask_and_backdrop_removed\n";
echo "submission_build=2.2.485\n";
