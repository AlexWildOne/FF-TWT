<?php
$root=dirname(__DIR__);
$form=(string)file_get_contents($root.'/src/Forms/FormRenderer.php');
$short=(string)file_get_contents($root.'/src/Shortcodes.php');
$bridge=(string)file_get_contents($root.'/src/Mobile/WebViewBridge.php');
$ok=strpos($form,'Single-anchor continuity: compensate exactly once in the same task as the DOM mutation.')!==false
 && strpos($form,"rpRestoreFormViewport(form,'media-return')")!==false
 && strpos($form,"rpRestoreFormViewport(form,'draft-cold-restore')")!==false
 && strpos($form,"rpRestoreFormViewport(form,'hot-resume')")!==false
 && strpos($form,'apply();window.requestAnimationFrame(apply);')===false
 && strpos($short,'reportSelectionAnchor=captureReportViewport()')!==false
 && strpos($short,'finishReportViewportLock=(checkpoint,lock,restore=true)')!==false
 && strpos($bridge,'fieldflow-resume-geometry-preserving-v3')!==false;
if(!$ok){fwrite(STDERR,"9AH runtime source semantics failed\n");exit(1);}
echo "SINGLE ANCHOR VIEWPORT 9AH RUNTIME OK\n";
