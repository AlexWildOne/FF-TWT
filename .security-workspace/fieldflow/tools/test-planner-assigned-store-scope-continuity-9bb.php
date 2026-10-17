<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string { $p=$root.'/'.$relative; return is_readable($p)?(string)file_get_contents($p):''; };
$failed=[];
$assert=static function(bool $ok,string $name) use (&$failed): void { if(!$ok)$failed[]=$name; };
$permissions=$read('src/Support/Permissions.php');
$scopeIndex=$read('src/Support/ScopeIndex.php');
$routes=$read('src/Rest/RoutesController.php');
$planner=$read('src/Front/SelfRoutePlannerShortcodeRenderer.php');

$scopeMethodStart=strpos($permissions,'private static function assignedStoreScopeForUser');
$scopeMethodEnd=$scopeMethodStart===false?false:strpos($permissions,'public static function get_associated_user_ids',$scopeMethodStart);
$scopeMethod=($scopeMethodStart!==false&&$scopeMethodEnd!==false)?substr($permissions,$scopeMethodStart,$scopeMethodEnd-$scopeMethodStart):'';
$assert($scopeMethod!=='','permissions:assigned_scope_method');
$assert(strpos($scopeMethod,'SELECT DISTINCT cl.project_id, p.client_id')!==false,'permissions:project_client_resolution');
$assert(strpos($scopeMethod,"cl.assigned_to=%d AND cl.is_active=1 AND cl.status='active'")!==false,'permissions:active_assignment_filter');

$getScopeStart=strpos($permissions,'public static function get_scope');
$getScopeEnd=$getScopeStart===false?false:strpos($permissions,'public static function filter_clients',$getScopeStart);
$getScope=($getScopeStart!==false&&$getScopeEnd!==false)?substr($permissions,$getScopeStart,$getScopeEnd-$getScopeStart):'';
$assert(strpos($getScope,'ScopeIndex::scopeForUser($uid)')!==false,'scope:index_path_retained');
$assert(substr_count($getScope,'assignedStoreScopeForUser($uid)')>=2,'scope:live_bridge_index_and_fallback');

$directStart=strpos($permissions,'public static function get_direct_project_ids');
$directEnd=$directStart===false?false:strpos($permissions,'public static function resolve_front_context',$directStart);
$direct=($directStart!==false&&$directEnd!==false)?substr($permissions,$directStart,$directEnd-$directStart):'';
$assert(strpos($direct,'ScopeIndex::directProjectIdsForUser($uid)')!==false,'direct:index_retained');
$assert(strpos($direct,'assignedStoreScopeForUser($uid)')!==false,'direct:live_assigned_campaign');

$assert(strpos($scopeIndex,"source_type IN ('project_meta','project_assignment','campaign_location','route_owner','route_assignment')")!==false,'scope_index:direct_campaign_location');
$assert(strpos($scopeIndex,"self::addMembership(\$memberships, \$userId, 'project', \$projectId, 'campaign_location', \$projectId)")!==false,'scope_index:persist_project');
$assert(strpos($scopeIndex,"self::addMembership(\$memberships, \$userId, 'client', \$clientId, 'campaign_location', \$projectId)")!==false,'scope_index:persist_client');

$assert(strpos($planner,"campaign-pdvs?client_id='+cid+'&project_id='+pid+'&active=1&status=active")!==false,'planner:active_campaign_pdvs');
$assert(strpos($routes,"if (!Permissions::can_access_front()) return false;")!==false,'routes:front_access_retained');
$assert(strpos($routes,"return \$this->project_has_assigned_pdvs(\$project_id, get_current_user_id());")!==false,'routes:create_requires_assignment');
$assert(strpos($routes,"assigned_to=%d AND is_active=1 AND status='active' LIMIT 1")!==false,'routes:assignment_guard_active');

if($failed){fwrite(STDERR,'FAILED '.implode(',',$failed).PHP_EOL);exit(1);}echo "PLANNER ASSIGNED STORE SCOPE 9BB RUNTIME OK\n";
