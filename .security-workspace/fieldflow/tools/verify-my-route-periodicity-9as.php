<?php
$root = dirname(__DIR__);
$checks = [
    'service' => [
        'file' => 'src/Services/PeriodicityProgressService.php',
        'needles' => ["CONTRACT = 'fieldflow-my-route-periodicity-control-v1'", "SubmissionStatusPolicy::operationalSql('fs')", "'planned'", "'no_visit'", 'periodicityLabel'],
    ],
    'rest' => [
        'file' => 'src/Rest/RoutesController.php',
        'needles' => ["'/my-periodicity'", 'PeriodicityProgressService::forUser', 'get_current_user_id()'],
    ],
    'front' => [
        'file' => 'src/Shortcodes.php',
        'needles' => ['rp-periodicity', 'Cobertura do mês', 'data-periodicity-filter="attention"', 'loadPeriodicity', 'forcePeriodicity'],
    ],
    'phase6_continuity' => [
        'file' => 'src/Cloud/Phase6StableRelease.php',
        'needles' => ["SOURCE_RELEASE = '2.2.562'", "STABLE_RELEASE = '2.2.563'"],
    ],
    'release_gate' => [
        'file' => 'src/Support/ReleaseGate.php',
        'needles' => ["MY_ROUTE_PERIODICITY_9AS_CONTRACT = 'fieldflow-my-route-periodicity-control-v1'", 'checkMyRoutePeriodicity9ASContract'],
    ],
    'package_validator' => [
        'file' => 'src/Support/ReleasePackageValidator.php',
        'needles' => ['my_route_periodicity_9as_ok', 'fieldflow-my-route-periodicity-control-v1'],
    ],
    'release_manager' => [
        'file' => 'src/Admin/ReleaseManager.php',
        'needles' => ['My Route Periodicity Control 9AS', "packageResult['my_route_periodicity_9as_ok']"],
    ],
    'schema_frozen' => [
        'file' => 'src/Support/Migrations.php',
        'needles' => ["LATEST_SCHEMA_VERSION = '2.2.529'"],
    ],
    'submission_frozen' => [
        'file' => 'src/Forms/SubmissionPipeline.php',
        'needles' => ["public const BUILD = '2.2.485'"],
    ],
    'media_isolation_preserved' => [
        'file' => 'src/Support/Plugin.php',
        'needles' => ["WORDPRESS_MEDIA_ISOLATION_CONTRACT = 'fieldflow-wordpress-media-isolation-v1'", 'isWordPressMediaProcessingRequest'],
    ],
];
$failed = [];
foreach ($checks as $name => $spec) {
    $path = $root . '/' . $spec['file'];
    $raw = is_readable($path) ? file_get_contents($path) : '';
    if ($raw === '') { $failed[] = $name . ':file'; continue; }
    foreach ($spec['needles'] as $needle) if (strpos($raw, $needle) === false) $failed[] = $name . ':' . $needle;
}
if ($failed) {
    fwrite(STDERR, "MY ROUTE PERIODICITY 9AS CONTRACT FAILED\n" . implode("\n", $failed) . "\n");
    exit(1);
}
echo "MY ROUTE PERIODICITY 9AS CONTRACT OK\n";
$pluginRaw = file_get_contents($root . '/fieldflow.php');
$release = '';
if (preg_match("/define\('FIELDFLOW_VERSION',\s*'([^']+)'\)/", (string)$pluginRaw, $m)) $release = (string)$m[1];
if ($release === '' || version_compare($release, '2.2.563', '<')) {
    fwrite(STDERR, "MY ROUTE PERIODICITY 9AS CONTRACT FAILED\nrelease_identity:requires>=2.2.563\n");
    exit(1);
}
echo "release=" . $release . "\n";
echo "contract=fieldflow-my-route-periodicity-control-v1\n";
echo "surface=minha_rota\n";
echo "mode=read_only_monthly_coverage\n";
