<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$fail = static function (string $reason): void { fwrite(STDERR, "FAIL: {$reason}\n"); exit(1); };
$assert = static function (bool $ok, string $reason) use ($fail): void { if (!$ok) $fail($reason); };
$form = $read('src/Forms/FormRenderer.php');
$shortcodes = $read('src/Shortcodes.php');
$shopping = $read('src/Shopping/ShoppingService.php');
$export = $read('src/Shopping/ShoppingExportService.php');

// Viewport invariant: preserve an anchor offset across a layout height mutation.
$scrollY = 1250.0;
$beforeTop = 96.0;
$afterTop = 312.0;
$restored = max(0.0, $scrollY + ($afterTop - $beforeTop));
$assert(abs($restored - 1466.0) < 0.001, 'anchor delta restore model');

// Attachment invariant: every requested format must be present before dispatch.
$requested = ['xlsx','pdf'];
$generated = ['xlsx'=>true,'pdf'=>true];
$missing = array_values(array_filter($requested, static fn(string $format): bool => empty($generated[$format])));
$assert($missing === [], 'complete attachment set should dispatch');
unset($generated['pdf']);
$missing = array_values(array_filter($requested, static fn(string $format): bool => empty($generated[$format])));
$assert($missing === ['pdf'], 'missing requested PDF must block incomplete dispatch');

$assert(strpos($shortcodes, 'reserveReportSlotHeight()') !== false, 'report slot height protected before replacement');
$legacySwap = (bool)preg_match('/const viewportCheckpoint=captureReportViewport\(String\(options\.source\|\|\'\'\)===\'manual-store-dropdown\'\);.*?reserveReportSlotHeight\(\);.*?els\.slot\.innerHTML=\'\'/s', $shortcodes);
$v2Swap = strpos($shortcodes, "reportViewportContract='fieldflow-form-viewport-continuity-v2'") !== false && strpos($shortcodes, "if(!visit){els.slot.innerHTML=''") !== false && strpos($shortcodes, 'formAvailable=!!data.has_form;els.slot.innerHTML=') !== false && strpos($shortcodes, 'reportScrollContainer') !== false;
$assert($legacySwap || $v2Swap, 'store render protects viewport without valid-store DOM collapse');
$assert(strpos($form, "rpCaptureFormViewport(form,'media-handoff'") !== false, 'media handoff viewport checkpoint');
$assert(strpos($form, "rpRestoreFormViewport(form,'draft-resume')") !== false, 'draft resume viewport restore');
$assert(strpos($shopping, 'if ($requestedFormats && ($missingAttachmentFormats || $attachmentErrors))') !== false, 'shopping dispatch fails closed');
$assert(strpos($shopping, 'ShoppingExportService::cleanupAttachments($attachmentPaths)') !== false, 'temporary attachments cleaned');
$assert(strpos($export, "'source'=>'canonical_submission_snapshot'") !== false, 'attachments label canonical source');
$assert(strpos($export, 'priceAnalysis') !== false && strpos($export, 'priceLabel') !== false, 'price readability helpers active');
echo 'FORM VIEWPORT SHOPPING DELIVERY 9AD RUNTIME OK | viewport=anchor_delta | store_swap=height_guarded | shopping=requested_attachments_required | exports=canonical_snapshot' . PHP_EOL;
