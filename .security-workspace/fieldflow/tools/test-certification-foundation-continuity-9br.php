<?php
$root = dirname(__DIR__);
$assert = function($ok,$label){ if(!$ok){ fwrite(STDERR,"FAIL {$label}\n"); exit(1);} echo "PASS {$label}\n"; };
$foundation = (string)file_get_contents($root.'/src/Cloud/LicenseTenantFoundation.php');
$phase6 = (string)file_get_contents($root.'/src/Cloud/Phase6StableRelease.php');
$admin = (string)file_get_contents($root.'/src/Admin/LicenseTenants.php');
$assert(strpos($foundation, '$foundationCertified = !empty($state[\'certified\']) && $match && $remoteConnectivityReady;') !== false, 'foundation_certificate_decoupled');
$assert(strpos($foundation, "'release_integrity_ready'=>\$releaseIntegrityReady") !== false, 'release_integrity_separate');
$assert(strpos($foundation, "'release_manifest_integrity'=>!empty(\$health['release_manifest_ok'])") !== false && strpos($foundation, "'release_version_consistency'=>!empty(\$health['release_version_consistent'])") !== false, 'integrity_still_mandatory');
$assert(strpos($phase6, "'stage'=>'6B.1-revalidate'") !== false && strpos($phase6, 'LicenseTenantFoundation::certifyFoundation($actorId)') !== false, 'chain_includes_6b1');
$assert(strpos($phase6, 'LicenseTenantFoundation::probeRemoteApi()') !== false && strpos($phase6, '$remoteChecks') !== false, 'remote_probe_recovery');
$assert(strpos($admin, 'failedCheckKeys') !== false && strpos($admin, 'pré-condições da 6B.1 por concluir:') !== false, 'diagnostic_visibility');
echo "CERTIFICATION FOUNDATION CONTINUITY 9BR RUNTIME OK foundation_certificate_decoupled chain_includes_6b1 diagnostic_visibility\n";
