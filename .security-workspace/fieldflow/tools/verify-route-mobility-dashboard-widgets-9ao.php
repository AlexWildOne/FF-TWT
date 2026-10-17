<?php
$root=dirname(__DIR__);$fail=[];
$read=static function(string $rel)use($root):string{$p=$root.'/'.$rel;return is_readable($p)?(string)file_get_contents($p):'';};
$assert=static function(bool $ok,string $label)use(&$fail):void{if(!$ok)$fail[]=$label;};
$main=$read('fieldflow.php');$svc=$read('src/Analytics/RouteMobilityWidgetService.php');$canonical=$read('src/Reporting/CanonicalOperationalReportService.php');$stats=$read('src/Rest/StatsController.php');$admin=$read('src/Admin/Assignments.php');$front=$read('src/Front/ClientPortalShortcodeRenderer.php');$orchestrator=$read('src/Analytics/WidgetOrchestrator.php');$gate=$read('src/Support/ReleaseGate.php');$validator=$read('src/Support/ReleasePackageValidator.php');$manager=$read('src/Admin/ReleaseManager.php');$phase=$read('PHASE-9AO-ROUTE-MOBILITY-DASHBOARD-WIDGETS.md');
$version=''; if(preg_match('/Version:\s*([0-9.]+)/',$main,$m))$version=(string)$m[1]; $assert($version!=='' && version_compare($version,'2.2.559','>='),'release successor');
$assert(strpos($svc,"CONTRACT = 'fieldflow-route-mobility-dashboard-widgets-v1'")!==false,'service contract');
foreach(['route_execution','visit_execution','route_distance_km','route_distance_breakdown','route_energy_cost_eur','route_toll_cost_eur','route_mobility_cost_eur','route_daily_mobility'] as $key)$assert(strpos($svc,"'{$key}'")!==false,'catalog '.$key);
$assert(strpos($svc,'CanonicalOperationalReportService::routeMobilitySnapshot')!==false,'canonical source');
$assert(strpos($canonical,'public static function routeMobilitySnapshot')!==false,'canonical public snapshot');
$assert(strpos($stats,'RouteMobilityWidgetService::buildMetric')!==false && strpos($stats,'RouteMobilityWidgetService::isSystemSettings')!==false,'stats integration');
$assert(strpos($admin,'Origem do widget')!==false && strpos($admin,'Rotas e mobilidade')!==false && strpos($admin,'system_metric_key')!==false,'intelligence center editor');
$assert(strpos($front,'isRouteMobilityMetric')!==false && strpos($front,'routeMobilityTableHtml')!==false && strpos($front,'Km acesso')!==false && strpos($front,'Km entre PDVs')!==false && strpos($front,'Km regresso')!==false,'front route tables');
$assert(strpos($orchestrator,"return 'mobility'")!==false && strpos($orchestrator,"'mobility'=>")!==false,'widget intelligence mobility topic');
$assert(strpos($gate,"ROUTE_MOBILITY_DASHBOARD_WIDGETS_9AO_CONTRACT = 'fieldflow-route-mobility-dashboard-widgets-v1'")!==false,'gate contract');
$assert(strpos($validator,'route_mobility_dashboard_widgets_9ao_ok')!==false,'package validator');
$assert(strpos($manager,'Route & Mobility Dashboard Widgets 9AO')!==false,'release manager');
$assert(strpos($phase,'`SOURCE_RELEASE` is `2.2.558` and `STABLE_RELEASE` is `2.2.559`')!==false,'phase identity');
if($fail){fwrite(STDERR,'9AO FAIL: '.implode(', ',$fail).PHP_EOL);exit(1);}echo 'ROUTE MOBILITY DASHBOARD WIDGETS 9AO CONTRACT OK | release=' . $version . ' | successor=>=2.2.559 | source=canonical_report | intelligence_center=configurable | frontend=current_layout'.PHP_EOL;
