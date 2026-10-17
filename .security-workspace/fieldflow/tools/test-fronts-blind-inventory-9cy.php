<?php
/** Regression: real Original Jameson plus a different red-labeled Carina bottle. */
require __DIR__ . '/test-fronts-sku-identity-9cx.php';
use RoutesPro\Vision\FrontsVisionService as V;

$catalog = [
    ff9cw_sku('P1','orange','BEBIDA ESPIRITUOSA JAMESON ORANGE 70 CL'),
    ff9cw_sku('P2','18anos','GG WHISKY JAMESON 18 ANOS TRIP.DEST 70CL'),
    ff9cw_sku('P3','litro','WHISKY IRLANDES JAMESON 1L'),
    ff9cw_sku('P4','original','WHISKY IRLANDES JAMESON 70CL'),
    ff9cw_sku('P5','black','WHISKY JAMESON BLACK BARREL 70CL'),
    ff9cw_sku('P6','cask','WHISKY JAMESON CASKMATES 70CL'),
];
$catalog[3]['has_reference'] = true;
$prompt = V::blindInventoryPrompt();
ff9co_assert(strpos($prompt,'JAMESON') === false && strpos($prompt,'BLACK BARREL') === false
 && strpos($prompt,'visible_text') !== false && strpos($prompt,'front_row') !== false,
 'blind_prompt_never_primes_variant');
$scan = V::parseBlindInventory(json_encode(['observations'=>[
 ['visible_text'=>'JAMESON TRIPLE DISTILLED','visible_brand'=>'JAMESON','box'=>[140,480,650,620], 'confidence'=>.96,'front_row'=>true],
 ['visible_text'=>'CARINA','visible_brand'=>'CARINA','box'=>[100,640,650,795], 'confidence'=>.98,'front_row'=>true,
    'code'=>'P5','guess'=>'JAMESON BLACK BARREL'],
]]), $catalog);
ff9co_assert($scan['items'][3]['model_fronts']===1 && $scan['items'][3]['fronts']===null
 && $scan['items'][3]['review_reason']==='capacity_unverified', 'jameson_real_brand_hint');
ff9co_assert($scan['items'][4]['fronts']===null && $scan['items'][4]['model_fronts']===null
 && $scan['items'][4]['boxes']===[], 'carina_never_assigned_black_barrel');
ff9co_assert($scan['vision_mode']===V::BLIND_INVENTORY_CONTRACT, 'blind_provenance');

$fabricatedCapacity = V::parseBlindInventory(json_encode(['observations'=>[
 ['visible_text'=>'JAMESON','visible_brand'=>'JAMESON','visible_capacity'=>'70CL',
  'box'=>[140,480,650,620], 'confidence'=>.99,'front_row'=>true],
]]),$catalog);
ff9co_assert($fabricatedCapacity['items'][3]['fronts']===null
 && $fabricatedCapacity['items'][3]['review_reason']==='capacity_unverified',
 'model_capacity_cannot_replace_ocr_label');

$knownCapacity = V::parseBlindInventory(json_encode(['observations'=>[
 ['visible_text'=>'JAMESON 70CL TRIPLE DISTILLED','visible_brand'=>'JAMESON','visible_capacity'=>'70CL',
  'box'=>[140,480,650,620], 'confidence'=>.94,'front_row'=>true],
]]),$catalog);
ff9co_assert($knownCapacity['items'][3]['fronts']===1 && !$knownCapacity['items'][3]['needs_review'], 'known_capacity_original');

$fakeVariant = V::parseBlindInventory(json_encode(['observations'=>[
 ['visible_text'=>'JAMESON BLACK BARREL','visible_brand'=>'JAMESON','visible_capacity'=>'70CL',
  'box'=>[130,650,680,800], 'confidence'=>.99,'front_row'=>true],
]]),$catalog);
ff9co_assert($fakeVariant['items'][4]['model_fronts']===1 && $fakeVariant['items'][4]['fronts']===null
 && $fakeVariant['items'][4]['review_reason']==='variant_manual_verification'
 && $fakeVariant['items'][4]['boxes']===[], 'even_model_hallucination_cannot_autocount_variant');

$inconsistentOCR = V::parseBlindInventory(json_encode(['observations'=>[
 ['visible_text'=>'CARINA','visible_brand'=>'JAMESON', 'box'=>[130,650,680,800],
  'confidence'=>1,'front_row'=>true],
]]),$catalog);
ff9co_assert($inconsistentOCR['items'][3]['model_fronts']===null
 && $inconsistentOCR['items'][4]['model_fronts']===null, 'contradictory_ocr_rejected');

$duplicateReflection = V::parseBlindInventory(json_encode(['observations'=>[
 ['visible_text'=>'JAMESON 70 CL','visible_brand'=>'JAMESON', 'box'=>[100,100,550,200],
  'confidence'=>.99,'front_row'=>true],
 ['visible_text'=>'JAMESON 70 CL','visible_brand'=>'JAMESON', 'box'=>[560,100,900,200],
  'confidence'=>.99,'front_row'=>false],
]]),$catalog);
ff9co_assert($duplicateReflection['items'][3]['fronts']===1,'back_or_reflection_not_another_front');

print 'FRONTS BLIND INVENTORY 9CY PHP OK | CARINA != Black Barrel | no SKU priming | one photo | human variant approval' . PHP_EOL;
