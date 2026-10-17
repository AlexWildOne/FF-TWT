<?php
define('ABSPATH', __DIR__ . '/');
if(!function_exists('remove_accents')){function remove_accents($v){return strtr((string)$v,['ã'=>'a','á'=>'a','à'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Ã'=>'A','Á'=>'A','É'=>'E','Ó'=>'O','Ç'=>'C']);}}
if(!function_exists('sanitize_text_field')){function sanitize_text_field($v){return is_scalar($v)?trim((string)$v):'';}}
if(!function_exists('sanitize_key')){function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v));}}
if(!function_exists('absint')){function absint($v){return abs((int)$v);}}
if(!function_exists('current_time')){function current_time($type){return $type==='mysql'?'2026-09-23 00:00:00':time();}}
if(!function_exists('wp_json_encode')){function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}}
require_once dirname(__DIR__).'/src/Services/RoutingGeographyService.php';
use RoutesPro\Services\RoutingGeographyService;
$fail=[];$ok=function($cond,$label)use(&$fail){if(!$cond)$fail[]=$label;};
$ok(RoutingGeographyService::normalizeCountryCode('Portugal')==='PT','Portugal alias');
$ok(RoutingGeographyService::normalizeCountryCode('Espanha')==='ES','Spain alias');
$ok(RoutingGeographyService::normalizeCountryCode('', '')==='','empty fallback remains empty');
$googlePt=['country_code'=>'PT','raw'=>['address_components'=>[['long_name'=>'Portugal','short_name'=>'PT','types'=>['country']]]]];
$googleEs=['country_code'=>'ES','raw'=>['address_components'=>[['long_name'=>'Spain','short_name'=>'ES','types'=>['country']]]]];
$ok(RoutingGeographyService::resultMatchesCountry($googlePt,'PT'),'PT result accepted');
$ok(!RoutingGeographyService::resultMatchesCountry($googleEs,'PT'),'ES result rejected for PT');
// Approximate Montijo Portugal versus Montijo, Badajoz.
$ok(RoutingGeographyService::coordinatesLikelyInCountry(38.7068,-8.9739,'PT'),'Montijo PT inside PT');
$ok(!RoutingGeographyService::coordinatesLikelyInCountry(38.909,-6.617,'PT'),'Montijo Badajoz rejected for PT');
$ok(RoutingGeographyService::coordinatesLikelyInCountry(38.909,-6.617,'ES'),'Montijo Badajoz inside ES');
$options=RoutingGeographyService::geocodeOptions('PT');
$ok(($options['country_code']??'')==='PT' && ($options['region']??'')==='pt','PT geocode options');
$source=file_get_contents(dirname(__DIR__).'/src/Services/RouteMetricsService.php');
$ok(strpos($source,'ensureGeographicIntegrityForRoute')!==false,'route ensure source');
$ok(strpos($source,'reconcileRouteStopGeography')!==false,'PDV reconciliation source');
$ok(strpos($source,"google_routes_geography_v3")!==false,'fresh route metrics source');
$mail=file_get_contents(dirname(__DIR__).'/src/Admin/ReportAutomations.php');
$ok(strpos($mail,'route_geography_unresolved:')!==false,'daily close fail closed');
$ok(strpos($mail,'ensureGeographicIntegrityForRoute($routeId')!==false,'daily close refresh');
if($fail){fwrite(STDERR,'9AN RUNTIME FAIL: '.implode(', ',$fail).PHP_EOL);exit(1);}echo 'ROUTING ENDPOINT GEOGRAPHIC INTEGRITY 9AN RUNTIME OK | Montijo_PT=accepted | Montijo_Badajoz_for_PT=rejected | daily_close=refresh_before_report'.PHP_EOL;
