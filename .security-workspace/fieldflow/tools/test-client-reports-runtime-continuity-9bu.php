<?php
$root=dirname(__DIR__);
$c=(string)file_get_contents($root.'/src/Front/ClientPortalShortcodeRenderer.php');
$a=function($ok,$l){if(!$ok){fwrite(STDERR,"FAIL $l\n");exit(1);}echo "PASS $l\n";};
$a((bool)preg_match('/async\s+function\s+loadReports\s*\(/', $c),'loader_defined');
$a(strpos($c,"api+'form-submissions?'")!==false,'endpoint_bridge');
$start=strpos($c,'function paramsForReports()');$end=$start===false?false:strpos($c,'function buildAdminPostExportUrl',$start);$params=($start!==false&&$end!==false)?substr($c,$start,$end-$start):'';
$a($params!==''&&strpos($params,"p.set('client_id'")!==false&&strpos($params,"p.set('project_id'")!==false&&strpos($params,"p.set('owner_user_id'")!==false&&strpos($params,"p.set('location_id'")!==false&&strpos($params,"p.set('date_from'")!==false&&strpos($params,"p.set('date_to'")!==false,'filter_bridge');
$a($params!==''&&strpos($params,"p.set('route_id'")===false&&strpos($params,'selectedRouteId')===false,'header_scope_authority');
$a(strpos($c,"state.reports={rows,columns:")!==false,'state_population');
echo "CLIENT REPORTS RUNTIME CONTINUITY 9BU RUNTIME OK loader_defined endpoint_bridge filter_bridge header_scope_authority state_population\n";
