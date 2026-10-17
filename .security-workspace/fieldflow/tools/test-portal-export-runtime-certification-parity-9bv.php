<?php
$root=dirname(__DIR__);
$client=(string)file_get_contents($root.'/src/Front/ClientPortalShortcodeRenderer.php');
$operative=(string)file_get_contents($root.'/src/Shortcodes.php');
$app=(string)file_get_contents($root.'/src/Front/AppShortcodeRenderer.php');
$gate=(string)file_get_contents($root.'/src/Support/ReleaseGate.php');
$validator=(string)file_get_contents($root.'/src/Support/ReleasePackageValidator.php');
$a=function($ok,$label){if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n";};
$clientDeps=strpos($client,'function exportUrl(format)')!==false&&strpos($client,'function dashboardExportUrl(format)')!==false&&strpos($client,'function routeExportUrl(format)')!==false&&strpos($client,'function safeRefreshExportLinks()')!==false&&strpos($client,"'routespro_export_submissions'")!==false&&strpos($client,"'routespro_export_dashboard'")!==false&&strpos($client,"'routespro_export_routes'")!==false;
$a($clientDeps,'client_export_dependencies_closed');
$start=strpos($client,'async function loadReports(');$end=$start===false?false:strpos($client,'function renderReports', $start);$loader=($start!==false&&$end!==false)?substr($client,$start,$end-$start):'';
$a($loader!==''&&strpos($loader,'safeRefreshExportLinks();')!==false&&strpos($loader,'renderReports(1); refreshExportLinks();')===false,'reports_failure_domain_isolated');
$a(strpos($operative,'function exportUrl(format)')!==false&&strpos($operative,"p.set('action','routespro_export_dashboard')")!==false&&strpos($operative,"p.set('self_mode','1')")!==false&&strpos($app,'fieldflow_route_today mode="app"')!==false,'operative_export_path_preserved');
$a(strpos($gate,'PORTAL_EXPORT_RUNTIME_CERT_PARITY_9BV_CONTRACT')!==false&&strpos($gate,'checkPortalExportRuntimeCertParity9BVContract')!==false&&strpos($validator,'portal_export_runtime_cert_parity_9bv_ok')!==false&&strpos($validator,'fieldflow-portal-export-runtime-certification-parity-v1')!==false,'gate_validator_parity');
echo "PORTAL EXPORT RUNTIME CERTIFICATION PARITY 9BV RUNTIME OK client_export_dependencies_closed reports_failure_domain_isolated operative_export_path_preserved gate_validator_parity\n";
