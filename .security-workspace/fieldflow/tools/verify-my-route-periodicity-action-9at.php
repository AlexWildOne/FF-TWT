<?php
$root = dirname(__DIR__);
$plugin = (string)@file_get_contents($root . '/fieldflow.php');
$release = '';
if (preg_match("/define\\(\\s*['\"]FIELDFLOW_VERSION['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $plugin, $m)) $release = (string)$m[1];
$checks = [
    'src/Services/PeriodicityProgressService.php' => ["ACTION_UX_CONTRACT = 'fieldflow-my-route-periodicity-action-ux-v1'", 'businessDaysRemaining', 'riskProfile', "'risk_level'", "'business_days_remaining'"],
    'src/Shortcodes.php' => ['Só falta cumprir', 'fieldflow:planner-intent:v1', 'fieldflow:open-planner', 'rp-periodicity-risk', 'business_days_remaining', 'fieldflow:self-routes-created'],
    'src/Front/AppShortcodeRenderer.php' => ['fieldflow-my-route-periodicity-action-ux-v1', 'fieldflow:open-planner', 'fieldflow:planner-focus-store', 'fieldflow:planner-intent:v1'],
    'src/Front/SelfRoutePlannerShortcodeRenderer.php' => ['fieldflow:planner-focus-store', 'applyPlannerIntent', 'plannerIntentStorageKey', 'intent-focus'],
    'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.563'", "STABLE_RELEASE = '2.2.564'"],
    'src/Support/ReleaseGate.php' => ["MY_ROUTE_PERIODICITY_ACTION_9AT_CONTRACT = 'fieldflow-my-route-periodicity-action-ux-v1'", 'checkMyRoutePeriodicityAction9ATContract'],
    'src/Support/ReleasePackageValidator.php' => ['my_route_periodicity_action_9at_ok', 'fieldflow-my-route-periodicity-action-ux-v1'],
    'src/Admin/ReleaseManager.php' => ['My Route Periodicity Action UX 9AT', "packageResult['my_route_periodicity_action_9at_ok']"],
    'PHASE-9AT-MY-ROUTE-PERIODICITY-ACTION-UX.md' => ['fieldflow-my-route-periodicity-action-ux-v1', '`SOURCE_RELEASE` is `2.2.563` and `STABLE_RELEASE` is `2.2.564`'],
];
$failed=[];
if ($release === '' || version_compare($release, '2.2.564', '<')) $failed[]='fieldflow.php:release below 2.2.564';
foreach($checks as $rel=>$needles){$path=$root.'/'.$rel;$raw=is_readable($path)?(string)file_get_contents($path):'';if($raw===''){$failed[]=$rel.':missing';continue;}foreach($needles as $needle){if(strpos($raw,$needle)===false)$failed[]=$rel.':'.$needle;}}
$migrations=(string)@file_get_contents($root.'/src/Support/Migrations.php');
$submission=(string)@file_get_contents($root.'/src/Forms/SubmissionPipeline.php');
if(strpos($migrations,"LATEST_SCHEMA_VERSION = '2.2.529'")===false)$failed[]='schema changed';
if(strpos($submission,"public const BUILD = '2.2.485'")===false)$failed[]='submission build changed';
if($failed){fwrite(STDERR,"MY ROUTE PERIODICITY ACTION 9AT CONTRACT FAILED\n".implode("\n",$failed)."\n");exit(1);}
echo "MY ROUTE PERIODICITY ACTION 9AT CONTRACT OK\n";
echo "release={$release}\n";
echo "contract=fieldflow-my-route-periodicity-action-ux-v1\n";
