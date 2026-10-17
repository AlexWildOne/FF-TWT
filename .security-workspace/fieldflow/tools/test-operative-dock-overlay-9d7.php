<?php
/** 9D7 isolated source contract, no WordPress, database, network or mutation. */
$root=dirname(__DIR__).DIRECTORY_SEPARATOR;
$read=static function(string $relative)use($root):string{
    $filename=$root.str_replace('/',DIRECTORY_SEPARATOR,$relative);
    if(!is_file($filename))throw new RuntimeException('9D7 missing: '.$relative);
    return (string)file_get_contents($filename);
};
$check=static function(bool $pass,string $name):void{
    if(!$pass){fwrite(STDERR,'9D7 FAIL: '.$name."\n");exit(1);}
};
$main=$read('fieldflow.php');
$check((bool)preg_match("/define\('FIELDFLOW_VERSION', '([0-9.]+)'\)/",$main,$match) && version_compare($match[1],'2.2.632','>='),'candidate identity');
$phase=$read('src/Cloud/Phase6StableRelease.php');
$check(str_contains($phase,"public const SOURCE_RELEASE = '2.2.631';")&&str_contains($phase,"public const STABLE_RELEASE = '2.2.632';"),'release chain');
$js=$read('assets/fieldflow-operative-dock-overlay-9d7.js');
$css=$read('assets/fieldflow-operative-dock-overlay-9d7.css');
$enqueue=$read('src/Front/ResponsiveAssets.php');
foreach(['fieldflow-operative-dock-overlay-9d7-v1','touchstart','touchmove','onDocumentScroll','canScrollVertically','--ffv-dock-height','ffv-dock-hidden','ffvDockAuthority','ff-keyboard-open'] as $term)$check(str_contains($js,$term),'JS '.$term);
$check(!preg_match('/\b(?:fetch|XMLHttpRequest|indexedDB|localStorage|sessionStorage)\s*\(/',$js),'pure presentation');
foreach(['#routespro-app.ffv-visual-9d5.ffv-operative-overlay-9d7','--ffv-dock-reserve','padding-bottom','scroll-padding-bottom','ffv-dock-hidden'] as $term)$check(str_contains($css,$term),'CSS '.$term);
$check(!str_contains($css,'#routespro-client-portal'),'client unaffected');
foreach(['FIELDFLOW_OPERATIVE_OVERLAY_9D7','fieldflow-operative-dock-overlay-9d7.css','fieldflow-operative-dock-overlay-9d7.js','fieldflow-frontend-core-'.$match[1].'.js','fieldflow-offline-runtime-'.$match[1].'.js'] as $term)$check(str_contains($enqueue,$term),'enqueue '.$term);
$check(str_contains($read('assets/fieldflow-visual-consolidation-9d5.js'),"root.dataset.ffvDockAuthority==='9d7'"),'9D5 handoff');
$check(str_contains($read('assets/fieldflow-operative-dock-parity-9d6.js'),"root.dataset.ffvDockAuthority==='9d7'"),'9D6 handoff');
foreach(['#rpcp-from','#rpcp-to'] as $term)$check(str_contains($read('assets/fieldflow-visual-consolidation-9d5.js'),$term),'client date scope '.$term);
echo "OPERATIVE DOCK OVERLAY 9D7 PHP PASS | WebView gesture | scroll reserve | preserved 9D6/9D5\n";
