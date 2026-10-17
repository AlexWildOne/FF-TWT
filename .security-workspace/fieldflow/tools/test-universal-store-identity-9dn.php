<?php
/** 9DN isolated assertions: no DB writes, no external service calls. */
define('ABSPATH', __DIR__.'/');
function wp_strip_all_tags($v) { return strip_tags((string)$v); }
function remove_accents($v) { return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v) ?: (string)$v; }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_\-]+/', '', (string)$v)); }
function fail9DN($message) { throw new RuntimeException('9DN FAILED: '.$message); }
function check9DN($value,$message) { if (!$value) fail9DN($message); }
require_once dirname(__DIR__).'/src/Stocks/StockService.php';
require_once dirname(__DIR__).'/src/Stores/StoreIdentityAuthority.php';
require_once dirname(__DIR__).'/src/Mapping/MappingEngine.php';
require_once dirname(__DIR__).'/src/Shopping/ShoppingService.php';
use RoutesPro\Stores\StoreIdentityAuthority as I;
use RoutesPro\Mapping\MappingEngine as M;
use RoutesPro\Shopping\ShoppingService as H;
$locations = [
 ['id'=>1,'name'=>'CNT Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>2,'name'=>'MDL Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>3,'name'=>'CBD Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>4,'name'=>'PD Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>5,'name'=>'Makro Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>6,'name'=>'Arcol Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>7,'name'=>'M. Cunha Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
 ['id'=>8,'name'=>'Loja Matosinhos','external_ref'=>'123','city'=>'Matosinhos'],
];
foreach (['CNT'=>'continente','MDL'=>'continente','CBD'=>'continente','SONAE'=>'continente',
 'PD'=>'pingodoce','pingo_doce'=>'pingodoce','Arcol'=>'arcol','M. Cunha'=>'mcunha',
 'Mcunha'=>'mcunha','Makro'=>'makro','Recheio'=>'recheio','M'=>''] as $source=>$wanted) {
 check9DN(I::family($source)===$wanted, 'family: '.$source);
}
foreach (['CNT Matosinhos'=>'cnt','MDL Matosinhos'=>'mdl','CBD Matosinhos'=>'cbd'] as $name=>$wanted) {
 check9DN(I::subtype($name)===$wanted, 'subtype '.$name);
}
foreach ($locations as $loc) {
 $expected = ['continente','continente','continente','pingodoce','makro','arcol','mcunha',''][$loc['id']-1];
 check9DN(I::locationFamily($loc)===$expected, 'verified family '.$loc['id']);
}
foreach ([['sonae','CNT Matosinhos',1,true], ['sonae','CNT Matosinhos',2,false],
 ['sonae','MDL Matosinhos',1,false], ['sonae','MDL Matosinhos',2,true],
 ['sonae','CBD Matosinhos',3,true], ['sonae','Matosinhos',1,false],
 ['pingo_doce','PD Matosinhos',4,true],['pingo_doce','PD Matosinhos',5,false],
 ['arcol','Arcol Matosinhos',6,true],['arcol','Arcol Matosinhos',7,false],
 ['mcunha','M Cunha Matosinhos',7,true],['mcunha','M Cunha Matosinhos',6,false],
 ['sonae','Makro Matosinhos',1,false]] as [$retailer,$source,$id,$expected]) {
 check9DN(I::sourceMatchesLocation($retailer,$source,$locations[$id-1],true)===$expected,
     'source->PDV '.$retailer.':'.$source.':'.$id);
}
foreach (['Continente Modelo'=>2,'Continente Bom Dia'=>3,'Continente'=>1,'Pingo Doce'=>4,
 'Arcol'=>6,'M. Cunha'=>7] as $label=>$only) {
 foreach ($locations as $loc) check9DN(I::locationMatches($label,$loc)===($loc['id']===$only),
     'location filter '.$label.' / '.$loc['id']);
}
check9DN(I::locationMatches('Sonae',$locations[0]) && I::locationMatches('Sonae',$locations[1]) && I::locationMatches('Sonae',$locations[2]),'sonae supplier accepts only recognized subtypes');
check9DN(!I::locationMatches('Sonae',$locations[4]) && !I::locationMatches('Sonae',$locations[7]),'unknown or Makro never Sonae');
$base=['source_label'=>'MDL Matosinhos','external_ref'=>'123','meta_json'=>['insignia'=>'sonae']];
$r=M::storeCandidates($base,$locations,10);
check9DN(count($r)===1 && $r[0]['target_id']===2, 'numeric reference may never override subtype');
$base=['source_label'=>'Matosinhos','external_ref'=>'123','meta_json'=>['insignia'=>'sonae']];
check9DN(M::storeCandidates($base,$locations)===[], 'generic Sonae without subtype needs review');
$base=['source_label'=>'M Cunha Matosinhos','external_ref'=>'123','meta_json'=>['insignia'=>'mcunha']];
$r=M::storeCandidates($base,$locations,10);
check9DN(count($r)===1 && $r[0]['target_id']===7, 'M Cunha not merchandiser M');
foreach ([[1,1,true],[1,2,false],[1,8,false],[4,4,true],[6,7,false],[7,7,true]] as [$old,$new,$wanted]) {
 check9DN(I::canUpdateLocation($locations[$old-1],$locations[$new-1])===$wanted,'CSV guard '.$old.' '.$new);
}
$ref = new ReflectionMethod(H::class, 'locationMatchesInsigniaStrict');
check9DN($ref->invoke(null,$locations[0],'Continente') && !$ref->invoke(null,$locations[4],'Continente'),'Shopping strict matching');
check9DN(H::canonicalInsigniaLabel('Mcunha')==='M. Cunha' && H::canonicalInsigniaLabel('Arcol')==='Arcol','Cash & Carry canonical names');
check9DN(!I::sourceMatchesLocation('sonae','CBD Matosinhos', ['name'=>'PD Matosinhos'], true),'no cross-retailer mapping by code');
echo "UNIVERSAL STORE IDENTITY 9DN PASS\n";
