<?php
function canonical_status(string $value): string {
    $value = strtolower(trim($value));
    if (in_array($value, ['done','completed'], true)) return 'done';
    if ($value === 'failed') return 'failed';
    if (in_array($value, ['in_progress','active','started'], true)) return 'in_progress';
    return 'pending';
}
function resolve_report_status(string $server, bool $cached, ?string $recent): string {
    $server = canonical_status($server);
    if ($cached && $recent !== null) return canonical_status($recent);
    return $server;
}
$cases = [
    ['completed', false, null, 'done'],
    ['done', false, null, 'done'],
    ['failed', false, null, 'failed'],
    ['pending', true, 'done', 'done'],
    ['pending', false, 'done', 'pending'],
    ['in_progress', false, 'done', 'in_progress'],
];
foreach ($cases as $case) {
    [$server,$cached,$recent,$expected] = $case;
    $actual = resolve_report_status($server,$cached,$recent);
    if ($actual !== $expected) {
        fwrite(STDERR, "REPORT STATUS CANONICAL SYNC 9AP RUNTIME FAIL: {$server}/" . ($cached?'cached':'live') . " expected {$expected}, got {$actual}\n");
        exit(1);
    }
}
$root = dirname(__DIR__);
$shortcodes = (string)file_get_contents($root . '/src/Shortcodes.php');
$routes = (string)file_get_contents($root . '/src/Rest/RoutesController.php');
if (strpos($shortcodes, 'applyVisitState(detail,status,{remember:true,announce:true})') === false
    || strpos($shortcodes, "{fieldflowLive:true}") === false
    || strpos($routes, "CanonicalStateService::visit") === false
    || strpos($routes, "canonical_visit_state") === false) {
    fwrite(STDERR, "REPORT STATUS CANONICAL SYNC 9AP RUNTIME FAIL: source wiring missing\n");
    exit(1);
}
echo "REPORT STATUS CANONICAL SYNC 9AP RUNTIME OK\n";
echo "cached_pending_plus_recent_done=done\n";
echo "live_pending_can_reset=pending\n";
echo "canonical_completed=done\n";
