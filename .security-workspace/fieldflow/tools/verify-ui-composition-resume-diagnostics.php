<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$assert = static function(bool $ok, string $name): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$name}\n"); exit(1); }
};
$contains = static fn(string $haystack, array $needles): bool => array_reduce($needles, static fn(bool $ok, string $needle): bool => $ok && strpos($haystack, $needle) !== false, true);
$app = $read('src/Front/AppShortcodeRenderer.php');
$client = $read('src/Front/ClientPortalShortcodeRenderer.php');
$css = $read('assets/fieldflow-premium-shell-v2.css');
$bridge = $read('src/Mobile/WebViewBridge.php');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-9E-UI-COMPOSITION-RESUME-DIAGNOSTICS.md');

$assert($contains($app,['ff-ui-composition-v1','fieldflow-ui-composition-ownership-v1','fieldflow-resume-diagnostics-v1','fieldflow_ui_composition_enabled']), 'operative_composition');
$assert($contains($client,['ff-ui-composition-v1','fieldflow-ui-composition-ownership-v1','fieldflow-resume-diagnostics-v1','fieldflow_ui_composition_enabled']), 'client_composition');
$assert($contains($css,['Phase 9E, UI Composition Ownership + Resume Diagnostics foundation.','#rpcp-commercial-panel','#rpcp-briefings-panel','#rpcp-implementations-panel','#rpcp-stocks-panel','#rpcp-team-panel','#rpcp-analytics-panel','#rp-app-briefings','#rp-app-academy','#rp-app-expenses','#rp-app-messages','#rp-app-analytics','border:0!important','box-shadow:none!important']), 'surface_ownership_css');
$assert($contains($bridge,["fieldflow_mobile_resume_guard_enabled","fieldflow_mobile_resume_media_guard_enabled","fieldflow_mobile_resume_diagnostics_enabled","RESUME_DIAGNOSTICS_CONTRACT='fieldflow-resume-diagnostics-v1'",'ff-app-resume-media-hidden','iframe,img,object,embed','recordResumeDiagnostic','media-ready-under-guard','fieldflow.resume.diagnostic']), 'resume_diagnostics');
$assert($contains($foundation,["UI_COMPOSITION_CONTRACT = 'fieldflow-ui-composition-ownership-v1'","RESUME_DIAGNOSTICS_CONTRACT = 'fieldflow-resume-diagnostics-v1'"]), 'foundation_contracts');
$assert($contains($responsive,['ReactFrontFoundation::UI_COMPOSITION_CONTRACT','ReactFrontFoundation::RESUME_DIAGNOSTICS_CONTRACT','fieldflow-react-front-2.2.530.js']), 'responsive_contracts');
$assert($contains($gate,["public const VERSION = '1.79.0'",'checkUiCompositionResumeDiagnosticsContract']), 'release_gate');
$assert($contains($validator,["'ui_composition_resume_diagnostics_ok'=>false",'PHASE-9E-UI-COMPOSITION-RESUME-DIAGNOSTICS.md']), 'package_validator');
$assert(strpos($manager,'UI Composition &amp; Resume Diagnostics')!==false, 'release_manager_kpi');
$assert($contains($phase6,["public const VERSION = '1.0.87'","public const SOURCE_RELEASE = '2.2.529'","public const STABLE_RELEASE = '2.2.530'"]), 'phase6_identity');
$assert(strpos($submission,"public const BUILD = '2.2.485'")!==false, 'submission_build');
$assert($contains($doc,['fieldflow-ui-composition-resume-diagnostics-v1','`SOURCE_RELEASE` is `2.2.520` and `STABLE_RELEASE` is `2.2.521`','No P1-P4 routing or optimisation-rule change.','No database schema change.']), 'phase_doc');

echo "UI COMPOSITION RESUME DIAGNOSTICS CONTRACT OK\n";
echo "composition=client,operative:surface_ownership\n";
echo "resume=media_guard+diagnostics\n";
echo "submission_build=2.2.485\n";
