<?php
$root = dirname(__DIR__);
$fail = [];
$req = [
    'src/Analytics/MonthlyExecutiveAnalyticsService.php'=>['fieldflow-monthly-executive-analytics-v1','primary_kpis','risk_locations','commercial_signals','mobility_cost_per_visit_eur'],
    'src/Rest/StatsController.php'=>['MonthlyExecutiveAnalyticsService','monthly_executive'],
    'src/Admin/Routes.php'=>['rp-routes-financial-summary','reportingProjection','data-mobility-cost','Custo deslocação'],
    'src/Front/ClientPortalShortcodeRenderer.php'=>['rpcp-monthly-executive','Este mês em 30 segundos','renderMonthlyExecutive','Como funciona esta análise?'],
    'src/Analytics/AnalyticsAIService.php'=>['fieldflow-explainable-analytics-v1','explainability','does_not_do'],
    'src/Analytics/FieldFlowAskService.php'=>['No Analytics Cliente','Como é calculada a confiança?','O FieldFlow altera alguma coisa sozinho?'],
    'src/Shortcodes.php'=>['ffadExplainabilityHtml','Como funciona esta análise?'],
    'PHASE-9BS-EXECUTIVE-ANALYTICS-ROUTE-FINANCIAL-VISIBILITY.md'=>['fieldflow-executive-analytics-route-financial-visibility-v1','No database schema'],
];
foreach ($req as $rel=>$markers) {
    $raw = is_readable($root.'/'.$rel) ? (string)file_get_contents($root.'/'.$rel) : '';
    foreach ($markers as $marker) if (strpos($raw,$marker) === false) $fail[]=$rel.':'.$marker;
}
$main = is_readable($root.'/fieldflow.php') ? (string)file_get_contents($root.'/fieldflow.php') : '';
preg_match('/Version:\s*([0-9.]+)/', $main, $hm);
preg_match("/FIELDFLOW_VERSION'\s*,\s*'([^']+)'/", $main, $cm);
$version = (string)($cm[1] ?? $hm[1] ?? '0.0.0');
if (version_compare($version,'2.2.590','<')) $fail[]='fieldflow.php:successor=>=2.2.590';
if ($fail) { fwrite(STDERR,"EXECUTIVE ANALYTICS ROUTE FINANCIAL VISIBILITY 9BS CONTRACT FAIL\n".implode("\n",$fail)."\n"); exit(1); }
echo "EXECUTIVE ANALYTICS ROUTE FINANCIAL VISIBILITY 9BS CONTRACT OK successor=>=2.2.590\n";
