<?php
/** 9DC: isolated scroll owner, 9D9 continuity and no business mutation. */
$root=dirname(__DIR__).DIRECTORY_SEPARATOR;
$read=static function(string $relative)use($root):string{
    $file=$root.$relative;
    if(!is_readable($file)){fwrite(STDERR,"9DC missing: $relative\n");exit(1);}
    return (string)file_get_contents($file);
};
$assert=static function(bool $ok,string $description):void{
    if(!$ok){fwrite(STDERR,"9DC FAIL: $description\n");exit(1);}
};
$plugin=$read('fieldflow.php');
$assert((bool)preg_match("/define\('FIELDFLOW_VERSION', '([^']+)'\)/",$plugin,$m)
    && version_compare($m[1],'2.2.637','>='),'minimum version');
$app=$read('src/Front/AppShortcodeRenderer.php');
$client=$read('src/Front/ClientPortalShortcodeRenderer.php');
$js=$read('assets/fieldflow-operative-dock-scroll-9dc.js');
$css=$read('assets/fieldflow-operative-dock-scroll-9dc.css');
foreach([
    '$dock9dc_enabled = $shared_dock_enabled',
    'FIELDFLOW_OPERATIVE_SCROLL_AUTHORITY_9DC',
    'fieldflow-operative-dock-scroll-9dc.css',
    'fieldflow-operative-dock-scroll-9dc.js',
    'data-fieldflow-contract="fieldflow-operative-dock-scroll-authority-9dc-v1"',
    '$dock9d8_enabled = !$shared_dock_enabled',
    "file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-visual-consolidation-9d5.js')",
] as $needle)$assert(str_contains($app,$needle),'shortcode '.$needle);
foreach([
    "const CONTRACT='fieldflow-operative-dock-scroll-authority-9dc-v1'",
    "const ROOT='#routespro-app[data-fieldflow-portal=\"operative\"]'",
    "document.addEventListener('scroll',onDocumentScroll,true)",
    "document.addEventListener('touchstart',onTouchStart",
    "document.addEventListener('touchmove',onTouchMove",
    "document.addEventListener('fieldflow:responsive-ready',boot)",
    'ffv-operative-dock-collapsed-9dc',
    'root.dataset.ffvDockControllerActive',
    'positions.set(host',
    'syncDock()',
    'fitActive()',
    'document.querySelectorAll(ROOT).forEach(enhance)',
] as $needle)$assert(str_contains($js,$needle),'scroll owner '.$needle);
$assert(!str_contains($css,'#routespro-client-portal'),'CSS scoped to operative');
$assert(!str_contains($client,'fieldflow-operative-dock-scroll-9dc'),'Client untouched');
$assert(!preg_match('/\b(?:fetch|XMLHttpRequest|localStorage|indexedDB|sessionStorage)\s*\(/',$js),'no network/storage');
foreach(['position:fixed!important','ffv-operative-dock-collapsed-9dc','visibility:hidden!important',
    'scroll-padding-bottom','env(safe-area-inset-bottom,0px)','scroll-snap-align:center'] as $needle)
    $assert(str_contains($css,$needle),'mobile reserve '.$needle);
$gate=$read('src/Support/ReleaseGate.php');
$validator=$read('src/Support/ReleasePackageValidator.php');
$assert(str_contains($gate,'checkOperativeDockScrollAuthority9DCContract'),'Release Gate');
$assert(str_contains($validator,'operative_dock_scroll_authority_9dc_ok'),'Package Validator');
$assert(str_contains($read('assets/fieldflow-visual-consolidation-9d5.js'),"root.dataset.ffvDockAuthority==='9d8'"),'9D5 yields to 9DC sentinel');
$phase=$read('src/Cloud/Phase6StableRelease.php');
$assert((bool)preg_match("/^\s*public const SOURCE_RELEASE = '([^']+)';/m",$phase,$source),'active predecessor');
$assert((bool)preg_match("/^\s*public const STABLE_RELEASE = '([^']+)';/m",$phase,$stable),'active successor');
$parts=explode('.',$source[1]);
$next=count($parts)===3?($parts[0].'.'.$parts[1].'.'.((int)$parts[2]+1)):'';
$assert($next!=='' && hash_equals($next,$stable[1]) && hash_equals($stable[1],$m[1]),'dynamic N+1 authority');
echo "OPERATIVE DOCK SCROLL 9DC PASS | nested scrolling, owner isolation, inline delivery, fallback, reserve, no business data\n";
