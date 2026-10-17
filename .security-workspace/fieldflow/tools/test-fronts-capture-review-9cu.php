<?php
// 9CU regression: run the historical 9CO fixture first, then exercise real 9CU server logic.
require __DIR__ . '/test-fronts-camera-reading-9co.php';

use RoutesPro\Vision\FrontsVisionService as V;
use RoutesPro\Vision\VisionUsageGuard as Budget;
use RoutesPro\Support\Config;

Config::$provider = 'azure';
$GLOBALS['wpdb']->lockEnabled = true;
file_put_contents($GLOBALS['ff9co']['photo'], FF9CO_JPEG);
V::saveClientSettings(9, ['mode'=>'on', 'share_space'=>true, 'monthly_limit'=>25, 'daily_limit'=>25,
    'monthly_budget_cents'=>2500, 'reservation_cents'=>25, 'reference_images'=>1]);
$calls = 0;
V::$aiCaller = static function (string $module, string $prompt, array $params) use (&$calls): string {
    $calls++;
    return '{"observations":[{"visible_text":"JAMESON 70 CL","visible_brand":"JAMESON","visible_capacity":"70CL","box":[110,220,720,440],"confidence":0.91,"front_row":true}]}';
};
$request = ['client_id'=>9, 'project_id'=>77, 'location_id'=>345, 'route_id'=>31, 'stop_id'=>44,
    'form_id'=>22, 'date'=>'2026-10-08', 'question_key'=>'frentes', 'question_type'=>'product_matrix',
    'capture_id'=>'ffcameracapture9cu001', 'items'=>[
        ['key'=>'r0','name'=>'JAMESON 70CL','ref'=>'205-0501','capacity'=>'70'],
        ['key'=>'r1','name'=>'JAMESON BLACK BARREL 70CL','ref'=>'2050502','capacity'=>'70'],
    ]];
$photo = ['path'=>$GLOBALS['ff9co']['photo'], 'url'=>'', 'capture'=>'camera'];
$before = Budget::summary(9)['monthly_requests'];
$first = V::read($request,$photo,7);
$after = Budget::summary(9)['monthly_requests'];
ff9co_assert($calls === 1 && $after === $before + 1 && empty($first['reused']), 'one_paid_capture');
$replay = V::read($request,$photo,7);
ff9co_assert($calls === 1 && $replay['reading_id'] === $first['reading_id'] && !empty($replay['reused'])
    && Budget::summary(9)['monthly_requests'] === $after, 'identical_capture_no_second_paid_call');
$secondRequest = $request;
$secondRequest['capture_id'] = 'ffcameracapture9cu002';
$second = V::read($secondRequest,$photo,7);
ff9co_assert($calls === 2 && $second['reading_id'] !== $first['reading_id'], 'new_photo_attempt_is_billed');
$otherUser = V::read($request,$photo,8);
ff9co_assert($calls === 3 && $otherUser['reading_id'] !== $first['reading_id'], 'replay_isolation_by_user');
$wrongContext = $request;
$wrongContext['location_id'] = 346;
$otherContext = V::read($wrongContext,$photo,7);
ff9co_assert($calls === 4 && $otherContext['reading_id'] !== $first['reading_id'], 'replay_isolation_by_store');

$GLOBALS['wpdb']->lockEnabled = false;
$blocked = $request;
$blocked['capture_id'] = 'ffcameracapture9cu004';
$spend = Budget::summary(9)['monthly_requests'];
try { V::read($blocked,$photo,7); ff9co_fail('capture_lock_must_fail_closed'); }
catch (\RuntimeException $error) { ff9co_assert(stripos($error->getMessage(),'em análise')!==false, 'capture_replay_lock_message'); }
ff9co_assert(Budget::summary(9)['monthly_requests'] === $spend && $calls === 4, 'capture_lock_no_paid_call');
$GLOBALS['wpdb']->lockEnabled = true;

$skus = [['code'=>'P1','key'=>'r0','name'=>'Jameson'],['code'=>'P2','key'=>'r1','name'=>'Absolut']];
$unknown = V::parseResult('{"items":[{"code":"P1","fronts":1.7,"confidence":0.88},{"code":"P2","fronts":0,"confidence":0.2}]}',$skus);
ff9co_assert($unknown['items'][0]['fronts'] === null && $unknown['items'][0]['needs_review'] === true
    && $unknown['items'][1]['fronts'] === 0 && $unknown['items'][1]['needs_review'] === true, 'no_fraction_and_low_conf_zero');
$ok = V::parseResult('{"items":[{"code":"P1","fronts":5,"confidence":0.99,"boxes":[[0,0,100,100]]}]}',[$skus[0]]);
ff9co_assert($ok['items'][0]['needs_review'] === true && $ok['items'][0]['review_reason'] === 'no_visual_proof' && $ok['items'][0]['fronts'] === null, 'box_disagreement_requires_review');
$ok = V::parseResult('{"items":[{"code":"P1","fronts":5,"confidence":0.99}]}',[$skus[0]]);
ff9co_assert($ok['items'][0]['needs_review'] === true && $ok['items'][0]['fronts'] === null && $ok['items'][0]['model_fronts'] === 5, 'confidence_alone_never_validates_sku');
$ok = V::parseResult('{"items":[{"code":"P1","fronts":1,"confidence":0.95,"label_text":"Jameson","boxes":[[100,100,900,400]]}]}',[$skus[0]]);
ff9co_assert($ok['items'][0]['needs_review'] === false && $ok['items'][0]['fronts'] === 1, 'visual_evidence_validated');
ff9co_assert(V::captureReplayKey($request,$GLOBALS['ff9co']['photo'],7) !== V::captureReplayKey($request,$GLOBALS['ff9co']['photo'],8), 'replay_key_user_isolation');
$noId = $request; unset($noId['capture_id']);
ff9co_assert(V::captureReplayKey($noId,$GLOBALS['ff9co']['photo'],7)==='', 'legacy_requests_unchanged');
@unlink($GLOBALS['ff9co']['photo']);
echo 'FRONTS CAPTURE REVIEW 9CU PHP OK | replay cache | locked duplicates | visitor scope | camera confidence | strict integers | cost reserve' . PHP_EOL;
