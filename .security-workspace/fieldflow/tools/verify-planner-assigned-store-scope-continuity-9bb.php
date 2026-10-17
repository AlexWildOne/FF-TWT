<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string { $p=$root.'/'.$relative; return is_readable($p)?(string)file_get_contents($p):''; };
$failed=[];
$assert=static function(bool $ok,string $name) use (&$failed): void { if(!$ok)$failed[]=$name; };
$permissions=$read('src/Support/Permissions.php');
$scopeIndex=$read('src/Support/ScopeIndex.php');
$routes=$read('src/Rest/RoutesController.php');
$planner=$read('src/Front/SelfRoutePlannerShortcodeRenderer.php');
$phase=$read('src/Cloud/Phase6StableRelease.php');
$gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php');
$manager=$read('src/Admin/ReleaseManager.php');
$doc=$read('PHASE-9BB-PLANNER-ASSIGNED-STORE-SCOPE-CONTINUITY.md');
foreach([
    'assignedStoreScopeForUser',
    'FROM {$px}campaign_locations cl',
    "cl.assigned_to=%d AND cl.is_active=1 AND cl.status='active'",
    'Atribuições reais de lojas também são uma associação direta à campanha.'
] as $m)$assert(strpos($permissions,$m)!==false,'permissions:'.$m);
foreach([
    "'campaign_location'",
    'FROM {$px}campaign_locations cl',
    "source_type IN ('project_meta','project_assignment','campaign_location','route_owner','route_assignment')"
] as $m)$assert(strpos($scopeIndex,$m)!==false,'scope_index:'.$m);
$assert(strpos($routes,'project_has_assigned_pdvs')!==false,'routes:assigned_guard');
$assert(strpos($routes,"assigned_to=%d AND is_active=1 AND status='active'")!==false,'routes:active_assignment');
$assert(strpos($planner,'Carregar lojas atribuídas')!==false,'planner:assigned_label');
$assert(strpos($planner,'campaign-pdvs?client_id=')!==false && strpos($planner,'&active=1&status=active')!==false,'planner:assigned_endpoint');
$assert(strpos($phase,"SOURCE_RELEASE = '2.2.571'")!==false && strpos($phase,"STABLE_RELEASE = '2.2.572'")!==false,'phase6_successor');
$assert(strpos($gate,"PLANNER_ASSIGNED_STORE_SCOPE_9BB_CONTRACT = 'fieldflow-planner-assigned-store-scope-continuity-v1'")!==false,'gate_contract');
$assert(strpos($gate,'checkPlannerAssignedStoreScope9BBContract')!==false,'gate_check');
$assert(strpos($validator,'planner_assigned_store_scope_9bb_ok')!==false,'validator_check');
$assert(strpos($manager,"packageResult['planner_assigned_store_scope_9bb_ok']")!==false,'manager_kpi');
$assert(strpos($doc,'fieldflow-planner-assigned-store-scope-continuity-v1')!==false,'doc_contract');
$assert(strpos($read('src/Support/Migrations.php'),"LATEST_SCHEMA_VERSION = '2.2.529'")!==false,'schema_unchanged');
$assert(strpos($read('src/Forms/SubmissionPipeline.php'),"public const BUILD = '2.2.485'")!==false,'submission_unchanged');
if($failed){fwrite(STDERR,'FAILED '.implode(',',$failed).PHP_EOL);exit(1);}echo "PLANNER ASSIGNED STORE SCOPE 9BB CONTRACT OK\n";
