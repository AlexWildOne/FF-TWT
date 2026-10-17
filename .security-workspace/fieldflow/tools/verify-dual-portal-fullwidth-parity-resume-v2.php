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
$app=$read('src/Front/AppShortcodeRenderer.php');
$client=$read('src/Front/ClientPortalShortcodeRenderer.php');
$css=$read('assets/fieldflow-premium-shell-v2.css');
$bridge=$read('src/Mobile/WebViewBridge.php');
$gate=$read('src/Support/ReleaseGate.php');
$foundation=$read('src/Front/ReactFrontFoundation.php');
$submission=$read('src/Forms/SubmissionPipeline.php');
$assert($contains($app,['fieldflow-dual-portal-fullwidth-parity-v2','fieldflow-app-resume-paint-guard-v2','rp-app-analytics']), 'operative_contract');
$assert($contains($client,['fieldflow-dual-portal-fullwidth-parity-v2','fieldflow-app-resume-paint-guard-v2','rpcp-analytics-panel']), 'client_contract');
$assert($contains($css,['Phase 9D2, Dual Portal Full-Width Parity + Resume Hardening v2.','#rpcp-commercial-panel','#rpcp-briefings-panel','#rpcp-implementations-panel','#rpcp-stocks-panel','#rpcp-team-panel','rpcp-analytics-panel','#rp-app-briefings','#rp-app-academy','#rp-app-expenses','#rp-app-messages','rp-app-analytics']), 'field_verified_tabs');
$assert($contains($bridge,["RESUME_PAINT_GUARD_V2_CONTRACT='fieldflow-app-resume-paint-guard-v2'",'ff-app-resume-portal-hidden','portal-ready-under-guard','content-visibility:hidden!important']), 'resume_guard_v2');
$assert($contains($gate,['legacy_or_true_fullwidth_contract','checkDualPortalFullwidthParityResumeV2Contract']), 'historical_gate_successor');
$assert($contains($foundation,["DUAL_PORTAL_FULLWIDTH_PARITY_CONTRACT = 'fieldflow-dual-portal-fullwidth-parity-v2'","APP_RESUME_PAINT_GUARD_V2_CONTRACT = 'fieldflow-app-resume-paint-guard-v2'"]), 'foundation_contracts');
$assert(strpos($submission,"public const BUILD = '2.2.485'")!==false,'submission_build');
echo "DUAL PORTAL FULLWIDTH PARITY RESUME V2 CONTRACT OK\n";
echo "fullwidth_parity=client,operative\n";
echo "resume_guard=v2\n";
echo "submission_build=2.2.485\n";
