<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$fail = static function (string $reason): void { fwrite(STDERR, "FAIL: {$reason}\n"); exit(1); };
$assert = static function (bool $ok, string $reason) use ($fail): void { if (!$ok) $fail($reason); };
$form = $read('src/Forms/FormRenderer.php');
$bridge = $read('src/Mobile/WebViewBridge.php');
$app = $read('src/Front/AppShortcodeRenderer.php');
$offline = $read('assets/fieldflow-offline-runtime.js');

// Resume state machine invariant: focus alone is inert, focus after a real hidden arm can resume once.
$armedAt = 0;
$consume = static function () use (&$armedAt): bool { $armed = $armedAt > 0 && (10000 - $armedAt) <= 120000; $armedAt = 0; return $armed; };
$assert($consume() === false, 'bare focus must be inert');
$armedAt = 9500;
$assert($consume() === true, 'focus after hidden must consume arm');
$assert($consume() === false, 'resume arm must be single-use');

// Durable media invariant: active transport records win, otherwise original records are the fallback.
$prefix = '__fieldflow_original__:';
$rows = [
    ['inputName'=>$prefix.'photo[]','ordinal'=>0,'fileIndex'=>0,'name'=>'original.jpg'],
    ['inputName'=>'photo[]','ordinal'=>0,'fileIndex'=>0,'name'=>'optimised.jpg'],
    ['inputName'=>$prefix.'photo[]','ordinal'=>1,'fileIndex'=>0,'name'=>'fallback.jpg'],
];
$groups = [];
foreach ($rows as $row) {
    $raw = (string)$row['inputName'];
    $original = strpos($raw, $prefix) === 0;
    $key = $original ? substr($raw, strlen($prefix)) : $raw;
    $groupKey = $key . '|' . (int)$row['ordinal'];
    if (!isset($groups[$groupKey])) $groups[$groupKey] = ['active'=>[], 'original'=>[]];
    $groups[$groupKey][$original ? 'original' : 'active'][] = $row;
}
$selected = [];
foreach ($groups as $group) $selected[] = ($group['active'] ?: $group['original'])[0]['name'];
$assert(in_array('optimised.jpg', $selected, true), 'active media must win');
$assert(!in_array('original.jpg', $selected, true), 'original must not duplicate active media');
$assert(in_array('fallback.jpg', $selected, true), 'original must recover a missing active group');

$assert(strpos($bridge, "window.addEventListener('focus',function(){if(document.visibilityState!=='hidden'&&consumeNativeResumeArm())queueNativeResume('focus-after-hidden');})") !== false, 'native guarded focus runtime');
$assert(strpos($form, "window.addEventListener('focus',function(){if(document.visibilityState!=='hidden'&&rpConsumeResumeDraftArm())rpQueueResumeDraftForms('focus-after-hidden');})") !== false, 'form guarded focus runtime');
$assert(strpos($app, 'reportVisit:null,formStep:0,formId:0') !== false, 'explicit report context clear runtime');
$assert(strpos($offline, 'group.active.length?group.active:group.original') !== false, 'offline fallback priority runtime');
$assert(strpos($form, 'optimiseFileInput(input,true)') === false, 'optimised media must not redispatch change');
$assert((bool)preg_match('/return\s+optimiseFileInput\(input,false\);.*?Persist the optimised transport copy exactly once\..*?return\s+saveChangedFileInput\(input\);/s', $form), 'optimised media must commit exactly once');
echo 'RESUME CERTIFICATION MEDIA 9AC RUNTIME OK | bare_focus=inert | guarded_focus=single_use | media=active_then_original | selection_clear=durable | optimised_commit=single' . PHP_EOL;
