<?php
$root=dirname(__DIR__);
$files=[
 'src/Forms/FormRenderer.php'=>['fieldflow-single-anchor-viewport-continuity-v4','fieldflow-hot-resume-dom-authority-v1',"rpRestoreFormViewport(form,'media-return')","rpRestoreFormViewport(form,'draft-cold-restore')","rpRestoreFormViewport(form,'hot-resume')"],
 'src/Shortcodes.php'=>['reportSingleAnchorViewportContract','reportSelectionAnchor=captureReportViewport()','options.viewportCheckpoint||captureReportViewport()'],
 'src/Mobile/WebViewBridge.php'=>['fieldflow-resume-geometry-preserving-v3'],
 'PHASE-9AH-SINGLE-ANCHOR-VIEWPORT-CONTINUITY.md'=>['fieldflow-single-anchor-viewport-continuity-v4','`SOURCE_RELEASE` is `2.2.551` and `STABLE_RELEASE` is `2.2.552`'],
];
$missing=[];
foreach($files as $relative=>$markers){$path=$root.'/'.$relative;$raw=is_readable($path)?(string)file_get_contents($path):'';if($raw===''){$missing[]=$relative.':file';continue;}foreach($markers as $marker)if(strpos($raw,$marker)===false)$missing[]=$relative.':'.$marker;}
$form=(string)file_get_contents($root.'/src/Forms/FormRenderer.php');
$bridge=(string)file_get_contents($root.'/src/Mobile/WebViewBridge.php');
if(strpos($form,'apply();window.requestAnimationFrame(apply);')!==false)$missing[]='double_restore';
if(strpos($form,"finally(function(){rpRestoreFormViewport(form,'media-selected')")!==false)$missing[]='late_media_restore';
if(preg_match("/routesproResumeDraft=function\(opts\)\{[\s\S]{0,900}?restoreDraft\(\);/",$form))$missing[]='hot_resume_replays_draft';
if(preg_match('/ff-app-resume-portal-hidden[^\n\r]*content-visibility\s*:\s*hidden/i',$bridge))$missing[]='resume_geometry_collapsed';
if($missing){fwrite(STDERR,'9AH FAILED: '.implode(', ',$missing).PHP_EOL);exit(1);}
echo "SINGLE ANCHOR VIEWPORT 9AH CONTRACT OK\n";
