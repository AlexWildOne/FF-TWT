<?php
// 9CV source contract and continuity verifier. No WordPress or DB required.
$base = dirname(__DIR__);
$needed = [
    'fieldflow.php' => ["Version: 2.2.619", "define('FIELDFLOW_VERSION', '2.2.619')"],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.618'", "public const STABLE_RELEASE = '2.2.619'"],
    'src/Shortcodes.php' => ['fieldflow-report-context-recovery-9cv-v1','isReportDatabaseError=raw=>','id="rpf-retry"','reportRetryLastAt<4000', 'matchesCachedContext=cached=>', "if(!sameLiveForm)els.slot.innerHTML=''"],
    'src/Forms/Forms.php' => ['fieldflow-report-context-recovery-9cv-v1','fieldflow_report_context_unavailable', '], 503);'],
    'src/Support/ReleaseGate.php' => ['REPORT_CONTEXT_RECOVERY_9CV_CONTRACT','checkReportContextRecovery9CVContract'],
    'src/Support/ReleasePackageValidator.php'=>['report_context_recovery_9cv_ok'],
    'PHASE-9CV-REPORT-CONTEXT-RECOVERY.md' => ['2.2.618 -> 2.2.619'],
];
$errors=[];
foreach ($needed as $relative=>$markers) {
    $txt=@file_get_contents($base.'/'.$relative);
    if ($txt===false) { $errors[]=$relative.' missing';continue; }
    foreach ($markers as $marker) if (strpos($txt,$marker)===false) $errors[]=$relative.': missing '.$marker;
}
if ($errors) {fwrite(STDERR, implode("\n",$errors)."\n");exit(1);}
echo "REPORT CONTEXT RECOVERY 9CV PHP OK\n";
