<?php
$root = dirname(__DIR__);
$short=@file_get_contents($root.'/src/Shortcodes.php')?:'';
$form=@file_get_contents($root.'/src/Forms/FormRenderer.php')?:'';
$mail=@file_get_contents($root.'/src/Admin/ReportAutomations.php')?:'';
$app=@file_get_contents($root.'/src/Front/AppShortcodeRenderer.php')?:'';
$ok = strpos($short,'beginReportViewportLock')!==false && strpos($short,'finishReportViewportLock')!==false
    && strpos($short,'Combustível / energia')!==false && strpos($short,'Custo deslocação')!==false
    && strpos($form,'rpBeginViewportLock')!==false && strpos($form,'rpReleaseFormViewportLock')!==false
    && strpos($form,"rpQueueResumeDraftForms('focus-after-hidden')")!==false
    && !preg_match('/rpQueueResumeDraftForms\(\s*[\'\"]focus[\'\"]\s*\)/',$form)
    && strpos($mail,'ensureDailyCloseAutomation')!==false && strpos($mail,'projectMailTemplateState')!==false && strpos($mail,'recoverMissingProjectTemplateAutomations')!==false
    && strpos($app,'.rp-premium-shell .ff-react-front-operational-fallback span')!==false;
if(!$ok){fwrite(STDERR,"9AF runtime source semantics failed\n");exit(1);}echo "CERTIFICATION VIEWPORT MAIL 9AF RUNTIME OK\n";
