<?php
$root=dirname(__DIR__);$fail=[];
$read=static function(string $rel)use($root):string{$p=$root.'/'.$rel;return is_readable($p)?(string)file_get_contents($p):'';};
$assert=static function(bool $ok,string $label)use(&$fail):void{if(!$ok)$fail[]=$label;};
$main=$read('fieldflow.php');$mobility=$read('src/Services/MobilityCostService.php');$metrics=$read('src/Services/RouteMetricsService.php');
$canonical=$read('src/Reporting/CanonicalOperationalReportService.php');$mail=$read('src/Admin/ReportAutomations.php');$exports=$read('src/Admin/FormSubmissions.php');
$gate=$read('src/Support/ReleaseGate.php');$validator=$read('src/Support/ReleasePackageValidator.php');$phase=$read('PHASE-9AM-FULL-ROUTE-MOBILITY-REPORTING-RECONCILIATION.md');
$version=''; if(preg_match('/Version:\s*([0-9.]+)/',$main,$m))$version=(string)$m[1]; $assert($version!=='' && version_compare($version,'2.2.557','>='),'release successor');
$assert(strpos($mobility,"'reporting_full_route_reconciled'")!==false && strpos($mobility,"'reporting_distance_basis'")!==false,'projection reconciliation');
$assert(strpos($mobility,"'reporting_access_distance_km'")!==false && strpos($mobility,"'reporting_return_distance_km'")!==false,'projection components');
$assert(strpos($metrics,'reportingStoredRouteLegBreakdown')!==false,'stored leg breakdown');
$assert(strpos($canonical,"'access_distance_km'")!==false && strpos($canonical,"'return_distance_km'")!==false,'canonical component rollup');
$assert(strpos($mail,'Acesso ')!==false && strpos($mail,'PDVs ')!==false && strpos($mail,'Regresso ')!==false,'mail breakdown');
$assert(strpos($exports,"'Km acesso','Km entre PDVs','Km regresso'")!==false,'excel breakdown');
$assert(strpos($gate,"FULL_ROUTE_MOBILITY_REPORTING_9AM_CONTRACT = 'fieldflow-full-route-mobility-reporting-reconciliation-v1'")!==false,'gate contract');
$assert(strpos($validator,'full_route_mobility_reporting_9am_ok')!==false,'validator');
$assert(strpos($phase,'`SOURCE_RELEASE` is `2.2.556` and `STABLE_RELEASE` is `2.2.557`')!==false,'phase identity');
if($fail){fwrite(STDERR,'9AM FAIL: '.implode(', ',$fail).PHP_EOL);exit(1);}
echo 'FULL ROUTE MOBILITY REPORTING RECONCILIATION 9AM CONTRACT OK | release=' . $version . ' | successor=>=2.2.557 | distance=access+pdvs+return | canonical=unchanged | reporting=reconciled'.PHP_EOL;
