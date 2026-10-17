<?php
/** 9DS runtime: read-only synthetic form rendering and retailer identity. */
require_once __DIR__.'/test-form-cardex-identity-continuity-9dr.php';
require_once dirname(__DIR__).'/src/Forms/FormRenderer.php';
function esc_html($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function esc_attr($value){return esc_html($value);}
$checks=0;
$assert9DS=function($test,string $message) use (&$checks): void {
    $checks++;
    if (!$test) throw new RuntimeException('9DS FAIL '.$checks.': '.$message);
};
// Imported non-clone cardex where its retailer lives in the title, not in the
// insurer/vendor column. Restores valid historic Sonae/PD links only.
$wpdb->locations[108]=['id'=>108,'name'=>'Continente Bom Dia Braga','tags'=>'','meta_json'=>'','client_id'=>10,'project_id'=>20];
$wpdb->cardex[21]=['id'=>21,'insignia'=>'Pernod Ricard','name'=>'Pernod Ricard · Sonae',
    'client_id'=>10,'project_id'=>20,'status'=>'active','meta_json'=>'{}'];
$wpdb->links[]=['id'=>21,'cardex_id'=>21,'location_id'=>108];
$assert9DS(\RoutesPro\Forms\ProductCardex::resolve_cardex_id_for_location(108,10,20)===21,'historic client-label Sonae cardex recovers from title');
$question=['key'=>'numero_de_frentes','label'=>'Número de Frentes','type'=>'product_matrix',
    'product_source'=>'cardex_auto','cardex_id'=>14];
$ctx=['location_id'=>108,'client_id'=>10,'project_id'=>20];
$assert9DS(\RoutesPro\Forms\ProductCardex::question_cardex_id($question,$ctx)===21,
    'automatic question ignores stale fixed Pingo Doce cardex ID');
$assert9DS(\RoutesPro\Forms\ProductCardex::cardex_available_for_location(21,108),'inferred legacy retailer accepted only for matching store');
$assert9DS(!\RoutesPro\Forms\ProductCardex::cardex_available_for_location(21,103),'cross-insignia store rejected');
// A custom approved clone never adopts the originating cardex name's retailer.
$wpdb->cardex[23]=['id'=>23,'insignia'=>'Garcias','name'=>'Cópia de Pingo Doce',
    'client_id'=>10,'project_id'=>20,'status'=>'active','meta_json'=>'{"clone_source_id":14,"clone_scope":"manual","clone_publication_approved":true}'];
$wpdb->links[]=['id'=>23,'cardex_id'=>23,'location_id'=>106];
$assert9DS(\RoutesPro\Forms\ProductCardex::cardex_available_for_location(23,106),'custom clone retains Garcias scope');
$assert9DS(!\RoutesPro\Forms\ProductCardex::cardex_available_for_location(23,103),'custom clone cannot open on Pingo Doce');
// A missing cardex never erases the question's place in a wizard or the
// previous answer. These are historical rows from exactly this visit.
$badCtx=['location_id'=>105,'client_id'=>10,'project_id'=>20,'_daily_existing'=>true];
$stored=[['ref'=>'SKU-1','name'=>'Jameson Original','before'=>4,'after'=>7]];
$schema=['questions'=>[
    $question,
    ['key'=>'sugestao','type'=>'order_suggestion','label'=>'Sugestão de encomenda','product_source'=>'cardex_auto'],
], 'layout'=>['mode'=>'single']];
$html=\RoutesPro\Forms\FormRenderer::render_questions($schema,['numero_de_frentes'=>$stored],$badCtx);
$assert9DS(str_contains($html,'data-rp-cardex-unavailable="1"'),'unavailable question rendered as visible notice');
$assert9DS(str_contains($html,'Número de Frentes'),'fronts label not silently removed');
$assert9DS(str_contains($html,'Sugestão de encomenda'),'order suggestion still present');
$assert9DS(str_contains($html,'Jameson Original') && str_contains($html,'>7<'),'same-day fronts visible read-only');
$assert9DS(!str_contains($html,'name="numero_de_frentes['),'missing cardex never emits editable fake SKU');
// Safety contracts: both persisted web and native edits preserve their own
// submissions when binding is unavailable, not another visit's snapshots.
$web=file_get_contents(dirname(__DIR__).'/src/Forms/Forms.php');
$native=file_get_contents(dirname(__DIR__).'/src/Mobile/SubmissionService.php');
$payload=file_get_contents(dirname(__DIR__).'/src/Mobile/FormPayloadService.php');
$assert9DS(str_contains($web,'$daily_existing && array_key_exists($key, $existing_prefill)'), 'web same-day preservation guard');
$assert9DS(str_contains($native,'$is_update && array_key_exists($key, $prior_visit_answers)'), 'native same-day preservation guard');
$assert9DS(str_contains($payload,"['binding_error']") && str_contains($payload,"['read_only']"), 'native missing-binding diagnostic');
$assert9DS(\RoutesPro\Forms\ProductCardex::question_binding_issue($question,$badCtx)!=='','missing cardex reports actionable reason');
echo "FORM CARDEX VISIBLE BINDING 9DS PASS | checks={$checks} | missing=visible | history=read_only | no_cross_retailer | web_and_native_guarded\n";
