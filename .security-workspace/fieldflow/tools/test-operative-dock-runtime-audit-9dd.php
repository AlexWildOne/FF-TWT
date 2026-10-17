<?php
/** 9DD is an opt-in diagnostic, not a claim of corrected mobile UI. */
$root=dirname(__DIR__);
$errors=[];
$check=static function(bool $ok,string $label)use(&$errors){if(!$ok)$errors[]=$label;};
$read=static function(string $f)use($root){return (string)@file_get_contents($root.'/'.$f);};
$php=$read('src/Front/AppShortcodeRenderer.php');
$js=$read('assets/fieldflow-operative-dock-audit-9dd.js');
$css=$read('assets/fieldflow-operative-dock-audit-9dd.css');
$check(str_contains($php,'ff_dock_diag') && str_contains($php,'$dock_diag_requested'), 'php opt-in');
$check(str_contains($php,'if ($dock_diag_requested): ?>') && str_contains($php,'PHP <?php echo esc_html(FIELDFLOW_VERSION)'), 'visible server marker');
$check(str_contains($php,'if ($dock_diag_requested && is_readable'), 'diagnostic assets conditional');
$check(str_contains($php,'fieldflow-operative-dock-audit-9dd.js')&&str_contains($php,'fieldflow-operative-dock-audit-9dd.css'), 'asset loads');
$check(str_contains($js,'fieldflow-operative-dock-real-runtime-audit-9dd-v1'), 'JS contract');
$check(str_contains($js,"document.addEventListener('scroll'")&&str_contains($js,"document.addEventListener('touchmove'"), 'event evidence');
$check(str_contains($js,"dockState:") && str_contains($js,'getComputedStyle') && str_contains($js,'bottomElements:bottom()'), 'DOM evidence');
$check(str_contains($js,"dock.style")===false && str_contains($js,"el.style.setProperty('transform'"), 'manual test only');
$check(str_contains($js,"if(button.dataset.ffddAction==='toggle')force()"),'user action gate');
$check(str_contains($js,"window.addEventListener('pagehide',restore)"),'recovery on exit');
$check(!str_contains($js,'fetch(')&&!str_contains($js,'XMLHttpRequest')&&!str_contains($js,'localStorage')&&!str_contains($js,'sessionStorage'),'no telemetry or storage');
$check(str_contains($css,'#ff-dock-audit-9dd')&&str_contains($css,'z-index:2147483646'),'opt-in overlay CSS');
$check(str_contains($read('src/Support/ReleaseGate.php'),'checkOperativeDockRealRuntimeAudit9DDContract()'),'gate active');
$check(str_contains($read('src/Support/ReleasePackageValidator.php'),'operative_dock_real_runtime_audit_9dd_ok'),'package gate active');
foreach(['assets/fieldflow-operative-dock-scroll-9dc.js','assets/fieldflow-operative-dock-scroll-9dc.css','assets/fieldflow-visual-consolidation-9d5.js','src/Front/ClientPortalShortcodeRenderer.php'] as $file){
    $check(is_file($root.'/'.$file),'preserve '.$file);
}
if($errors){fwrite(STDERR,'OPERATIVE DOCK REAL RUNTIME AUDIT 9DD FAIL: '.implode(', ',$errors)."\n");exit(1);}
echo "OPERATIVE DOCK REAL RUNTIME AUDIT 9DD PASS\n";
