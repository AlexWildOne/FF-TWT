<?php
$root = dirname(__DIR__);
$checks = [
    'src/Shortcodes.php' => ['fieldflow-form-viewport-lock-v1','beginReportViewportLock','finishReportViewportLock','Combustível / energia','Custo deslocação','blur({preventScroll:true})'],
    'src/Forms/FormRenderer.php' => ['fieldflow-form-viewport-lock-v1','rpBeginViewportLock','rpReleaseFormViewportLock','media-handoff','media-selected',"rpQueueResumeDraftForms('focus-after-hidden')"],
    'src/Front/AppShortcodeRenderer.php' => ['ff-react-front-operational-fallback','.rp-premium-shell .ff-react-front-operational-fallback span','Operação pronta','Navegação clássica disponível'],
    'src/Admin/ReportAutomations.php' => ['projectMailTemplateState','ensureDailyCloseAutomation','recoverMissingProjectTemplateAutomations','Template do projeto · recuperável','Template canónico · recuperável','Deliberately paused rows are never reactivated here.'],
    'src/Support/ReleasePackageValidator.php' => ['certification_viewport_mail_9af_ok','fieldflow-certification-viewport-lock-mail-template-recovery-v1'],
];
$missing=[];
foreach($checks as $rel=>$markers){$p=$root.'/'.$rel;$c=is_readable($p)?file_get_contents($p):'';if($c===''){$missing[]=$rel.':file';continue;}foreach($markers as $m)if(strpos($c,$m)===false)$missing[]=$rel.':'.$m;}
$form=@file_get_contents($root.'/src/Forms/FormRenderer.php')?:'';
if(preg_match('/rpQueueResumeDraftForms\(\s*[\'\"]focus[\'\"]\s*\)/',$form))$missing[]='FormRenderer:bare_focus_restore';
if($missing){fwrite(STDERR,"9AF FAILED: ".implode(', ',$missing).PHP_EOL);exit(1);}echo "CERTIFICATION VIEWPORT MAIL 9AF CONTRACT OK\n";
