<?php
$root=dirname(__DIR__);
$read=static function(string $p)use($root):string{return is_readable($root.'/'.$p)?(string)file_get_contents($root.'/'.$p):'';};
$fail=[];
$assert=static function(bool $ok,string $name)use(&$fail):void{if(!$ok)$fail[]=$name;};
$app=$read('src/Front/AppShortcodeRenderer.php');
$form=$read('src/Forms/FormRenderer.php');
$bridge=$read('src/Mobile/WebViewBridge.php');
$assert(strpos($app,'const resumeTtlMs=12*60*60*1000')!==false,'bounded_ttl');
$assert(strpos($app,"const resumeStorageKey='fieldflow:resume:operational:v1:'+resumeScope")!==false,'user_scoped_storage');
$assert(strpos($app,"panelFromQuery||panelFromHash||panelFromResume||panelFromStorage")!==false,'explicit_navigation_precedence');
$assert(strpos($app,"if(panelFromResume==='report'&&resumeCheckpoint&&resumeCheckpoint.reportVisit)pendingReportVisit")!==false,'report_visit_boot_restore');
$assert(strpos($app,'reportNeedsSelection')!==false&&strpos($app,'schedulePendingReportVisit')!==false&&strpos($app,'restoreResumeContext')!==false,'report_visit_resume_restore');
$assert(substr_count($app,'snapshotResumeContext(')>=4,'lifecycle_checkpointing');
$assert(strpos($form,'rpCheckpointExternalMediaHandoff($(this),source)')!==false,'camera_trigger_checkpoint');
$assert(strpos($form,"contract:'fieldflow-field-resume-navigation-continuity-v1'")!==false,'handoff_contract');
$assert(strpos($form,'step_index:parseInt(wizard&&wizard.dataset?wizard.dataset.rpCurrentStep')!==false,'step_checkpoint');
$assert(strpos($bridge,'localStorage.removeItem(key)')!==false&&strpos($bridge,'fieldflow:last-report-visit')!==false,'logout_removes_durable_context');
if($fail){fwrite(STDERR,'FAIL: '.implode(',',$fail)."\n");exit(1);}echo "FIELD RESUME NAVIGATION 9AA RUNTIME OK\n";
