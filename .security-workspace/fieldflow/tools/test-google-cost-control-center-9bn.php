<?php
namespace {
    define('ABSPATH', __DIR__ . '/');
    define('DAY_IN_SECONDS', 86400);
    function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; }
    function get_transient($k){ return false; }
    function set_transient($k,$v,$ttl){ return true; }
}
namespace RoutesPro\Support {
    final class Config {
        public static array $values=[
            'google_cost_control_enabled'=>1,
            'google_cost_block_matrix'=>1,
            'google_cost_block_pro'=>1,
            'google_cost_block_enterprise'=>1,
            'google_cost_alert_premium'=>0,
            'google_routes_preference'=>'TRAFFIC_UNAWARE',
            'google_routes_waypoint_optimization_enabled'=>0,
            'google_routes_tolls_enabled'=>0,
            'google_route_matrix_enabled'=>0,
            'google_places_contact_enrichment_enabled'=>0,
        ];
        public static function get(?string $key=null,$default=null){ return $key===null?self::$values:(self::$values[$key]??$default); }
    }
}
namespace RoutesPro\Observability {
    final class ApiUsageLedger {
        public static array $rows=[];
        public static function record(string $provider,string $operation,array $data=[]):int { self::$rows[]=[$provider,$operation,$data]; return count(self::$rows); }
        public static function summary(int $days=30):array { return ['avoided_events'=>count(self::$rows),'budget_usage_pct'=>0]; }
        public static function byOperation(int $days=30,int $limit=100):array { return []; }
    }
}
namespace {
    require_once dirname(__DIR__).'/src/Services/GoogleCostControlCenter.php';
    use RoutesPro\Services\GoogleCostControlCenter;
    use RoutesPro\Support\Config;
    use RoutesPro\Observability\ApiUsageLedger;

    $checks=0;
    $assert=static function(bool $ok,string $name) use (&$checks){ if(!$ok) throw new \RuntimeException('FAIL '.$name); $checks++; echo 'PASS '.$name.PHP_EOL; };

    $ess=GoogleCostControlCenter::authorizeOperation('compute_routes_essentials');
    $assert(!empty($ess['allowed']),'essentials_allowed');

    $pro=GoogleCostControlCenter::authorizeOperation('compute_routes_pro');
    $assert(empty($pro['allowed']) && ($pro['reason']??'')==='pro_blocked','pro_blocked_before_http');

    $ent=GoogleCostControlCenter::authorizeOperation('compute_routes_enterprise');
    $assert(empty($ent['allowed']) && ($ent['reason']??'')==='enterprise_blocked','enterprise_blocked_before_http');

    $matrix=GoogleCostControlCenter::authorizeOperation('compute_route_matrix_essentials',['units'=>100]);
    $assert(empty($matrix['allowed']) && ($matrix['reason']??'')==='matrix_blocked','matrix_blocked_before_http');

    $assert(count(ApiUsageLedger::$rows)===3,'blocked_events_metered_without_requests');
    $assert((int)(ApiUsageLedger::$rows[2][2]['request_count']??-1)===0 && (float)(ApiUsageLedger::$rows[2][2]['units']??0)===100.0,'matrix_block_records_avoided_units');

    Config::$values['google_routes_tolls_enabled']=1;
    Config::$values['google_routes_preference']='TRAFFIC_AWARE';
    Config::$values['google_route_matrix_enabled']=1;
    $drift=GoogleCostControlCenter::configurationDrift();
    $assert(count($drift)>=3,'premium_configuration_drift_detected');

    Config::$values['google_cost_control_enabled']=0;
    $off=GoogleCostControlCenter::authorizeOperation('compute_routes_enterprise');
    $assert(!empty($off['allowed']),'cost_control_can_be_explicitly_disabled');

    echo 'GOOGLE COST CONTROL CENTER 9BN RUNTIME OK checks='.$checks.PHP_EOL;
}
