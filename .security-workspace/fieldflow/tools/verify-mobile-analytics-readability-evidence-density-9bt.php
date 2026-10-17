<?php
$root = dirname(__DIR__);
$fail = [];
$req = [
 'src/Front/ClientPortalShortcodeRenderer.php'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','width:100vw!important','data-rp-evidence-store-detail','data-rp-evidence-date-detail','height:50px!important','content:\'Abrir\'!important','rp-performance-leader'],
 'src/Shortcodes.php'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','width:100vw!important','ffad-evidence-section','data-ffad-detail','height:50px!important','ffad-performance-focus'],
 'PHASE-9BT-MOBILE-ANALYTICS-READABILITY-EVIDENCE-DENSITY.md'=>['fieldflow-mobile-analytics-readability-evidence-density-v1','No database schema'],
];
foreach ($req as $rel=>$markers) {
 $raw=is_readable($root.'/'.$rel)?(string)file_get_contents($root.'/'.$rel):'';
 foreach($markers as $marker) if(strpos($raw,$marker)===false) $fail[]=$rel.':'.$marker;
}
$main=is_readable($root.'/fieldflow.php')?(string)file_get_contents($root.'/fieldflow.php'):'';
preg_match('/Version:\s*([0-9.]+)/',$main,$hm); preg_match("/FIELDFLOW_VERSION'\s*,\s*'([^']+)'/",$main,$cm);
$version=(string)($cm[1]??$hm[1]??'0.0.0');
if(version_compare($version,'2.2.591','<')) $fail[]='fieldflow.php:successor=>=2.2.591';
$phase=is_readable($root.'/src/Cloud/Phase6StableRelease.php')?(string)file_get_contents($root.'/src/Cloud/Phase6StableRelease.php'):'';
foreach(["public const SOURCE_RELEASE = '2.2.590'","public const STABLE_RELEASE = '2.2.591'"] as $m) if(strpos($phase,$m)===false) $fail[]='Phase6:'.$m;
if($fail){fwrite(STDERR,"MOBILE ANALYTICS READABILITY EVIDENCE DENSITY 9BT CONTRACT FAIL\n".implode("\n",$fail)."\n");exit(1);}
echo "MOBILE ANALYTICS READABILITY EVIDENCE DENSITY 9BT CONTRACT OK successor=>=2.2.591\n";
