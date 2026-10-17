<?php
/** 9D6 isolated source/contract regression. No WordPress, network or database. */
$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$read = static function (string $relative) use ($root): string {
    $path = $root . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) throw new RuntimeException('9D6 missing: ' . $relative);
    return (string)file_get_contents($path);
};
$check = static function (bool $pass, string $why): void {
    if (!$pass) {fwrite(STDERR, '9D6 FAIL: ' . $why . "\n");exit(1);}
};
$main=$read('fieldflow.php');
$check((bool)preg_match("/define\('FIELDFLOW_VERSION', '([0-9.]+)'\)/",$main,$match) && version_compare($match[1],'2.2.631','>='),'successor identity');
$check(str_contains($read('src/Cloud/Phase6StableRelease.php'),"public const SOURCE_RELEASE = '2.2.630';"),'certified source');
$check(str_contains($read('src/Cloud/Phase6StableRelease.php'),"public const STABLE_RELEASE = '2.2.631';"),'candidate version');
$js=$read('assets/fieldflow-operative-dock-parity-9d6.js');
$css=$read('assets/fieldflow-operative-dock-parity-9d6.css');
$legacy=$read('assets/fieldflow-visual-consolidation-9d5.js');
$assets=$read('src/Front/ResponsiveAssets.php');
foreach (['fieldflow-operative-dock-parity-9d6-v1',"document.addEventListener('scroll',onDocumentScroll,true)",'ffvDockAuthority', 'WeakMap', 'ff-keyboard-open', 'scrollHeight>source.clientHeight+3'] as $marker) $check(str_contains($js,$marker),'operative capture '.$marker);
$check(!preg_match('/\b(?:fetch|XMLHttpRequest|indexedDB|localStorage|sessionStorage)\s*\(/',$js), 'no network/storage');
$check(str_contains($legacy,"root.dataset.ffvDockAuthority==='9d6'"),'single operative dock authority');
$check(str_contains($css,'#routespro-app.ffv-visual-9d5.ffv-operative-parity-9d6'),'CSS scoped operative');
$check(!str_contains($css,'#routespro-client-portal'), 'client unaffected');
$check(str_contains($css,'var(--ffv-dock-height,66px)'),'form scroll reserve');
foreach (['FIELDFLOW_OPERATIVE_PARITY_9D6','fieldflow-operative-dock-parity-9d6.css','fieldflow-operative-dock-parity-9d6.js','fieldflow-frontend-core-'.$match[1].'.js','fieldflow-offline-runtime-'.$match[1].'.js'] as $marker) $check(str_contains($assets,$marker),'enqueue '.$marker);
$client=$read('src/Front/ClientPortalShortcodeRenderer.php');
$check(str_contains($client,'function statusLabel(status)'), 'display-only labels');
$check(str_contains($client,'data-stop-status="done"'),'preserved business status');
foreach(['#rpcp-from','#rpcp-to','#rpcp-refresh'] as $field) $check(str_contains($read('assets/fieldflow-visual-consolidation-9d5.js'),$field),'single client scope authority '.$field);
echo "OPERATIVE DOCK PARITY 9D6 PHP PASS | nested vertical scroll | client unchanged | no API changes\n";
