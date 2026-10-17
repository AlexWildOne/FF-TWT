<?php
/** 9CZ: tariff-independent quota, provider-metered tokens and EUR estimate provenance. */
define('ABSPATH','/tmp');
$GLOBALS['ff_cost_options']=[];
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$value)); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function get_option($key,$default=false) { return $GLOBALS['ff_cost_options'][$key] ?? $default; }
function update_option($key,$value,$autoload=false) { $GLOBALS['ff_cost_options'][$key]=$value; return true; }
function current_time($kind) { return $kind==='mysql'?'2026-10-10 16:00:00':strtotime('2026-10-10 16:00:00'); }
function ff_assert(bool $ok,string $tag): void { if (!$ok) { fwrite(STDERR,"FINOPS 9CZ FAIL: $tag\n");exit(1); } }
require dirname(__DIR__).'/src/Vision/VisionCostMeter.php';
use RoutesPro\Vision\VisionCostMeter as Meter;
ff_assert(Meter::CONTRACT==='fieldflow-fronts-ai-usage-finops-9cz-v1','contract');
$unknown=Meter::quote('azure','gpt-4o-mini',['input_tokens'=>12345,'output_tokens'=>1200]);
ff_assert($unknown['status']==='tariff_missing' && $unknown['estimated_cost_eur']===null && $unknown['token_source']==='provider','not_lying_when_tariff_missing');
$unmetered=Meter::quote('azure','gpt-4o-mini',[]);
ff_assert($unmetered['status']==='unmetered' && $unmetered['estimated_cost_eur']===null,'never_guess_image_tokens');
$rate=Meter::saveRate('azure','gpt-4o-mini','0,12','0.480000');
ff_assert($rate['input_eur_per_million']===.12 && $rate['output_eur_per_million']===.48,'decimal_eur_rates');
$quote=Meter::quote('azure','gpt-4o-mini',['input_tokens'=>10000,'output_tokens'=>2000]);
ff_assert($quote['status']==='priced' && abs($quote['estimated_cost_eur']-.00216)<.000000001,'provider_token_quote');
ff_assert($quote['token_source']==='provider','provider_metering_provenance');
ff_assert(Meter::quote('azure','gpt-4o-mini',[])['status']==='unmetered','unmetered_even_when_tariff_set');
ff_assert(Meter::quote('azure','gpt-4o-vision',['input_tokens'=>10000])['status']==='tariff_missing','price_specific_to_deployment');
ff_assert(Meter::quote('google','gpt-4o-mini',['input_tokens'=>10000])['status']==='tariff_missing','price_specific_to_provider');
foreach (['0','-1','5000','not-price','0.0000001'] as $invalid) {
  try { Meter::saveRate('azure','gpt-4o-mini',$invalid,'0.48'); ff_assert(false,'invalid_rate_accepted'); }
  catch (InvalidArgumentException $ok) {}
}
ff_assert(Meter::quote('azure','gpt-4o-mini',['input_tokens'=>10000,'output_tokens'=>2000])['status']==='priced','invalid_rate_did_not_overwrite');
$root=dirname(__DIR__);
$read=fn($p)=>file_get_contents($root.'/'.$p);
$ai=$read('src/Services/AI.php'); $form=$read('src/Vision/FrontsVisionService.php');
$admin=$read('src/Admin/FrontsVisionAdmin.php');$guard=$read('src/Vision/VisionUsageGuard.php');
ff_assert(strpos($ai,'AIManager::')===false || strpos($ai,'visionMeteredUsage')!==false,'ai_usage');
ff_assert(strpos($ai,'self::$lastVisionReceipt = []')!==false && strpos($ai,'getLastUsage()')!==false,'fresh_receipt_and_tokens');
ff_assert(strpos($form,'VisionUsageGuard::reserve($clientId, $settings)')!==false
  && strpos($form,"'max_attempts' => 1")!==false,'same_safety_gate_one_attempt');
ff_assert(strpos($form,"'_ff_ai_usage_log_id'")!==false && strpos($form,"'_ff_ai_cost_status'")!==false,'reading_audit_link');
ff_assert(strpos($admin,'check_admin_referer(\'fieldflow_vision_tariffs\'')!==false
  && strpos($admin,'current_user_can(\'routespro_manage\'')!==false,'tariff_update_secured');
ff_assert(strpos($guard,"'reserved_cents'")!==false && strpos($guard,"'monthly_limit'")!==false,'reserved_budget_not_removed');
echo 'FRONTS AI COST METERING 9CZ PHP OK | measured usage | explicit EUR tariffs | unpriced != zero | safe reserve | audit\n';
