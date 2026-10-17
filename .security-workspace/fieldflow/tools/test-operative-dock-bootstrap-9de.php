<?php
// OPERATIVE DOCK BOOTSTRAP 9DE PASS
$root = dirname(__DIR__).DIRECTORY_SEPARATOR;
$read = static function(string $path) use($root):string{
    $f=$root.$path;if(!is_readable($f)){fwrite(STDERR,"9DE missing $path\n");exit(1);}
    return (string)file_get_contents($f);
};
$assert=static function(bool $ok,string $reason):void{if(!$ok){fwrite(STDERR,"9DE FAIL $reason\n");exit(1);}};
$main=$read('fieldflow.php');
$assert((bool)preg_match("/define\('FIELDFLOW_VERSION', '([^']+)'\)/",$main,$v)&&version_compare($v[1],'2.2.639','>='),'version');
$app=$read('src/Front/AppShortcodeRenderer.php');
foreach(['FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE','data-fieldflow-dock-delivery-9de=',
'fieldflow-operative-dock-bootstrap-9de.js','fieldflow-operative-dock-bootstrap-9de.css',
'data-fieldflow-contract="fieldflow-operative-dock-bootstrap-recovery-9de-v1"',
'$dock9dc_enabled && !$dock9de_enabled',
'data-fieldflow-portal="operative"'] as $marker)
    $assert(str_contains($app,$marker),'shortcode '.$marker);
$responsive=$read('src/Front/ResponsiveAssets.php');
$assert(str_contains($responsive,'$legacyDockAllowed9de'),'9D6 and 9D7 do not compete');
$js=$read('assets/fieldflow-operative-dock-bootstrap-9de.js');
foreach(['win.FieldFlowOperativeDock9DE = {',"const ROOT = '#routespro-app[data-fieldflow-portal=", 
'listen(doc, \'touchmove\', onTouchMove','listen(doc, \'scroll\', onScroll, true)',
'watcher.observe(doc.documentElement','root.dataset.ffvDockControllerActive = \'operative-9de\'',
'ffv-operative-dock-collapsed-9de','bar.style.setProperty(\'transform\'','scheduleReconcile',
'function onPointerMove(','function onTouchMove('] as $marker)
    $assert(str_contains($js,$marker),'runtime '.$marker);
$assert(!str_contains($js,'#routespro-client-portal'),'Client isolation');
$assert(!preg_match('/\b(fetch|XMLHttpRequest|indexedDB|localStorage|sessionStorage)\s*\(/',$js),'no network/store');
$css=$read('assets/fieldflow-operative-dock-bootstrap-9de.css');
$assert(str_contains($css,'#routespro-app[data-fieldflow-portal="operative"]'),'CSS scope');
$assert(!str_contains($css,'#routespro-client-portal'),'CSS Client untouched');
$assert(str_contains($read('src/Support/ReleaseGate.php'),'checkOperativeDockBootstrapRecovery9DEContract'),'Release Gate');
$assert(str_contains($read('src/Support/ReleasePackageValidator.php'),'operative_dock_bootstrap_recovery_9de_ok'),'package validator');
$assert(str_contains($read('PHASE-9DE-OPERATIVE-DOCK-BOOTSTRAP-RECOVERY.md'),'fieldflow-operative-dock-bootstrap-recovery-9de-v1'),'documentation');
$phase=$read('src/Cloud/Phase6StableRelease.php');
$assert((bool)preg_match("/^\s*public const SOURCE_RELEASE = '([^']+)';/m",$phase,$from),'active source');
$assert((bool)preg_match("/^\s*public const STABLE_RELEASE = '([^']+)';/m",$phase,$to),'active successor');
$parts=explode('.',$from[1]);
$expected=count($parts)===3?$parts[0].'.'.$parts[1].'.'.((int)$parts[2]+1):'';
$assert($expected!=='' && $expected===$to[1] && $to[1]===$v[1],'N+1');
echo "OPERATIVE DOCK BOOTSTRAP 9DE PASS | autonomous loader, legacy isolation, read-only diagnostic, integrity, succession\n";
