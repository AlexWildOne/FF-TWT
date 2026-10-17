<?php
$root = dirname(__DIR__);
$requirements = [
    'src/Forms/DailySubmissionService.php' => ['find_previous_operational', 'SubmissionStatusPolicy::operationalSql()', 'visit_memory_question_eligible', 'project_id > 0'],
    'src/Forms/Forms.php' => ['DailySubmissionService::find_previous_operational', 'ProductCardex::normalise_fronts_submission_rows', 'Memória da loja', "'history_mode' => 'inline'", 'form_record_history_inline'],
    'src/Mobile/FormPayloadService.php' => ['DailySubmissionService::find_previous_operational', 'visit_memory_question_eligible', "'previous_visit'"],
    'src/Forms/ProductCardex.php' => ['normalise_fronts_submission_rows', "'before'", "'after'", "'qty'"],
    'src/Mobile/SubmissionService.php' => ['ProductCardex::normalise_fronts_submission_rows'],
    'src/Cloud/Phase6StableRelease.php' => ["SOURCE_RELEASE = '2.2.573'", "STABLE_RELEASE = '2.2.574'"],
    'PHASE-9BD-FORM-VISIT-MEMORY-FRONTS-CONTINUITY.md' => ['fieldflow-form-visit-memory-fronts-continuity-v1'],
];
$missing = [];
foreach ($requirements as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $missing[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $missing[] = $relative . ':' . $marker;
}
if ($missing) {
    fwrite(STDERR, "FORM VISIT MEMORY FRONTS 9BD CONTRACT FAILED\n" . implode("\n", $missing) . "\n");
    exit(1);
}
echo "FORM VISIT MEMORY FRONTS 9BD CONTRACT OK\n";
