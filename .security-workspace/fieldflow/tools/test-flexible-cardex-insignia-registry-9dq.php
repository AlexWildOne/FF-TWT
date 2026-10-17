<?php
/** 9DQ synthetic WordPress/MySQL tests, no production mutation. */
$GLOBALS['ff9dq_options']=[];
function get_option($key,$default=false) { return $GLOBALS['ff9dq_options'][$key] ?? $default; }
function update_option($key,$value,$autoload=null) { $GLOBALS['ff9dq_options'][$key]=$value; return true; }
require __DIR__.'/test-safe-cardex-clone-publication-9dp.php';
use RoutesPro\Admin\CardexCloneService as Cloner9DQ;
use RoutesPro\Forms\ProductCardex as ProductCardex9DQ;
$checks9dq=0;
$ensure=function(bool $truth,string $label) use (&$checks9dq) { if (!$truth) throw new RuntimeException('9DQ FAIL '.$label); $checks9dq++; };
$expectReject=function(callable $fn,string $label) use ($ensure) { $thrown=false;try {$fn();}catch (RuntimeException $e){$thrown=true;}$ensure($thrown,$label); };
foreach (['Poupança','Garcias','Malaquias','Central Cash'] as $label) {
    $ensure(Cloner9DQ::supportedInsignia($label),'new brand permitted '.$label);
    $ensure(Cloner9DQ::canonicalInsignia($label)===$label,'canonical retains commercial spelling '.$label);
}
foreach (['Sonae','M','PD Matosinhos','Makro Matosinhos','Loja','Outros','123456'] as $label) {
    $ensure(!Cloner9DQ::supportedInsignia($label),'ambiguous name disallowed '.$label);
}
$ensure(Cloner9DQ::canonicalInsignia('pd')==='Pingo Doce','PD alias canonicalised');
$ensure(Cloner9DQ::canonicalInsignia('mdl')==='Continente Modelo','MDL alias isolated');
$ensure(Cloner9DQ::canonicalInsignia('CBD')==='Continente Bom Dia','CBD alias isolated');
$ensure(Cloner9DQ::canonicalInsignia('poupanca')==='poupanca','new names not silently reclassified');
$wpdb->tables['ff_routespro_locations'][29]=['id'=>29,'name'=>'Poupança Porto','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1];
$wpdb->tables['ff_routespro_locations'][30]=['id'=>30,'name'=>'Loja Central Cash Gaia','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20,'is_active'=>1];
$wpdb->tables['ff_routespro_locations'][31]=['id'=>31,'name'=>'PD Vila Nova de Gaia','tags'=>'','meta_json'=>json_encode(['insignia'=>'Garcias']),'client_id'=>10,'project_id'=>20,'is_active'=>1];
$wpdb->tables['ff_routespro_locations'][32]=['id'=>32,'name'=>'Loja de Braga','tags'=>'','meta_json'=>json_encode(['retailer'=>'Garcias','insignia'=>'Malaquias']),'client_id'=>10,'project_id'=>20,'is_active'=>1];
$wpdb->tables['ff_routespro_locations'][33]=['id'=>33,'name'=>'Loja Setúbal','tags'=>'','meta_json'=>json_encode(['insignia'=>'Garcias']),'client_id'=>10,'project_id'=>20,'is_active'=>1];
$wpdb->tables['ff_routespro_locations'][34]=['id'=>34,'name'=>'Central Cash Braga','tags'=>'','meta_json'=>json_encode(['insignia'=>'Malaquias']),'client_id'=>10,'project_id'=>20,'is_active'=>1];
$wpdb->tables['ff_routespro_locations'][35]=['id'=>35,'name'=>'Garcias Outro Cliente','tags'=>'','meta_json'=>'','client_id'=>12,'project_id'=>20,'is_active'=>1];
$ensure(Cloner9DQ::locationMatchesInsignia('Poupança',$wpdb->tables['ff_routespro_locations'][29]),'known prefix is explicit PDV evidence');
$ensure(Cloner9DQ::locationMatchesInsignia('Central Cash',$wpdb->tables['ff_routespro_locations'][30]),'multiword prefix with Loja');
$ensure(!Cloner9DQ::locationMatchesInsignia('Garcias',$wpdb->tables['ff_routespro_locations'][31]),'known Pingo Doce identity cannot be hijacked by metadata');
$ensure(!Cloner9DQ::locationMatchesInsignia('Garcias',$wpdb->tables['ff_routespro_locations'][32]),'conflicting dedicated retailer fields fail closed');
$ensure(Cloner9DQ::locationMatchesInsignia('Garcias',$wpdb->tables['ff_routespro_locations'][33]),'explicit dedicated metadata can validate custom brand');
$ensure(!Cloner9DQ::locationMatchesInsignia('Central Cash',$wpdb->tables['ff_routespro_locations'][34]),'explicit contrary metadata wins over store name');
$ensure(!Cloner9DQ::locationMatchesInsignia('Garcias',$wpdb->tables['ff_routespro_locations'][29]),'custom brands cannot steal stores from another custom brand');
$ensure(!Cloner9DQ::locationInScope($wpdb->tables['ff_routespro_locations'][35],10,20,'Garcias'),'custom retailer cannot link other client');
$ensure(!Cloner9DQ::locationInScope($wpdb->tables['ff_routespro_locations'][30],10,20,'Garcias'),'other custom retailer not eligible');
$ensure(Cloner9DQ::locationInScope($wpdb->tables['ff_routespro_locations'][33],10,20,'Garcias'),'explicit Garcias store scoped');
$ensure(count(get_option(Cloner9DQ::INSIGNIA_OPTION,[]))>=2,'original 9DP clones are recorded');
$registeredBefore=count(get_option(Cloner9DQ::INSIGNIA_OPTION,[]));
$wpdb->failNextItem=true;
$expectReject(fn()=>Cloner9DQ::duplicate(50,'Malaquias','Broken clone'),'clone transaction rolls back');
$ensure(count(get_option(Cloner9DQ::INSIGNIA_OPTION,[]))===$registeredBefore,'failed clone never registers new brand');
$garcias=Cloner9DQ::duplicate(50,'Garcias','Cardex Garcias');
$ensure(ProductCardex9DQ::get_cardex($garcias)['status']==='draft','custom brand clone remains draft');
$ensure(!ProductCardex9DQ::cardex_available_for_location($garcias,33),'draft never visible in fixed form');
$ensure(in_array('Garcias',get_option(Cloner9DQ::INSIGNIA_OPTION,[]),true),'new brand persisted only after successful clone');
$ensure(isset(Cloner9DQ::insignias()['Garcias']),'registered brand visible in next selector');
$wpdb->insert('ff_routespro_product_cardex_locations',['cardex_id'=>$garcias,'location_id'=>31]);
$expectReject(fn()=>Cloner9DQ::publish($garcias,'manual',true),'cross retailer link cannot be published');
foreach ($wpdb->tables['ff_routespro_product_cardex_locations'] as $id=>$link) {
    if ($link['cardex_id']===$garcias) unset($wpdb->tables['ff_routespro_product_cardex_locations'][$id]);
}
$wpdb->insert('ff_routespro_product_cardex_locations',['cardex_id'=>$garcias,'location_id'=>33]);
$ensure(Cloner9DQ::publish($garcias,'manual',true)===2,'approved custom brand can publish');
$ensure(ProductCardex9DQ::cardex_available_for_location($garcias,33),'published custom cardex serves intended store');
$ensure(!ProductCardex9DQ::cardex_available_for_location($garcias,29),'published custom cardex remains isolated');
$wpdb->insert('ff_routespro_product_cardex_locations',['cardex_id'=>$garcias,'location_id'=>29]);
$ensure(!ProductCardex9DQ::cardex_available_for_location($garcias,29),'wrong linked store cannot bypass brand authority');
$other=Cloner9DQ::duplicate(50,'Poupança','Cardex Poupança');
$ensure(in_array('Poupança',get_option(Cloner9DQ::INSIGNIA_OPTION,[]),true),'accented brand saved');
$ensure(ProductCardex9DQ::get_cardex($other)['status']==='draft','second custom clone separate');
$ensure(Cloner9DQ::locationMatchesInsignia('poupanca',$wpdb->tables['ff_routespro_locations'][29]),'accent-insensitive exact identity');
$labels=get_option(Cloner9DQ::INSIGNIA_OPTION,[]);Cloner9DQ::rememberInsignia('poupanca');
$ensure($labels===get_option(Cloner9DQ::INSIGNIA_OPTION,[]),'case/accent aliases do not register duplicates');
$service=file_get_contents(dirname(__DIR__).'/src/Admin/ProductCardex.php');
$ensure(str_contains($service,'list="ff-cardex-brand-options"') && str_contains($service,'name="destination_insignia"'),'new brand combobox present');
echo "FLEXIBLE CARDEX INSIGNIA REGISTRY 9DQ PASS | checks={$checks9dq} | brands=dynamic | security=fail-closed | draft=isolated | publication=explicit\n";
