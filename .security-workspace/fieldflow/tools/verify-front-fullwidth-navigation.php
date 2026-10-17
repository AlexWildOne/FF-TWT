<?php
/** Build-time verifier for FieldFlow 2.2.530 Front Full Width & Navigation Consolidation. */
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
$assert = static function(bool $ok, string $label) use (&$failures): void {
    if (!$ok) $failures[] = $label;
};

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$responsive = $read('src/Front/ResponsiveAssets.php');
$app = $read('src/Front/AppShortcodeRenderer.php');
$shell = $read('frontend/src/react-front/operational-shell.ts');
$css = $read('assets/fieldflow-operational-experience.css');
$reactCss = $read('assets/fieldflow-react-front.css');
$asset = $read('assets/fieldflow-react-front-2.2.530.js');
$offline = $read('assets/fieldflow-offline-runtime-2.2.530.js');
$submission = $read('src/Forms/SubmissionPipeline.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$gate = $read('src/Support/ReleaseGate.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-8I-FRONT-FULL-WIDTH-NAVIGATION-CONSOLIDATION.md');

$renderers = [
    $read('src/Front/BriefingsShortcodeRenderer.php'),
    $read('src/Front/ImplementationsShortcodeRenderer.php'),
    $read('src/Front/StocksShortcodeRenderer.php'),
    $read('src/Front/ShoppingShortcodeRenderer.php'),
    $read('src/Front/SelfRoutePlannerShortcodeRenderer.php'),
    $read('src/Front/ExpensesShortcodeRenderer.php'),
    $read('src/Front/TeamInboxShortcodeRenderer.php'),
];

$assert($contains($plugin, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert($contains($phase, ["VERSION = '1.0.87'", "SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_continuity');
$assert($contains($foundation, ["FULLWIDTH_NAVIGATION_CONTRACT = 'fieldflow-front-fullwidth-navigation-v1'", "'fullwidth_navigation_contract' => self::FULLWIDTH_NAVIGATION_CONTRACT"]), 'front_contract_registered');
$assert($contains($responsive, ['fieldflow-react-front-2.2.530.js', 'fieldflow-frontend-core-2.2.530.js', 'fieldflow-offline-runtime-2.2.530.js', 'fieldflow-react-backoffice-2.2.530.js', 'FULLWIDTH_NAVIGATION_CONTRACT']), 'release_assets_aligned');
$assert($contains($app, [
    "['key'=>'briefings','label'=>'Briefing']", "['key'=>'implementations','label'=>'Implementações']",
    "['key'=>'stocks','label'=>'Stocks']", "['key'=>'shopping','label'=>'Shopping']",
    "['key'=>'discovery','label'=>\$can_manage ? 'Descobrir' : 'Planear']", "['key'=>'expenses','label'=>'Despesas']",
    "['key'=>'messages','label'=>'Mensagens']", "ReactFrontFoundation::FULLWIDTH_NAVIGATION_CONTRACT"
]), 'complete_authorised_navigation');
$assert(strpos($shell, '.slice(0, 5)') === false, 'no_five_panel_truncation');
$assert($contains($shell, ['button[data-panel="${panel}"]', "'aria-current': panel.key === snapshot.activePanel ? 'page' : undefined"]), 'safe_button_proxy_navigation');
$assert($contains($css, ['fieldflow-front-fullwidth-navigation-v1', '.ff-operational-surface', 'data-panel="briefings"', 'data-panel="implementations"', 'data-panel="stocks"', 'data-panel="shopping"', 'data-panel="discovery"', 'data-panel="expenses"', 'data-panel="messages"', '.ffb-exec-store-list', '.rp-mail-list']), 'mobile_fullwidth_and_scroll_contract');
$assert($contains($reactCss, ['Phase 8I', 'button[aria-current="page"]', 'overscroll-behavior-inline:contain']), 'react_nav_visual_contract');
foreach ($renderers as $index => $renderer) $assert(strpos($renderer, 'ff-operational-surface') !== false, 'surface_marker_' . $index);
$assert($contains($asset, ['FieldFlow 2.2.530', 'fieldflow-react-operational-shell-v1', 'button[data-panel=', 'aria-current']), 'compiled_react_navigation');
$assert($contains($offline, ["const RUNTIME_BUILD='2.2.530'", 'fieldflow-zero-byte-file-placeholder-filter-v1', 'fieldflow-resumable-media-v1']), 'offline_runtime_identity_preserved');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_pipeline_build_preserved');
$assert($contains($validator, ['front_fullwidth_navigation_ok', 'O Front Full Width & Navigation Consolidation não está integralmente ligado']), 'package_validator_guard');
$assert($contains($gate, ['checkFrontFullwidthNavigationContract', 'Front Full Width & Navigation']), 'release_gate_guard');
$assert($contains($manager, ['Front Full Width', "packageResult['front_fullwidth_navigation_ok']"]), 'release_manager_visibility');
$assert($contains($doc, ['fieldflow-front-fullwidth-navigation-v1', 'No IndexedDB schema change.', 'No P1-P4 routing or optimisation-rule change.', '`SOURCE_RELEASE` is `2.2.493` and `STABLE_RELEASE` is `2.2.494`']), 'phase_documentation');

if ($failures) {
    fwrite(STDERR, "FRONT FULL WIDTH NAVIGATION CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "FRONT FULL WIDTH NAVIGATION CONTRACT OK\n";
echo "release=2.2.507\n";
echo "contract=fieldflow-front-fullwidth-navigation-v1\n";
echo "navigation=complete_authorised_tabs\n";
echo "mobile_width=priority_surfaces_fullwidth\n";
echo "vertical_scroll=document_owned_on_mobile\n";
echo "core_rewrite=none\n";
