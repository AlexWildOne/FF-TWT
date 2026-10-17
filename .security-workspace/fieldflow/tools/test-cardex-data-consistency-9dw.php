<?php
/** Pure behavioural and wiring regression tests for Cardex Data Consistency Center. */
if (!defined('ABSPATH')) define('ABSPATH',__DIR__.'/');
$root=dirname(__DIR__).'/';
require_once $root.'src/Admin/CardexConsistency.php';
$assertions=0;
function ff657(bool $ok,string $label): void {
    global $assertions;
    $assertions++;
    if (!$ok) throw new RuntimeException('9DW assertion '.$assertions.': '.$label);
}
$base=['cardex_id'=>5,'items_count'=>14,'linked_count'=>1,'duplicate_sku'=>[],
    'stock_retailer_mismatch'=>false,'stock_foreign_sku'=>0,'stock_unmatched_sku'=>0];
$c='\\RoutesPro\\Admin\\CardexConsistency';
$s=$c::classify($base);
ff657($s['code']==='ok','valid Cardex accepted');
ff657($s['ok']===true,'valid status flag');
ff657($s['label']==='Cardex resolvido','status label');
foreach ([
    ['cardex_id',0,'missing_cardex'], ['items_count',0,'empty_assortment'],
    ['duplicate_sku',['123456'],'duplicate_sku'],
    ['stock_retailer_mismatch',true,'stock_retailer_mismatch'],
    ['stock_foreign_sku',2,'stock_foreign_sku'],
    ['linked_count',2,'multiple_links'],
    ['stock_unmatched_sku',3,'stock_unmatched_sku'],
] as [$key,$value,$expected]) {
    $input=$base;$input[$key]=$value;
    ff657($c::classify($input)['code']===$expected,'classification '.$key);
    ff657(!$c::classify($input)['ok'],'not ready '.$key);
}
$mix=$base;$mix['cardex_id']=0;$mix['items_count']=0;$mix['stock_foreign_sku']=3;
ff657($c::classify($mix)['code']==='missing_cardex','missing Cardex precedence');
$mix=$base;$mix['items_count']=0;$mix['stock_retailer_mismatch']=true;
ff657($c::classify($mix)['code']==='empty_assortment','empty assortment precedence');
$mix=$base;$mix['duplicate_sku']=['005'];$mix['linked_count']=3;
ff657($c::classify($mix)['code']==='duplicate_sku','duplicate SKU precedence');
$mix=$base;$mix['stock_retailer_mismatch']=true;$mix['linked_count']=3;
ff657($c::classify($mix)['code']==='stock_retailer_mismatch','wrong retailer precedence');
$mix=$base;$mix['linked_count']=0;
ff657($c::classify($mix)['code']==='ok','unlinked but correctly auto-resolved cardex valid');
$files=[
 'src/Admin/CardexConsistency.php'=>['private const PER_PAGE = 30;','private static function stockSnapshot(', 'private static function briefingQuestions(', 'AND b.client_id','StoreIdentityAuthority::locationFamily(', 'return self::check','Cardex::get_items(', 'duplicate_sku', 'cc_client', 'cc_project', 'cc_retailer', 'cc_query', 'cc_store', 'cc_form', 'ContextQuestions::briefing_for_context(', 'self::classify(', 'function renderDetail(', 'status=%s'],
 'src/Admin/ProductCardex.php'=>["require_once __DIR__ . '/CardexConsistency.php'", 'CardexConsistency::render();', 'consistency=1'],
 'src/Admin/Forms.php'=>['twt-fb-cardex-fixed-picker','twt-fb-auto-cardex-note','consistency=1'],
 'assets/routespro-form-builder.js'=>[".toggle(source === 'cardex_fixed')", ".toggle(source === 'cardex_auto')", ".toggle(source === 'manual')"],
 'src/Cloud/Phase6StableRelease.php'=>["SOURCE_RELEASE = '2.2.656'","STABLE_RELEASE = '2.2.657'"],
];
// The literal “return self::check” is a release gate marker, not part of the read-only screen.
$files['src/Admin/CardexConsistency.php']=array_values(array_filter($files['src/Admin/CardexConsistency.php'],static fn($s)=>$s!=='return self::check'));
foreach ($files as $file=>$markers) {
    $raw=file_get_contents($root.$file);
    ff657($raw!==false && $raw!=='','exists '.$file);
    foreach ($markers as $marker) ff657(strpos($raw,$marker)!==false,$file.' marker '.$marker);
}
$code=file_get_contents($root.'src/Admin/CardexConsistency.php');
foreach (['$wpdb->insert(', '$wpdb->update(', '$wpdb->delete(', 'START TRANSACTION', 'DELETE FROM ', 'UPDATE routespro_', 'wp_insert_post(', 'update_option('] as $write) {
    ff657(strpos($code,$write)===false,'read-only '.$write);
}
echo 'CARDEX DATA CONSISTENCY 9DW PASS ('.$assertions.' assertions)' . PHP_EOL;
