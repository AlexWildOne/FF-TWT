<?php
$root = dirname(__DIR__);
$fail = [];
$req = [
    'src/Cloud/LicenseTenantFoundation.php'=>['release_integrity_ready', '$foundationCertified = !empty($state[\'certified\']) && $match && $remoteConnectivityReady;', "'certification_requires_revalidation'=>!empty(\$state['certified']) && (!\$match || !\$remoteConnectivityReady)"],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.588'", "public const STABLE_RELEASE = '2.2.589'", "'stage'=>'6B.1-revalidate'", 'LicenseTenantFoundation::certifyFoundation($actorId)', 'failedChecks'],
    'src/Admin/LicenseTenants.php'=>['failedCheckKeys', 'Ainda existem pré-condições da 6B.1 por concluir:'],
    'src/Support/ReleaseGate.php'=>['CERTIFICATION_FOUNDATION_CONTINUITY_9BR_CONTRACT','checkCertificationFoundationContinuity9BRContract'],
    'PHASE-9BR-CERTIFICATION-FOUNDATION-CONTINUITY.md'=>['fieldflow-certification-foundation-continuity-v1','No database schema'],
];
foreach ($req as $rel=>$markers) {
    $raw = is_readable($root.'/'.$rel) ? (string)file_get_contents($root.'/'.$rel) : '';
    foreach ($markers as $marker) if (strpos($raw,$marker) === false) $fail[]=$rel.':'.$marker;
}
if ($fail) { fwrite(STDERR, "CERTIFICATION FOUNDATION CONTINUITY 9BR CONTRACT FAIL\n".implode("\n",$fail)."\n"); exit(1); }
echo "CERTIFICATION FOUNDATION CONTINUITY 9BR CONTRACT OK successor=>=2.2.589\n";
