<?php
define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v)); } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
require_once dirname(__DIR__) . '/src/Reporting/DailyCommercialReportService.php';
require_once dirname(__DIR__) . '/src/Reporting/CanonicalOperationalReportService.php';

use RoutesPro\Reporting\CanonicalOperationalReportService;
use RoutesPro\Reporting\DailyCommercialReportService;

$metrics = [[
    'smart_briefing_rollup'=>1,
    'source_type'=>'briefing',
    'dashboard_title'=>'Briefing semanal',
    'briefing_questions'=>[
        ['key'=>'q_stock','label'=>'Stock correto?','type'=>'radio','target_assignment_keys'=>['q_stock:10','q_stock:20'],'answered_assignment_keys'=>['q_stock:10']],
        ['key'=>'q_display','label'=>'Expositor implementado?','type'=>'radio','target_assignment_keys'=>['q_display:30'],'answered_assignment_keys'=>[]],
        ['key'=>'q_other','label'=>'Pergunta fora da rota','type'=>'radio','target_assignment_keys'=>['q_other:99'],'answered_assignment_keys'=>[]],
    ],
]];
$routing = ['stops'=>[
    ['location_id'=>10,'status'=>'done'],
    ['location_id'=>30,'status'=>'done'],
]];
$method = new ReflectionMethod(CanonicalOperationalReportService::class, 'briefing');
$method->setAccessible(true);
$briefing = $method->invoke(null, $metrics, $routing);
$summary = (array)($briefing['summary'] ?? []);
$rows = array_values((array)($briefing['rows'] ?? []));
$keys = array_column($rows, 'question_key');
$ok = (int)($summary['questions'] ?? 0) === 2
    && (int)($summary['target_assignments'] ?? 0) === 2
    && (int)($summary['answered_assignments'] ?? 0) === 1
    && (int)($summary['pending_assignments'] ?? 0) === 1
    && (int)($summary['target_stores'] ?? 0) === 2
    && abs((float)($summary['coverage_percent'] ?? 0) - 50.0) < 0.01
    && in_array('q_stock', $keys, true)
    && in_array('q_display', $keys, true)
    && !in_array('q_other', $keys, true);

$analysis = DailyCommercialReportService::build([
    'kpis'=>[
        'visits_planned'=>2,'visits_completed'=>2,'execution_rate'=>100,'operational_closure_rate'=>100,
        'briefing_questions'=>2,'briefing_answered'=>1,'briefing_target_assignments'=>2,'briefing_answered_assignments'=>1,
        'briefing_pending_assignments'=>1,'briefing_target_stores'=>2,'briefing_coverage_percent'=>50,
    ],
    'routing'=>$routing,
    'fronts'=>['rows'=>[]],'extra_spaces'=>['rows'=>[]],'orders'=>['rows'=>[]],'evidence'=>['rows'=>[]],
    'briefing'=>$briefing,
]);
$briefAlerts = array_values(array_filter((array)($analysis['alerts'] ?? []), static fn($row): bool => is_array($row) && ($row['type'] ?? '') === 'briefing'));
$ok = $ok && count($briefAlerts) === 1
    && strpos((string)($briefAlerts[0]['detail'] ?? ''), 'mapeadas') !== false
    && (int)($analysis['facts']['commercial']['briefing_pending_assignments'] ?? -1) === 1;

if (!$ok) {
    fwrite(STDERR, '9AJ runtime assertions failed' . PHP_EOL);
    fwrite(STDERR, json_encode(['briefing'=>$briefing,'analysis'=>$analysis], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
echo 'BRIEFING STORE SCOPE REPORTING 9AJ RUNTIME OK | route_stores=2 | applicable_questions=2 | target_assignments=2 | answered=1 | pending=1 | excluded_unmapped_route_question=1' . PHP_EOL;
