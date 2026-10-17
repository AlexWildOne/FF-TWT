<?php
define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v)); } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
require_once dirname(__DIR__) . '/src/Reporting/DailyCommercialReportService.php';
use RoutesPro\Reporting\DailyCommercialReportService;
$report = [
    'kpis'=>[
        'visits_planned'=>3,'visits_completed'=>2,'visits_justified'=>1,'visits_remaining'=>0,'execution_rate'=>66.7,'operational_closure_rate'=>100,
        'fronts_before'=>30,'fronts_after'=>35,'fronts_gain'=>5,'fronts_growth_percent'=>16.7,'fronts_locations_gain'=>1,
        'extra_spaces'=>1,'order_suggestions'=>1,'order_lines'=>2,'order_boxes'=>4,'evidence_files'=>2,'briefing_questions'=>2,'briefing_answered'=>2,'briefing_coverage_percent'=>100,
    ],
    'routing'=>['stops'=>[
        ['route_id'=>7,'location_id'=>1,'location_name'=>'Loja A','status'=>'done','justification'=>''],
        ['route_id'=>7,'location_id'=>2,'location_name'=>'Loja B','status'=>'done','justification'=>''],
        ['route_id'=>7,'location_id'=>3,'location_name'=>'Loja C','status'=>'failed','justification'=>'Responsavel indisponivel'],
    ]],
    'fronts'=>['rows'=>[
        ['location'=>'Loja A','product'=>'Marca X','before'=>10,'after'=>17,'difference'=>7],
        ['location'=>'Loja B','product'=>'Marca Y','before'=>20,'after'=>18,'difference'=>-2],
    ]],
    'extra_spaces'=>['rows'=>[['location'=>'Loja A','type'=>'Expositor']]],
    'orders'=>['rows'=>[['location'=>'Loja A','product'=>'Marca X'],['location'=>'Loja B','product'=>'Marca Y']]],
    'briefing'=>['rows'=>[['question'=>'Q1'],['question'=>'Q2']]],
    'evidence'=>['rows'=>[['location'=>'Loja A'],['location'=>'Loja B']]],
];
$r=DailyCommercialReportService::build($report);
$ok = ($r['contract'] ?? '') === DailyCommercialReportService::CONTRACT
    && !empty($r['capabilities']['fronts'])
    && count((array)($r['gains'] ?? [])) === 1
    && count((array)($r['losses'] ?? [])) === 1
    && count((array)($r['exceptions'] ?? [])) === 1
    && !empty($r['client']['paragraphs'])
    && !empty($r['merchandiser']['alerts']);
$empty = DailyCommercialReportService::build([
    'kpis'=>['visits_planned'=>1,'visits_completed'=>1,'execution_rate'=>100],
    'routing'=>['stops'=>[['location_name'=>'Loja','status'=>'done']]],
    'fronts'=>['rows'=>[]],
    'extra_spaces'=>['rows'=>[]],
    'orders'=>['rows'=>[]],
    'briefing'=>['rows'=>[]],
    'evidence'=>['rows'=>[]],
]);
$ok = $ok && empty($empty['capabilities']['fronts']) && empty($empty['capabilities']['orders']);
if (!$ok) { fwrite(STDERR, '9AI runtime assertions failed' . PHP_EOL); exit(1); }
echo 'MAIL INTELLIGENCE DAILY REPORTING 9AI RUNTIME OK | execution=66.7 | closure=100 | gains=1 | losses=1 | exceptions=1 | client=dynamic | merchandiser=alerts' . PHP_EOL;
