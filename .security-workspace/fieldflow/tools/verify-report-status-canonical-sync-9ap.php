<?php
$root = dirname(__DIR__);
$required = [
    'src/Shortcodes.php' => [
        'fieldflow-report-status-canonical-sync-v1',
        'recentVisitStates',
        'fieldflowLive:true',
        'canonicalVisitStatus(stop.canonical_status||stop.status||\'pending\')',
        "document.addEventListener('fieldflow:visit-completed'",
        'cached&&remembered',
    ],
    'src/Rest/RoutesController.php' => [
        'CanonicalStateService::visit',
        "'canonical_status'",
        "'legacy_status'",
        "'status_source'",
        'canonical_visit_state',
        'front_stop_status_from_canonical',
    ],
    'PHASE-9AP-REPORT-STATUS-CANONICAL-SYNC.md' => [
        'fieldflow-report-status-canonical-sync-v1',
        '`SOURCE_RELEASE` is `2.2.559` and `STABLE_RELEASE` is `2.2.560`',
    ],
];
$missing = [];
foreach ($required as $file => $markers) {
    $path = $root . '/' . $file;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $missing[] = $file . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $file . ':' . $marker;
}
$pipeline = is_readable($root . '/src/Forms/SubmissionPipeline.php') ? (string)file_get_contents($root . '/src/Forms/SubmissionPipeline.php') : '';
if (strpos($pipeline, "public const BUILD = '2.2.485'") === false) $missing[] = 'submission:certified_build_changed';
$migrations = is_readable($root . '/src/Support/Migrations.php') ? (string)file_get_contents($root . '/src/Support/Migrations.php') : '';
if (strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") === false) $missing[] = 'schema:unexpected_change';
if ($missing) {
    fwrite(STDERR, "REPORT STATUS CANONICAL SYNC 9AP CONTRACT FAIL\n" . implode("\n", $missing) . "\n");
    exit(1);
}
echo "REPORT STATUS CANONICAL SYNC 9AP CONTRACT OK\n";
