<?php
/** Source-only check. Fail if old controllers are reintroduced alongside Client dock. */
$dir=dirname(__DIR__).'/';
$get=static function(string $name)use($dir):string {return (string)file_get_contents($dir.$name);};
$check=static function(bool $ok,string $message):void {
 if(!$ok){fwrite(STDERR,"9D9 FAIL: $message\n");exit(1);}
};
$app=$get('src/Front/AppShortcodeRenderer.php');
$client=$get('src/Front/ClientPortalShortcodeRenderer.php');
$shared=$get('assets/fieldflow-visual-consolidation-9d5.js');
$responsive=$get('src/Front/ResponsiveAssets.php');
$main = $get('fieldflow.php');
$check((bool)preg_match("/define\('FIELDFLOW_VERSION', '([^']+)'\)/", $main, $mm) && version_compare($mm[1], '2.2.634', '>='), '9D9 is present in a successor');
$check(str_contains($app,"file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-visual-consolidation-9d5.js')"),'reuse exact Client JS');
$check(str_contains($app,'data-fieldflow-dock-controller='),'operative selection marker');
$check(str_contains($app,'$dock9d8_enabled = !$shared_dock_enabled'),'old 9D8 disabled');
$check(str_contains($app,'FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9'),'dedicated kill switch');
$check(str_contains($responsive,'if ($legacyOperativeDock'),'9D6 and 9D7 are fallback only');
$check(substr_count($responsive,'if ($legacyOperativeDock')===2,'both legacy layers off by default');
$check(str_contains($shared,"dock.classList.add('ffv-dock-hidden')")&&str_contains($shared,"dock.classList.remove('ffv-dock-hidden')"),'exact Client show/hide controller');
$check(!str_contains($client,'fieldflow-operative-client-dock-parity-9d9-v1'),'Client renderer untouched');
$phase = $get('src/Cloud/Phase6StableRelease.php');
$check((bool)preg_match("/public const SOURCE_RELEASE = '([^']+)'/", $phase, $from), 'source release');
$check((bool)preg_match("/public const STABLE_RELEASE = '([^']+)'/", $phase, $to) && $to[1] === $mm[1], 'active stable candidate');
echo "OPERATIVE CLIENT DOCK PARITY 9D9 PASS\n";
