<?php
/** 9DV source-of-truth and zero-confirmation tests, no DB access. */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)) ?: '';} }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){return trim(strip_tags((string)$v));} }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($v){return trim(strip_tags((string)$v));} }
if (!function_exists('absint')) { function absint($v){return abs((int)$v);} }
if (!function_exists('remove_accents')) { function remove_accents($v){$r=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$v);return $r===false?(string)$v:$r;} }
require_once $root . '/src/Forms/OrderSuggestionIntegrity.php';
use RoutesPro\Forms\OrderSuggestionIntegrity as S;
$checks=0;
$check=static function(bool $ok,string $name) use (&$checks){$checks++;if(!$ok)throw new RuntimeException('9DV FAIL #'.$checks.': '.$name);};
$allowed=[
 ['item_id'=>11,'ref'=>'P4','name'=>'Jameson Original','category'=>'WHISKY','units_per_case'=>6,'capacity_cl'=>70,'item_type'=>'P4'],
 ['item_id'=>12,'ref'=>'P5','name'=>'Jameson Black Barrel','category'=>'WHISKY','units_per_case'=>6,'capacity_cl'=>70,'item_type'=>'P5'],
];
$raw=static fn($ref='P4',$qty='',$source='auto',$id=11,$notes=''):array => [[
 'item_id'=>$id,'ref'=>$ref,'name'=>$ref==='P5'?'Jameson Black Barrel':'Jameson Original',
 'order_qty'=>$qty,'quantity_source'=>$source,'notes'=>$notes,'units_per_case'=>987
]];
$front=static fn($qty,$confirmed=1,$ref='P4',$id=11):array => [[
 'item_id'=>$id,'ref'=>$ref,'name'=>$ref==='P5'?'Jameson Black Barrel':'Jameson Original',
 'after'=>$qty,'fronts_confirmed'=>$confirmed
]];
$norm=static fn($rows,$fronts=[],$present=true,$cardex=5,$allowedList=null,$zero=1,$decimals=false):array => S::normalise($rows,$allowedList??$allowed,$fronts,$present,$cardex,$zero,$decimals);
$check(S::CONTRACT==='fieldflow-order-suggestion-integrity-9dv-v1','contract');
foreach ([null,'',' ','??',-1] as $v) $check(!S::confirmedZero(['after'=>$v]),'empty-or-invalid-not-zero '.var_export($v,true));
$check(S::confirmedZero(['after'=>0]),'numeric-zero-explicit');
$check(S::confirmedZero(['after'=>'0,0','fronts_confirmed'=>1]),'comma-zero-explicit');
$check(!S::confirmedZero(['after'=>0,'fronts_confirmed'=>0]),'historic-zero-unconfirmed');
$check(!S::confirmedZero(['after'=>'','qty'=>0]),'after-blank-overrides-old-qty');
$check(!S::confirmedZero(['after'=>3]),'positive-not-zero');
$check(($norm($raw(),$front('0'))[0]['order_qty']??null)===1.0,'web-confirmed-zero-one-box');
$check(($norm($raw(),$front('0'))[0]['quantity_source']??'')==='auto','auto-provenance');
$check($norm($raw(),$front(''))===[],'blank-fronts-no-auto');
$check($norm($raw(),$front('0',0))===[],'prefill-zero-no-auto');
$check($norm($raw(),$front('4'))===[],'positive-fronts-no-auto');
$check($norm($raw(),$front('0'),false)===[],'paired-fronts-not-submitted');
$check($norm($raw(),$front('0',1,'P5',12))===[],'another-reference-zero-never-leaks');
$check(($norm($raw('P4','3','manual'),$front('0'))[0]['order_qty']??null)===3.0,'manual-overrides-zero');
$check(($norm($raw('P4','2','manual'),$front('5'))[0]['quantity_source']??'')==='manual','manual-when-fronts-positive');
$check(($norm($raw('P4','4','manual'),[],false)[0]['order_qty']??null)===4.0,'manual-without-front-payload-is-allowed-if-in-assortment');
$check(($norm($raw('P4','','manual',11,'Audit note'),$front('0'))[0]['order_qty']??null)==='','manual-blank-with-notes-not-autofilled');
$check(($norm($raw('P4','','manual',11,'Audit note'),$front('0'))[0]['notes']??'')==='Audit note','manual-note-survives');
$check(($norm($raw('P4','4','auto'),$front('5'))===[]),'forged-auto-positive-does-not-survive');
$check(($norm($raw('P4','50','auto'),$front('0'))[0]['order_qty']??null)===1.0,'auto-amount-derived-from-configuration-only');
$check(($norm($raw('P4','11','manual'),$front('0'))[0]['units_per_case']??null)===6.0,'trusted-units-not-posted-987');
$check(($norm($raw('P4','2','manual'),$front('0'))[0]['cardex_id']??null)===5,'trusted-cardex-not-posted');
$check(($norm($raw('P4','2','manual'),$front('0'))[0]['units_total']??null)===12.0,'units-total');
$check($norm($raw('P4',4,'manual',999),$front('0'))===[],'wrong-store-item-id-denied');
$check($norm($raw('P5',4,'manual',11),$front('0'))===[],'impersonated-item-reference-denied');
$check($norm($raw('PD-PRIVATE',3,'manual',0),[],false)===[],'unpaired-unknown-product-denied');
$check($norm($raw('P4',2,'manual',12),$front('0'))===[],'no-ref-substitution-by-id');
$custom=[['item_id'=>0,'ref'=>'NEW-SKU','name'=>'New Authorized Front','qty'=>'0','fronts_confirmed'=>1,'units_per_case'=>12]];
$check(($norm([['ref'=>'NEW-SKU','name'=>'New Authorized Front','order_qty'=>'','quantity_source'=>'auto']],$custom)[0]['order_qty']??null)===1.0,'store-custom-product-present-in-fronts');
$check($norm([['ref'=>'NEW-SKU','name'=>'New Authorized Front','order_qty'=>'','quantity_source'=>'auto']],$custom,false)===[],'custom-ref-without-paired-proof');
$check(($norm($raw(),$front('0'),true,5,null,2)[0]['order_qty']??null)===2.0,'configured-two-boxes');
$check(($norm($raw(),$front('0'),true,5,null,1.25,true)[0]['order_qty']??null)===1.25,'decimal-configuration');
$check($norm($raw(),$front('0'),true,5,null,0)===[],'disabled-automatic-suggestion');
$check(($norm($raw('P4','0','manual'),$front('0'))[0]['order_qty']??null)===0.0,'manual-zero-preserved-not-promoted');
$check($norm($raw('P4','-1','manual'),$front('5'))===[],'negative-quantity-rejected');
$check($norm($raw('P4','nan','manual'),$front('5'))===[],'nonnumeric-quantity-rejected');
$check(count($norm(array_merge($raw('P4',2,'manual'),$raw('P4',4,'manual')),$front('0')) )===1,'duplicate-identity-not-double-counted');
$check(($norm([['ref'=>'P4','name'=>'Jameson Original','order_qty'=>3,'quantity_source'=>'manual']],[],false,0,$allowed)[0]['cardex_id']??-1)===0,'manual-scope-no-forged-cardex');
$src=file_get_contents($root.'/src/Mobile/SubmissionService.php');
$check(str_contains($src,'ProductCardex::normalise_order_submission_rows('),'mobile-same-canonical-order-authority');
$check(str_contains($src,"['product_matrix','order_suggestion']"),'mobile-preserves-unavailable-cardex-answers');
$src=file_get_contents($root.'/src/Forms/Forms.php');
$check(str_contains($src,'ProductCardex::normalise_order_submission_rows('),'web-same-canonical-order-authority');
$src=file_get_contents($root.'/src/Forms/FormRenderer.php');
$check(str_contains($src,'function pairedFrontQuestion('),'single-paired-front-field');
$check(str_contains($src,'data-fronts-confirmed'),'zero-confirmation-carries-through-web');
$check(!str_contains($src,"if(raw==='' || (!isNaN(Number(raw)) && Number(raw)===0)) hasZero=true;"),'old-blank-is-zero-removed');
$check(!str_contains($src,"return (frontKey&&pairedKey===frontKey)||(cardexId&&orderCardex===cardexId);"),'no-unscoped-cross-question-pairing');
echo 'ORDER SUGGESTION INTEGRITY 9DV PASS | checks='.$checks."\n";
