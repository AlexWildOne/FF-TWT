<?php
$root = dirname(__DIR__);
$checks = [
    'src/Shortcodes.php' => [
        'fieldflow-passive-viewport-continuity-v3','reportPassiveViewportContract','Passive continuity deliberately performs no scrollTop/window.scrollTo write.',
        'fieldflow:form-external-handoff','fieldflow:form-viewport-unlocked',
    ],
    'src/Forms/FormRenderer.php' => [
        'fieldflow-passive-viewport-continuity-v3','Do not write scrollTop. The WebView/browser owns scrolling in 2.2.551.',
        'position:fixed!important;left:-10000px!important;top:0!important',"rpQueueResumeDraftForms('focus-after-hidden')",
    ],
    'src/Admin/ReportAutomations.php' => ['if ($automationType === \'daily_close\' && isset($ranges[\'daily\'])) $attachmentPeriodKeys = [\'daily\'];',"'audience' => 'manager'",'Canonical report data uses full operational metric visibility','Excel and PDF are part of the order-suggestion delivery contract','orderSuggestionMailEligibility'],
    'src/Shopping/ShoppingService.php' => ['Shopping delivery contract: every verification email carries both canonical attachments.','$requestedFormats = [\'xlsx\',\'pdf\'];'],
    'src/Services/MailDispatchService.php' => ['private static function attachmentPaths','$seen = [];','realpath($path) ?: $path','isset($seen[$key])'],
    'src/Support/ReleasePackageValidator.php' => ['mail_flow_viewport_9ag_ok','fieldflow-mail-flow-finalization-true-viewport-freeze-v1'],
];
$missing=[];
foreach($checks as $rel=>$markers){$p=$root.'/'.$rel;$c=is_readable($p)?file_get_contents($p):'';if($c===''){$missing[]=$rel.':file';continue;}foreach($markers as $m)if(strpos($c,$m)===false)$missing[]=$rel.':'.$m;}
$short=@file_get_contents($root.'/src/Shortcodes.php')?:'';
$form=@file_get_contents($root.'/src/Forms/FormRenderer.php')?:'';
if(strpos($short,"root.style.position='fixed';")!==false)$missing[]='Shortcodes:report_shell_fixed_lock';
if(strpos($short,'reportViewportGuardRaf=window.requestAnimationFrame')!==false)$missing[]='Shortcodes:continuous_scroll_guard';
if(strpos($form,'rpRequestFormVisualFreeze(form,')!==false)$missing[]='FormRenderer:visual_freeze_runtime';
if(strpos($form,"document.body.style.position==='fixed'")!==false||strpos($form,"document.body.style.position='fixed'")!==false)$missing[]='FormRenderer:body_fixed_lock';
if(preg_match('/rpQueueResumeDraftForms\(\s*[\'\"]focus[\'\"]\s*\)/',$form))$missing[]='FormRenderer:bare_focus_restore';
if($missing){fwrite(STDERR,'9AG FAILED: '.implode(', ',$missing).PHP_EOL);exit(1);}echo "MAIL FLOW VIEWPORT 9AG CONTRACT OK\n";
