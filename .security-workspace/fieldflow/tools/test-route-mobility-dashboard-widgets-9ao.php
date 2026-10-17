<?php
namespace {
define('ABSPATH', __DIR__ . '/');
if(!function_exists('sanitize_text_field')){function sanitize_text_field($v){return is_scalar($v)?trim((string)$v):'';}}
if(!function_exists('sanitize_key')){function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v));}}
if(!function_exists('absint')){function absint($v){return abs((int)$v);}}
if(!function_exists('remove_accents')){function remove_accents($v){return (string)$v;}}
}
namespace RoutesPro\Services {
final class MobilityCostService {
    public static function reportingProjection(array $m): array {
        return [
            'reporting_distance_km'=>$m['distance_km']??null,
            'reporting_access_distance_km'=>$m['access_distance_km']??null,
            'reporting_inter_stop_distance_km'=>$m['inter_stop_distance_km']??null,
            'reporting_return_distance_km'=>$m['return_distance_km']??null,
            'reporting_distance_basis'=>'canonical_test',
            'reporting_full_route_reconciled'=>1,
            'reporting_costs_available'=>!empty($m['costs_available'])?1:0,
            'reporting_partial'=>!empty($m['partial'])?1:0,
            'reporting_recovered'=>0,
            'reporting_quality'=>'canonical',
            'reporting_energy_cost_eur'=>$m['energy_cost_eur']??null,
            'reporting_fuel_cost_eur'=>$m['energy_cost_eur']??null,
            'reporting_electricity_cost_eur'=>0,
            'reporting_toll_cost_eur'=>$m['toll_cost_eur']??null,
            'reporting_estimated_litres'=>$m['estimated_litres']??null,
            'reporting_estimated_kwh'=>0,
            'reporting_total_mobility_cost_eur'=>$m['total_mobility_cost_eur']??null,
        ];
    }
}
}
namespace {
require_once dirname(__DIR__).'/src/Reporting/CanonicalOperationalReportService.php';
require_once dirname(__DIR__).'/src/Analytics/RouteMobilityWidgetService.php';
use RoutesPro\Analytics\RouteMobilityWidgetService;
$stats=['by_day'=>[
 ['date'=>'2026-09-22','route_id'=>101,'route_status'=>'completed','client_id'=>7,'project_id'=>9,'stops'=>6,'stops_done'=>6,'stops_justified_failed'=>0,'stops_remaining'=>0,'done_rate'=>100,'mobility_cost'=>['enabled'=>1,'costs_available'=>1,'distance_km'=>160.0,'access_distance_km'=>34.0,'inter_stop_distance_km'=>92.0,'return_distance_km'=>34.0,'energy_cost_eur'=>24.0,'toll_cost_eur'=>10.0,'total_mobility_cost_eur'=>34.0,'estimated_litres'=>12.0,'profile_label'=>'Gasolina · Teste']],
 ['date'=>'2026-09-22','route_id'=>102,'route_status'=>'active','client_id'=>7,'project_id'=>9,'stops'=>4,'stops_done'=>2,'stops_justified_failed'=>0,'stops_remaining'=>2,'done_rate'=>50,'mobility_cost'=>['enabled'=>1,'costs_available'=>1,'distance_km'=>50.0,'access_distance_km'=>10.0,'inter_stop_distance_km'=>30.0,'return_distance_km'=>10.0,'energy_cost_eur'=>7.0,'toll_cost_eur'=>2.0,'total_mobility_cost_eur'=>9.0]],
]];
$binding=['id'=>33,'metric_key'=>'route_distance_km','metric_label'=>'Km executados'];$dashboard=['id'=>5,'title'=>'Operação','sort_order'=>10];
$metric=function(string $key)use($binding,$dashboard,$stats){$b=$binding;$b['metric_key']=$key;$b['metric_label']='';return RouteMobilityWidgetService::buildMetric($b,['metric_source'=>'route_mobility','system_metric_key'=>$key,'client_id'=>7,'project_id'=>9],$dashboard,$stats);};
$fail=[];$ok=function($cond,$label)use(&$fail){if(!$cond)$fail[]=$label;};
$distance=$metric('route_distance_km');
$ok(($distance['value']??'')==='160,0 km','closed route km');
$ok((float)($distance['series'][0]['value']??0)===160.0,'daily km series');
$ok((int)($distance['record_count']??0)===1,'open route excluded from mobility');
$breakdown=$metric('route_distance_breakdown');
$series=[];foreach((array)($breakdown['series']??[]) as $row)$series[(string)($row['label']??'')]=(float)($row['value']??0);
$ok(($series['Acesso']??0)===34.0 && ($series['Entre PDVs']??0)===92.0 && ($series['Regresso']??0)===34.0,'distance composition');
$cost=$metric('route_mobility_cost_eur');
$ok(($cost['value']??'')==='34,00 €','fuel+tolls cost');
$execution=$metric('route_execution');
$ok(($execution['value']??'')==='50,0%','route execution includes open route');
$ok(count((array)($execution['rows']??[]))===2,'execution route rows');
$daily=$metric('route_daily_mobility');
$row=(array)($daily['rows'][0]??[]);
$ok((float)($row['access_distance_km']??0)===34.0 && (float)($row['inter_stop_distance_km']??0)===92.0 && (float)($row['return_distance_km']??0)===34.0,'daily table components');
if($fail){fwrite(STDERR,'9AO RUNTIME FAIL: '.implode(', ',$fail).PHP_EOL);exit(1);}echo 'ROUTE MOBILITY DASHBOARD WIDGETS 9AO RUNTIME OK | closed_km=160 | access=34 | pdvs=92 | return=34 | mobility=34EUR | execution=50%'.PHP_EOL;
}
