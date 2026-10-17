<?php
/** Build-time verifier for FieldFlow 2.2.530 Briefing Progress Tabs Hardening continuity. */
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
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$renderer = $read('src/Front/BriefingsShortcodeRenderer.php');
$doc = $read('PHASE-8L-BRIEFING-PROGRESS-TABS-HARDENING.md');
$submission = $read('src/Forms/SubmissionPipeline.php');
$briefingService = $read('src/Briefings/BriefingService.php');
$formBridge = $read('src/Forms/BriefingFormBridge.php');

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($gate, ["VERSION = '1.79.0'", 'checkBriefingProgressTabsHardeningContract', 'fieldflow-briefing-tabstrip-hardening-v1']), 'release_gate_contract');
$assert($contains($renderer, [
    'Phase 8L, 2.2.497: Briefing Progress Tabs Hardening.',
    'fieldflow-briefing-tabstrip-hardening-v1',
    '.ffb-tabs{display:flex!important',
    'flex-wrap:nowrap!important',
    'overflow-x:auto!important',
    'overflow-y:hidden!important',
    'touch-action:pan-x pan-y',
    'flex:0 0 auto!important',
    'min-width:max-content!important',
    'scroll-snap-align:start',
    'function activateTab(card,key)',
    "btn.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'})",
]), 'briefing_tabstrip_layout');
$assert($contains($validator, ['briefing_progress_tabs_hardening_ok', 'O Briefing Progress Tabs Hardening não está integralmente ligado']), 'package_validator_guard');
$assert($contains($manager, ['Briefing Tabs Hardening', "packageResult['briefing_progress_tabs_hardening_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-briefing-tabstrip-hardening-v1', 'No Briefing data, import or mapping change.', 'No read/view/acknowledgement logic change.', '`SOURCE_RELEASE` is `2.2.496` and `STABLE_RELEASE` is `2.2.497`']), 'phase_documentation');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_pipeline_build_preserved');
$assert($contains($briefingService, ['class BriefingService', 'published', 'acknowledge']), 'briefing_business_runtime_present');
$assert($contains($formBridge, ['class BriefingFormBridge']), 'briefing_form_bridge_present');

if ($failures) {
    fwrite(STDERR, "BRIEFING PROGRESS TABS HARDENING CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "BRIEFING PROGRESS TABS HARDENING CONTRACT OK\n";
echo "release=2.2.507\n";
echo "contract=fieldflow-briefing-tabstrip-hardening-v1\n";
echo "tab_layout=single_row_intrinsic_width\n";
echo "touch_scroll=horizontal_plus_vertical_page\n";
echo "business_logic_change=none\n";
echo "submission_build=2.2.485\n";
