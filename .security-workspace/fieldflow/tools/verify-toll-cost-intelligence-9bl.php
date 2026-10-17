<?php
$root=dirname(__DIR__);
$requirements=[
    'src/Support/TollEstimator.php'=>['INTELLIGENCE_CONTRACT','PT-2026-IMT-IP-ASCENDI-v1','estimateFromGoogleRoute',"'A22'=>['rate'=>0.000", "'A41'=>['rate'=>0.085"],
    'src/Support/Config.php'=>["'toll_intelligence_enabled' => 1","'toll_vehicle_class' => 1",'api_rate_google_routes_compute_routes_essentials_per_1000_eur'],
    'src/Support/Plugin.php'=>['applyTollCostIntelligence','fieldflow_toll_cost_intelligence_migrated','fieldflow-toll-cost-intelligence-v1'],
    'src/Services/GoogleRoutes.php'=>['TOLL_COST_INTELLIGENCE_CONTRACT','routes.legs.steps.navigationInstruction.instructions','compute_routes_essentials','compute_routes_enterprise','toll_confidence','toll_tariff_version'],
    'src/Observability/ApiUsageLedger.php'=>['strpos($operationKey, \'compute_routes_\')','api_rate_google_routes_route_per_1000_eur'],
    'src/Admin/Settings.php'=>['Portagens estimadas FieldFlow','toll_intelligence_enabled','toll_vehicle_class'],
    'src/Admin/SecurityObservability.php'=>['Compute Routes Essentials por 1.000','Compute Routes Enterprise por 1.000'],
    'src/Services/RouteMetricsService.php'=>['toll_confidence','toll_tariff_version','toll_cost_intelligence_contract'],
    'src/Admin/Routes.php'=>['Confiança:','toll_confidence','toll_cost_intelligence_contract'],
    'src/Support/ReleaseGate.php'=>['TOLL_COST_INTELLIGENCE_9BL_CONTRACT','checkTollCostIntelligence9BLContract'],
    'src/Support/ReleasePackageValidator.php'=>['toll_cost_intelligence_9bl_ok'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.582'","public const STABLE_RELEASE = '2.2.583'"],
    'PHASE-9BL-TOLL-COST-INTELLIGENCE.md'=>['fieldflow-toll-cost-intelligence-v1'],
];
$missing=[];
foreach($requirements as $file=>$markers){
    $raw=(string)@file_get_contents($root.'/'.$file);
    foreach($markers as $marker) if(strpos($raw,$marker)===false) $missing[]=$file.':'.$marker;
}
if($missing){fwrite(STDERR,implode(PHP_EOL,$missing).PHP_EOL);exit(1);}
echo "TOLL COST INTELLIGENCE 9BL CONTRACT OK successor=>=2.2.583\n";
