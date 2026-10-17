<?php
/** 9D1 multi-SKU/dedup and confirmed history regression, without network calls. */
require __DIR__ . '/test-fronts-blind-inventory-9cy.php';
use RoutesPro\Vision\FrontsVisionService as V;

function ff9d1_assert($condition, string $name): void {
    if (!$condition) { fwrite(STDERR, "FRONTS 9D1 FAIL: {$name}\n"); exit(1); }
}
$catalog = [
    ['key'=>'r0','code'=>'P1','name'=>'WHISKY IRLANDES JAMESON 70CL','catalog_id'=>101],
    ['key'=>'r1','code'=>'P2','name'=>'VODKA ABSOLUT 70CL','catalog_id'=>102],
    ['key'=>'r2','code'=>'P3','name'=>'JAMESON BLACK BARREL 70CL','catalog_id'=>103],
];
$observations = [
    ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','box'=>[130,60,760,190],'confidence'=>.98,'front_row'=>true],
    ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','box'=>[130,200,760,330],'confidence'=>.96,'front_row'=>true],
    ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','box'=>[130,340,760,470],'confidence'=>.94,'front_row'=>true],
    ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','box'=>[132,61,758,190],'confidence'=>.97,'front_row'=>true],
    ['visible_text'=>'ABSOLUT 70CL','visible_brand'=>'ABSOLUT','box'=>[130,500,760,630],'confidence'=>.96,'front_row'=>true],
    ['visible_text'=>'ABSOLUT 70CL','visible_brand'=>'ABSOLUT','box'=>[130,650,760,790],'confidence'=>.97,'front_row'=>true],
    ['visible_text'=>'CARINA','visible_brand'=>'CARINA','box'=>[150,815,710,960],'confidence'=>.98,'front_row'=>true],
];
$multiple = V::parseBlindInventory(json_encode(['observations'=>$observations]), $catalog);
ff9d1_assert($multiple['items'][0]['fronts']===3 && $multiple['items'][1]['fronts']===2,
    'multi_sku_multiple_fronts');
ff9d1_assert($multiple['items'][2]['fronts']===null && $multiple['items'][2]['model_fronts']===null,
    'black_barrel_never_invented');
ff9d1_assert($multiple['deduplicated']===1 && $multiple['unidentified']===1,
    'duplicate_observation_same_sku');
ff9d1_assert($multiple['vision_mode']===V::BLIND_INVENTORY_CONTRACT,
    'blind_inventory_preserved');
$disputed = V::parseBlindInventory(json_encode(['observations'=>[
    ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','box'=>[130,100,760,230],'confidence'=>.99,'front_row'=>true],
    ['visible_text'=>'ABSOLUT 70CL','visible_brand'=>'ABSOLUT','box'=>[135,105,755,235],'confidence'=>.99,'front_row'=>true],
]]),$catalog);
ff9d1_assert($disputed['items'][0]['fronts']===null && $disputed['items'][1]['fronts']===null
    && $disputed['items'][0]['review_reason']==='duplicate_position'
    && $disputed['items'][1]['review_reason']==='duplicate_position'
    && $disputed['items'][0]['boxes']===[], 'overlapping_different_skus_manual_review');
$adjacent = V::parseBlindInventory(json_encode(['observations'=>[
    ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','box'=>[130,90,760,220],'confidence'=>.99,'front_row'=>true],
    ['visible_text'=>'JAMESON 70CL','visible_brand'=>'JAMESON','box'=>[130,150,760,280],'confidence'=>.97,'front_row'=>true],
]]),$catalog);
ff9d1_assert($adjacent['items'][0]['fronts']===null && $adjacent['items'][0]['review_reason']==='duplicate_position'
    && $adjacent['deduplicated']===0, 'adjacent_partial_overlap_not_silently_collapsed');

$prior = ['items'=>[
    ['key'=>'r0','name'=>'JAMESON 70CL','catalog_id'=>101],
    ['key'=>'r1','name'=>'ABSOLUT 70CL','catalog_id'=>102],
    ['key'=>'r2','name'=>'JAMESON BLACK BARREL 70CL','catalog_id'=>103],
]];
$confirmed = [['key'=>'r0','fronts'=>6],['key'=>'r1','fronts'=>0],['key'=>'r2','fronts'=>1]];
$current = [
    ['key'=>'r82','name'=>'ABSOLUT 70CL','catalog_id'=>102],
    ['key'=>'r25','name'=>'JAMESON 70CL','catalog_id'=>101],
    ['key'=>'r49','name'=>'JAMESON BLACK BARREL 70CL','catalog_id'=>103],
];
$previous = V::compareConfirmedWithPrevious($current,$prior,$confirmed,'product_matrix');
ff9d1_assert($previous===['r82'=>0,'r25'=>6,'r49'=>1], 'reordered_cardex_confirmed_zero');
$duplicate = V::compareConfirmedWithPrevious([['key'=>'r1','name'=>'ABSOLUT 70CL','catalog_id'=>102],
    ['key'=>'r2','name'=>'ABSOLUT 70CL','catalog_id'=>102]],$prior,$confirmed,'product_matrix');
ff9d1_assert($duplicate===[], 'ambiguous_duplicate_catalog_skipped');
$sharePast=['items'=>[
    ['key'=>'x','name'=>'JAMESON 70CL','catalog_id'=>101,'card'=>'focus','side'=>'pr'],
    ['key'=>'y','name'=>'JAMESON 70CL','catalog_id'=>101,'card'=>'focus','side'=>'competitor'],
]];
$share=V::compareConfirmedWithPrevious([
    ['key'=>'newpr','name'=>'JAMESON 70CL','catalog_id'=>101,'card'=>'focus','side'=>'pr'],
    ['key'=>'newco','name'=>'JAMESON 70CL','catalog_id'=>101,'card'=>'focus','side'=>'competitor'],
],$sharePast,[['key'=>'x','fronts'=>5],['key'=>'y','fronts'=>2]],'share_space_matrix');
ff9d1_assert($share===['newpr'=>5,'newco'=>2], 'share_space_sides_never_mixed');
ff9d1_assert(V::VISION_INTELLIGENCE_II_CONTRACT==='fieldflow-fronts-vision-intelligence-9d1-v1', 'contract');
echo 'FRONTS VISION INTELLIGENCE 9D1 PHP OK | duplicate_observation_same_sku | multi_sku | adjacent_review | share_space_sides | no_added_ai_calls' . PHP_EOL;
