<?php
$root=dirname(__DIR__);
$requirements=[
    'src/Services/GoogleCostControlCenter.php'=>["CONTRACT = 'fieldflow-google-cost-control-center-v1'",'authorizeOperation','configurationDrift','recordBlocked','POLÍTICA SAUDÁVEL'],
    'src/Services/GoogleRoutes.php'=>['GoogleCostControlCenter::authorizeOperation','google_cost_control_blocked'],
    'src/Observability/ApiUsageLedger.php'=>["status === 'blocked'","SUM(status='blocked') blocked",'avoided_events'],
    'src/Support/Config.php'=>["'google_cost_control_enabled' => 1","'google_cost_block_matrix' => 1","'google_cost_block_pro' => 1","'google_cost_block_enterprise' => 1"],
    'src/Support/Plugin.php'=>['applyGoogleCostControlCenter','fieldflow_google_cost_control_center_migrated','fieldflow-google-cost-control-center-v1'],
    'src/Admin/SecurityObservability.php'=>['Google Cost Control Center','Bloquear Google Route Matrix','Bloquear Google Routes Pro','Bloquear Google Routes Enterprise'],
    'src/Admin/Settings.php'=>['Google Cost Control Center:','bloqueios financeiros têm prioridade'],
    'src/Support/ReleaseGate.php'=>['GOOGLE_COST_CONTROL_CENTER_9BN_CONTRACT','checkGoogleCostControlCenter9BNContract'],
    'src/Support/ReleasePackageValidator.php'=>['google_cost_control_center_9bn_ok'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.584'","public const STABLE_RELEASE = '2.2.585'"],
    'PHASE-9BN-GOOGLE-COST-CONTROL-CENTER.md'=>['fieldflow-google-cost-control-center-v1'],
];
$missing=[];
foreach($requirements as $file=>$markers){
    $raw=(string)@file_get_contents($root.'/'.$file);
    foreach($markers as $marker) if(strpos($raw,$marker)===false) $missing[]=$file.':'.$marker;
}
if($missing){fwrite(STDERR,implode(PHP_EOL,$missing).PHP_EOL);exit(1);}
echo "GOOGLE COST CONTROL CENTER 9BN CONTRACT OK successor=>=2.2.585\n";
