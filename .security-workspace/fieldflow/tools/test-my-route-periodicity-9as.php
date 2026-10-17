<?php
define('ABSPATH', __DIR__ . '/');
function sanitize_key($value){ return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
function sanitize_text_field($value){ return trim(strip_tags((string)$value)); }
function absint($value){ return abs((int)$value); }
function current_time($format){ return date($format); }
require_once dirname(__DIR__) . '/src/Services/PeriodicityProgressService.php';

use RoutesPro\Services\PeriodicityProgressService;

function invoke_private(string $method, array $args){
    $ref = new ReflectionMethod(PeriodicityProgressService::class, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs(null, $args);
}
function assert_same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, 'FAIL ' . $label . ' expected=' . var_export($expected,true) . ' actual=' . var_export($actual,true) . "\n");
        exit(1);
    }
    echo 'PASS ' . $label . "\n";
}
assert_same('P1 · 1 visita/mês', invoke_private('periodicityLabel', ['monthly',1]), 'p1');
assert_same('P2 · 2 visitas/mês', invoke_private('periodicityLabel', ['monthly',2]), 'p2');
assert_same('P3 · 3 visitas/mês', invoke_private('periodicityLabel', ['monthly',3]), 'p3');
assert_same('P4 · 4 visitas/mês', invoke_private('periodicityLabel', ['monthly',4]), 'p4');
assert_same('1 visita/semana', invoke_private('periodicityLabel', ['weekly',1]), 'weekly');
assert_same('Coberta no plano', invoke_private('statusLabel', ['planned',0,0]), 'planned_status');
assert_same('Sem visita este mês', invoke_private('statusLabel', ['no_visit',2,0]), 'no_visit_status');
$bounds = invoke_private('monthBounds', ['2026-09']);
assert_same(['2026-09-01','2026-09-30'], $bounds, 'month_bounds');
$weeks = invoke_private('businessWeeks', ['2026-09-01','2026-09-30']);
if (count($weeks) < 4 || count($weeks) > 5) {
    fwrite(STDERR, 'FAIL business_weeks count=' . count($weeks) . "\n");
    exit(1);
}
echo 'PASS business_weeks=' . count($weeks) . "\n";
echo "MY ROUTE PERIODICITY 9AS RUNTIME OK\n";
