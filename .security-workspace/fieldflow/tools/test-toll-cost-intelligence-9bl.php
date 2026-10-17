<?php
namespace {
    define('ABSPATH', __DIR__ . '/');
    define('DAY_IN_SECONDS', 86400);
    $GLOBALS['ff9bl_options']=[
        'routespro_settings'=>[
            'routing_provider'=>'google_routes',
            'google_routes_api_key'=>'test-key-never-used',
            'google_routes_preference'=>'TRAFFIC_UNAWARE',
            'google_routes_route_mode'=>'fastest_tolls',
            'google_route_matrix_enabled'=>0,
            'google_routes_waypoint_optimization_enabled'=>0,
            'google_routes_tolls_enabled'=>0,
            'toll_intelligence_enabled'=>1,
            'toll_vehicle_class'=>1,
            'routing_fallback_internal'=>1,
            'routing_cache_days'=>30,
        ],
    ];
    function get_option($k,$d=false){ return $GLOBALS['ff9bl_options'][$k] ?? $d; }
    function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; }
    function wp_json_encode($v,$flags=0,$depth=512){ return json_encode($v,$flags,$depth); }
    function wp_parse_args($args,$defaults=[]){ return array_merge($defaults,is_array($args)?$args:[]); }
    function current_time($type,$gmt=false){ return $type==='Y-m-d' ? '2026-10-02' : '2026-10-02 01:00:00'; }
    function get_transient($k){ return false; }
    function set_transient($k,$v,$ttl){ return true; }
    function is_wp_error($v){ return false; }
}
namespace RoutesPro\Security { final class CredentialStore { public static function supported(){ return false; } } }
namespace {
    require_once dirname(__DIR__).'/src/Support/Config.php';
    require_once dirname(__DIR__).'/src/Support/TollEstimator.php';
    require_once dirname(__DIR__).'/src/Services/GoogleRoutes.php';
    use RoutesPro\Support\TollEstimator;
    use RoutesPro\Services\GoogleRoutes;

    $checks=0;
    $assert=static function(bool $ok,string $name) use (&$checks){ if(!$ok) throw new \RuntimeException('FAIL '.$name); $checks++; echo 'PASS '.$name.PHP_EOL; };
    $route=static function(string $road,int $meters,float $slat,float $slng,float $elat,float $elng):array{
        return ['legs'=>[['steps'=>[['distanceMeters'=>$meters,'navigationInstruction'=>['instructions'=>'Continue pela '.$road],'startLocation'=>['latLng'=>['latitude'=>$slat,'longitude'=>$slng]],'endLocation'=>['latLng'=>['latitude'=>$elat,'longitude'=>$elng]]]]]]];
    };

    $a1=TollEstimator::estimateFromGoogleRoute($route('A1',50000,39.0,-8.8,39.4,-8.7),50,1);
    $assert(($a1['cost_eur']??0)>0 && ($a1['confidence']??'')==='high','a1_estimate_positive');
    $a22=TollEstimator::estimateFromGoogleRoute($route('A22',40000,37.1,-8.4,37.1,-7.9),40,1);
    $assert((float)($a22['cost_eur']??-1)===0.0,'a22_free_zero');
    $a4free=TollEstimator::estimateFromGoogleRoute($route('A4',30000,41.25,-7.95,41.30,-7.65),30,1);
    $assert((float)($a4free['cost_eur']??-1)===0.0,'a4_transmontana_free');
    $a4west=TollEstimator::estimateFromGoogleRoute($route('A4',30000,41.20,-8.60,41.25,-8.20),30,1);
    $assert((float)($a4west['cost_eur']??0)>0,'a4_porto_amarante_tolled');
    $a41c3=TollEstimator::estimateFromGoogleRoute($route('A41',20000,41.10,-8.70,41.20,-8.50),20,3);
    $assert((float)($a41c3['cost_eur']??-1)===0.0,'a41_2026_heavy_exemption');
    $assert(($a1['tariff_version']??'')===TollEstimator::TARIFF_VERSION && isset($a1['min_eur'],$a1['max_eur']),'versioned_range');

    $method=new \ReflectionMethod(GoogleRoutes::class,'routeBillingOperation');
    $method->setAccessible(true);
    $assert($method->invoke(null,false,false,'TRAFFIC_UNAWARE',3)==='compute_routes_essentials','essentials_without_google_tolls');
    $assert($method->invoke(null,true,false,'TRAFFIC_UNAWARE',3)==='compute_routes_enterprise','google_tolls_enterprise');
    $assert($method->invoke(null,false,true,'TRAFFIC_UNAWARE',3)==='compute_routes_pro','waypoint_optimization_pro');
    echo 'TOLL COST INTELLIGENCE 9BL RUNTIME OK checks='.$checks.PHP_EOL;
}
