<?php
/** Source-level verification of 9D8 fallback without WordPress or network. */
$dir=dirname(__DIR__).DIRECTORY_SEPARATOR;
$get=static function(string $name) use($dir):string {
    $path=$dir.str_replace('/',DIRECTORY_SEPARATOR,$name);
    if(!is_readable($path)) throw new RuntimeException('9D8 missing: '.$name);
    return (string)file_get_contents($path);
};
$check=static function(bool $ok,string $what):void {
    if(!$ok){fwrite(STDERR,"9D8 FAIL: $what\n");exit(1);}
};
$main=$get('fieldflow.php');
$check((bool)preg_match("/define\('FIELDFLOW_VERSION', '(2\.2\.\d+)'\);/",$main,$ver) && version_compare($ver[1],'2.2.633','>='),'candidate version');
$app=$get('src/Front/AppShortcodeRenderer.php');
foreach(['FIELDFLOW_OPERATIVE_DOCK_AUTHORITY_9D8','fieldflow-operative-dock-authority-9d8.css','fieldflow-operative-dock-authority-9d8.js','data-fieldflow-contract="fieldflow-operative-inline-dock-authority-9d8-v1"',"echo (string)file_get_contents(ROUTESPRO_PATH"] as $s)$check(str_contains($app,$s),'shortcode-owned '.$s);
$check(!str_contains($get('src/Front/ClientPortalShortcodeRenderer.php'),'fieldflow-operative-dock-authority-9d8'),'client unmodified');
$js=$get('assets/fieldflow-operative-dock-authority-9d8.js');
$css=$get('assets/fieldflow-operative-dock-authority-9d8.css');
foreach(['fieldflow-operative-inline-dock-authority-9d8-v1',"root.dataset.ffvDockAuthority='9d8'",'touchstart','touchmove','ffv-dock-collapsed-9d8','document.addEventListener(\'scroll\'','movement(-dy,\'touch\')'] as $s)$check(str_contains($js,$s),'gesture '.$s);
$check(!str_contains($js,'canScrollVertically'),'must not gate touches on a guessed scroll container');
$check(!preg_match('/\b(?:fetch|XMLHttpRequest|indexedDB|localStorage|sessionStorage)\s*\(/',$js),'no network/storage');
foreach(['#routespro-app.ffv-dock-authority-9d8','ffv-dock-collapsed-9d8','visibility:hidden!important','--ffv-dock-reserve','scroll-padding-bottom'] as $s)$check(str_contains($css,$s),'dock reserve '.$s);
$check(!str_contains($css,'#routespro-client-portal'),'no Client CSS');
foreach(['fieldflow-visual-consolidation-9d5.js','fieldflow-operative-dock-parity-9d6.js','fieldflow-operative-dock-overlay-9d7.js'] as $old)$check(str_contains($get('assets/'.$old),"root.dataset.ffvDockAuthority==='9d8'"),'old handler yields '.$old);
$phase=$get('src/Cloud/Phase6StableRelease.php');
$check(str_contains($phase,"public const SOURCE_RELEASE = '2.2.632';")&&str_contains($phase,"public const STABLE_RELEASE = '2.2.633';"),'successor chain');
echo "OPERATIVE DOCK AUTHORITY 9D8 PHP PASS | shortcode inline fallback, single operative owner, no Client mutation\n";
