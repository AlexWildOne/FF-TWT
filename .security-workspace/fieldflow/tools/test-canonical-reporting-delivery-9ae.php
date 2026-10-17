<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$fail = static function (string $reason): void { fwrite(STDERR, "FAIL: {$reason}\n"); exit(1); };
$assert = static function (bool $ok, string $reason) use ($fail): void { if (!$ok) $fail($reason); };
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v)); } }
if (!function_exists('current_time')) { function current_time($type){ return $type === 'mysql' ? '2026-09-22 18:00:00' : time(); } }
require_once $root . '/src/Reporting/CanonicalOperationalReportService.php';
$data = [
    'total_routes'=>1,'completed_routes'=>1,'total_stops'=>4,'done_stops'=>3,
    'by_day'=>[['route_id'=>10,'date'=>'2026-09-22','route_status'=>'completed','project_name'=>'Campanha','client_name'=>'Cliente','user_name'=>'Merchan','stops'=>4,'stops_done'=>3,'stops_justified_failed'=>1,'stops_remaining'=>0,'done_rate'=>75]],
    'form_analytics'=>['metrics'=>[
        ['metric_key'=>'fronts','metric_label'=>'Frentes','fronts_summary'=>['before_total'=>20,'after_total'=>27,'difference_total'=>7,'up'=>1,'down'=>0,'same'=>0,'new'=>0,'removed'=>0],'rows'=>[['location'=>'Loja A','before'=>20,'after'=>27,'difference'=>7,'growth'=>35]]],
        ['metric_key'=>'espaco_extra','metric_label'=>'Espaco extra conquistado','rows'=>[['location'=>'Loja A','answer'=>'Sim']]],
        ['metric_key'=>'briefing_1','metric_label'=>'Pergunta briefing','question_key'=>'briefing_1','briefing_priority'=>1,'record_count'=>3,'value'=>'Sim'],
    ]],
];
$report = \RoutesPro\Reporting\CanonicalOperationalReportService::build($data, ['from'=>'2026-09-22','to'=>'2026-09-22'], []);
$k = $report['kpis'];
$assert((float)$k['execution_rate'] === 75.0, 'execution must exclude justified non-visits');
$assert((float)$k['operational_closure_rate'] === 100.0, 'operational closure includes justified non-visits');
$assert((float)$k['fronts_gain'] === 7.0, 'fronts gain');
$assert((int)$k['extra_spaces'] === 1, 'extra space');
$assert((float)$k['briefing_coverage_percent'] === 100.0, 'briefing coverage');
$shortcodes = $read('src/Shortcodes.php');
$assert(strpos($shortcodes, "reportViewportContract='fieldflow-form-viewport-continuity-v2'") !== false, 'v2 viewport active');
$renderStart = strpos($shortcodes, 'async function renderForm(options={})');
$renderEnd = strpos($shortcodes, 'async function loadVisits', $renderStart ?: 0);
$render = ($renderStart !== false && $renderEnd !== false) ? substr($shortcodes, $renderStart, $renderEnd - $renderStart) : '';
$assert($render !== '', 'renderForm source');
$noVisitClear = strpos($render, "if(!visit){els.slot.innerHTML=''");
$fetch = strpos($render, 'fetchOperationalNetwork', $noVisitClear === false ? 0 : $noVisitClear);
$swap = strpos($render, 'formAvailable=!!data.has_form;els.slot.innerHTML=', $fetch === false ? 0 : $fetch);
$assert($noVisitClear !== false && $fetch !== false && $swap !== false && $noVisitClear < $fetch && $fetch < $swap, 'valid store keeps old form until replacement ready');
$mail = $read('src/Admin/ReportAutomations.php');
$assert(strpos($mail, 'reconcileMissedDailyClosures') !== false, 'daily close reconciler');
$assert(strpos($mail, 'Faltam anexos obrigatorios') !== false, 'attachments fail closed');
echo 'CANONICAL REPORTING DELIVERY 9AE RUNTIME OK | execution=75 | closure=100 | fronts=+7 | extra_spaces=1 | briefing=100 | store_swap=atomic | mail=reconciled' . PHP_EOL;
