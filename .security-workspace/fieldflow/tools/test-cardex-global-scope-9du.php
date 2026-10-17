<?php
/** 9DU: fixture matrix for all linked Cardex classes, including failed historical Sonae group. */
require_once __DIR__.'/test-cardex-scope-truth-9dt.php';
use RoutesPro\Forms\ProductCardex as C;
use RoutesPro\Stores\StoreIdentityAuthority as Identity;
$wpdb=new ScopeDb9DT();
foreach ([
    314=>'Continente Modelo Santo Tirso',
    411=>'Continente Bom Dia Braga',
    412=>'Continente Gaia',
    413=>'MDL Espinho',
    414=>'CBD Porto',
    416=>'Pingo Doce Matosinhos',
    417=>'Pingo Doce Aveiro',
    418=>'Garcias Porto',
    419=>'Makro Porto',
    420=>'Modelo Loja sem cardex',
] as $id=>$name) {
    $wpdb->locations[$id]=['id'=>$id,'name'=>$name,'tags'=>'','meta_json'=>'','client_id'=>2,'project_id'=>2];
}
$def=static function(int $id,string $name,string $insignia,int $client=2,int $project=2,string $meta='{}',string $status='active') use (&$wpdb): void {
    $wpdb->cardex[$id]=['id'=>$id,'name'=>$name,'insignia'=>$insignia,'client_id'=>$client,'project_id'=>$project,'status'=>$status,'meta_json'=>$meta];
};
$link=static function(int $id,int $cardex,int $location) use (&$wpdb): void {
    $wpdb->links[]=['id'=>$id,'cardex_id'=>$cardex,'location_id'=>$location];
};
$def(5,'Pernod Ricard  - Sonae','Continente');
$def(6,'Pernod Ricard - Pingo Doce','Pingo Doce');
$def(7,'Pernod Ricard - Garcias','Garcias',2,2,'{"clone_source_id":6,"clone_scope":"manual","clone_publication_approved":true}');
$def(8,'Pernod Ricard - Makro','Makro');
$def(9,'Pernod Ricard - Continente','Continente');
$def(10,'Pernod Ricard - Modelo','MDL');
$def(11,'Pernod Ricard  - Sonae','CNT');
$def(12,'Pernod Ricard  - Sonae','Continente',2,2,'{"clone_source_id":6,"clone_scope":"manual","clone_publication_approved":true}');
$def(13,'Pernod Ricard  - Sonae','Continente',9,2);
$def(14,'Pernod Ricard  - Sonae','Continente',2,9);
$def(15,'Pernod Ricard  - Sonae','Continente',2,2,'{}','draft');
$def(16,'Pernod Ricard - Sonae','Continente');
foreach ([314,411,412,413,414] as $id) $link($id,5,$id);
$link(416,6,416);$link(417,6,417);
$link(418,7,418);$link(419,8,419);
$link(600,9,314);$link(601,11,411);$link(602,12,413);
$link(603,13,411);$link(604,14,414);$link(605,15,414);
$checks=0;
$check=static function(bool $test,string $label) use (&$checks): void {
    ++$checks;
    if (!$test) throw new RuntimeException('9DU FAIL #'.$checks.' '.$label);
};
foreach ([314,411,412,413,414] as $id) {
    $decision=C::inspect_location_binding(5,$id,2,2);
    $check($decision['code']==='ok' && $decision['resolved_id']===5,
        'Sonae legacy umbrella explicitly linked and valid in store '.$id);
    $check($decision['retailer_evidence']==='Sonae' && $decision['legacy_sonae_umbrella']===true,
        'Sonae inferred only from verified title/store link '.$id);
    $check(C::cardex_available_for_location(5,$id),'cardex available for fixed question in '.$id);
}
$wpdb->cardex[5]['name'] = "Pernod Ricard \u{00B7} Sonae";
$check(C::inspect_location_binding(5,314,2,2)['code']==='ok',
    'Real BO middle-dot Sonae title also resolves linked Modelo');
$check(!Identity::locationMatches('Continente',$wpdb->locations[314]),
    'Global 9DN identity must still distinguish CNT and Modelo outside Cardex');
$check(!Identity::locationMatches('Continente',$wpdb->locations[411]),
    'Global 9DN identity must still distinguish CNT and Bom Dia outside Cardex');
$check(C::inspect_location_binding(9,314,2,2)['code']==='retailer_mismatch',
    'An exact Continente cardex is never an umbrella unless the legacy title verifies Sonae');
$check(C::inspect_location_binding(11,411,2,2)['code']==='retailer_mismatch',
    'CNT cardex may never acquire Bom Dia by explicit link');
$check(C::inspect_location_binding(12,413,2,2)['code']==='retailer_mismatch',
    'Cloned cardex may never gain historical umbrella exception');
$check(C::inspect_location_binding(13,411,2,2)['code']==='client_mismatch',
    'Explicit Sonae link never overrides client');
$check(C::inspect_location_binding(14,414,2,2)['code']==='project_mismatch',
    'Explicit Sonae link never overrides project');
$check(C::inspect_location_binding(15,414,2,2)['code']==='draft',
    'Draft remains unpublished');
foreach ([416,417] as $id) {
    $check(C::resolve_cardex_id_for_location($id,2,2)===6,
        'Normal Pingo Doce link unchanged '.$id);
}
$check(C::inspect_location_binding(7,418,2,2)['code']==='ok',
    'Custom cloned Garcias remains independent of known brands');
$check(C::inspect_location_binding(8,419,2,2)['code']==='ok',
    'Makro cardex remains independent');
$check(!C::cardex_available_for_location(16,420),
    'Unlinked legacy Sonae umbrella remains unavailable to a fixed-cardex form');
$check(C::resolve_cardex_id_for_location(420,2,2)!==16,
    'Unlinked Sonae umbrella cannot reach unrelated stores in auto mode');
$check(C::resolve_cardex_id_for_location(420,2,2)!==5,
    'Unlinked historical Sonae umbrella may never reach a different store via automatic detection');
$question=['type'=>'product_matrix','product_source'=>'cardex_auto','cardex_id'=>6];
$check(C::question_cardex_id($question,['location_id'=>314,'client_id'=>2,'project_id'=>2])===5,
    'Frentes automatic source ignores stale fixed cardex id');
$check(C::question_cardex_id(['type'=>'order_suggestion','product_source'=>'cardex_auto'],
    ['location_id'=>411,'client_id'=>2,'project_id'=>2])===5,
    'Sugestao de encomenda resolves the same Sonae umbrella');
// A dedicated exact Modelo cardex must outrank an umbrella at identical client/project rank.
$link(900,10,413);
$check(C::resolve_cardex_id_for_location(413,2,2)===10,
    'More precise MDL cardex is given priority over umbrella when linked');
// Bad newest known-family link may not hide an older valid Sonae link.
$link(901,8,314);
$check(C::resolve_cardex_id_for_location(314,2,2)===5,
    'Cross-retailer link cannot steal Modelo visit even if more recent');
$admin=file_get_contents(dirname(__DIR__).'/src/Admin/ProductCardex.php');
$check(str_contains($admin,'render_global_binding_audit(') && str_contains($admin,'audit_bindings') &&
       str_contains($admin,'LIMIT 1500') && str_contains($admin,'$limit=60') &&
       str_contains($admin,'inspect_location_binding(') && str_contains($admin,'is_active=1'),
    'Read-only global audit is paginated, uses current visit and base product count');
$check(str_contains($admin,'Ver detalhe') && str_contains($admin,'audit_client') && str_contains($admin,'audit_store'),
    'Global audit has transparent filters and per-store diagnostic drilldown');
echo 'CARDEX GLOBAL LINKED SCOPE 9DU PASS | checks='.$checks.' | legacy umbrella Sonae | other retailers isolated | cross tenant | global audit' . PHP_EOL;
