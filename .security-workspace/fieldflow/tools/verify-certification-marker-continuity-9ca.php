<?php
$root = dirname(__DIR__);
$requirements = [
    'fieldflow.php' => ['FIELDFLOW_VERSION'],
    'src/Forms/Forms.php' => ['Frentes reutiliza o valor final anterior','DailySubmissionService::new_visit_autofill_prefill','9BD historical certification marker only'],
    'src/Forms/DailySubmissionService.php' => ['visit_memory_autofill_question_eligible','new_visit_autofill_prefill'],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.597'","public const STABLE_RELEASE = '2.2.598'","// public const SOURCE_RELEASE = '2.2.596'","// public const STABLE_RELEASE = '2.2.597'"],
    'src/Support/ReleaseGate.php' => ['CERTIFICATION_MARKER_CONTINUITY_9CA_CONTRACT','checkCertificationMarkerContinuity9CAContract'],
    'src/Support/ReleasePackageValidator.php' => ['certification_marker_continuity_9ca_ok','Certification Marker Continuity 9CA'],
    'src/Admin/ReleaseManager.php' => ["packageResult['certification_marker_continuity_9ca_ok']"],
    'PHASE-9CA-CERTIFICATION-MARKER-CONTINUITY.md' => ['fieldflow-certification-marker-continuity-9ca-v1','`SOURCE_RELEASE` is `2.2.597` and `STABLE_RELEASE` is `2.2.598`','No database schema changes','No FlutterFlow source modification is required'],
];
$missing = [];
foreach ($requirements as $relative => $markers) {
    $raw = is_readable($root.'/'.$relative) ? (string)file_get_contents($root.'/'.$relative) : '';
    if ($raw === '') { $missing[] = $relative.':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw,$marker) === false) $missing[] = $relative.':'.$marker;
}
$plugin = is_readable($root.'/fieldflow.php') ? (string)file_get_contents($root.'/fieldflow.php') : '';
if (!preg_match("/FIELDFLOW_VERSION',\s*'([^']+)'/", $plugin, $m) || version_compare((string)$m[1], '2.2.598', '<')) $missing[] = 'fieldflow.php:successor-version';
if ($missing) {
    fwrite(STDERR,"CERTIFICATION MARKER CONTINUITY 9CA CONTRACT FAILED\n".implode("\n",$missing)."\n");
    exit(1);
}
echo "CERTIFICATION MARKER CONTINUITY 9CA CONTRACT OK successor=>=2.2.598\n";
