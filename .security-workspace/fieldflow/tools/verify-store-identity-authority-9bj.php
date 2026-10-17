<?php
$root=dirname(__DIR__);
$requirements=[
    'src/Forms/DailySubmissionService.php'=>['canonical_location_sql','_canonical_location_id','Historical FieldFlow builds could persist location_id=route_stop_id'],
    'src/Forms/BindingResolver.php'=>['explicit route stop is the canonical store identity',"\$context['route_stop_id']"],
    'src/Forms/Forms.php'=>['$daily_existing && !$daily_context_conflict','another route/stop/store'],
    'src/Mobile/FormPayloadService.php'=>['$existing && !$context_conflict','never expose another route/stop/store report'],
    'src/Forms/DraftService.php'=>['payload_identity_matches_scope','payload_identity_from_scope',"'retained' => 'identity'"],
    'src/Forms/FormRenderer.php'=>['function draftIdentity()','identity:draftIdentity()','allowRouteRebase'],
    'frontend/src/drafts/draft-core.ts'=>['identity?: FieldFlowDraftIdentity','allowRouteRebase?: boolean','const saved = snapshot.identity'],
    'src/Stores/StoreProfileService.php'=>['DailySubmissionService::canonical_location_sql'],
    'src/Support/ReleaseGate.php'=>['STORE_IDENTITY_AUTHORITY_9BJ_CONTRACT','checkStoreIdentityAuthority9BJContract'],
    'src/Support/ReleasePackageValidator.php'=>['store_identity_authority_9bj_ok'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.580'","public const STABLE_RELEASE = '2.2.581'"],
    'PHASE-9BJ-STORE-IDENTITY-AUTHORITY.md'=>['fieldflow-store-identity-authority-v1'],
];
$missing=[];
foreach($requirements as $file=>$markers){
    $raw=(string)@file_get_contents($root.'/'.$file);
    foreach($markers as $marker) if(strpos($raw,$marker)===false) $missing[]=$file.':'.$marker;
}
if($missing){fwrite(STDERR,implode(PHP_EOL,$missing).PHP_EOL);exit(1);}
echo "STORE IDENTITY AUTHORITY 9BJ CONTRACT OK successor=>=2.2.581\n";
