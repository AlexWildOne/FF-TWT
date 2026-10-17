<?php
namespace {
    define('ABSPATH', __DIR__ . '/');
    define('DAY_IN_SECONDS', 86400);
    $GLOBALS['ff9bk_options']=[
        'routespro_settings'=>[
            'routing_provider'=>'google_routes',
            'google_routes_api_key'=>'test-key-never-used',
            'google_routes_preference'=>'TRAFFIC_UNAWARE',
            'google_routes_route_mode'=>'fastest_tolls',
            'google_route_matrix_enabled'=>0,
            'google_routes_waypoint_optimization_enabled'=>0,
            'google_routes_tolls_enabled'=>0,
            'routing_fallback_internal'=>1,
            'routing_cache_days'=>30,
        ],
    ];
    function get_option($k,$d=false){ return $GLOBALS['ff9bk_options'][$k] ?? $d; }
    function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; }
    function wp_json_encode($v,$flags=0,$depth=512){ return json_encode($v,$flags,$depth); }
    function wp_parse_args($args,$defaults=[]){ return array_merge($defaults,is_array($args)?$args:[]); }
    function current_time($type,$gmt=false){ return $type==='Y-m-d' ? '2026-10-02' : '2026-10-02 00:30:00'; }
    function get_transient($k){ return false; }
    function set_transient($k,$v,$ttl){ return true; }
    function is_wp_error($v){ return false; }
}
namespace RoutesPro\Security {
    final class CredentialStore { public static function supported(){ return false; } }
}
namespace {
    require_once dirname(__DIR__).'/src/Support/Config.php';
    require_once dirname(__DIR__).'/src/Support/TollEstimator.php';
    require_once dirname(__DIR__).'/src/Services/GoogleRoutes.php';
    use RoutesPro\Services\GoogleRoutes;
    use RoutesPro\Support\Config;

    $checks=0;
    $assert=static function(bool $ok,string $name) use (&$checks){
        if(!$ok) throw new \RuntimeException('FAIL '.$name);
        $checks++; echo 'PASS '.$name.PHP_EOL;
    };
    $points=[
        ['key'=>'start','name'=>'Base','address'=>'Porto, Portugal','lat'=>41.1579,'lng'=>-8.6291],
        ['key'=>'a','name'=>'Loja A','address'=>'Gaia, Portugal','lat'=>41.1336,'lng'=>-8.6174],
        ['key'=>'end','name'=>'Base','address'=>'Porto, Portugal','lat'=>41.1579,'lng'=>-8.6291],
    ];
    $sig1=GoogleRoutes::routeSignature($points);
    $sig2=GoogleRoutes::routeSignature($points);
    $assert(strlen($sig1)===64 && hash_equals($sig1,$sig2),'signature_is_deterministic');
    $renamed=$points; $renamed[1]['name']='Loja A Renovada'; $renamed[1]['address']='Rua formatada de outra forma';
    $assert(hash_equals($sig1,GoogleRoutes::routeSignature($renamed)),'display_changes_do_not_refresh_known_coordinates');
    $changed=$points; $changed[1]['lat']=41.1400;
    $assert(!hash_equals($sig1,GoogleRoutes::routeSignature($changed)),'signature_changes_with_route_identity');
    $matrix=GoogleRoutes::calculateMatrix($points,['ignore_provider'=>true]);
    $assert(empty($matrix['ok']) && !empty($matrix['skipped']) && ($matrix['reason']??'')==='matrix_disabled_by_cost_policy','matrix_disabled_without_http');
    $assert(Config::get('google_routes_preference')==='TRAFFIC_UNAWARE','traffic_unaware_default_authority');
    $assert((int)Config::get('google_routes_tolls_enabled')===0,'google_tolls_are_opt_in');
    $assert((int)Config::get('google_routes_waypoint_optimization_enabled')===0,'google_waypoint_optimization_is_opt_in');
    echo 'STATIC ROUTING COST AUTHORITY 9BK RUNTIME OK checks='.$checks.PHP_EOL;
}
