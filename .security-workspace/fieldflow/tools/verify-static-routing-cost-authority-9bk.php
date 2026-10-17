<?php
$root=dirname(__DIR__);
$requirements=[
    'src/Support/Config.php'=>["'google_routes_preference' => 'TRAFFIC_UNAWARE'","'google_route_matrix_enabled' => 0","'google_routes_waypoint_optimization_enabled' => 0","'google_routes_tolls_enabled' => 0"],
    'src/Support/Plugin.php'=>['applyStaticRoutingCostAuthority','fieldflow_static_routing_cost_authority_migrated','fieldflow-static-routing-cost-authority-v1'],
    'src/Services/GoogleRoutes.php'=>['STATIC_ROUTING_COST_CONTRACT','routeSignature','matrix_disabled_by_cost_policy','google_routes_tolls_enabled','google_routes_waypoint_optimization_enabled'],
    'src/Services/Planning/RoutingMatrixService.php'=>["VERSION = 'fieldflow_routing_matrix_v2'",'google_route_matrix_enabled','Estimador rodoviário interno'],
    'src/Services/RouteMetricsService.php'=>['google_routes_static_snapshot_v4','route_signature_unchanged','existing_google_snapshot_adopted','routing_cost_contract'],
    'src/Services/RouteOptimizationService.php'=>['route_order_or_composition_changed','routing_finalization'],
    'src/Admin/Routes.php'=>["['mode' => 'internal']",'routing_signature','google_routes_static_snapshot_v4','build_route_schedule_details($dayDate, $dayPoints, $start, $end, false)','build_route_schedule_details($date, $points, $start, $end, false)'],
    'src/Admin/CampaignLocations.php'=>["['mode'=>'internal','cache_days'=>0]",'routing_signature','google_routes_static_snapshot_v4',"['routing_matrix_mode'=>'internal']"],
    'src/Admin/Settings.php'=>['Custos Google avançados','google_route_matrix_enabled','google_routes_tolls_enabled'],
    'src/Rest/IntegrationsController.php'=>['matrix_disabled_by_cost_policy','google_route_matrix_enabled'],
    'src/Support/ReleaseGate.php'=>['STATIC_ROUTING_COST_AUTHORITY_9BK_CONTRACT','checkStaticRoutingCostAuthority9BKContract'],
    'src/Support/ReleasePackageValidator.php'=>['static_routing_cost_authority_9bk_ok'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.581'","public const STABLE_RELEASE = '2.2.582'"],
    'PHASE-9BK-STATIC-ROUTING-COST-AUTHORITY.md'=>['fieldflow-static-routing-cost-authority-v1'],
];
$missing=[];
foreach($requirements as $file=>$markers){
    $raw=(string)@file_get_contents($root.'/'.$file);
    foreach($markers as $marker) if(strpos($raw,$marker)===false) $missing[]=$file.':'.$marker;
}
if($missing){fwrite(STDERR,implode(PHP_EOL,$missing).PHP_EOL);exit(1);}
echo "STATIC ROUTING COST AUTHORITY 9BK CONTRACT OK successor=>=2.2.582\n";
