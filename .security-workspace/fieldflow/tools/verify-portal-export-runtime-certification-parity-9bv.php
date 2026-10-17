<?php
$root=dirname(__DIR__);$fail=[];
$client=(string)file_get_contents($root.'/src/Front/ClientPortalShortcodeRenderer.php');
foreach(['fieldflow-portal-export-runtime-certification-parity-v1','function exportUrl(format)','function dashboardExportUrl(format)','function routeExportUrl(format)','function safeRefreshExportLinks()','routespro_export_submissions','routespro_export_dashboard','routespro_export_routes'] as $m)if(strpos($client,$m)===false)$fail[]='client:'.$m;
$operative=(string)file_get_contents($root.'/src/Shortcodes.php');foreach(['function exportUrl(format)',"p.set('action','routespro_export_dashboard')",'self_mode'] as $m)if(strpos($operative,$m)===false)$fail[]='operative:'.$m;
$app=(string)file_get_contents($root.'/src/Front/AppShortcodeRenderer.php');if(strpos($app,'fieldflow_route_today mode="app"')===false)$fail[]='operative:route_bridge';
$gate=(string)file_get_contents($root.'/src/Support/ReleaseGate.php');foreach(['PORTAL_EXPORT_RUNTIME_CERT_PARITY_9BV_CONTRACT','checkPortalExportRuntimeCertParity9BVContract'] as $m)if(strpos($gate,$m)===false)$fail[]='gate:'.$m;
$validator=(string)file_get_contents($root.'/src/Support/ReleasePackageValidator.php');foreach(['portal_export_runtime_cert_parity_9bv_ok','fieldflow-portal-export-runtime-certification-parity-v1'] as $m)if(strpos($validator,$m)===false)$fail[]='validator:'.$m;
$main=(string)file_get_contents($root.'/fieldflow.php');preg_match("/FIELDFLOW_VERSION'\s*,\s*'([^']+)'/",$main,$m);$v=$m[1]??'0.0.0';if(version_compare($v,'2.2.593','<'))$fail[]='successor';
$phase=(string)file_get_contents($root.'/src/Cloud/Phase6StableRelease.php');foreach(["public const SOURCE_RELEASE = '2.2.592'","public const STABLE_RELEASE = '2.2.593'"] as $x)if(strpos($phase,$x)===false)$fail[]='phase:'.$x;
if($fail){fwrite(STDERR,"PORTAL EXPORT RUNTIME CERTIFICATION PARITY 9BV CONTRACT FAIL\n".implode("\n",$fail)."\n");exit(1);}echo "PORTAL EXPORT RUNTIME CERTIFICATION PARITY 9BV CONTRACT OK successor=>=2.2.593\n";
