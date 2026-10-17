<?php
/** 9CX: exact Cardex strings from the Portuguese Jameson test in the field. */
require __DIR__ . '/test-fronts-visual-evidence-9cw.php';
use RoutesPro\Vision\FrontsVisionService as V;

$catalog = [
    ff9cw_sku('P1','orange','BEBIDA ESPIRITUOSA JAMESON ORANGE 70 CL'),
    ff9cw_sku('P2','18anos','GG WHISKY JAMESON 18 ANOS TRIP.DEST 70CL'),
    ff9cw_sku('P3','litro','WHISKY IRLANDES JAMESON 1L'),
    ff9cw_sku('P4','original','WHISKY IRLANDES JAMESON 70CL'),
    ff9cw_sku('P5','black','WHISKY JAMESON BLACK BARREL 70CL'),
    ff9cw_sku('P6','cask','WHISKY JAMESON CASKMATES 70CL'),
];
$identity = V::variantDiscriminators($catalog);
foreach ($identity as $code=>$sku) ff9co_assert($sku['brand']==='JAMESON', 'brand_is_jameson_for_'.$code);
ff9co_assert($identity['P3']['capacity']==='100' && $identity['P4']['capacity']==='70' && $identity['P4']['capacity_required'], 'one_litre_vs_seventy_cl');
ff9co_assert($identity['P5']['variant']===['BLACK','BARREL'], 'black_barrel_stays_distinct');
ff9co_assert($identity['P1']['variant']===['ORANGE'], 'orange_stays_distinct');

// The photo is visually one Original. A model-provided P4 positive with "Jameson"
// on the label must not be rejected for lack of the word "IRLANDES".
$recognised = V::parseResult(json_encode(['items'=>[
 ['code'=>'P4','fronts'=>1,'confidence'=>0.96,'label_text'=>'Jameson','capacity_text'=>'70CL','boxes'=>[[130,480,760,630]]],
 ['code'=>'P5','fronts'=>1,'confidence'=>0.99,'label_text'=>'Jameson','variant_text'=>'','boxes'=>[[140,485,755,627]]],
]],JSON_UNESCAPED_UNICODE), $catalog);
ff9co_assert($recognised['items'][3]['fronts']===1 && !$recognised['items'][3]['needs_review'], 'real_jameson_original_detected');
ff9co_assert($recognised['items'][4]['fronts']===null && $recognised['items'][4]['review_reason']==='variant_unverified', 'phantom_black_barrel_not_counted');

// Capacity cannot be read from every front-facing label. Keep a visible 1-front
// proposal in model_fronts, but leave the canonical form blank until a human checks.
$capacityUnknown = V::parseResult('{"items":[{"code":"P4","fronts":1,"confidence":0.98,"label_text":"Jameson","boxes":[[130,480,760,630]]}]}',$catalog);
ff9co_assert($capacityUnknown['items'][3]['review_reason']==='capacity_unverified'
 && $capacityUnknown['items'][3]['model_fronts']===1 && $capacityUnknown['items'][3]['fronts']===null, 'branded_proposal_pending_capacity');

// No relaxation for a false brand or a different variant.
$cardhu = V::parseResult('{"items":[{"code":"P4","fronts":1,"confidence":0.99,"label_text":"Cardhu","capacity_text":"70cl","boxes":[[130,480,760,630]]}]}',$catalog);
ff9co_assert($cardhu['items'][3]['fronts']===null && $cardhu['items'][3]['review_reason']==='brand_unverified', 'wrong_brand_rejected');
$wrong = V::parseResult('{"items":[{"code":"P5","fronts":1,"confidence":0.99,"label_text":"Jameson","capacity_text":"70cl","boxes":[[130,480,760,630]]}]}',$catalog);
ff9co_assert($wrong['items'][4]['fronts']===null && $wrong['items'][4]['review_reason']==='variant_unverified', 'black_barrel_variant_still_required');

// If *both* positively verify a variant but claim the same physical bottle,
// the position conflict still blocks both SKUs for explicit review.
$twoSupported = V::parseResult('{"items":[{"code":"P4","fronts":1,"confidence":0.99,"label_text":"Jameson 70cl","boxes":[[130,480,760,630]]},{"code":"P5","fronts":1,"confidence":0.99,"label_text":"Jameson BLACK BARREL 70CL","variant_text":"BLACK BARREL","boxes":[[132,482,761,631]]}]}',$catalog);
ff9co_assert($twoSupported['items'][3]['fronts']===null && $twoSupported['items'][4]['fronts']===null && $twoSupported['items'][3]['review_reason']==='duplicate_position', 'independently_supported_collision_blocked');

print 'FRONTS SKU IDENTITY 9CX PHP OK | Portuguese Cardex brand | capacity 1L/70CL | real Jameson detected | Black Barrel rejected | manual review | no extra Azure calls' . PHP_EOL;
