<?php
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root.'/'.$relative;
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$forms = $read('src/Forms/Forms.php');
$daily = $read('src/Forms/DailySubmissionService.php');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$checks = [
    '9bd_marker' => strpos($forms,'Frentes reutiliza o valor final anterior') !== false,
    '9bz_runtime_preserved' => strpos($forms,'DailySubmissionService::new_visit_autofill_prefill') !== false
        && strpos($daily,'visit_memory_autofill_question_eligible') !== false
        && strpos($daily,"'image_upload', 'categorized_image_upload', 'file_upload'") !== false
        && strpos($daily,"'order_suggestion', 'product_matrix'") !== false,
    'successor_continuity' => strpos($phase6,"public const SOURCE_RELEASE = '2.2.597'") !== false
        && strpos($phase6,"public const STABLE_RELEASE = '2.2.598'") !== false,
    'gate_validator_parity' => strpos($gate,'checkCertificationMarkerContinuity9CAContract') !== false
        && strpos($validator,'certification_marker_continuity_9ca_ok') !== false,
];
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR,"CERTIFICATION MARKER CONTINUITY 9CA RUNTIME FAILED | ".implode(',',$failed)."\n");
    exit(1);
}
echo "CERTIFICATION MARKER CONTINUITY 9CA RUNTIME OK | 9bd_marker=1 9bz_runtime_preserved=1 successor_continuity=1 gate_validator_parity=1\n";
