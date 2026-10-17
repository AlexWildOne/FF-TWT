<?php
/**
 * Build-time, non-destructive successor verifier for Operational Intelligence & Field Reliability.
 * Runs without WordPress and complements the in-app Release Gate 1.19.0.
 */
$root = dirname(__DIR__);
$failures = [];
$passes = [];

$assert = static function (bool $ok, string $name, string $detail = '') use (&$failures, &$passes): void {
    if ($ok) { $passes[] = $name; return; }
    $failures[] = $name . ($detail !== '' ? ': ' . $detail : '');
};
$source = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$containsAll = static function (string $text, array $markers): bool {
    foreach ($markers as $marker) if (strpos($text, $marker) === false) return false;
    return true;
};

// Temporal evaluation contract.
$temporal = $source('src/Analytics/TemporalAnalyticsService.php');
$stats = $source('src/Rest/StatsController.php');
$operational = $source('src/Analytics/OperationalIntelligenceService.php');
$ai = $source('src/Analytics/AnalyticsAIService.php');
$assert($containsAll($temporal, [
    'closed_days_only', 'today_is_in_progress', 'future_never_reduces_operational_scores',
    'justified_non_visits_are_terminal_not_executed', 'function forEvaluation',
]), 'temporal_service_contract');
$assert($containsAll($stats, [
    'stops_justified_failed', 'stops_terminal', 'stops_remaining',
    'TemporalAnalyticsService::enrich', 'TemporalAnalyticsService::forEvaluation',
]), 'stats_temporal_envelope');
$assert($containsAll($operational, ['TemporalAnalyticsService::forEvaluation', 'open_operational_window']), 'operational_temporal_guard');
$assert($containsAll($ai, ['TemporalAnalyticsService::forEvaluation', 'temporal_context', 'future_planned']), 'ai_temporal_guard');

// Execute the pure temporal model with tiny WordPress-compatible stubs.
if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('current_time')) { function current_time($type) { return $type === 'Y-m-d' ? '2026-09-18' : '2026-09-18 12:00:00'; } }
if (!function_exists('sanitize_key')) { function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string)$value)); } }
require_once $root . '/src/Analytics/TemporalAnalyticsService.php';
$fixture = [
    'from'=>'2026-09-17','to'=>'2026-09-20','total_routes'=>4,'total_stops'=>24,'done_stops'=>7,'done_rate'=>29.2,
    'by_day'=>[
        ['route_id'=>1,'date'=>'2026-09-17','route_status'=>'completed','stops'=>6,'stops_done'=>5,'stops_justified_failed'=>1,'stops_terminal'=>6,'stops_remaining'=>0,'done_rate'=>83.3],
        ['route_id'=>2,'date'=>'2026-09-18','route_status'=>'in_progress','stops'=>6,'stops_done'=>2,'stops_justified_failed'=>0,'stops_terminal'=>2,'stops_remaining'=>4,'done_rate'=>33.3],
        ['route_id'=>3,'date'=>'2026-09-19','route_status'=>'planned','stops'=>6,'stops_done'=>0,'stops_justified_failed'=>0,'stops_terminal'=>0,'stops_remaining'=>6,'done_rate'=>0],
        ['route_id'=>4,'date'=>'2026-09-20','route_status'=>'planned','stops'=>6,'stops_done'=>0,'stops_justified_failed'=>0,'stops_terminal'=>0,'stops_remaining'=>6,'done_rate'=>0],
    ],
];
$enriched = \RoutesPro\Analytics\TemporalAnalyticsService::enrich($fixture, [['location_id'=>10,'stops'=>1,'stops_done'=>1,'done_rate'=>100]]);
$evaluated = \RoutesPro\Analytics\TemporalAnalyticsService::forEvaluation($enriched);
$assert(!empty($enriched['temporal_context']['open_window']), 'temporal_fixture_open_window');
$assert((int)$evaluated['total_routes'] === 1 && (int)$evaluated['total_stops'] === 6 && (int)$evaluated['done_stops'] === 5, 'temporal_fixture_closed_only', 'future/today leaked into evaluation totals');
$assert((int)($enriched['temporal_context']['today']['unresolved_stops'] ?? -1) === 4, 'temporal_fixture_today_progress');
$assert((int)($enriched['temporal_context']['future']['routes'] ?? -1) === 2 && (int)($enriched['temporal_context']['future']['stops'] ?? -1) === 12, 'temporal_fixture_future_plan');
$assert((int)($enriched['temporal_context']['closed']['justified_failed_stops'] ?? -1) === 1 && (float)($enriched['temporal_context']['closed']['done_rate'] ?? -1) === 83.3, 'temporal_fixture_truthful_kpis');

// Ask FieldFlow UX and help.
$ask = $source('src/Analytics/FieldFlowAskService.php');
$shortcodes = $source('src/Shortcodes.php');
$portal = $source('src/Front/ClientPortalShortcodeRenderer.php');
$adminAsk = $source('assets/fieldflow-analytics2-admin.js');
$assert($containsAll($ask, ['isHelpQuestion', 'helpAnswer', 'Rotas futuras planeadas', 'não deve ser apresentada como visita executada']), 'ask_help_temporal_contract');
$assert(strpos($shortcodes, "function askUser(question){if(!els.askThread)return;if(els.askThread.querySelector('.ffad-ask-empty'))els.askThread.innerHTML='';}") !== false, 'front_user_question_hidden');
$assert(strpos($portal, "function portalAskUser(question){if(!els.askThread)return;if(els.askThread.querySelector('.rp-ask-empty'))els.askThread.innerHTML='';}") !== false, 'portal_user_question_hidden');
$assert(strpos($adminAsk, "function renderAskUser(question){\n        if(!askThread)return;\n        if(askThread.querySelector('.ff-a2-ask-empty'))askThread.innerHTML='';\n    }") !== false, 'admin_user_question_hidden');
$assert($containsAll($ai, ['compactTemporalForAI', "'today_routes'=>", "'future_routes'=>"]), 'ai_temporal_context_compacted');
$assert($containsAll($ask, ["'today_routes','today_progress','future_routes','future_route_days'", 'A leitura de execução e planeamento do contexto atual é:']), 'ask_temporal_fallback');
$assert(strpos($shortcodes, 'Como funciona o FieldFlow?') !== false && strpos($portal, 'Como funciona o FieldFlow?') !== false, 'help_discoverability');

// Cardex bridge preserves deliberate manual lists.
$cardex = $source('src/Forms/ProductCardex.php');
$assert($containsAll($cardex, [
    '_fieldflow_runtime_cardex_bridge', '$question[\'product_source\'] = \'cardex_fixed\';',
    'Explicit manual product lists are never changed', 'if (!$uses_cardex && $manual_rows) continue;',
]), 'cardex_legacy_empty_order_bridge');

// Daily-close post-commit reliability, still idempotent through the existing queue and claims.
$mail = $source('src/Admin/ReportAutomations.php');
$events = $source('src/Rest/EventsController.php');
$assert($containsAll($mail, ['queueDailyCloseForRoute', 'self::$routeCloseQueue[$routeId] = $routeId;', 'scheduleDailyCloseRetry', 'dispatchDailyCloseForRoute', 'claimDispatch']), 'daily_close_idempotent_queue');
$assert($containsAll($events, ["\$wpdb->query('COMMIT');", "route_status'] ?? '')) === 'completed'", 'operationally_complete', 'ReportAutomations::queueDailyCloseForRoute($route_id)']), 'daily_close_post_commit_fallback');

// Release continuity.
$fieldflow = $source('fieldflow.php');
$readme = $source('readme.txt');
$phase = $source('src/Cloud/Phase6StableRelease.php');
$gate = $source('src/Support/ReleaseGate.php');
$responsive = $source('src/Front/ResponsiveAssets.php');
$version = '';
if (preg_match('/Version:\s*([0-9.]+)/', $fieldflow, $m)) $version = (string)$m[1];
$stableTag = '';
if (preg_match('/Stable tag:\s*([0-9.]+)/', $readme, $m)) $stableTag = (string)$m[1];
$sourceRelease = '';
$stableRelease = '';
if (preg_match("/SOURCE_RELEASE = '([^']+)'/", $phase, $m)) $sourceRelease = (string)$m[1];
if (preg_match("/STABLE_RELEASE = '([^']+)'/", $phase, $m)) $stableRelease = (string)$m[1];
$gateVersion = '';
if (preg_match("/public const VERSION = '([^']+)'/", $gate, $m)) $gateVersion = (string)$m[1];
$assert($version !== '' && version_compare($version, '2.2.530', '>=') && $stableTag === $version, 'release_version_alignment');
$assert($stableRelease === $version && $sourceRelease !== '' && version_compare($sourceRelease, $stableRelease, '<'), 'phase6_release_continuity');
$assert($gateVersion !== '' && version_compare($gateVersion, '1.79.0', '>=') && strpos($gate, 'fieldflow-operational-intelligence-hardening-v1') !== false, 'release_gate_contract');
$assert($version !== '' && strpos($responsive, 'fieldflow-offline-runtime-' . $version . '.js') !== false, 'offline_runtime_cache_version');

if ($failures) {
    fwrite(STDERR, "OPERATIONAL INTELLIGENCE HARDENING CONTRACT FAILED\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "OPERATIONAL INTELLIGENCE HARDENING CONTRACT OK\n";
echo "successor=>=2.2.530\n";
echo 'Checks: ' . count($passes) . "\n";
foreach ($passes as $pass) echo " - {$pass}\n";
