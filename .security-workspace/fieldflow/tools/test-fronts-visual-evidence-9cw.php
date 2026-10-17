<?php
/**
 * 9CW real PHP parser regression. The Jameson case is a synthetic model response
 * representing the supplied shelf photo, NOT an additional AI call nor a photo fixture.
 */
require __DIR__ . '/test-fronts-capture-review-9cu.php';

use RoutesPro\Vision\FrontsVisionService as V;

function ff9cw_sku(string $code, string $key, string $name): array {
    return ['code'=>$code,'key'=>$key,'name'=>$name,'catalog_id'=>0,'has_reference'=>false];
}
$items = [
    ff9cw_sku('P1', 'r0', 'JAMESON 70CL'),
    ff9cw_sku('P2', 'r1', 'JAMESON BLACK BARREL 70CL'),
];
$variants = V::variantDiscriminators($items);
ff9co_assert($variants['P1']['brand'] === 'JAMESON'
    && $variants['P2']['variant'] === ['BLACK','BARREL'], 'distinctive_variant_tokens');

// 1. Photo: one Original Jameson. AI hallucinates second variant with same box.
$sameBottle = V::parseResult(json_encode(['items'=>[
    ['code'=>'P1','fronts'=>1,'confidence'=>0.98,'label_text'=>'Jameson','boxes'=>[[150,520,930,800]]],
    ['code'=>'P2','fronts'=>1,'confidence'=>0.99,'label_text'=>'Jameson','variant_text'=>'','boxes'=>[[160,530,940,810]]],
]],JSON_UNESCAPED_UNICODE), $items);
ff9co_assert($sameBottle['items'][0]['fronts'] === 1 && $sameBottle['items'][1]['fronts'] === null
    && $sameBottle['items'][0]['review_reason'] === ''
    && $sameBottle['items'][1]['review_reason'] === 'variant_unverified', 'one_bottle_two_skus_blocked');

// 2. Even if the model fabricates a distinct location, it must show the variant text.
$wrongVariant = V::parseResult(json_encode(['items'=>[
    ['code'=>'P1','fronts'=>1,'confidence'=>0.94,'label_text'=>'Jameson','boxes'=>[[150,450,910,570]]],
    ['code'=>'P2','fronts'=>1,'confidence'=>0.99,'label_text'=>'Jameson','variant_text'=>'','boxes'=>[[150,670,910,780]]],
]],JSON_UNESCAPED_UNICODE), $items);
ff9co_assert($wrongVariant['items'][0]['fronts'] === 1 && !$wrongVariant['items'][0]['needs_review']
    && $wrongVariant['items'][1]['fronts'] === null && $wrongVariant['items'][1]['model_fronts'] === 1
    && $wrongVariant['items'][1]['review_reason'] === 'variant_unverified', 'black_barrel_phantom_is_held');

// 3. A genuinely distinct and legible variant is allowed when boxes do not collide.
$distinct = V::parseResult(json_encode(['items'=>[
    ['code'=>'P1','fronts'=>1,'confidence'=>0.94,'label_text'=>'Jameson','boxes'=>[[150,450,910,570]]],
    ['code'=>'P2','fronts'=>1,'confidence'=>0.95,'label_text'=>'Jameson','variant_text'=>'BLACK BARREL','boxes'=>[[150,680,910,800]]],
]],JSON_UNESCAPED_UNICODE), $items);
ff9co_assert($distinct['items'][0]['fronts'] === 1 && $distinct['items'][1]['fronts'] === 1
    && !$distinct['items'][1]['needs_review'], 'legible_and_distinct_variant_allowed');

// 4. A model confidence claim without evidence is never sufficient.
$unsupported = V::parseResult('{"items":[{"code":"P2","fronts":1,"confidence":0.99}]}',$items);
ff9co_assert($unsupported['items'][1]['fronts'] === null && $unsupported['items'][1]['review_reason'] === 'no_visual_proof', 'no_evidence_no_front');
$brand = V::parseResult('{"items":[{"code":"P1","fronts":1,"confidence":0.98,"label_text":"Cardhu","boxes":[[150,450,910,570]]}]}',$items);
ff9co_assert($brand['items'][0]['fronts'] === null && $brand['items'][0]['review_reason'] === 'brand_unverified', 'brand_mismatch_not_counted');

// 5. Duplicate positions within one SKU never create two fronts.
$one = [ff9cw_sku('P1','r0','Jameson 70CL')];
$dup = V::parseResult('{"items":[{"code":"P1","fronts":2,"confidence":0.99,"label_text":"Jameson","boxes":[[100,100,900,410],[110,110,910,415]]}]}',$one);
ff9co_assert($dup['items'][0]['fronts'] === null && $dup['items'][0]['review_reason'] === 'duplicate_position', 'intra_sku_duplicate_blocked');

// 6. Invalid coordinates, duplicate product keys, and weak confidence fail closed.
$invalid = V::parseResult('{"items":[{"code":"P1","fronts":1,"confidence":0.99,"label_text":"Jameson","boxes":[[-1,5,900,330]]}]}',$one);
ff9co_assert($invalid['items'][0]['fronts'] === null && !$invalid['items'][0]['boxes'], 'invalid_box_rejected');
$duplicate = V::parseResult('{"items":[{"code":"P1","fronts":1},{"code":"P1","fronts":1}]}',$one);
ff9co_assert($duplicate['items'][0]['fronts'] === null && $duplicate['items'][0]['review_reason'] === 'duplicate_code', 'duplicate_json_code_rejected');
$weak = V::parseResult('{"items":[{"code":"P1","fronts":1,"confidence":0.80,"label_text":"Jameson","boxes":[[100,100,900,410]]}]}',$one);
ff9co_assert($weak['items'][0]['fronts'] === null && $weak['items'][0]['review_reason'] === 'low_confidence', 'confidence_not_enough');

// 7. An absent SKU remains zero, not a second positive claim.
$absent = V::parseResult('{"items":[{"code":"P2","fronts":0,"confidence":0.95,"boxes":[]}]}',$items);
ff9co_assert($absent['items'][1]['fronts'] === 0 && !$absent['items'][1]['needs_review'], 'confirmed_absence_distinct_from_unknown');

// 8. Same brand, different bottle capacities must not be confused.
$macieira = [ff9cw_sku('P1','r0','MACIEIRA 70CL'),ff9cw_sku('P2','r1','MACIEIRA 100CL')];
$cap = V::parseResult('{"items":[{"code":"P1","fronts":1,"confidence":0.97,"label_text":"Macieira","boxes":[[110,100,890,350]]}]}',$macieira);
ff9co_assert($cap['items'][0]['fronts'] === null && $cap['items'][0]['review_reason'] === 'capacity_unverified', 'capacity_variant_requires_visual_text');
$cap = V::parseResult('{"items":[{"code":"P1","fronts":1,"confidence":0.97,"label_text":"Macieira","capacity_text":"70cl","boxes":[[110,100,890,350]]}]}',$macieira);
ff9co_assert($cap['items'][0]['fronts'] === 1, 'matching_capacity_allowed');

// 8. Prompt requires unique physical positions and verifiable variant text.
$prompt = V::prompt($items,'product_matrix',1,true);
ff9co_assert(strpos($prompt,'JAMESON BLACK BARREL') !== false
    && strpos($prompt,'EXATAMENTE uma identidade') !== false
    && strpos($prompt,'boxes') !== false && strpos($prompt,'variant_text') !== false, 'azure_visual_evidence_prompt');

print 'FRONTS VISUAL EVIDENCE 9CW PHP OK | phantom Jameson Black Barrel | duplicate positions | brand and variant proofs | fail closed | no extra AI calls' . PHP_EOL;
